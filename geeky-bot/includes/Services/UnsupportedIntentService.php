<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recognises shopper requests Geeky Bot genuinely cannot serve.
 *
 * Routing has a policy branch, a Product Expert branch and a catalog-discovery
 * branch, and discovery is the fall-through. Discovery almost always returns
 * something, so a question outside all three came back as a confident product
 * recommendation: "where is my order?" answered with an Office Footrest, and
 * "what did I buy last time" answered with a Bluetooth speaker, a travel mug and
 * an organiser pouch. Nothing was broken in the search itself -- the assistant
 * simply had no way to say "that is not something I can do".
 *
 * For an assistant whose value is grounded answers, a confident wrong answer is
 * worse than an admission, because the shopper cannot tell the difference. This
 * service exists to make that admission possible.
 *
 * Two rules keep it from swallowing questions that do work:
 *
 * 1. It only matches first-person requests about the shopper's OWN order or
 *    account. Store-policy questions ("what is your return policy", "when will I
 *    receive my refund") are answered from indexed policy pages and are resolved
 *    earlier in the pipeline regardless.
 * 2. It never inspects product attributes. "what material is used", "is it
 *    machine washable" and every other product-fact question belong to Product
 *    Expert and are handled before this runs.
 *
 * Every result passes through the `geekybot_unsupported_intent` filter so an
 * addon that does implement a capability can clear it.
 */
class UnsupportedIntentService {
    /**
     * @param string $message Raw shopper message.
     * @return array{type: string, message: string}
     */
    public function detect($message) {
        $lower = $this->normalize($message);
        $result = array('type' => '', 'message' => '');

        if ($lower !== '') {
            $type = $this->match_type($lower);
            if ($type !== '') {
                $result = array('type' => $type, 'message' => $this->reply_for($type));
            }
        }

        /**
         * Filters the detected out-of-scope intent.
         *
         * An addon that implements one of these capabilities should return an
         * empty `type` so the request continues down the normal pipeline.
         *
         * @param array  $result  Detected intent, with `type` and `message`.
         * @param string $message Raw shopper message.
         */
        $result = (array) apply_filters('geekybot_unsupported_intent', $result, $message);

        return array(
            'type' => isset($result['type']) ? sanitize_key((string) $result['type']) : '',
            'message' => isset($result['message']) ? (string) $result['message'] : '',
        );
    }

    /**
     * @param string $lower Normalised message.
     * @return string
     */
    private function match_type($lower) {
        // Order history. "what did I buy", "my last order", "last time I ordered".
        $history = array(
            '/\b(?:what|which)\b[^?]{0,24}\bdid\s+i\s+(?:buy|order|purchase|get)\b/u',
            '/\b(?:what|which)\b[^?]{0,24}\bhave\s+i\s+(?:bought|ordered|purchased)\b/u',
            '/\bmy\s+(?:last|latest|previous|recent|past|earlier)\s+(?:order|orders|purchase|purchases)\b/u',
            '/\blast\s+time\s+i\s+(?:bought|ordered|purchased|shopped)\b/u',
            '/\bi\s+(?:bought|ordered|purchased)\b[^?]{0,24}\b(?:last|before|previously|earlier)\b/u',
            '/\bmy\s+(?:order|purchase)\s+history\b/u',
            '/\bre-?order\b/u',
        );
        foreach ($history as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'order_history';
            }
        }

        // Order status. Deliberately requires the order to be the shopper's own,
        // so "how long does delivery take" stays a shipping-policy question.
        $status = array(
            '/\bwhere\s+is\s+my\s+(?:order|parcel|package|delivery|shipment|item|refund\s+order)\b/u',
            '/\b(?:track|tracking)\b[^?]{0,16}\bmy\s+(?:order|parcel|package|shipment|delivery)\b/u',
            '/\bmy\s+order\s+(?:status|number|id|tracking)\b/u',
            '/\bwhen\s+will\s+my\s+(?:order|parcel|package|delivery|shipment|item)\b/u',
            '/\bhas\s+my\s+(?:order|parcel|package|payment)\s+(?:shipped|arrived|dispatched|been\s+\w+)\b/u',
            '/\bstatus\s+of\s+my\s+(?:order|delivery|shipment)\b/u',
            '/\border\s+(?:number\s+)?#\s*\d+/u',
            '/\bdid\s+my\s+(?:order|payment)\s+go\s+through\b/u',
            '/\bcancel\s+my\s+(?:order|purchase)\b/u',
        );
        foreach ($status as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'order_status';
            }
        }

        // Account and personal data.
        $account = array(
            '/\bmy\s+account\b/u',
            '/\b(?:change|update|reset|edit)\s+my\s+(?:password|email|address|details|profile|phone)\b/u',
            '/\bmy\s+(?:saved\s+)?(?:address|addresses|password|profile|invoice|invoices|receipt|receipts|wishlist)\b/u',
            '/\b(?:log|sign)\s+(?:me\s+)?(?:in|out)\b/u',
            '/\bmy\s+(?:loyalty|reward|store)\s+(?:points|credit|balance)\b/u',
        );
        foreach ($account as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'account';
            }
        }

        // Completing a purchase is out of scope whatever is installed. Commerce
        // Pro contributes cart and checkout BUTTONS to the widget, not a chat
        // command that places an order -- its only chat seam is comparison. The
        // stand-down below assumed an addon took over all of this wording, so on
        // a Pro store "can you place my order?" matched nothing at all and
        // catalog discovery answered it with three unrelated products.
        $checkout = array(
            // Deliberately tolerant of what sits between the verb and the noun.
            // Requiring them adjacent missed "place or modify my order" and
            // "place a real order here", both of which are the same request.
            '/\b(?:place|submit|complete|finalise|finalize|confirm)\b[^?]{0,30}\b(?:order|purchase|checkout)\b/u',
            '/\b(?:can|could|will|would)\s+you\s+(?:place|submit|complete|make|do)\s+(?:my\s+|the\s+|an?\s+)?(?:order|purchase|checkout)\b/u',
            '/\bcheck\s?out\s+for\s+me\b/u',
            '/\b(?:pay|paying)\s+for\s+(?:my\s+|this\s+|the\s+)?(?:order|purchase|it|them|items?)\b/u',
            '/\b(?:order|buy|purchase)\s+(?:it|this|that|them|these)\s+for\s+me\b/u',
        );
        foreach ($checkout as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'checkout_action';
            }
        }

        // Cart actions. Only out of scope while no addon provides them, so an
        // addon that does implement chat-driven cart commands keeps its own
        // handling.
        if (!$this->cart_actions_available()) {
            $cart = array(
                '/\badd\b[^?]{0,20}\bto\s+(?:my\s+)?(?:cart|basket|bag)\b/u',
                '/\b(?:remove|delete)\b[^?]{0,20}\bfrom\s+(?:my\s+)?(?:cart|basket)\b/u',
                '/\bmy\s+(?:cart|basket)\b/u',
                '/\b(?:check\s?out|checkout|place\s+(?:my\s+)?order|complete\s+(?:my\s+)?(?:order|purchase))\b/u',
                '/\b(?:buy|purchase|order)\s+(?:it|this|that|them|these)\b/u',
                '/\bapply\s+(?:a\s+)?(?:coupon|discount\s+code|promo\s+code)\b/u',
            );
            foreach ($cart as $pattern) {
                if (preg_match($pattern, $lower)) {
                    return 'cart_action';
                }
            }
        }

        return '';
    }

    /**
     * @param string $type Detected intent type.
     * @return string
     */
    private function reply_for($type) {
        $account_url = $this->account_url();

        switch ($type) {
            case 'order_history':
                $reply = __("I can't see your order history, so I can't tell you what you bought before. Your past orders are listed under your account.", 'geeky-bot');
                break;
            case 'order_status':
                $reply = __("I can't look up orders or delivery tracking. You'll find the current status of your order under your account, and the store team can help if something looks wrong.", 'geeky-bot');
                break;
            case 'account':
                $reply = __("I can't view or change your account details. You can manage those yourself under your account.", 'geeky-bot');
                break;
            case 'cart_action':
                $reply = __("I can't add items to your cart or complete a purchase. You can add this from the product page and check out as usual.", 'geeky-bot');
                break;
            case 'checkout_action':
                $reply = __("I can't place an order or take a payment. You can add what you want to your cart and complete checkout yourself, and I'll help you decide what to buy before that.", 'geeky-bot');
                break;
            default:
                return '';
        }

        if ($account_url !== '' && in_array($type, array('order_history', 'order_status', 'account'), true)) {
            /* translators: 1: sentence explaining the limit, 2: My Account URL. */
            return sprintf(__('%1$s You can open it here: %2$s', 'geeky-bot'), $reply, $account_url);
        }

        return $reply;
    }

    /**
     * Whether any addon provides cart and checkout actions.
     *
     * @return bool
     */
    private function cart_actions_available() {
        $available = class_exists('GeekyBot\\Services\\LicenseService')
            && LicenseService::is_commerce_pro_active();

        /**
         * Filters whether chat-driven cart actions are available.
         *
         * @param bool $available True when an addon handles cart actions.
         */
        return (bool) apply_filters('geekybot_cart_actions_available', $available);
    }

    /**
     * @return string
     */
    private function account_url() {
        if (!function_exists('wc_get_page_permalink')) {
            return '';
        }

        $url = wc_get_page_permalink('myaccount');

        return is_string($url) ? esc_url_raw($url) : '';
    }

    /**
     * @param string $value Raw message.
     * @return string
     */
    private function normalize($value) {
        $value = wp_strip_all_tags((string) $value);
        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $value = remove_accents($value);
        $value = str_replace(array('’', '`'), "'", $value);
        $value = preg_replace("/\b(\w+)'(s|re|ve|ll|m|d)\b/u", '$1$2', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return trim((string) $value);
    }
}

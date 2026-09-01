<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps storefront responses concise, safe, and free from internal metadata.
 *
 * ChatService stores a richer payload for conversation continuity and admin review.
 * The public REST API must expose only fields the storefront actually needs.
 */
class ShopperOutputService {
    /**
     * Register the public REST error boundary.
     */
    public function hooks() {
        add_filter('rest_post_dispatch', array($this, 'filter_rest_response'), 20, 3);
    }

    /**
     * Replace internal REST error codes and data with shopper-safe output.
     *
     * @param mixed $response REST response.
     * @param mixed $server REST server.
     * @param mixed $request REST request.
     * @return mixed
     */
    public function filter_rest_response($response, $server, $request) {
        unset($server);

        if (!$request instanceof \WP_REST_Request || strpos((string) $request->get_route(), '/geekybot/v1/') !== 0) {
            return $response;
        }
        if (!$response instanceof \WP_REST_Response || $response->get_status() < 400) {
            return $response;
        }

        $data = $response->get_data();
        $internal_code = is_array($data) && !empty($data['code']) ? sanitize_key((string) $data['code']) : '';
        $original = is_array($data) && !empty($data['message']) ? (string) $data['message'] : '';
        $public_code = 'shopping_request_failed';
        $message = __("I couldn't complete that request right now. Please try again.", 'geeky-bot');

        if ($internal_code === 'geekybot_bad_nonce') {
            $public_code = 'shopping_session_expired';
            $message = __('Your shopping session expired. Please refresh the page and try again.', 'geeky-bot');
        } elseif ($internal_code === 'geekybot_message_too_long') {
            $public_code = 'message_too_long';
            $message = $this->clean_message($original, 240);
        } elseif (in_array($internal_code, array('geekybot_rate_limited', 'geekybot_catalog_rate_limited'), true)) {
            $public_code = 'rate_limited';
            $message = $this->clean_message($original, 240);
        } elseif ($internal_code === 'geekybot_catalog_failed') {
            $public_code = 'product_search_unavailable';
            $message = __('Product search is temporarily unavailable. Please try again.', 'geeky-bot');
        } elseif (in_array($internal_code, array('geekybot_bad_event', 'geekybot_bad_limit'), true)) {
            $public_code = 'invalid_request';
            $message = __('That request could not be completed.', 'geeky-bot');
        }

        $response->set_data(array(
            'code' => $public_code,
            'message' => $message !== '' ? $message : __("I couldn't complete that request right now. Please try again.", 'geeky-bot'),
            'data' => array('status' => absint($response->get_status())),
        ));

        return $response;
    }
    /**
     * Build the public chat response sent to the browser.
     *
     * @param array $payload Internal chat payload.
     * @return array
     */
    public function public_chat_payload($payload) {
        $payload = is_array($payload) ? $payload : array();

        $public = array(
            'message' => $this->public_message(isset($payload['message']) ? $payload['message'] : ''),
            'products' => $this->public_products(isset($payload['products']) ? $payload['products'] : array()),
            'sessionKey' => $this->valid_session_key(isset($payload['sessionKey']) ? $payload['sessionKey'] : ''),
            'knowledge' => $this->public_knowledge(isset($payload['knowledge']) ? $payload['knowledge'] : array()),
        );

        if (!empty($payload['product_expert']) && is_array($payload['product_expert'])) {
            $product_expert = $this->public_product_expert($payload['product_expert']);
            if (!empty($product_expert)) {
                $public['product_expert'] = $product_expert;
            }
        }

        if (!empty($payload['comparison']) && is_array($payload['comparison'])) {
            $comparison = $this->public_comparison($payload['comparison']);
            if (!empty($comparison)) {
                $public['comparison'] = $comparison;
            }
        }

        /**
         * Allows trusted extensions to adjust display-only public fields.
         *
         * Extensions must not add prompts, license data, settings, search
         * analysis, internal reason codes, or customer-private data.
         *
         * @param array $public Sanitized storefront response.
         * @param array $payload Full internal response.
         */
        $filtered = apply_filters('geekybot_public_chat_payload', $public, $payload);
        if (!is_array($filtered)) {
            return $public;
        }

        // Rebuild the response from the same public allowlist after filters run.
        // This prevents extensions from accidentally exposing internal search,
        // prompt, license, analytics, or conversation-state metadata.
        $safe = array(
            'message' => $this->public_message(isset($filtered['message']) ? $filtered['message'] : $public['message']),
            'products' => $this->public_products(isset($filtered['products']) ? $filtered['products'] : $public['products']),
            'sessionKey' => $this->valid_session_key(isset($filtered['sessionKey']) ? $filtered['sessionKey'] : $public['sessionKey']),
            'knowledge' => $this->public_knowledge(isset($filtered['knowledge']) ? $filtered['knowledge'] : $public['knowledge']),
        );

        if (!empty($filtered['product_expert']) && is_array($filtered['product_expert'])) {
            $product_expert = $this->public_product_expert($filtered['product_expert']);
            if (!empty($product_expert)) {
                $safe['product_expert'] = $product_expert;
            }
        }

        if (!empty($filtered['comparison']) && is_array($filtered['comparison'])) {
            $comparison = $this->public_comparison($filtered['comparison']);
            if (!empty($comparison)) {
                $safe['comparison'] = $comparison;
            }
        }

        return $safe;
    }

    /**
     * Sanitize product cards for public output.
     *
     * @param array $products Products.
     * @return array
     */
    public function public_products($products) {
        $clean = array();
        foreach ((array) $products as $product) {
            if (!is_array($product) || empty($product['id']) || empty($product['name'])) {
                continue;
            }

            $row = array(
                'id' => absint($product['id']),
                'name' => $this->clean_inline($product['name']),
                'shortDescription' => $this->clean_message(isset($product['shortDescription']) ? $product['shortDescription'] : '', 500),
                'priceHtml' => $this->safe_price_html(isset($product['priceHtml']) ? $product['priceHtml'] : ''),
                'priceText' => $this->clean_inline(isset($product['priceText']) ? $product['priceText'] : ''),
                'url' => esc_url_raw(isset($product['url']) ? $product['url'] : ''),
                'image' => esc_url_raw(isset($product['image']) ? $product['image'] : ''),
                'stockStatus' => $this->allowed_key(isset($product['stockStatus']) ? $product['stockStatus'] : '', array('instock', 'outofstock', 'onbackorder')),
                'stockLabel' => $this->clean_inline(isset($product['stockLabel']) ? $product['stockLabel'] : ''),
                'type' => $this->allowed_key(isset($product['type']) ? $product['type'] : '', array('simple', 'variable', 'variation', 'grouped', 'external')),
                'rating' => max(0, min(5, (float) (isset($product['rating']) ? $product['rating'] : 0))),
                'categories' => $this->clean_list(isset($product['categories']) ? $product['categories'] : array(), 12, 100),
                'sku' => $this->clean_inline(isset($product['sku']) ? $product['sku'] : '', 100),
                'isPurchasable' => !empty($product['isPurchasable']),
                'isInStock' => !empty($product['isInStock']),
                'requiresOptions' => !empty($product['requiresOptions']),
            );

            if (!empty($product['attributeSummary'])) {
                $row['attributeSummary'] = $this->clean_message($product['attributeSummary'], 600);
            }

            if (!empty($product['searchMatch']) && is_array($product['searchMatch'])) {
                $match = $this->public_search_match($product['searchMatch']);
                if (!empty($match)) {
                    $row['searchMatch'] = $match;
                }
            }

            $clean[] = $row;
            if (count($clean) >= 8) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Return a shopper-safe assistant message.
     *
     * @param mixed $value Message value.
     * @return string
     */
    /**
     * Prepare a reply for the storefront.
     *
     * Sanitising only. The leak screen deliberately does NOT run here, because
     * by this point the text is the plugin's own: every provider response is
     * already screened by AiService the moment it is read, so a second pass adds
     * nothing for model output while punishing our own strings for resembling
     * one. A store whose returns policy legitimately mentions an API key had the
     * whole answer replaced with a generic error, and a store installed at
     * /geekybot_demo/ lost every reply carrying a My Account link -- silently,
     * because a rejected message is never logged.
     *
     * @param mixed $value Reply text.
     * @return string
     */
    private function public_message($value) {
        $message = $this->clean_message($value, 1800);
        if ($message !== '') {
            return $message;
        }

        return __("I couldn't complete that request right now. Please try again.", 'geeky-bot');
    }

    /**
     * Reject model output that appears to expose internal instructions or errors.
     *
     * Applied to provider responses only, at the point AiService reads them --
     * see AiService::clean_ai_text(). That call is now the single line of defence
     * between a model and a shopper, so it must not be removed: nothing screens
     * this text again downstream.
     *
     * It is deliberately NOT applied to the plugin's own replies. Those are
     * authored here, and screening them only ever produced false positives on
     * ordinary wording that happens to resemble an internal name.
     *
     * @param string $text Candidate model output.
     * @return string Empty string when unsafe.
     */
    public function guarded_ai_text($text) {
        $text = $this->clean_message($text, 1800);
        if ($text === '') {
            return '';
        }

        // Links to this store are not a leak. The identifier rule below matches
        // a `geekybot_` or `gbcp_` name anywhere, and a WordPress install living
        // at a path such as /geekybot_demo/ or /gbcp_staging/ puts that string
        // inside its own public address. Every reply carrying a My Account link
        // was therefore discarded and replaced with the generic error, silently,
        // because a rejected message is never logged.
        //
        // Removing the store's own URLs before scanning keeps the rule's real
        // job intact: an internal option name is still caught wherever it
        // appears, and so is a genuinely internal path such as wp-content.
        $scannable = $this->without_store_urls($text);

        $patterns = array(
            '/\b(?:system|developer|hidden)\s+(?:prompt|instruction|message)s?\b/iu',
            '/\b(?:api|license)\s*key\b/iu',
            '/\b(?:wrapper_code|searchcontext|product_family_term|sale_required|runtime entitlement)\b/iu',
            '/\b(?:geekybot|gbcp)_[a-z0-9_]+\b/iu',
            '/\b(?:fatal error|uncaught (?:error|exception)|stack trace|sqlstate|permission_callback)\b/iu',
            '/\b(?:wp-content|wp-includes|wpdb|rest route)\b/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $scannable)) {
                return '';
            }
        }

        return $text;
    }

    /**
     * Blank out links that point at this store, so they cannot be mistaken for
     * an internal identifier.
     *
     * Only the site's own addresses are removed, and only for scanning. The
     * shopper still receives the original text, links included.
     *
     * @param string $text Candidate message.
     * @return string
     */
    private function without_store_urls($text) {
        $roots = array();

        foreach (array('home_url', 'site_url') as $source) {
            if (!function_exists($source)) {
                continue;
            }
            $url = call_user_func($source, '/');
            if (is_string($url) && $url !== '') {
                $roots[] = rtrim($url, '/');
            }
        }

        $roots = array_values(array_unique(array_filter($roots)));
        if (empty($roots)) {
            return $text;
        }

        // Longest first, so a site URL nested inside a home URL goes whole.
        usort($roots, function ($a, $b) {
            return strlen($b) <=> strlen($a);
        });

        foreach ($roots as $root) {
            // The address plus whatever path follows it, so a link such as
            // /geekybot_demo/my-account/ is removed in one piece.
            $text = preg_replace('#' . preg_quote($root, '#') . '[^\s]*#iu', ' ', $text);
        }

        return $text;
    }

    /**
     * @param mixed $value Message value.
     * @param int   $limit Character limit.
     * @return string
     */
    public function clean_message($value, $limit = 1800) {
        $value = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $value);
        $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = preg_replace('/[ \t]+/u', ' ', $value);
        $value = preg_replace('/\s*\R\s*/u', ' ', $value);
        $value = trim($value);

        return function_exists('mb_substr') ? mb_substr($value, 0, absint($limit)) : substr($value, 0, absint($limit));
    }

    private function public_knowledge($knowledge) {
        $clean = array();
        foreach ((array) $knowledge as $source) {
            if (!is_array($source)) {
                continue;
            }
            $url = esc_url_raw(isset($source['url']) ? $source['url'] : '');
            $title = $this->clean_inline(isset($source['title']) ? $source['title'] : '', 190);
            if ($url === '' || $title === '') {
                continue;
            }
            $clean[] = array(
                'id' => absint(isset($source['id']) ? $source['id'] : 0),
                'title' => $title,
                'url' => $url,
            );
            if (count($clean) >= 3) {
                break;
            }
        }
        return $clean;
    }

    private function public_product_expert($meta) {
        if (empty($meta['verified'])) {
            return array();
        }

        $allowed_labels = array(
            __('From store details', 'geeky-bot'),
            __('Checked store details', 'geeky-bot'),
        );
        $label = $this->clean_inline(isset($meta['verifiedLabel']) ? $meta['verifiedLabel'] : '', 120);
        if (!in_array($label, $allowed_labels, true)) {
            $label = __('From store details', 'geeky-bot');
        }

        return array(
            'verified' => true,
            'verifiedLabel' => $label,
            'productId' => absint(isset($meta['productId']) ? $meta['productId'] : 0),
            'alwaysShowSource' => !empty($meta['alwaysShowSource']) || (!empty($meta['sourceStatus']) && sanitize_key((string) $meta['sourceStatus']) === 'checked'),
        );
    }

    private function public_comparison($comparison) {
        $products = $this->public_products(isset($comparison['products']) ? $comparison['products'] : array());
        if (count($products) < 2) {
            return array();
        }

        $allowed_keys = array('priceHtml', 'stockLabel', 'rating', 'categories', 'attributeSummary', 'shortDescription');
        $rows = array();
        foreach ((array) (isset($comparison['rows']) ? $comparison['rows'] : array()) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $key = isset($row['key']) ? (string) $row['key'] : '';
            if (!in_array($key, $allowed_keys, true)) {
                continue;
            }
            $rows[] = array(
                'label' => $this->clean_inline(isset($row['label']) ? $row['label'] : '', 80),
                'key' => $key,
                'html' => !empty($row['html']),
            );
        }

        // Authored by the plugin or a commerce addon, so sanitised rather than
        // screened, for the same reason as public_message().
        $message = $this->clean_message(isset($comparison['message']) ? $comparison['message'] : '', 1800);
        if ($message === '') {
            $message = __('Here is the product comparison.', 'geeky-bot');
        }

        return array(
            'message' => $message,
            'products' => $products,
            'rows' => array_slice($rows, 0, 8),
        );
    }

    private function public_search_match($match) {
        $matched = $this->clean_list(isset($match['matched']) ? $match['matched'] : array(), 6, 120);
        $not_confirmed = $this->clean_list(isset($match['notConfirmed']) ? $match['notConfirmed'] : array(), 6, 120);
        if (empty($matched) && empty($not_confirmed)) {
            return array();
        }

        return array(
            'type' => $this->allowed_key(isset($match['type']) ? $match['type'] : 'match', array('match', 'close')),
            'label' => $this->clean_inline(isset($match['label']) ? $match['label'] : __('Match details', 'geeky-bot'), 80),
            'matchedLabel' => $this->clean_inline(isset($match['matchedLabel']) ? $match['matchedLabel'] : __('Matched', 'geeky-bot'), 80),
            'matched' => $matched,
            'notConfirmedLabel' => $this->clean_inline(isset($match['notConfirmedLabel']) ? $match['notConfirmedLabel'] : __('Not confirmed', 'geeky-bot'), 80),
            'notConfirmed' => $not_confirmed,
        );
    }

    private function safe_price_html($html) {
        $allowed = array(
            'span' => array('class' => true, 'aria-hidden' => true),
            'del' => array('aria-hidden' => true),
            'ins' => array(),
            'bdi' => array(),
            'small' => array('class' => true),
        );
        return wp_kses((string) $html, $allowed);
    }

    private function clean_inline($value, $limit = 190) {
        $value = $this->clean_message($value, $limit);
        return trim($value);
    }

    private function clean_list($values, $limit, $item_limit) {
        $clean = array();
        foreach ((array) $values as $value) {
            $value = $this->clean_inline($value, $item_limit);
            if ($value === '' || in_array($value, $clean, true)) {
                continue;
            }
            $clean[] = $value;
            if (count($clean) >= absint($limit)) {
                break;
            }
        }
        return $clean;
    }

    private function allowed_key($value, $allowed) {
        $value = sanitize_key((string) $value);
        return in_array($value, (array) $allowed, true) ? $value : '';
    }

    private function valid_session_key($value) {
        $value = (string) $value;
        return preg_match('/^[a-f0-9\-]{32,64}$/', $value) ? $value : '';
    }
}

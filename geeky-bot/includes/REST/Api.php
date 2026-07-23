<?php
namespace GeekyBot\REST;

if (!defined('ABSPATH')) {
    exit;
}

use GeekyBot\Services\ChatService;
use GeekyBot\Services\ProductService;
use GeekyBot\Services\Settings;
use GeekyBot\Services\RateLimiter;
use GeekyBot\Services\AnalyticsEventService;
use GeekyBot\Services\ShopperOutputService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class Api {
    const NAMESPACE = 'geekybot/v1';

    public function hooks() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/chat', array(
            'methods' => 'POST',
            'callback' => array($this, 'chat'),
            'permission_callback' => array($this, 'public_nonce_permission'),
            'args' => array(
                'message' => array('required' => true, 'type' => 'string'),
                'sessionKey' => array('required' => false, 'type' => 'string'),
            ),
        ));

        register_rest_route(self::NAMESPACE, '/products', array(
            'methods' => 'GET',
            'callback' => array($this, 'products'),
            'permission_callback' => array($this, 'catalog_permission'),
            'args' => array(
                'q' => array('required' => false, 'type' => 'string'),
                'limit' => array('required' => false, 'type' => 'integer'),
            ),
        ));


        register_rest_route(self::NAMESPACE, '/events', array(
            'methods' => 'POST',
            'callback' => array($this, 'event'),
            'permission_callback' => array($this, 'event_permission'),
            'args' => array(
                'eventType' => array('required' => true, 'type' => 'string'),
                'sessionKey' => array('required' => false, 'type' => 'string'),
                'objectId' => array('required' => false, 'type' => 'integer'),
                'objectLabel' => array('required' => false, 'type' => 'string'),
                'context' => array('required' => false, 'type' => 'object'),
            ),
        ));
    }

    public function public_nonce_permission(WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('geekybot_bad_nonce', __('Security check failed. Please refresh the page and try again.', 'geeky-bot'), array('status' => 403));
        }

        $limited = (new RateLimiter())->check_chat_limit();
        if (is_wp_error($limited)) {
            return $limited;
        }

        return true;
    }

    public function event_permission(WP_REST_Request $request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error('geekybot_bad_nonce', __('Security check failed. Please refresh the page and try again.', 'geeky-bot'), array('status' => 403));
        }

        $event_type = sanitize_key((string) $request->get_param('eventType'));
        if (!in_array($event_type, AnalyticsEventService::allowed_event_types(), true)) {
            return new WP_Error('geekybot_bad_event', __('This analytics event is not allowed.', 'geeky-bot'), array('status' => 400));
        }

        $limited = (new RateLimiter())->check_event_limit();
        if (is_wp_error($limited)) {
            return $limited;
        }

        return true;
    }

    public function catalog_permission(WP_REST_Request $request) {
        $limit = $request->get_param('limit') ? absint($request->get_param('limit')) : absint(Settings::get('max_products', 4));
        if ($limit > 8) {
            return new WP_Error('geekybot_bad_limit', __('Product search limit is too high.', 'geeky-bot'), array('status' => 400));
        }

        $limited = (new RateLimiter())->check_catalog_limit();
        if (is_wp_error($limited)) {
            return $limited;
        }

        return true;
    }

    public function chat(WP_REST_Request $request) {
        $message = (string) $request->get_param('message');
        if (function_exists('mb_strlen') ? mb_strlen($message) > 1000 : strlen($message) > 1000) {
            return new WP_Error('geekybot_message_too_long', __('Please keep your question shorter so I can search the store accurately.', 'geeky-bot'), array('status' => 400));
        }
        $session_key = (string) $request->get_param('sessionKey');

        try {
            $service = new ChatService();
            $response = $service->reply($message, $session_key);
            $public = (new ShopperOutputService())->public_chat_payload($response);
            return new WP_REST_Response($public, 200);
        } catch (\Throwable $error) {
            /**
             * Fires when a storefront chat request fails unexpectedly.
             *
             * The shopper receives a generic message. The error object is
             * available only to trusted server-side logging integrations.
             */
            do_action('geekybot_internal_chat_error', $error);

            return new WP_Error(
                'geekybot_request_failed',
                __("I couldn't complete that request right now. Please try again.", 'geeky-bot'),
                array('status' => 500)
            );
        }
    }

    public function event(WP_REST_Request $request) {
        $service = new AnalyticsEventService();
        $recorded = $service->record(
            sanitize_key((string) $request->get_param('eventType')),
            array(
                'session_key' => sanitize_text_field((string) $request->get_param('sessionKey')),
                'object_id' => absint($request->get_param('objectId')),
                'object_label' => sanitize_text_field((string) $request->get_param('objectLabel')),
                'context' => is_array($request->get_param('context')) ? $request->get_param('context') : array(),
            )
        );

        return new WP_REST_Response(array('recorded' => (bool) $recorded), $recorded ? 201 : 200);
    }

    public function products(WP_REST_Request $request) {
        $q = sanitize_text_field((string) $request->get_param('q'));
        $q = function_exists('mb_substr') ? mb_substr($q, 0, 200) : substr($q, 0, 200);
        $limit = $request->get_param('limit') ? absint($request->get_param('limit')) : absint(Settings::get('max_products', 4));
        $limit = max(1, min(8, $limit));
        try {
            $service = new ProductService();
            $products = $service->search($q, $limit);
            return new WP_REST_Response(array(
                'products' => (new ShopperOutputService())->public_products($products),
            ), 200);
        } catch (\Throwable $error) {
            do_action('geekybot_internal_catalog_error', $error);
            return new WP_Error(
                'geekybot_catalog_failed',
                __('Product search is temporarily unavailable. Please try again.', 'geeky-bot'),
                array('status' => 500)
            );
        }
    }
}

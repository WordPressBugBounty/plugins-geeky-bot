<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One way to call the AI providers, shared by chat answers, Smart Catalog,
 * Rescue and learning.
 *
 * Until 2.2.0 every provider error became an empty answer: the chat quietly
 * fell back to local answers, the call was still charged to the budget, and
 * nobody was told. This class builds requests each model accepts, swaps
 * retired models for their replacement, sorts errors into classes the
 * merchant can act on, pauses calls that cannot succeed, and records the
 * outcome for the admin notice and the health check.
 */
class AiClient {
    const DEFAULT_MODEL = 'gpt-6-luna';
    const HEALTH_OPTION = 'geekybot_ai_health';
    const QUIRKS_OPTION = 'geekybot_ai_model_quirks';
    const PAUSE_TRANSIENT = 'geekybot_ai_paused';
    const PAUSE_SECONDS = 600;
    const OPENAI_URL = 'https://api.openai.com/v1/chat/completions';

    /**
     * OpenAI models that are shut down or scheduled to be, with the date they
     * stop answering and the replacement OpenAI names. From
     * https://developers.openai.com/api/docs/deprecations, read 17 Nov 2026.
     * A dated snapshot ("gpt-4o-mini-2024-07-18") follows its base model.
     */
    const RETIREMENTS = array(
        'gpt-4.1-nano' => array('2026-10-23', 'gpt-6-luna'),
        'gpt-5-nano' => array('2026-12-11', 'gpt-6-luna'),
        'gpt-5-mini' => array('2026-12-11', 'gpt-6-luna'),
        'gpt-4o-mini' => array('2027-01-14', 'gpt-6-luna'),
        'gpt-4o' => array('2027-01-14', 'gpt-6-luna'),
        'gpt-4.1' => array('2027-01-14', 'gpt-6-luna'),
        'gpt-4.1-mini' => array('2027-01-14', 'gpt-6-luna'),
        'gpt-5.4-nano' => array('2027-04-01', 'gpt-6-luna'),
    );

    /** Errors that retrying cannot fix: calls pause and the merchant is told. */
    const HARD_ERRORS = array('auth', 'billing', 'region');

    public function hooks() {
        add_action('admin_notices', array($this, 'admin_notice'));
        add_action('admin_post_geekybot_ai_switch_model', array($this, 'handle_switch_model'));
    }

    /* ---------------------------------------------------------------------
     * Models
     * ------------------------------------------------------------------ */

    /**
     * Model ID without a dated snapshot suffix.
     *
     * @param string $model Model ID.
     * @return string
     */
    public static function base_model($model) {
        return (string) preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', strtolower(trim((string) $model)));
    }

    /**
     * Retirement entry for a model, or null when it has none.
     *
     * @param string $model Model ID.
     * @return array{date: string, replacement: string, retired: bool}|null
     */
    public static function retirement($model) {
        $base = self::base_model($model);
        if (!isset(self::RETIREMENTS[$base])) {
            return null;
        }
        list($date, $replacement) = self::RETIREMENTS[$base];

        return array(
            'date' => $date,
            'replacement' => $replacement,
            'retired' => gmdate('Y-m-d') >= $date,
        );
    }

    /**
     * The model to call: the one asked for, or its replacement once OpenAI
     * has shut it down.
     *
     * @param string $model Model ID.
     * @return string
     */
    public static function effective_model($model) {
        $model = trim((string) $model);
        if ($model === '') {
            return self::DEFAULT_MODEL;
        }
        $retirement = self::retirement($model);

        return ($retirement && $retirement['retired']) ? $retirement['replacement'] : $model;
    }

    /**
     * gpt-6: reasoning off by default here, sampling allowed.
     * reasoning: gpt-5 and o-series, which reject sampling parameters.
     * classic: everything else.
     *
     * @param string $model Model ID.
     * @return string
     */
    public static function family($model) {
        $base = self::base_model($model);
        if (preg_match('/^gpt-6/', $base)) {
            return 'gpt6';
        }
        if (preg_match('/^(gpt-5|o\d)/', $base)) {
            return 'reasoning';
        }

        return 'classic';
    }

    /**
     * Chat Completions body for a model.
     *
     * Every current model accepts `max_completion_tokens`, and gpt-6 models
     * reject `max_tokens`. Parameters a model has already refused once are
     * left out (see learn_quirk()).
     *
     * @param array $spec {model, messages, max_tokens, json?, temperature?, reasoning_room?}
     * @return array
     */
    public static function chat_payload(array $spec) {
        $model = (string) $spec['model'];
        $max_tokens = max(16, absint($spec['max_tokens'] ?? 450));
        $payload = array(
            'model' => $model,
            'messages' => array_values((array) $spec['messages']),
        );

        switch (self::family($model)) {
            case 'gpt6':
                // Short grounded answers need no reasoning, and with it off
                // the whole token ceiling goes to the answer.
                $payload['reasoning_effort'] = 'none';
                $payload['temperature'] = (float) ($spec['temperature'] ?? 0.2);
                $payload['max_completion_tokens'] = $max_tokens;
                break;
            case 'reasoning':
                $payload['reasoning_effort'] = preg_match('/^gpt-5/', self::base_model($model)) ? 'minimal' : 'low';
                // Hidden reasoning tokens come out of the same ceiling.
                $payload['max_completion_tokens'] = max($max_tokens * 4, absint($spec['reasoning_room'] ?? 4000));
                break;
            default:
                $payload['temperature'] = (float) ($spec['temperature'] ?? 0.2);
                $payload['max_completion_tokens'] = $max_tokens;
        }

        if (!empty($spec['json'])) {
            $payload['response_format'] = array('type' => 'json_object');
        }

        $quirks = get_option(self::QUIRKS_OPTION, array());
        $known = is_array($quirks) && isset($quirks[self::base_model($model)]) ? (array) $quirks[self::base_model($model)] : array();
        foreach ($known as $param => $fix) {
            if ($fix === 'drop') {
                unset($payload[$param]);
            } elseif ($param === 'reasoning_effort' && isset($payload['reasoning_effort'])) {
                $payload['reasoning_effort'] = (string) $fix;
            }
        }

        return $payload;
    }

    /**
     * Remember that a model refused a parameter, so later requests leave it
     * out instead of failing first.
     *
     * @param string $model Model ID.
     * @param string $param Parameter name.
     * @param string $fix   'drop', or a replacement value for reasoning_effort.
     * @return void
     */
    private static function learn_quirk($model, $param, $fix) {
        $quirks = get_option(self::QUIRKS_OPTION, array());
        $quirks = is_array($quirks) ? $quirks : array();
        $base = self::base_model($model);
        $quirks[$base] = isset($quirks[$base]) ? (array) $quirks[$base] : array();
        $quirks[$base][sanitize_key($param)] = sanitize_key($fix);
        // A few models at most; keep the option small if IDs keep changing.
        $quirks = array_slice($quirks, -20, null, true);
        update_option(self::QUIRKS_OPTION, $quirks, false);
    }

    /* ---------------------------------------------------------------------
     * Requests
     * ------------------------------------------------------------------ */

    /**
     * One Chat Completions call with recovery.
     *
     * At most three attempts: the request, then one of a fix for a refused
     * parameter, a swap to the replacement model after "model not found", or
     * a single retry after a rate limit or outage.
     *
     * @param array  $spec    See chat_payload(); 'model' may be a retired ID.
     * @param int    $timeout Seconds for the first attempt.
     * @param string $purpose chat|catalog|rescue|learning, for the health record.
     * @return array {ok: true, data: array, model: string} or {ok: false, class, http, message}
     */
    public static function openai_chat(array $spec, $timeout, $purpose = 'chat') {
        if (self::paused()) {
            return self::failure('paused', 0, __('AI calls are paused after an error the provider will keep returning.', 'geeky-bot'));
        }

        $key = Settings::secret('openai_api_key');
        if ($key === '') {
            return self::failure('auth', 0, __('No OpenAI API key is saved.', 'geeky-bot'));
        }

        $asked = (string) ($spec['model'] ?? '');
        $spec['model'] = self::effective_model($asked);
        if ($spec['model'] !== $asked && $asked !== '') {
            self::note_swap($asked, $spec['model']);
        }

        $deadline = microtime(true) + max(5, (int) $timeout) + 6;
        $payload = self::chat_payload($spec);
        $result = null;
        $retried = false;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $left = $deadline - microtime(true);
            if ($attempt > 1 && $left < 3) {
                break;
            }
            $response = wp_remote_post(self::OPENAI_URL, array(
                'timeout' => $attempt === 1 ? (int) $timeout : (int) max(3, min($timeout, $left)),
                'headers' => array(
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($payload),
            ));
            $result = self::read_openai($response);
            if ($result['ok']) {
                $result['model'] = $payload['model'];
                self::record_success('openai', $payload['model']);
                return $result;
            }

            if ($attempt === 3) {
                break;
            }
            // A refused parameter: leave it out (or step reasoning down) and retry.
            if ($result['class'] === 'request' && $result['param'] !== '' && array_key_exists($result['param'], $payload)) {
                $param = $result['param'];
                if ($param === 'max_tokens') {
                    $payload['max_completion_tokens'] = $payload['max_tokens'];
                    unset($payload['max_tokens']);
                    self::learn_quirk($payload['model'], 'max_tokens', 'drop');
                } elseif ($param === 'reasoning_effort' && $result['code'] === 'unsupported_value' && $payload['reasoning_effort'] !== 'low') {
                    $payload['reasoning_effort'] = 'low';
                    self::learn_quirk($payload['model'], 'reasoning_effort', 'low');
                } else {
                    unset($payload[$param]);
                    self::learn_quirk($payload['model'], $param, 'drop');
                }
                continue;
            }
            // The model is gone, or the key cannot use it: try the default once.
            if ($result['class'] === 'model' && self::base_model($payload['model']) !== self::DEFAULT_MODEL) {
                $retirement = self::retirement($payload['model']);
                $to = $retirement ? $retirement['replacement'] : self::DEFAULT_MODEL;
                self::note_swap($payload['model'], $to);
                $spec['model'] = $to;
                $payload = self::chat_payload($spec);
                continue;
            }
            if (!$retried && self::worth_retrying($result)) {
                $retried = true;
                if ($result['class'] === 'rate_limit') {
                    usleep(800000);
                }
                continue;
            }
            break;
        }

        self::record_error('openai', $payload['model'], $result, $purpose);
        return $result;
    }

    /**
     * One call to a Zywrap wrapper.
     *
     * @param string $endpoint Proxy URL.
     * @param array  $body     Wrapper request.
     * @param int    $timeout  Seconds.
     * @param string $purpose  For the health record.
     * @return array {ok: true, data: array} or {ok: false, class, http, message}
     */
    public static function zywrap_post($endpoint, array $body, $timeout, $purpose = 'chat') {
        if (self::paused()) {
            return self::failure('paused', 0, __('AI calls are paused after an error the provider will keep returning.', 'geeky-bot'));
        }
        $key = Settings::secret('zywrap_api_key');
        if ($key === '' || !$endpoint) {
            return self::failure('auth', 0, __('No Zywrap API key is saved.', 'geeky-bot'));
        }

        $result = null;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = wp_remote_post($endpoint, array(
                'timeout' => (int) $timeout,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $key,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode($body),
            ));
            $result = self::read_zywrap($response);
            if ($result['ok']) {
                self::record_success('zywrap', '');
                return $result;
            }
            if (!self::worth_retrying($result)) {
                break;
            }
        }

        self::record_error('zywrap', '', $result, $purpose);
        return $result;
    }

    /**
     * @param array|\WP_Error $response HTTP response.
     * @return array
     */
    private static function read_openai($response) {
        if (is_wp_error($response)) {
            return self::failure('unavailable', 0, $response->get_error_message());
        }
        $http = absint(wp_remote_retrieve_response_code($response));
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($http >= 200 && $http < 300 && is_array($body)) {
            return array('ok' => true, 'data' => $body);
        }

        $error = is_array($body) && isset($body['error']) && is_array($body['error']) ? $body['error'] : array();
        $type = (string) ($error['type'] ?? '');
        $code = (string) ($error['code'] ?? '');
        $message = (string) ($error['message'] ?? sprintf('HTTP %d', $http));
        $billing_codes = array('insufficient_quota', 'credit_balance_exhausted', 'organization_spend_limit_exceeded', 'project_spend_limit_exceeded', 'organization_usage_limit_exceeded', 'billing_hard_limit_reached');

        if ($http === 401) {
            $class = 'auth';
        } elseif ($http === 403) {
            $class = (strpos($code, 'country') !== false || strpos($code, 'region') !== false || stripos($message, 'country') !== false) ? 'region' : 'auth';
        } elseif ($http === 404 || $code === 'model_not_found') {
            $class = 'model';
        } elseif ($http === 429) {
            $class = (in_array($code, $billing_codes, true) || $type === 'insufficient_quota') ? 'billing' : 'rate_limit';
        } elseif ($http === 400) {
            $class = 'request';
        } elseif ($http >= 200 && $http < 300) {
            $class = 'empty';
        } else {
            $class = 'unavailable';
        }

        $failure = self::failure($class, $http, $message);
        $failure['code'] = sanitize_key($code);
        $failure['param'] = sanitize_key((string) ($error['param'] ?? ''));

        return $failure;
    }

    /**
     * Zywrap answers {error: {message, code}} with 401/403 for a key it does
     * not accept, 402 for no credits, 429 for its rate limit, and an HTML page
     * during an outage.
     *
     * @param array|\WP_Error $response HTTP response.
     * @return array
     */
    private static function read_zywrap($response) {
        if (is_wp_error($response)) {
            return self::failure('unavailable', 0, $response->get_error_message());
        }
        $http = absint(wp_remote_retrieve_response_code($response));
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ($http >= 200 && $http < 300 && is_array($body)) {
            return array('ok' => true, 'data' => $body);
        }

        $message = is_array($body) && !empty($body['error']['message']) ? (string) $body['error']['message'] : sprintf('HTTP %d', $http);
        if ($http === 401 || $http === 403) {
            $class = 'auth';
        } elseif ($http === 402) {
            $class = 'billing';
        } elseif ($http === 429) {
            // "Monthly free request limit exceeded" will not clear in seconds.
            $class = preg_match('/credit|monthly|quota|plan/i', $message) ? 'billing' : 'rate_limit';
        } elseif ($http === 400 || $http === 404) {
            $class = 'request';
        } else {
            $class = 'unavailable';
        }

        return self::failure($class, $http, $message);
    }

    /**
     * One retry, and only when the provider itself answered with a rate limit
     * or a server error. A timeout or a refused connection is not retried:
     * the shopper would wait the whole timeout again for the same result.
     *
     * @param array $result Failed result.
     * @return bool
     */
    private static function worth_retrying(array $result) {
        return $result['class'] === 'rate_limit' || ($result['class'] === 'unavailable' && $result['http'] >= 500);
    }

    private static function failure($class, $http, $message) {
        return array(
            'ok' => false,
            'class' => $class,
            'http' => (int) $http,
            'code' => '',
            'param' => '',
            'message' => self::clean_message($message),
        );
    }

    /**
     * Provider messages are shown to the merchant; never echo anything that
     * looks like a key back, even the partly masked one OpenAI includes.
     *
     * @param string $message Provider message.
     * @return string
     */
    private static function clean_message($message) {
        $message = preg_replace('/\b(sk|zw|key)[-_][A-Za-z0-9_\-\*\.]{6,}/i', '$1-…', wp_strip_all_tags((string) $message));

        return wp_html_excerpt(trim((string) $message), 220, '…');
    }

    /* ---------------------------------------------------------------------
     * Health
     * ------------------------------------------------------------------ */

    /**
     * True while calls are paused after a hard error. Saving the AI settings
     * clears the pause (see Settings::update()).
     *
     * @return bool
     */
    public static function paused() {
        return (bool) get_transient(self::PAUSE_TRANSIENT);
    }

    public static function clear_pause() {
        delete_transient(self::PAUSE_TRANSIENT);
    }

    /**
     * @return array {provider, model, last_ok, error|null, swap|null}
     */
    public static function health() {
        $health = get_option(self::HEALTH_OPTION, array());

        return wp_parse_args(is_array($health) ? $health : array(), array(
            'provider' => '',
            'model' => '',
            'last_ok' => 0,
            'error' => null,
            'swap' => null,
        ));
    }

    private static function save_health(array $health) {
        update_option(self::HEALTH_OPTION, $health, false);
    }

    /**
     * Written when something changed or at most hourly, so a busy store does
     * not update an option on every answer.
     */
    private static function record_success($provider, $model) {
        $health = self::health();
        $changed = !empty($health['error']) || $health['provider'] !== $provider || $health['model'] !== $model;
        if (!$changed && time() - (int) $health['last_ok'] < HOUR_IN_SECONDS) {
            return;
        }
        $health['provider'] = $provider;
        $health['model'] = $model;
        $health['last_ok'] = time();
        $health['error'] = null;
        self::save_health($health);
    }

    private static function record_error($provider, $model, array $result, $purpose) {
        if ($result['class'] === 'paused') {
            return;
        }
        $health = self::health();
        $previous = is_array($health['error']) ? $health['error'] : null;
        $same = $previous && $previous['class'] === $result['class'] && $previous['provider'] === $provider;
        $health['error'] = array(
            'class' => $result['class'],
            'http' => (int) $result['http'],
            'message' => $result['message'],
            'provider' => $provider,
            'model' => $model,
            'purpose' => sanitize_key($purpose),
            'first_at' => $same ? (int) $previous['first_at'] : time(),
            'at' => time(),
            'count' => $same ? absint($previous['count']) + 1 : 1,
        );
        self::save_health($health);

        if (in_array($result['class'], self::HARD_ERRORS, true)) {
            set_transient(self::PAUSE_TRANSIENT, $result['class'], self::PAUSE_SECONDS);
        }

        /**
         * Fires when an AI call fails after Geeky Bot's own recovery.
         *
         * @param string $class   auth|billing|region|model|rate_limit|request|unavailable|empty.
         * @param array  $details {provider, model, http, message, purpose}.
         */
        do_action('geekybot_ai_error', $result['class'], array(
            'provider' => $provider,
            'model' => $model,
            'http' => (int) $result['http'],
            'message' => $result['message'],
            'purpose' => $purpose,
        ));
    }

    private static function note_swap($from, $to) {
        $health = self::health();
        if (is_array($health['swap']) && $health['swap']['from'] === $from && $health['swap']['to'] === $to) {
            return;
        }
        $health['swap'] = array('from' => (string) $from, 'to' => (string) $to, 'at' => time());
        self::save_health($health);
    }

    /**
     * Plain-language line for an error class.
     *
     * @param string $class Error class.
     * @param string $provider openai|zywrap.
     * @return string
     */
    public static function explain($class, $provider = 'openai') {
        $name = $provider === 'zywrap' ? 'Zywrap' : 'OpenAI';
        switch ($class) {
            case 'auth':
                /* translators: %s: AI provider name. */
                return sprintf(__('%s did not accept the API key. Check or replace the key.', 'geeky-bot'), $name);
            case 'billing':
                /* translators: %s: AI provider name. */
                return sprintf(__('%s says the account has no credit or reached its spending limit. Add credit or raise the limit with the provider.', 'geeky-bot'), $name);
            case 'region':
                /* translators: %s: AI provider name. */
                return sprintf(__('%s does not serve this server\'s country or region.', 'geeky-bot'), $name);
            case 'model':
                return __('The chosen model is not available to this API key. Geeky Bot used its default model instead; choose a model that works.', 'geeky-bot');
            case 'rate_limit':
                /* translators: %s: AI provider name. */
                return sprintf(__('%s is limiting how many requests this key can send. Answers came from store data until it allowed more.', 'geeky-bot'), $name);
            case 'request':
                /* translators: %s: AI provider name. */
                return sprintf(__('%s refused the request format. Updating Geeky Bot or choosing another model usually fixes this.', 'geeky-bot'), $name);
            default:
                /* translators: %s: AI provider name. */
                return sprintf(__('%s did not answer in time or was unavailable.', 'geeky-bot'), $name);
        }
    }

    /**
     * Problems worth interrupting the merchant for: a hard error, a model
     * error, or a refused request, within the last day.
     *
     * @return array|null The health error.
     */
    public static function serious_error() {
        $health = self::health();
        $error = $health['error'];
        if (!is_array($error) || time() - (int) $error['at'] > DAY_IN_SECONDS) {
            return null;
        }

        return in_array($error['class'], array('auth', 'billing', 'region', 'model', 'request'), true) ? $error : null;
    }

    /**
     * Models the store is set to use that OpenAI retires.
     *
     * @return array<string, array> Setting key => retirement entry + model.
     */
    public static function retiring_models() {
        if (Settings::get('provider_mode', 'local') !== 'openai' && !Settings::has_secret('openai_api_key')) {
            return array();
        }
        $found = array();
        foreach (array('openai_model', 'search_ai_model') as $key) {
            $model = (string) Settings::get($key, '');
            $retirement = $model !== '' ? self::retirement($model) : null;
            if ($retirement) {
                $found[$key] = $retirement + array('model' => $model);
            }
        }

        return $found;
    }

    /* ---------------------------------------------------------------------
     * Admin
     * ------------------------------------------------------------------ */

    public function admin_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $screen_id = $screen ? (string) $screen->id : '';
        $on_plugin_page = strpos($screen_id, 'geekybot') !== false;
        if (!$on_plugin_page && !in_array($screen_id, array('dashboard', 'plugins'), true)) {
            return;
        }
        $settings_url = admin_url('admin.php?page=geekybot-settings#gb-settings-ai');

        $error = self::serious_error();
        if ($error) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('Geeky Bot: AI answers are failing.', 'geeky-bot') . '</strong> '
                . esc_html(self::explain($error['class'], $error['provider'])) . ' '
                . esc_html__('Shoppers get answers from your store data without AI until it is fixed.', 'geeky-bot')
                . ' <a href="' . esc_url($settings_url) . '">' . esc_html__('Open AI & Privacy', 'geeky-bot') . '</a></p></div>';
            return;
        }

        // Retirement warnings only on the plugin's own screens: they need a decision, not urgency.
        if (!$on_plugin_page) {
            return;
        }
        foreach (self::retiring_models() as $retirement) {
            $date = date_i18n(get_option('date_format'), strtotime($retirement['date'] . ' 12:00:00'));
            $line = $retirement['retired']
                /* translators: 1: old model, 2: date, 3: replacement model. */
                ? sprintf(__('OpenAI shut down %1$s on %2$s. Geeky Bot now uses %3$s for it.', 'geeky-bot'), $retirement['model'], $date, $retirement['replacement'])
                /* translators: 1: old model, 2: date, 3: replacement model. */
                : sprintf(__('OpenAI shuts down %1$s on %2$s. Geeky Bot will then use %3$s, OpenAI\'s replacement, which also costs less.', 'geeky-bot'), $retirement['model'], $date, $retirement['replacement']);
            $button = wp_nonce_url(admin_url('admin-post.php?action=geekybot_ai_switch_model'), 'geekybot_ai_switch_model');
            echo '<div class="notice notice-warning"><p><strong>Geeky Bot:</strong> ' . esc_html($line)
                . ' <a class="button button-small" href="' . esc_url($button) . '">'
                /* translators: %s: replacement model. */
                . esc_html(sprintf(__('Switch to %s now', 'geeky-bot'), $retirement['replacement'])) . '</a></p></div>';
            return;
        }
    }

    /**
     * One click from the retirement notice: every retiring model setting
     * moves to its replacement.
     */
    public function handle_switch_model() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to change Geeky Bot settings.', 'geeky-bot'));
        }
        check_admin_referer('geekybot_ai_switch_model');
        $changes = array();
        foreach (self::retiring_models() as $key => $retirement) {
            $changes[$key] = $retirement['replacement'];
        }
        if ($changes) {
            Settings::update(wp_parse_args($changes, Settings::all()));
        }
        wp_safe_redirect(admin_url('admin.php?page=geekybot-settings&updated=1#gb-settings-ai'));
        exit;
    }
}

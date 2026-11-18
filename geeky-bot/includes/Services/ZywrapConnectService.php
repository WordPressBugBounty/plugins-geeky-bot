<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create or connect a Zywrap account from Geeky Bot → AI & Privacy.
 *
 * Choosing Zywrap used to mean leaving WordPress, signing up, finding the API
 * Keys page, creating a key and pasting it back. The Zywrap plugin measured
 * that path: of 20 people who signed up through it, 4 ever used it. This uses
 * the same two flows the Zywrap plugin uses since 1.4.0, against the same
 * Zywrap endpoints:
 *
 *   - In wp-admin (ajax_signup_start, ajax_signup_verify): the admin enters an
 *     email, Zywrap emails a 6-digit code, and the code is traded for a key.
 *     A new address creates an account; a known one signs in.
 *   - On zywrap.com (handle_connect_start, handle_return): for Google, GitHub
 *     and two-factor accounts, which an emailed code must not bypass.
 *
 * Both keep a PKCE verifier on this server, so a code seen in a browser
 * history or an email cannot be redeemed anywhere else. Nothing is sent to
 * Zywrap until the admin clicks, and only the email, the optional name, this
 * site's address and name, and where the sign-up came from (Geeky Bot, its
 * version and the UTM tags below) are sent.
 */
class ZywrapConnectService {
    const ACCOUNT_OPTION = 'geekybot_zywrap_account';

    const NONCE = 'geekybot_zywrap_signup';
    const CONNECT_ACTION = 'geekybot_zywrap_connect';
    const DISCONNECT_ACTION = 'geekybot_zywrap_disconnect';

    /** Pending sign-ups and connects, one per admin; abandoned ones expire. */
    const SIGNUP_PREFIX = 'geekybot_zywrap_signup_';
    const CONNECT_PREFIX = 'geekybot_zywrap_connect_';
    const PENDING_TTL = 600;

    /** Where on Geeky Bot's screens the admin started, for utm_content. */
    const SOURCES = array('ai-privacy', 'smart-catalog', 'setup');

    public function hooks() {
        add_action('wp_ajax_geekybot_zywrap_signup_start', array($this, 'ajax_signup_start'));
        add_action('wp_ajax_geekybot_zywrap_signup_verify', array($this, 'ajax_signup_verify'));
        add_action('admin_post_' . self::CONNECT_ACTION, array($this, 'handle_connect_start'));
        add_action('admin_post_' . self::DISCONNECT_ACTION, array($this, 'handle_disconnect'));
        add_action('admin_init', array($this, 'handle_return'));
    }

    /**
     * Zywrap's API. GEEKYBOT_ZYWRAP_API_URL points a test site at a local
     * Zywrap backend.
     *
     * @param string $path Path below the API root.
     * @return string
     */
    public static function api_url($path) {
        $base = defined('GEEKYBOT_ZYWRAP_API_URL') ? (string) GEEKYBOT_ZYWRAP_API_URL : 'https://api.zywrap.com';

        return untrailingslashit($base) . '/' . ltrim($path, '/');
    }

    /**
     * Zywrap's website, where people sign in and top up.
     *
     * @param string $path Path below the site root.
     * @return string
     */
    public static function app_url($path) {
        $base = defined('GEEKYBOT_ZYWRAP_APP_URL') ? (string) GEEKYBOT_ZYWRAP_APP_URL : 'https://zywrap.com';

        return untrailingslashit($base) . '/' . ltrim($path, '/');
    }

    /**
     * The UTM tags every Zywrap link and sign-up from Geeky Bot carries, so
     * Zywrap can count what Geeky Bot brings.
     *
     * @param string $campaign zywrap-signup, zywrap-connect or zywrap-topup.
     * @param string $content  Where it was clicked: one of SOURCES.
     * @return array
     */
    public static function utm($campaign, $content) {
        return array(
            'utm_source' => 'geeky-bot',
            'utm_medium' => 'wordpress-plugin',
            'utm_campaign' => $campaign,
            'utm_content' => self::source($content),
        );
    }

    /**
     * A link to zywrap.com with the UTM tags.
     *
     * @param string $path     Path on zywrap.com.
     * @param string $campaign UTM campaign.
     * @param string $content  Where it was clicked.
     * @return string
     */
    public static function link($path, $campaign, $content = 'ai-privacy') {
        return add_query_arg(array_map('rawurlencode', self::utm($campaign, $content)), self::app_url($path));
    }

    /**
     * One of SOURCES; anything else is the AI & Privacy page.
     *
     * @param string $value Raw value.
     * @return string
     */
    public static function source($value) {
        $value = sanitize_key((string) $value);

        return in_array($value, self::SOURCES, true) ? $value : 'ai-privacy';
    }

    /**
     * Who is signing up, for Zywrap's records of where accounts come from.
     *
     * @param string $campaign UTM campaign.
     * @param string $content  Where it was clicked.
     * @return array
     */
    private static function source_payload($campaign, $content) {
        return array_merge(array(
            'plugin' => 'geeky-bot',
            'version' => defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '',
        ), self::utm($campaign, $content));
    }

    /** The key's name on Zywrap's API Keys page. */
    private static function key_name() {
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);

        return 'Geeky Bot – ' . ($host !== '' ? $host : wp_strip_all_tags(get_bloginfo('name')));
    }

    /**
     * The site as Zywrap identifies it. site_url(), not home_url(): wp-admin
     * lives under site_url(), and Zywrap only returns codes to the same site.
     */
    private static function site_url() {
        return untrailingslashit(site_url());
    }

    /** AI & Privacy, where zywrap.com sends the browser back to. */
    public static function settings_url($args = array()) {
        return add_query_arg($args, admin_url('admin.php?page=geekybot-settings'));
    }

    public static function connect_url($content) {
        return wp_nonce_url(admin_url('admin-post.php?action=' . self::CONNECT_ACTION . '&zywrap_from=' . self::source($content)), self::CONNECT_ACTION);
    }

    public static function disconnect_url() {
        return wp_nonce_url(admin_url('admin-post.php?action=' . self::DISCONNECT_ACTION), self::DISCONNECT_ACTION);
    }

    /** The account this site connected through Geeky Bot, or null. */
    public static function account() {
        $account = get_option(self::ACCOUNT_OPTION);

        return is_array($account) && Settings::has_secret('zywrap_api_key') ? $account : null;
    }

    private static function base64url($bytes) {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * POST JSON to Zywrap. Returns [status, decoded body] or a WP_Error.
     *
     * @param string $path Path below the API root.
     * @param array  $body Request body.
     * @return array|\WP_Error
     */
    private static function post_json($path, array $body) {
        $response = wp_remote_post(self::api_url($path), array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode($body),
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $json = json_decode(wp_remote_retrieve_body($response), true);

        return array((int) wp_remote_retrieve_response_code($response), is_array($json) ? $json : array());
    }

    /**
     * Keep the key (encrypted, like every provider key), make Zywrap the
     * answer mode, and remember which account it is.
     *
     * @param array $json Zywrap's /verify or /exchange reply.
     * @return bool False when the key could not be stored.
     */
    private static function save_connection(array $json) {
        $key = sanitize_text_field((string) $json['apiKey']);
        $current = Settings::all();
        $endpoint = (string) $current['zywrap_endpoint'];

        // A partial save: every other setting is passed through unchanged.
        Settings::update(wp_parse_args(array(
            'provider_mode' => 'zywrap',
            'zywrap_endpoint' => $endpoint !== '' ? $endpoint : self::api_url('/v1/proxy'),
            'zywrap_api_key' => $key,
        ), $current));

        if (Settings::secret('zywrap_api_key') !== $key) {
            return false;
        }

        update_option(self::ACCOUNT_OPTION, array(
            'email' => isset($json['account']['email']) ? sanitize_email($json['account']['email']) : '',
            'key_name' => isset($json['keyName']) ? sanitize_text_field($json['keyName']) : '',
            'credits' => isset($json['credits']) ? (int) $json['credits'] : null,
            'created' => !empty($json['createdAccount']),
            'connected_at' => time(),
        ), false);

        return true;
    }

    /**
     * An AJAX error with Zywrap's own wording when it gave one in English, or
     * Geeky Bot's translated sentence otherwise.
     */
    private static function ajax_error($code, $json = array()) {
        $message = self::error_message($code);
        if (is_array($json) && !empty($json['message']) && is_string($json['message']) && strpos(determine_locale(), 'en') === 0) {
            $message = sanitize_text_field($json['message']);
        }

        wp_send_json_error(array(
            'code' => sanitize_key((string) $code),
            'message' => $message,
            'attemptsLeft' => isset($json['attemptsLeft']) ? (int) $json['attemptsLeft'] : null,
        ));
    }

    /**
     * wp_ajax_geekybot_zywrap_signup_start
     *
     * Step 1: asks Zywrap to email a 6-digit code, only once the admin has
     * ticked the consent box.
     */
    public function ajax_signup_start() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            self::ajax_error('forbidden');
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked above.
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        $accept = isset($_POST['accept']) && $_POST['accept'] === '1';
        $from = self::source(isset($_POST['from']) ? wp_unslash($_POST['from']) : '');
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (!is_email($email)) {
            self::ajax_error('invalid_email');
        }
        if (!$accept) {
            self::ajax_error('terms_required');
        }

        $verifier = self::base64url(random_bytes(32));
        $result = self::post_json('/v1/connect/wordpress/signup/start', array(
            'email' => $email,
            'name' => $name,
            'siteUrl' => self::site_url(),
            'siteName' => wp_strip_all_tags(get_bloginfo('name')),
            'keyName' => self::key_name(),
            'codeChallenge' => self::base64url(hash('sha256', $verifier, true)),
            'codeChallengeMethod' => 'S256',
            'acceptTerms' => true,
            'source' => self::source_payload('zywrap-signup', $from),
        ));

        if (is_wp_error($result)) {
            self::ajax_error('network');
        }
        list($status, $json) = $result;
        if ($status !== 200 || empty($json['flowId'])) {
            self::ajax_error(!empty($json['code']) ? $json['code'] : 'server', $json);
        }

        set_transient(self::SIGNUP_PREFIX . get_current_user_id(), array(
            'flow_id' => sanitize_text_field((string) $json['flowId']),
            'verifier' => $verifier,
            'from' => $from,
        ), self::PENDING_TTL);

        wp_send_json_success(array(
            'email' => $email,
            'existingAccount' => !empty($json['existingAccount']),
        ));
    }

    /**
     * wp_ajax_geekybot_zywrap_signup_verify
     *
     * Step 2: trades the emailed code for this site's key. A wrong code keeps
     * the pending sign-up so the admin can try again (Zywrap counts the tries);
     * anything that ends the code on Zywrap's side ends it here too.
     */
    public function ajax_signup_verify() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            self::ajax_error('forbidden');
        }

        $pending = get_transient(self::SIGNUP_PREFIX . get_current_user_id());
        if (!is_array($pending) || empty($pending['flow_id'])) {
            self::ajax_error('expired_code');
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Checked above; reduced to digits.
        $code = isset($_POST['code']) ? preg_replace('/\D+/', '', (string) wp_unslash($_POST['code'])) : '';
        if (strlen($code) !== 6) {
            self::ajax_error('invalid_code');
        }

        $result = self::post_json('/v1/connect/wordpress/signup/verify', array(
            'flowId' => $pending['flow_id'],
            'code' => $code,
            'codeVerifier' => $pending['verifier'],
            'siteUrl' => self::site_url(),
            'keyName' => self::key_name(),
            'source' => self::source_payload('zywrap-signup', isset($pending['from']) ? $pending['from'] : ''),
        ));

        if (is_wp_error($result)) {
            self::ajax_error('network');
        }
        list($status, $json) = $result;
        if ($status !== 200 || empty($json['apiKey']) || !is_string($json['apiKey'])) {
            $reason = !empty($json['code']) ? $json['code'] : 'server';
            if ($reason !== 'wrong_code') {
                delete_transient(self::SIGNUP_PREFIX . get_current_user_id());
            }
            self::ajax_error($reason, $json);
        }

        delete_transient(self::SIGNUP_PREFIX . get_current_user_id());
        if (!self::save_connection($json)) {
            self::ajax_error('storage');
        }

        wp_send_json_success(array(
            'redirect' => self::settings_url(array('zywrap_connected' => '1')) . '#gb-settings-ai',
            'createdAccount' => !empty($json['createdAccount']),
        ));
    }

    /**
     * admin-post.php?action=geekybot_zywrap_connect
     *
     * Sends the browser to zywrap.com to sign in and approve this site.
     */
    public function handle_connect_start() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(self::error_message('forbidden')), '', array('response' => 403));
        }
        check_admin_referer(self::CONNECT_ACTION);

        $from = self::source(isset($_GET['zywrap_from']) ? wp_unslash($_GET['zywrap_from']) : '');
        $verifier = self::base64url(random_bytes(32));
        $state = self::base64url(random_bytes(24));
        set_transient(self::CONNECT_PREFIX . get_current_user_id(), array('state' => $state, 'verifier' => $verifier, 'from' => $from), self::PENDING_TTL);

        $params = array_merge(array(
            'site' => self::site_url(),
            'site_name' => wp_strip_all_tags(get_bloginfo('name')),
            'return' => self::settings_url(),
            'state' => $state,
            'challenge' => self::base64url(hash('sha256', $verifier, true)),
            'challenge_method' => 'S256',
            'key_name' => self::key_name(),
            'source_plugin' => 'geeky-bot',
            'source_version' => defined('GEEKYBOT_VERSION') ? GEEKYBOT_VERSION : '',
        ), self::utm('zywrap-connect', $from));

        // wp_redirect, not wp_safe_redirect: the destination is zywrap.com by design.
        wp_redirect(add_query_arg(array_map('rawurlencode', $params), self::app_url('/connect/wordpress'))); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
        exit;
    }

    /**
     * Runs on admin_init: picks up the browser coming back from zywrap.com
     * with a one-time code, or with the admin having cancelled.
     */
    public function handle_return() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- The state value, checked below, protects this request.
        if (!isset($_GET['page']) || $_GET['page'] !== 'geekybot-settings') {
            return;
        }
        $has_code = isset($_GET['zywrap_code']);
        $has_error = isset($_GET['zywrap_error']);
        if ((!$has_code && !$has_error) || !current_user_can('manage_options')) {
            return;
        }

        // Single use whatever happens next: a refresh must not get a second try.
        $pending = get_transient(self::CONNECT_PREFIX . get_current_user_id());
        delete_transient(self::CONNECT_PREFIX . get_current_user_id());

        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        if (!is_array($pending) || empty($pending['state']) || !hash_equals($pending['state'], $state)) {
            self::finish('expired');
        }
        if ($has_error) {
            self::finish('cancelled');
        }

        // The code is a JWT: letters, digits, '-', '_' and '.'.
        $code = (string) wp_unslash($_GET['zywrap_code']);
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (!preg_match('/^[A-Za-z0-9._-]{20,4096}$/', $code)) {
            self::finish('invalid');
        }

        $result = self::post_json('/v1/connect/wordpress/exchange', array(
            'code' => $code,
            'codeVerifier' => $pending['verifier'],
            'siteUrl' => self::site_url(),
            'keyName' => self::key_name(),
            'source' => self::source_payload('zywrap-connect', isset($pending['from']) ? $pending['from'] : ''),
        ));
        if (is_wp_error($result)) {
            self::finish('network');
        }
        list($status, $json) = $result;
        if ($status !== 200 || empty($json['apiKey']) || !is_string($json['apiKey'])) {
            self::finish(!empty($json['code']) ? sanitize_key((string) $json['code']) : 'server');
        }
        if (!self::save_connection($json)) {
            self::finish('storage');
        }

        wp_safe_redirect(self::settings_url(array('zywrap_connected' => '1')) . '#gb-settings-ai');
        exit;
    }

    /**
     * admin-post.php?action=geekybot_zywrap_disconnect
     *
     * Forgets the key on this site and goes back to Local answers if Zywrap was
     * answering. The key stays valid on Zywrap until it is revoked there.
     */
    public function handle_disconnect() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html(self::error_message('forbidden')), '', array('response' => 403));
        }
        check_admin_referer(self::DISCONNECT_ACTION);

        // Settings::update() keeps a secret it is given empty, so clear it directly.
        $settings = get_option(Settings::OPTION, array());
        $settings = is_array($settings) ? $settings : array();
        $settings['zywrap_api_key'] = '';
        if (isset($settings['provider_mode']) && $settings['provider_mode'] === 'zywrap') {
            $settings['provider_mode'] = 'local';
        }
        update_option(Settings::OPTION, $settings);
        delete_option(self::ACCOUNT_OPTION);

        wp_safe_redirect(self::settings_url(array('zywrap_disconnected' => '1')) . '#gb-settings-ai');
        exit;
    }

    /** Back to a clean settings URL, so no code stays in the address bar. */
    private static function finish($error) {
        wp_safe_redirect(self::settings_url(array('zywrap_connect_error' => sanitize_key($error))) . '#gb-settings-ai');
        exit;
    }

    /**
     * The sentence shown for a failed sign-up or connect, by Zywrap's reason code.
     *
     * @param string $code Reason code.
     * @return string
     */
    public static function error_message($code) {
        $messages = array(
            'forbidden' => __('You are not allowed to connect this site to Zywrap.', 'geeky-bot'),
            'invalid_email' => __('Please enter a valid email address.', 'geeky-bot'),
            'terms_required' => __('Please tick the box to agree to the Zywrap Terms of Service and Privacy Policy.', 'geeky-bot'),
            'invalid_code' => __('Please enter the 6-digit code from the email.', 'geeky-bot'),
            'wrong_code' => __('That code is not right. Check the email and try again.', 'geeky-bot'),
            'expired_code' => __('That code expired or was already used. Ask for a new one.', 'geeky-bot'),
            'code_unavailable' => __('That code expired or was already used. Ask for a new one.', 'geeky-bot'),
            'expired' => __('That sign-in expired or was already used. Please start again.', 'geeky-bot'),
            'cancelled' => __('Signing in on zywrap.com was cancelled. Nothing was changed.', 'geeky-bot'),
            'use_browser_connect' => __('This account uses two-factor authentication. Use "Sign in on zywrap.com" below.', 'geeky-bot'),
            'rate_limited' => __('Too many attempts. Please wait a few minutes and try again.', 'geeky-bot'),
            'network' => __('This site could not reach Zywrap. Check that it can make outgoing HTTPS requests, then try again.', 'geeky-bot'),
            'storage' => __('The Zywrap key could not be saved: this server cannot store keys encrypted. Ask your host to enable OpenSSL or Sodium for PHP.', 'geeky-bot'),
        );

        return isset($messages[$code])
            ? $messages[$code]
            : __('Connecting to Zywrap did not complete. Please try again.', 'geeky-bot');
    }

    /**
     * The box on AI & Privacy: a notice after connecting or disconnecting,
     * then either the connected account or the sign-up form. It sits inside
     * the settings form, so its fields carry no name and its buttons are
     * type="button": none of it is ever posted with the settings.
     *
     * A store already answering through OpenAI does not need a sign-up form in
     * its way: with $only_when_chosen the box stays hidden until the admin
     * picks the Zywrap answer mode (zywrap-connect.js shows it).
     *
     * @param string $from             Where the admin came from (utm_content).
     * @param bool   $only_when_chosen Hide until Zywrap is picked.
     * @return void
     */
    public static function render_box($from, $only_when_chosen = false) {
        $from = self::source($from);
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only notice flags.
        $connected_now = !empty($_GET['zywrap_connected']);
        $disconnected = !empty($_GET['zywrap_disconnected']);
        $error = isset($_GET['zywrap_connect_error']) ? sanitize_key(wp_unslash($_GET['zywrap_connect_error'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $account = self::account();
        ?>
        <div class="gb2-zywrap" id="gb-zywrap-connect" data-from="<?php echo esc_attr($from); ?>"<?php echo ($only_when_chosen && !$account && $error === '') ? ' data-only-when-chosen="1" hidden' : ''; ?>>
            <?php if ($error !== '') : ?>
                <div class="notice notice-error inline"><p><?php echo esc_html(self::error_message($error)); ?></p></div>
            <?php elseif ($disconnected) : ?>
                <div class="notice notice-info inline"><p><?php esc_html_e('Zywrap was disconnected. Chat answers are local again. The key still works on zywrap.com until you delete it there.', 'geeky-bot'); ?></p></div>
            <?php endif; ?>

            <?php if ($account) : ?>
                <?php if ($connected_now) : ?>
                    <div class="notice notice-success inline"><p><?php esc_html_e('Connected. Zywrap now writes the chat answers.', 'geeky-bot'); ?></p></div>
                <?php endif; ?>
                <p class="gb2-zywrap__status">
                    <strong><?php
                    /* translators: %s: email address of the Zywrap account. */
                    printf(esc_html__('Connected to Zywrap as %s.', 'geeky-bot'), esc_html($account['email'] !== '' ? $account['email'] : '—'));
                    ?></strong>
                    <?php if (isset($account['credits']) && $account['credits'] !== null) : ?>
                        <span><?php
                        /* translators: %s: number of Zywrap credits. */
                        printf(esc_html__('%s credits when it connected.', 'geeky-bot'), esc_html(number_format_i18n((int) $account['credits'])));
                        ?></span>
                    <?php endif; ?>
                </p>
                <p class="gb2-zywrap__links">
                    <a href="<?php echo esc_url(self::link('/dashboard', 'zywrap-topup', $from)); ?>" target="_blank" rel="noopener"><?php esc_html_e('See balance and top up', 'geeky-bot'); ?></a>
                    <a href="<?php echo esc_url(self::disconnect_url()); ?>"><?php esc_html_e('Disconnect', 'geeky-bot'); ?></a>
                </p>
            <?php elseif (!Settings::has_secret('zywrap_api_key')) : ?>
                <?php
                $user = wp_get_current_user();
                $email = ($user && $user->user_email) ? $user->user_email : get_option('admin_email');
                $name = $user ? trim((string) $user->display_name) : '';
                ?>
                <h3><?php esc_html_e('Create a free Zywrap account', 'geeky-bot'); ?></h3>
                <div class="gb2-zywrap__step" data-step="email">
                    <label for="gb-zywrap-email"><?php esc_html_e('Email', 'geeky-bot'); ?></label>
                    <input type="email" id="gb-zywrap-email" value="<?php echo esc_attr($email); ?>" autocomplete="email" />
                    <label for="gb-zywrap-name"><?php esc_html_e('Name (optional)', 'geeky-bot'); ?></label>
                    <input type="text" id="gb-zywrap-name" value="<?php echo esc_attr($name); ?>" autocomplete="name" />
                    <label class="gb2-zywrap__accept">
                        <input type="checkbox" id="gb-zywrap-accept" value="1" />
                        <span><?php
                        printf(
                            /* translators: 1: Terms of Service link, 2: Privacy Policy link. */
                            esc_html__('I agree to the Zywrap %1$s and %2$s. When I continue, my email, name and this site\'s address are sent to Zywrap to create or sign in to my account.', 'geeky-bot'),
                            '<a href="' . esc_url(self::link('/terms', 'zywrap-signup', $from)) . '" target="_blank" rel="noopener">' . esc_html__('Terms of Service', 'geeky-bot') . '</a>',
                            '<a href="' . esc_url(self::link('/privacy', 'zywrap-signup', $from)) . '" target="_blank" rel="noopener">' . esc_html__('Privacy Policy', 'geeky-bot') . '</a>'
                        );
                        ?></span>
                    </label>
                    <button type="button" class="gb2-btn gb2-btn--primary gb2-zywrap__send"><?php esc_html_e('Email me a code', 'geeky-bot'); ?></button>
                    <p class="gb2-zywrap__hint"><?php esc_html_e('Already have a Zywrap account? Enter its email to sign in instead. New accounts start with 10,000 free credits.', 'geeky-bot'); ?></p>
                </div>
                <div class="gb2-zywrap__step" data-step="code" hidden>
                    <p class="gb2-zywrap__sent"></p>
                    <label for="gb-zywrap-code"><?php esc_html_e('6-digit code', 'geeky-bot'); ?></label>
                    <input type="text" id="gb-zywrap-code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" />
                    <div class="gb2-zywrap__row">
                        <button type="button" class="gb2-btn gb2-btn--primary gb2-zywrap__verify"><?php esc_html_e('Connect', 'geeky-bot'); ?></button>
                        <button type="button" class="button-link gb2-zywrap__back"><?php esc_html_e('Use a different email', 'geeky-bot'); ?></button>
                        <button type="button" class="button-link gb2-zywrap__resend"><?php esc_html_e('Send a new code', 'geeky-bot'); ?></button>
                    </div>
                </div>
                <div class="gb2-zywrap__message" role="alert" aria-live="polite"></div>
                <p class="gb2-zywrap__hint">
                    <?php esc_html_e('Prefer Google or GitHub, or does your account use two-factor authentication?', 'geeky-bot'); ?>
                    <a href="<?php echo esc_url(self::connect_url($from)); ?>"><?php esc_html_e('Sign in on zywrap.com', 'geeky-bot'); ?></a>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * The sign-up script, on AI & Privacy only and only while there is no key.
     *
     * @param string $version Asset version.
     * @param string $from    Where the admin came from (utm_content).
     * @return void
     */
    public static function enqueue($version, $from) {
        if (!Settings::zywrap_visible() || Settings::has_secret('zywrap_api_key')) {
            return;
        }
        wp_enqueue_script('geekybot-zywrap-connect', GEEKYBOT_URL . 'assets/js/zywrap-connect.js', array(), $version, true);
        wp_localize_script('geekybot-zywrap-connect', 'GeekyBotZywrap', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'from' => self::source($from),
            'i18n' => array(
                /* translators: %s: email address. */
                'sentNew' => __('We emailed a 6-digit code to %s. Enter it below to create your account.', 'geeky-bot'),
                /* translators: %s: email address. */
                'sentExisting' => __('Welcome back. We emailed a 6-digit code to %s. Enter it below to connect this site.', 'geeky-bot'),
                'resent' => __('A new code is on its way.', 'geeky-bot'),
                'working' => __('One moment…', 'geeky-bot'),
                'failed' => __('Connecting to Zywrap did not complete. Please try again.', 'geeky-bot'),
                'needAccept' => self::error_message('terms_required'),
            ),
        ));
    }
}

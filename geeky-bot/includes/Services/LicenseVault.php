<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Encrypts local Commerce Pro license material with keys derived from this
 * WordPress installation's salts. A copied database cannot decrypt the value
 * on a normal second installation that uses different salts.
 */
final class LicenseVault {
    const SODIUM_PREFIX = 'sodium:';
    const OPENSSL_PREFIX = 'openssl:';

    public static function available() {
        return (function_exists('sodium_crypto_secretbox') && function_exists('sodium_crypto_secretbox_open'))
            || (function_exists('openssl_encrypt') && function_exists('openssl_decrypt'));
    }

    public static function encrypt($plain_text) {
        $plain_text = (string) $plain_text;
        if ($plain_text === '') {
            return '';
        }

        $key = self::key();

        if (function_exists('sodium_crypto_secretbox')) {
            try {
                $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = sodium_crypto_secretbox($plain_text, $nonce, $key);
                return self::SODIUM_PREFIX . base64_encode($nonce . $cipher);
            } catch (\Throwable $exception) {
                // Fall through to OpenSSL when available.
            }
        }

        if (function_exists('openssl_encrypt')) {
            try {
                $iv = random_bytes(12);
                $tag = '';
                $cipher = openssl_encrypt(
                    $plain_text,
                    'aes-256-gcm',
                    $key,
                    OPENSSL_RAW_DATA,
                    $iv,
                    $tag,
                    'geekybot-license-v2',
                    16
                );
                if (is_string($cipher) && strlen($tag) === 16) {
                    return self::OPENSSL_PREFIX . base64_encode($iv . $tag . $cipher);
                }
            } catch (\Throwable $exception) {
                return '';
            }
        }

        return '';
    }

    public static function decrypt($payload) {
        $payload = trim((string) $payload);
        if ($payload === '') {
            return '';
        }

        $key = self::key();

        if (strpos($payload, self::SODIUM_PREFIX) === 0 && function_exists('sodium_crypto_secretbox_open')) {
            $raw = base64_decode(substr($payload, strlen(self::SODIUM_PREFIX)), true);
            if (!is_string($raw) || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return '';
            }

            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            try {
                $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
                return is_string($plain) ? $plain : '';
            } catch (\Throwable $exception) {
                return '';
            }
        }

        if (strpos($payload, self::OPENSSL_PREFIX) === 0 && function_exists('openssl_decrypt')) {
            $raw = base64_decode(substr($payload, strlen(self::OPENSSL_PREFIX)), true);
            if (!is_string($raw) || strlen($raw) <= 28) {
                return '';
            }

            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $plain = openssl_decrypt(
                $cipher,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                'geekybot-license-v2'
            );
            return is_string($plain) ? $plain : '';
        }

        return '';
    }

    private static function key() {
        return hash('sha256', 'geekybot-license-vault-v2|' . wp_salt('secure_auth'), true);
    }
}

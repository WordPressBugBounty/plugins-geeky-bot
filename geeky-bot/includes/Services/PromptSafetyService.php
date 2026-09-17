<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Neutralises instruction-like sequences in text that reaches a language model.
 *
 * Catalog and policy text is merchant-authored, which on a single-owner store
 * is low risk. It stops being low risk on a marketplace, a multi-author
 * catalog, or a catalog populated by CSV import or scrape -- a product
 * description is an ordinary editor field that any contributor can write into.
 *
 * This runs in front of the system prompt's grounding rules, not instead of
 * them. The "never invent facts" constraint is doing real work and stays.
 */
class PromptSafetyService {
    const REPLACEMENT = '[removed]';
    const MAX_CONTEXT_CHARS = 20000;

    /**
     * Instruction-shaped sequences that have no legitimate reason to appear in
     * a product description or a policy page. Deliberately narrow: a pattern
     * that also matches ordinary shopping language would quietly damage real
     * catalog text, which is a worse outcome than a missed exotic payload.
     *
     * @return array
     */
    private static function patterns() {
        return array(
            // Chat-template and role markers.
            '/<\|[^>]{0,40}\|>/u',
            '/\[\/?INST\]/iu',
            '/<\/?(?:system|assistant|user|tool)>/iu',
            '/^\s*(?:system|assistant|developer)\s*:/imu',
            '/#{2,}\s*(?:system|instruction|instructions|prompt)\b/iu',
            // Override attempts.
            '/\b(?:ignore|disregard|forget|override|bypass)\b[^.\n]{0,40}\b(?:previous|prior|above|earlier|all|any|the)\b[^.\n]{0,20}\b(?:instruction|instructions|prompt|prompts|rule|rules|context|constraint|constraints)\b/iu',
            '/\b(?:new|updated|revised)\s+(?:system\s+)?(?:instruction|instructions|prompt|prompts)\b\s*:/iu',
            '/\byou\s+are\s+now\b[^.\n]{0,60}/iu',
            '/\b(?:act|behave|respond)\s+as\s+(?:if\s+you\s+are\s+|an?\s+)?(?:unrestricted|jailbroken|developer\s+mode|dan)\b/iu',
            // Attempts to pull the configuration back out.
            '/\b(?:reveal|print|repeat|output|show|expose|leak)\b[^.\n]{0,30}\b(?:system\s+prompt|hidden\s+instruction|initial\s+instruction|api\s+key|secret\s+key)\b/iu',
            // Fabrication instructions aimed at the grounding rules.
            '/\b(?:do\s*not|don\'?t|never)\b[^.\n]{0,30}\b(?:follow|obey|apply)\b[^.\n]{0,30}\b(?:instruction|instructions|rule|rules|guideline|guidelines)\b/iu',
        );
    }

    /**
     * Clean a block of untrusted text before it is placed in a prompt.
     *
     * @param mixed $text Untrusted text.
     * @return string
     */
    public static function sanitize_text($text) {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        // Zero-width and bidi characters are how a visually clean description
        // hides a payload from the merchant who is reviewing it.
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}-\x{206F}\x{FEFF}]/u', '', $text);
        $text = preg_replace('/[^\P{C}\n\t]+/u', ' ', $text);

        foreach (self::patterns() as $pattern) {
            $replaced = preg_replace($pattern, self::REPLACEMENT, $text);
            if (is_string($replaced)) {
                $text = $replaced;
            }
        }

        // Fenced blocks let injected text claim a different role visually.
        $text = str_replace(array('```', '~~~'), '', $text);
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim((string) $text);

        if (function_exists('mb_substr')) {
            return mb_substr($text, 0, self::MAX_CONTEXT_CHARS);
        }

        return substr($text, 0, self::MAX_CONTEXT_CHARS);
    }

    /**
     * Clean every string inside a structured context payload.
     *
     * Used for the provider-neutral endpoint, which sends the product and
     * policy arrays rather than an assembled prompt string.
     *
     * @param mixed $value Context value.
     * @param int   $depth Recursion guard.
     * @return mixed
     */
    public static function sanitize_payload($value, $depth = 0) {
        if ($depth > 6) {
            return is_scalar($value) ? $value : '';
        }

        if (is_string($value)) {
            return self::sanitize_text($value);
        }

        if (is_array($value)) {
            $clean = array();
            foreach ($value as $key => $item) {
                $clean[$key] = self::sanitize_payload($item, $depth + 1);
            }
            return $clean;
        }

        return $value;
    }
}

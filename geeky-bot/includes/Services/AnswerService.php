<?php
namespace GeekyBot\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Answers the merchant writes for questions the chat could not answer.
 *
 * Written from a card in Needs review (or added by hand under Store
 * Knowledge), an answer is sent back word for word whenever a shopper asks
 * the same question or a close rewording of it, in every answer mode. It is
 * the merchant's own text, so it is never rewritten by the AI.
 */
class AnswerService {
    const TABLE = 'geekybot_answers';
    const COUNT_OPTION = 'geekybot_answers_count';
    const CACHE_GROUP = 'geekybot_answers';
    const MAX_ANSWERS = 500;
    const MAX_ANSWER_LENGTH = 2000;

    /**
     * A close rewording must share at least this share of its meaningful
     * words with the saved question (Jaccard). High on purpose: a wrong
     * answer the merchant never meant for a question is worse than none.
     */
    const NEAR_MATCH = 0.8;

    /**
     * Words that carry no meaning for matching, in the languages the query
     * layer understands best. "what is your shipping cost" and "shipping
     * cost?" become the same question.
     */
    const STOPWORDS = array(
        'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'do', 'does', 'did', 'you', 'your', 'yours', 'i', 'me', 'my', 'we', 'our', 'us',
        'it', 'its', 'this', 'that', 'these', 'those', 'to', 'of', 'in', 'on', 'for', 'with', 'and', 'or', 'can', 'could', 'would', 'will', 'should',
        'may', 'might', 'please', 'hi', 'hello', 'hey', 'what', 'whats', 'how', 'there', 'here', 'have', 'has', 'any', 'tell', 'about', 'at', 'by',
        'el', 'la', 'los', 'las', 'de', 'del', 'un', 'una', 'y', 'o', 'en', 'que', 'es', 'por', 'para', 'su', 'tu',
        'le', 'les', 'des', 'du', 'et', 'est', 'vous', 'votre', 'pour', 'je',
        'der', 'die', 'das', 'und', 'ist', 'ein', 'eine', 'ihr', 'sie', 'wie', 'was', 'zu', 'mit',
    );

    public static function table() {
        global $wpdb;

        return $wpdb->prefix . self::TABLE;
    }

    public static function create_table() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset_collate = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            question text NOT NULL,
            question_norm varchar(191) NOT NULL DEFAULT '',
            answer text NOT NULL,
            hits bigint(20) unsigned NOT NULL DEFAULT 0,
            last_used_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY question_norm (question_norm)
        ) {$charset_collate};");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own plugin table.
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Question as stored and compared: the same normalising Needs review uses
     * to group questions, so a card and its answer always agree.
     *
     * @param string $question Question.
     * @return string
     */
    public static function normalize($question) {
        $norm = ConversationInsightsService::normalize_review_question($question);

        return function_exists('mb_substr') ? mb_substr($norm, 0, 191, 'UTF-8') : substr($norm, 0, 191);
    }

    /**
     * Meaningful words of a normalised question.
     *
     * @param string $norm Normalised question.
     * @return string[]
     */
    public static function tokens($norm) {
        $words = preg_split('/\s+/u', (string) $norm, -1, PREG_SPLIT_NO_EMPTY);
        $words = array_diff((array) $words, self::STOPWORDS);

        return array_values(array_unique($words));
    }

    /**
     * Every saved answer, newest first.
     *
     * @param int $limit Rows.
     * @return array<int, object>
     */
    public static function all($limit = self::MAX_ANSWERS) {
        global $wpdb;

        $cached = wp_cache_get('all_' . (int) $limit, self::CACHE_GROUP);
        if (is_array($cached)) {
            return $cached;
        }
        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY updated_at DESC, id DESC LIMIT %d", absint($limit)));
        $rows = is_array($rows) ? $rows : array();
        wp_cache_set('all_' . (int) $limit, $rows, self::CACHE_GROUP);

        return $rows;
    }

    /**
     * How many answers are saved. Kept in an option so a store with none,
     * which is most of them, never queries the table during a chat.
     *
     * @return int
     */
    public static function count() {
        return absint(get_option(self::COUNT_OPTION, 0));
    }

    private static function refresh_count() {
        global $wpdb;

        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        update_option(self::COUNT_OPTION, absint($wpdb->get_var("SELECT COUNT(*) FROM {$table}")), true);
        wp_cache_delete('all_' . self::MAX_ANSWERS, self::CACHE_GROUP);
    }

    /**
     * The saved answer for a shopper message: the same question, or a close
     * rewording of it.
     *
     * @param string $message Shopper message.
     * @return object|null Row with id, question, answer.
     */
    public static function find($message) {
        if (self::count() === 0) {
            return null;
        }
        $norm = self::normalize($message);
        if ($norm === '') {
            return null;
        }

        $answers = self::all();
        foreach ($answers as $row) {
            if ($row->question_norm === $norm) {
                return $row;
            }
        }

        $words = self::tokens($norm);
        if (count($words) < 2) {
            // One meaningful word ("shipping?") is too little to stand in for
            // a question the merchant wrote; it only matches exactly.
            return null;
        }

        $best = null;
        $best_score = 0.0;
        foreach ($answers as $row) {
            $saved = self::tokens($row->question_norm);
            if (count($saved) < 2) {
                continue;
            }
            $shared = count(array_intersect($words, $saved));
            $score = $shared / count(array_unique(array_merge($words, $saved)));
            if ($score > $best_score) {
                $best_score = $score;
                $best = $row;
            }
        }

        return ($best && $best_score >= self::NEAR_MATCH) ? $best : null;
    }

    /**
     * The saved answer for exactly this question, if there is one.
     *
     * @param string $question Question.
     * @return object|null
     */
    public static function for_question($question) {
        $norm = self::normalize($question);
        foreach (self::all() as $row) {
            if ($row->question_norm === $norm) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Add an answer, or replace the one for the same question.
     *
     * @param string $question Question as the shopper asked it.
     * @param string $answer   Merchant's answer, plain text.
     * @param int    $id       Answer to edit, or 0.
     * @return int|\WP_Error Answer ID.
     */
    public static function save($question, $answer, $id = 0) {
        global $wpdb;

        $question = trim(sanitize_text_field((string) $question));
        $answer = trim(sanitize_textarea_field((string) $answer));
        $norm = self::normalize($question);
        if ($norm === '' || $answer === '') {
            return new \WP_Error('geekybot_answer_empty', __('Write both the question and the answer.', 'geeky-bot'));
        }
        if (function_exists('mb_substr')) {
            $answer = mb_substr($answer, 0, self::MAX_ANSWER_LENGTH, 'UTF-8');
        }

        $existing = $id ? null : self::for_question($question);
        if ($existing) {
            $id = (int) $existing->id;
        }
        if (!$id && self::count() >= self::MAX_ANSWERS) {
            /* translators: %s: maximum number of answers. */
            return new \WP_Error('geekybot_answer_limit', sprintf(__('You can save up to %s answers. Delete one you no longer need first.', 'geeky-bot'), number_format_i18n(self::MAX_ANSWERS)));
        }

        $now = current_time('mysql');
        $data = array('question' => $question, 'question_norm' => $norm, 'answer' => $answer, 'updated_at' => $now);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own plugin table.
        if ($id) {
            $ok = $wpdb->update(self::table(), $data, array('id' => absint($id)));
        } else {
            $ok = $wpdb->insert(self::table(), $data + array('created_at' => $now, 'hits' => 0));
            $id = (int) $wpdb->insert_id;
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($ok === false || !$id) {
            return new \WP_Error('geekybot_answer_save', __('The answer could not be saved.', 'geeky-bot'));
        }
        self::refresh_count();

        return (int) $id;
    }

    public static function delete($id) {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Own plugin table.
        $wpdb->delete(self::table(), array('id' => absint($id)));
        self::refresh_count();
    }

    /**
     * Count a use, for the "Used" column.
     *
     * @param int $id Answer ID.
     * @return void
     */
    public static function record_hit($id) {
        global $wpdb;

        $table = self::table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Own plugin table.
        $wpdb->query($wpdb->prepare("UPDATE {$table} SET hits = hits + 1, last_used_at = %s WHERE id = %d", current_time('mysql'), absint($id)));
        wp_cache_delete('all_' . self::MAX_ANSWERS, self::CACHE_GROUP);
    }
}

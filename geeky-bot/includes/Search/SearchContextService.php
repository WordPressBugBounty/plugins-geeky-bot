<?php
namespace GeekyBot\Search;

use GeekyBot\ProductExpert\ProductQuestionRouter;
use GeekyBot\Services\ProductDiscoveryIntentService;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps commerce-only shopping context between shopper messages.
 *
 * Search Context V2.2 deliberately separates the original buying mission from
 * the mutable filters/results produced by shopping commands. Command wording
 * must never become product-search wording.
 */
class SearchContextService {
    const MAX_PRODUCT_IDS = 12;
    const MAX_MISSION_PRODUCT_IDS = 24;
    const MAX_HISTORY = 6;
    const STATE_VERSION = 8;

    public function resolve($message, $session_id) {
        $message = $this->clean_text($message);
        $previous = $this->load_latest($session_id);
        $previous_analysis_for_discovery = !empty($previous['activeAnalysis']) && is_array($previous['activeAnalysis'])
            ? $previous['activeAnalysis']
            : (!empty($previous['analysis']) && is_array($previous['analysis']) ? $previous['analysis'] : array());
        $discovery = (new ProductDiscoveryIntentService())->analyze($message, $previous_analysis_for_discovery);
        $command = (new ShoppingCommandResolver())->resolve($message, $previous);
        $action = !empty($command['action']) ? sanitize_key($command['action']) : '';

        // A question about the product the shopper already chose is not a new
        // mission, however much it looks like one.
        //
        // Selecting a product answers "You selected X. What would you like to
        // know about it?" -- and the obvious next line, "what material is
        // used?", was then classified as a fresh catalog mission. That reset
        // `selectedProductId` to 0 further down, so Product Expert had no
        // subject left and replied "I'm not sure which product you mean" to the
        // exact question the bot had just invited. "is it machine washable?"
        // was worse: with the selection gone it searched the catalog and
        // returned a memory-foam travel pillow.
        //
        // ProductQuestionRouter already decides what counts as a product-fact
        // question, and it deliberately ignores discovery and store-policy
        // wording, so reusing it keeps one definition of that in the codebase.
        $product_fact_question = (new ProductQuestionRouter())->route($message);
        $has_product_subject = !empty($previous['selectedProductId']) || !empty($previous['productIds']);
        $keeps_product_subject = $has_product_subject && !empty($product_fact_question['isQuestion']);

        /**
         * Filters whether this message keeps the previous product subject.
         *
         * A commerce addon that answers questions about the products on screen
         * needs the result set to survive its own follow-up: words like "bigger"
         * and "lighter" read as a fresh search to Product Discovery, which would
         * clear the very set the question is about.
         *
         * @param bool   $keeps_product_subject Whether context is retained.
         * @param string $message               Shopper message.
         * @param array  $previous              Previous conversation context.
         */
        $keeps_product_subject = (bool) apply_filters(
            'geekybot_keeps_product_subject',
            $keeps_product_subject,
            $message,
            $previous
        );

        // Product Discovery owns clear catalog missions before short-modifier
        // merging. A new product family, global sale/stock browse, or explicit
        // discovery request must not inherit an earlier mission's terms.
        if (!empty($discovery['isNewMission'])
            && !$keeps_product_subject
            && in_array($action, array('', 'filter_current', 'new_search'), true)) {
            $action = 'new_search';
            $command['action'] = 'new_search';
            $command['args'] = array();
        }
        $clean_message = isset($command['cleanMessage']) ? $this->clean_text($command['cleanMessage']) : $message;
        $args = !empty($command['args']) && is_array($command['args']) ? $command['args'] : array();

        $current_ids = $this->unique_ids(isset($previous['productIds']) ? $previous['productIds'] : array(), self::MAX_PRODUCT_IDS);
        $current_names = $this->clean_names(isset($previous['productNames']) ? $previous['productNames'] : array(), self::MAX_PRODUCT_IDS);
        $last_multi_ids = $this->unique_ids(
            !empty($previous['lastMultiProductIds'])
                ? $previous['lastMultiProductIds']
                : (!empty($previous['referenceProductIds']) ? $previous['referenceProductIds'] : (count($current_ids) >= 2 ? $current_ids : array())),
            self::MAX_PRODUCT_IDS
        );
        $last_multi_names = $this->clean_names(
            !empty($previous['lastMultiProductNames'])
                ? $previous['lastMultiProductNames']
                : (!empty($previous['referenceProductNames']) ? $previous['referenceProductNames'] : (count($current_names) >= 2 ? $current_names : array())),
            self::MAX_PRODUCT_IDS
        );
        $base_query = !empty($previous['baseMissionQuery'])
            ? $previous['baseMissionQuery']
            : (!empty($previous['missionQuery']) ? $previous['missionQuery'] : '');
        $base_analysis = !empty($previous['baseAnalysis']) && is_array($previous['baseAnalysis'])
            ? $previous['baseAnalysis']
            : (!empty($previous['analysis']) && is_array($previous['analysis']) ? $previous['analysis'] : array());
        $active_analysis = !empty($previous['activeAnalysis']) && is_array($previous['activeAnalysis'])
            ? $previous['activeAnalysis']
            : (!empty($previous['analysis']) && is_array($previous['analysis']) ? $previous['analysis'] : array());
        $mission_ids = $this->unique_ids(
            !empty($previous['missionProductIds']) ? $previous['missionProductIds'] : array_merge($last_multi_ids, $current_ids),
            self::MAX_MISSION_PRODUCT_IDS
        );

        $working_ids = count($current_ids) >= 2 ? $current_ids : $last_multi_ids;
        if (empty($working_ids)) {
            $working_ids = $current_ids;
        }
        $working_names = count($current_names) >= 2 ? $current_names : $last_multi_names;
        if (empty($working_names)) {
            $working_names = $current_names;
        }

        $base = array(
            'isFollowUp' => false,
            'action' => $action !== '' ? $action : 'new_search',
            'message' => $message,
            'effectiveQuery' => $clean_message,
            'missionQuery' => $clean_message,
            'baseMissionQuery' => $clean_message,
            'previousProductIds' => $working_ids,
            'previousProductNames' => $working_names,
            'previousCurrentProductIds' => $current_ids,
            'previousCurrentProductNames' => $current_names,
            'previousMissionProductIds' => $mission_ids,
            'previousReferenceProductIds' => $last_multi_ids,
            'previousReferenceProductNames' => $last_multi_names,
            'previousLastMultiProductIds' => $last_multi_ids,
            'previousLastMultiProductNames' => $last_multi_names,
            'previousAnalysis' => $active_analysis,
            'previousActiveAnalysis' => $active_analysis,
            'previousBaseAnalysis' => $base_analysis,
            'previousContext' => $previous,
            'previousHistory' => !empty($previous['history']) ? (array) $previous['history'] : array(),
            'selectedProductId' => !empty($previous['selectedProductId']) ? absint($previous['selectedProductId']) : 0,
            'pendingProductSelection' => !empty($previous['pendingProductSelection'])
                ? $this->sanitize_pending_selection($previous['pendingProductSelection'])
                : (!empty($previous['pendingProductClarification'])
                    ? $this->sanitize_pending_selection($previous['pendingProductClarification'])
                    : array()),
            // Compatibility alias for sessions created before the unified
            // pending-product-selection state was introduced.
            'pendingProductClarification' => !empty($previous['pendingProductSelection'])
                ? $this->sanitize_pending_selection($previous['pendingProductSelection'])
                : (!empty($previous['pendingProductClarification'])
                    ? $this->sanitize_pending_selection($previous['pendingProductClarification'])
                    : array()),
            'selectionIndex' => null,
            'commandArgs' => $args,
            'compareProductIds' => array(),
            'referenceProductId' => !empty($args['productId']) ? absint($args['productId']) : 0,
            'restoreContext' => array(),
            'remainingHistory' => array(),
            'discovery' => is_array($discovery) ? $discovery : array(),
        );

        if ($action === 'new_search' && !empty($discovery['isNewMission'])) {
            $base['isFollowUp'] = false;
            $base['action'] = 'new_search';
            $base['effectiveQuery'] = $message;
            $base['missionQuery'] = $message;
            $base['baseMissionQuery'] = $message;
            $base['previousProductIds'] = array();
            $base['previousProductNames'] = array();
            $base['previousCurrentProductIds'] = array();
            $base['previousCurrentProductNames'] = array();
            $base['previousMissionProductIds'] = array();
            $base['previousReferenceProductIds'] = array();
            $base['previousReferenceProductNames'] = array();
            $base['previousLastMultiProductIds'] = array();
            $base['previousLastMultiProductNames'] = array();
            $base['previousAnalysis'] = array();
            $base['previousActiveAnalysis'] = array();
            $base['previousBaseAnalysis'] = array();
            $base['selectedProductId'] = 0;
            $base['pendingProductSelection'] = array();
            $base['pendingProductClarification'] = array();
            return $base;
        }

        if ($action === 'reset_search') {
            $base['action'] = 'reset_search';
            $base['effectiveQuery'] = $clean_message;
            $base['missionQuery'] = $clean_message;
            $base['baseMissionQuery'] = $clean_message;
            return $base;
        }

        $has_context = $base_query !== '' || !empty($active_analysis) || !empty($current_ids) || !empty($last_multi_ids);
        $comparison_command = $action === 'compare';
        // A command that names what it acts on is self-contained, exactly as a
        // named comparison is. "add the Trek 32L to my cart" is a complete
        // request on the first message of a conversation, and forcing it to a
        // new search here threw the action away before anything could run it.
        // One that points at the screen instead -- "add second product" -- still
        // arrives with no product, and is answered by asking which, not by
        // searching for the word "second".
        $commerce_command = in_array($action, ShoppingCommandResolver::commerce_actions(), true);
        $explicit_named_compare = $comparison_command && !empty($args['explicitNames']) && !empty($args['namedProducts']);
        if ($message === '' || $action === '' || (!$has_context && !$comparison_command && !$commerce_command)) {
            $base['action'] = 'new_search';
            $base['effectiveQuery'] = $message;
            $base['missionQuery'] = $message;
            $base['baseMissionQuery'] = $message;
            return $base;
        }

        if ($explicit_named_compare) {
            // Explicit named comparison is a self-contained mission. It must not
            // inherit product IDs or wording from an earlier search.
            $base['isFollowUp'] = false;
            $base['action'] = 'compare';
            $base['effectiveQuery'] = $message;
            $base['missionQuery'] = $message;
            $base['baseMissionQuery'] = $message;
            $base['previousProductIds'] = array();
            $base['previousProductNames'] = array();
            $base['previousCurrentProductIds'] = array();
            $base['previousCurrentProductNames'] = array();
            $base['previousMissionProductIds'] = array();
            $base['previousReferenceProductIds'] = array();
            $base['previousReferenceProductNames'] = array();
            $base['previousLastMultiProductIds'] = array();
            $base['previousLastMultiProductNames'] = array();
            $base['selectedProductId'] = 0;
            return $base;
        }

        $base['isFollowUp'] = true;
        $base['missionQuery'] = $base_query;
        $base['baseMissionQuery'] = $base_query;

        if ($action === 'go_back') {
            $history = !empty($previous['history']) ? array_values((array) $previous['history']) : array();
            if (!empty($history)) {
                $restore = array_pop($history);
                if (is_array($restore)) {
                    $base['restoreContext'] = $this->sanitize_snapshot($restore);
                    $base['remainingHistory'] = array_slice($history, -self::MAX_HISTORY);
                    $base['effectiveQuery'] = !empty($base['restoreContext']['baseMissionQuery'])
                        ? $base['restoreContext']['baseMissionQuery']
                        : $base_query;
                }
            }
            return $base;
        }

        if ($action === 'cheaper') {
            $base['effectiveQuery'] = trim($base_query . ' budget friendly lower price');
        } elseif ($action === 'premium') {
            $base['effectiveQuery'] = $this->premium_query($base_query, $active_analysis);
        } elseif ($action === 'filter_current') {
            $base['effectiveQuery'] = trim($base_query . ' ' . $message);
        } else {
            // State-integrity rule: shopping-command text never becomes the mission query.
            $base['effectiveQuery'] = $base_query;
        }

        if ($action === 'select') {
            $base['selectionIndex'] = isset($args['selectionIndex']) ? absint($args['selectionIndex']) : null;
        }
        if ($action === 'compare' && !empty($args['productIds'])) {
            $base['compareProductIds'] = $this->unique_ids($args['productIds'], 4);
        }

        return $base;
    }

    public function build_payload($message, $products, $search_context, $resolution = array()) {
        $result_analysis = !empty($search_context['analysis']) && is_array($search_context['analysis'])
            ? $this->public_analysis($search_context['analysis'])
            : array();
        $current = $this->product_identity_lists($products);
        $current_ids = $current['ids'];
        $current_names = $current['names'];
        $action = !empty($resolution['action']) ? sanitize_key($resolution['action']) : 'new_search';
        $is_follow_up = !empty($resolution['isFollowUp']);
        $previous = !empty($resolution['previousContext']) && is_array($resolution['previousContext'])
            ? $this->sanitize_loaded_context($resolution['previousContext'])
            : array();

        if ($action === 'reset_search' || !$is_follow_up || $action === 'new_search') {
            $base_query = $this->clean_text(!empty($resolution['effectiveQuery']) ? $resolution['effectiveQuery'] : $message);
            $last_multi_ids = count($current_ids) >= 2 ? $current_ids : array();
            $last_multi_names = count($current_names) >= 2 ? $current_names : array();

            return array(
                'version' => self::STATE_VERSION,
                'baseMissionQuery' => $base_query,
                'missionQuery' => $base_query,
                'lastMessage' => $this->clean_text($message),
                'action' => $action === 'reset_search'
                    ? 'reset_search'
                    : ($action === 'compare' ? 'compare' : 'new_search'),
                'productIds' => $this->unique_ids($current_ids, self::MAX_PRODUCT_IDS),
                'productNames' => $this->clean_names($current_names, self::MAX_PRODUCT_IDS),
                'missionProductIds' => $this->unique_ids($current_ids, self::MAX_MISSION_PRODUCT_IDS),
                'lastMultiProductIds' => $this->unique_ids($last_multi_ids, self::MAX_PRODUCT_IDS),
                'lastMultiProductNames' => $this->clean_names($last_multi_names, self::MAX_PRODUCT_IDS),
                'referenceProductIds' => $this->unique_ids(!empty($last_multi_ids) ? $last_multi_ids : $current_ids, self::MAX_PRODUCT_IDS),
                'referenceProductNames' => $this->clean_names(!empty($last_multi_names) ? $last_multi_names : $current_names, self::MAX_PRODUCT_IDS),
                'selectedProductId' => 0,
                'baseAnalysis' => $result_analysis,
                'activeAnalysis' => $result_analysis,
                'analysis' => $result_analysis,
                'history' => array(),
                'commandArgs' => !empty($resolution['commandArgs']) ? $this->sanitize_command_args($resolution['commandArgs']) : array(),
                'updatedAt' => current_time('mysql'),
            );
        }

        if ($action === 'go_back' && !empty($resolution['restoreContext']) && is_array($resolution['restoreContext'])) {
            $restore = $this->sanitize_snapshot($resolution['restoreContext']);
            $restored_current_ids = !empty($current_ids) ? $current_ids : $restore['productIds'];
            $restored_current_names = !empty($current_names) ? $current_names : $restore['productNames'];

            return array(
                'version' => self::STATE_VERSION,
                'baseMissionQuery' => $restore['baseMissionQuery'],
                'missionQuery' => $restore['baseMissionQuery'],
                'lastMessage' => $this->clean_text($message),
                'action' => 'go_back',
                'productIds' => $this->unique_ids($restored_current_ids, self::MAX_PRODUCT_IDS),
                'productNames' => $this->clean_names($restored_current_names, self::MAX_PRODUCT_IDS),
                'missionProductIds' => $this->unique_ids($restore['missionProductIds'], self::MAX_MISSION_PRODUCT_IDS),
                'lastMultiProductIds' => $this->unique_ids($restore['lastMultiProductIds'], self::MAX_PRODUCT_IDS),
                'lastMultiProductNames' => $this->clean_names($restore['lastMultiProductNames'], self::MAX_PRODUCT_IDS),
                'referenceProductIds' => $this->unique_ids(!empty($restore['lastMultiProductIds']) ? $restore['lastMultiProductIds'] : $restored_current_ids, self::MAX_PRODUCT_IDS),
                'referenceProductNames' => $this->clean_names(!empty($restore['lastMultiProductNames']) ? $restore['lastMultiProductNames'] : $restored_current_names, self::MAX_PRODUCT_IDS),
                'selectedProductId' => absint($restore['selectedProductId']),
                'baseAnalysis' => $this->public_analysis($restore['baseAnalysis']),
                'activeAnalysis' => $this->public_analysis($restore['activeAnalysis']),
                'analysis' => $this->public_analysis($restore['activeAnalysis']),
                'history' => $this->sanitize_history(!empty($resolution['remainingHistory']) ? $resolution['remainingHistory'] : array()),
                'commandArgs' => array(),
                'updatedAt' => current_time('mysql'),
            );
        }

        $base_query = !empty($previous['baseMissionQuery'])
            ? $previous['baseMissionQuery']
            : (!empty($resolution['baseMissionQuery']) ? $resolution['baseMissionQuery'] : $message);
        $base_analysis = !empty($previous['baseAnalysis']) && is_array($previous['baseAnalysis'])
            ? $previous['baseAnalysis']
            : (!empty($resolution['previousBaseAnalysis']) ? $resolution['previousBaseAnalysis'] : $result_analysis);
        $previous_active = !empty($previous['activeAnalysis']) && is_array($previous['activeAnalysis'])
            ? $previous['activeAnalysis']
            : (!empty($resolution['previousActiveAnalysis']) ? $resolution['previousActiveAnalysis'] : array());
        $active_analysis = !empty($result_analysis) ? $result_analysis : $previous_active;

        $previous_mission_ids = !empty($previous['missionProductIds'])
            ? $previous['missionProductIds']
            : (!empty($resolution['previousMissionProductIds']) ? $resolution['previousMissionProductIds'] : array());
        $mission_ids = $this->unique_ids(array_merge((array) $previous_mission_ids, $current_ids), self::MAX_MISSION_PRODUCT_IDS);

        $previous_last_multi_ids = !empty($previous['lastMultiProductIds'])
            ? $previous['lastMultiProductIds']
            : (!empty($resolution['previousLastMultiProductIds']) ? $resolution['previousLastMultiProductIds'] : array());
        $previous_last_multi_names = !empty($previous['lastMultiProductNames'])
            ? $previous['lastMultiProductNames']
            : (!empty($resolution['previousLastMultiProductNames']) ? $resolution['previousLastMultiProductNames'] : array());
        if (count($current_ids) >= 2) {
            $last_multi_ids = $current_ids;
            $last_multi_names = $current_names;
        } else {
            $last_multi_ids = $previous_last_multi_ids;
            $last_multi_names = $previous_last_multi_names;
        }

        $selected_product_id = !empty($previous['selectedProductId']) ? absint($previous['selectedProductId']) : 0;
        if (in_array($action, array('best', 'best_discount', 'select'), true) && !empty($current_ids)) {
            $selected_product_id = absint($current_ids[0]);
        } elseif (in_array($action, array('similar', 'another_color'), true) && !empty($resolution['referenceProductId'])) {
            $selected_product_id = absint($resolution['referenceProductId']);
        } elseif (in_array($action, array('cheaper', 'premium', 'filter_current', 'remove_constraint', 'reset_search'), true)) {
            $selected_product_id = 0;
        } elseif (count($current_ids) >= 2) {
            // A fresh multi-product result set has no implicit focus. Clear a
            // selected product carried from an older Product Expert turn so
            // generic references such as "the product" cannot silently pick
            // the first or previously selected item.
            $selected_product_id = 0;
        }

        $history = !empty($resolution['previousHistory']) ? $this->sanitize_history($resolution['previousHistory']) : array();
        $snapshot = $this->snapshot_from_context($previous);
        if (!empty($snapshot['productIds']) || !empty($snapshot['lastMultiProductIds'])) {
            $history = $this->append_history_snapshot($history, $snapshot);
        }

        $reference_ids = !empty($last_multi_ids) ? $last_multi_ids : $current_ids;
        $reference_names = !empty($last_multi_names) ? $last_multi_names : $current_names;

        return array(
            'version' => self::STATE_VERSION,
            'baseMissionQuery' => $this->clean_text($base_query),
            'missionQuery' => $this->clean_text($base_query),
            'lastMessage' => $this->clean_text($message),
            'action' => $action,
            'productIds' => $this->unique_ids($current_ids, self::MAX_PRODUCT_IDS),
            'productNames' => $this->clean_names($current_names, self::MAX_PRODUCT_IDS),
            'missionProductIds' => $this->unique_ids($mission_ids, self::MAX_MISSION_PRODUCT_IDS),
            'lastMultiProductIds' => $this->unique_ids($last_multi_ids, self::MAX_PRODUCT_IDS),
            'lastMultiProductNames' => $this->clean_names($last_multi_names, self::MAX_PRODUCT_IDS),
            'referenceProductIds' => $this->unique_ids($reference_ids, self::MAX_PRODUCT_IDS),
            'referenceProductNames' => $this->clean_names($reference_names, self::MAX_PRODUCT_IDS),
            'selectedProductId' => $selected_product_id,
            'baseAnalysis' => $this->public_analysis($base_analysis),
            'activeAnalysis' => $this->public_analysis($active_analysis),
            'analysis' => $this->public_analysis($active_analysis),
            'history' => array_slice($history, -self::MAX_HISTORY),
            'commandArgs' => !empty($resolution['commandArgs']) ? $this->sanitize_command_args($resolution['commandArgs']) : array(),
            'updatedAt' => current_time('mysql'),
        );
    }

    /**
     * Preserves shopping mission/results while recording the product currently
     * referenced by a deterministic Product Expert answer.
     *
     * Product Q&A must not replace the last multi-product search list, because
     * later commands such as "compare the first and third" still refer to that
     * list.
     */
    public function build_product_question_payload($message, $product_id, $product_name = '', $resolution = array(), $pending_selection = array()) {
        $product_id = absint($product_id);
        $product_name = wp_strip_all_tags((string) $product_name);
        $resolution = is_array($resolution) ? $resolution : array();
        $pending_selection = $this->sanitize_pending_selection($pending_selection);
        $previous = !empty($resolution['previousContext']) && is_array($resolution['previousContext'])
            ? $this->sanitize_loaded_context($resolution['previousContext'])
            : array();

        if (empty($previous)) {
            $ids = $product_id ? array($product_id) : array();
            $names = $product_name !== '' ? array($product_name) : array();
            return array(
                'version' => self::STATE_VERSION,
                'baseMissionQuery' => '',
                'missionQuery' => '',
                'lastMessage' => $this->clean_text($message),
                'action' => !empty($pending_selection) ? 'product_question_clarification' : 'product_question',
                'productIds' => $this->unique_ids($ids, self::MAX_PRODUCT_IDS),
                'productNames' => $this->clean_names($names, self::MAX_PRODUCT_IDS),
                'missionProductIds' => $this->unique_ids($ids, self::MAX_MISSION_PRODUCT_IDS),
                'lastMultiProductIds' => array(),
                'lastMultiProductNames' => array(),
                'referenceProductIds' => $this->unique_ids($ids, self::MAX_PRODUCT_IDS),
                'referenceProductNames' => $this->clean_names($names, self::MAX_PRODUCT_IDS),
                'selectedProductId' => $product_id,
                'pendingProductSelection' => $pending_selection,
                'baseAnalysis' => array(),
                'activeAnalysis' => array(),
                'analysis' => array(),
                'history' => array(),
                'commandArgs' => array(),
                'updatedAt' => current_time('mysql'),
            );
        }

        $current_ids = !empty($previous['productIds']) ? $previous['productIds'] : array();
        $current_names = !empty($previous['productNames']) ? $previous['productNames'] : array();
        if (empty($current_ids) && $product_id) {
            $current_ids = array($product_id);
            $current_names = $product_name !== '' ? array($product_name) : array();
        }

        return array(
            'version' => self::STATE_VERSION,
            'baseMissionQuery' => $this->clean_text($previous['baseMissionQuery'] ?? ''),
            'missionQuery' => $this->clean_text($previous['baseMissionQuery'] ?? ''),
            'lastMessage' => $this->clean_text($message),
            'action' => !empty($pending_selection) ? 'product_question_clarification' : 'product_question',
            'productIds' => $this->unique_ids($current_ids, self::MAX_PRODUCT_IDS),
            'productNames' => $this->clean_names($current_names, self::MAX_PRODUCT_IDS),
            'missionProductIds' => $this->unique_ids($previous['missionProductIds'] ?? $current_ids, self::MAX_MISSION_PRODUCT_IDS),
            'lastMultiProductIds' => $this->unique_ids($previous['lastMultiProductIds'] ?? array(), self::MAX_PRODUCT_IDS),
            'lastMultiProductNames' => $this->clean_names($previous['lastMultiProductNames'] ?? array(), self::MAX_PRODUCT_IDS),
            'referenceProductIds' => $this->unique_ids($previous['referenceProductIds'] ?? $current_ids, self::MAX_PRODUCT_IDS),
            'referenceProductNames' => $this->clean_names($previous['referenceProductNames'] ?? $current_names, self::MAX_PRODUCT_IDS),
            'selectedProductId' => $product_id,
            'pendingProductSelection' => $pending_selection,
            'baseAnalysis' => $this->public_analysis($previous['baseAnalysis'] ?? array()),
            'activeAnalysis' => $this->public_analysis($previous['activeAnalysis'] ?? array()),
            'analysis' => $this->public_analysis($previous['activeAnalysis'] ?? array()),
            'history' => $this->sanitize_history($previous['history'] ?? array()),
            'commandArgs' => array(),
            'updatedAt' => current_time('mysql'),
        );
    }

    /**
     * Preserves the current shopping mission while recording a non-search
     * conversational turn such as an acknowledgement, statement, or product
     * reference. Pending Product Expert clarification is cleared because this
     * turn has already been handled outside the clarification flow.
     */
    public function build_conversation_payload($message, $resolution = array(), $product_id = 0, $product_name = '', $action = 'conversation', $pending_selection = array()) {
        $resolution = is_array($resolution) ? $resolution : array();
        $previous = !empty($resolution['previousContext']) && is_array($resolution['previousContext'])
            ? $this->sanitize_loaded_context($resolution['previousContext'])
            : array();
        $product_id = absint($product_id);
        $product_name = wp_strip_all_tags((string) $product_name);
        $pending_selection = $this->sanitize_pending_selection($pending_selection);
        $action = sanitize_key((string) $action);
        if ($action === '') {
            $action = 'conversation';
        }

        if (empty($previous)) {
            $ids = $product_id ? array($product_id) : array();
            $names = $product_name !== '' ? array($product_name) : array();
            return array(
                'version' => self::STATE_VERSION,
                'baseMissionQuery' => '',
                'missionQuery' => '',
                'lastMessage' => $this->clean_text($message),
                'action' => $action,
                'productIds' => $this->unique_ids($ids, self::MAX_PRODUCT_IDS),
                'productNames' => $this->clean_names($names, self::MAX_PRODUCT_IDS),
                'missionProductIds' => $this->unique_ids($ids, self::MAX_MISSION_PRODUCT_IDS),
                'lastMultiProductIds' => array(),
                'lastMultiProductNames' => array(),
                'referenceProductIds' => $this->unique_ids($ids, self::MAX_PRODUCT_IDS),
                'referenceProductNames' => $this->clean_names($names, self::MAX_PRODUCT_IDS),
                'selectedProductId' => $product_id,
                'pendingProductSelection' => $pending_selection,
                'baseAnalysis' => array(),
                'activeAnalysis' => array(),
                'analysis' => array(),
                'history' => array(),
                'commandArgs' => array(),
                'updatedAt' => current_time('mysql'),
            );
        }

        $selected_product_id = $product_id
            ? $product_id
            : (!empty($previous['selectedProductId']) ? absint($previous['selectedProductId']) : 0);

        return array(
            'version' => self::STATE_VERSION,
            'baseMissionQuery' => $this->clean_text($previous['baseMissionQuery'] ?? ''),
            'missionQuery' => $this->clean_text($previous['baseMissionQuery'] ?? ''),
            'lastMessage' => $this->clean_text($message),
            'action' => $action,
            'productIds' => $this->unique_ids($previous['productIds'] ?? array(), self::MAX_PRODUCT_IDS),
            'productNames' => $this->clean_names($previous['productNames'] ?? array(), self::MAX_PRODUCT_IDS),
            'missionProductIds' => $this->unique_ids($previous['missionProductIds'] ?? array(), self::MAX_MISSION_PRODUCT_IDS),
            'lastMultiProductIds' => $this->unique_ids($previous['lastMultiProductIds'] ?? array(), self::MAX_PRODUCT_IDS),
            'lastMultiProductNames' => $this->clean_names($previous['lastMultiProductNames'] ?? array(), self::MAX_PRODUCT_IDS),
            'referenceProductIds' => $this->unique_ids($previous['referenceProductIds'] ?? array(), self::MAX_PRODUCT_IDS),
            'referenceProductNames' => $this->clean_names($previous['referenceProductNames'] ?? array(), self::MAX_PRODUCT_IDS),
            'selectedProductId' => $selected_product_id,
            'pendingProductSelection' => $pending_selection,
            'baseAnalysis' => $this->public_analysis($previous['baseAnalysis'] ?? array()),
            'activeAnalysis' => $this->public_analysis($previous['activeAnalysis'] ?? array()),
            'analysis' => $this->public_analysis($previous['activeAnalysis'] ?? array()),
            'history' => $this->sanitize_history($previous['history'] ?? array()),
            'commandArgs' => array(),
            'updatedAt' => current_time('mysql'),
        );
    }

    private function product_identity_lists($products) {
        $ids = array();
        $names = array();
        foreach ((array) $products as $product) {
            if (!empty($product['id'])) {
                $ids[] = absint($product['id']);
            }
            if (!empty($product['name'])) {
                $names[] = wp_strip_all_tags((string) $product['name']);
            }
        }
        return array(
            'ids' => $this->unique_ids($ids, self::MAX_MISSION_PRODUCT_IDS),
            'names' => $this->clean_names($names, self::MAX_PRODUCT_IDS),
        );
    }

    private function load_latest($session_id) {
        if (!$session_id) {
            return array();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'geekybot_messages';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded live conversation context must not be stale.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed plugin table; session ID is prepared.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, direction, message, payload FROM {$table} WHERE session_id = %d ORDER BY id DESC LIMIT 30",
                absint($session_id)
            ),
            ARRAY_A
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

        if (empty($rows)) {
            return array();
        }

        foreach ($rows as $position => $row) {
            if (empty($row['payload']) || $row['direction'] !== 'bot') {
                continue;
            }

            $payload = json_decode((string) $row['payload'], true);
            if (!is_array($payload)) {
                continue;
            }

            if (!empty($payload['searchContext']) && is_array($payload['searchContext'])) {
                return $this->sanitize_loaded_context($payload['searchContext']);
            }

            if (empty($payload['products']) || !is_array($payload['products'])) {
                continue;
            }

            $ids = array();
            $names = array();
            foreach ($payload['products'] as $product) {
                if (!empty($product['id'])) {
                    $ids[] = absint($product['id']);
                }
                if (!empty($product['name'])) {
                    $names[] = wp_strip_all_tags((string) $product['name']);
                }
            }

            $mission_query = '';
            for ($older = $position + 1; $older < count($rows); $older++) {
                if ($rows[$older]['direction'] === 'user') {
                    $mission_query = $this->clean_text($rows[$older]['message']);
                    break;
                }
            }

            $ids = $this->unique_ids($ids, self::MAX_PRODUCT_IDS);
            $names = $this->clean_names($names, self::MAX_PRODUCT_IDS);
            return array(
                'version' => 1,
                'baseMissionQuery' => $mission_query,
                'missionQuery' => $mission_query,
                'productIds' => $ids,
                'productNames' => $names,
                'missionProductIds' => $ids,
                'lastMultiProductIds' => count($ids) >= 2 ? $ids : array(),
                'lastMultiProductNames' => count($names) >= 2 ? $names : array(),
                'referenceProductIds' => $ids,
                'referenceProductNames' => $names,
                'selectedProductId' => 0,
                'baseAnalysis' => array(),
                'activeAnalysis' => array(),
                'analysis' => array(),
                'history' => array(),
            );
        }

        return array();
    }

    private function sanitize_loaded_context($context) {
        $context = is_array($context) ? $context : array();
        $product_ids = $this->unique_ids(isset($context['productIds']) ? $context['productIds'] : array(), self::MAX_PRODUCT_IDS);
        $product_names = $this->clean_names(isset($context['productNames']) ? $context['productNames'] : array(), self::MAX_PRODUCT_IDS);
        $mission_ids = $this->unique_ids(isset($context['missionProductIds']) ? $context['missionProductIds'] : $product_ids, self::MAX_MISSION_PRODUCT_IDS);
        $legacy_reference_ids = $this->unique_ids(isset($context['referenceProductIds']) ? $context['referenceProductIds'] : array(), self::MAX_PRODUCT_IDS);
        $legacy_reference_names = $this->clean_names(isset($context['referenceProductNames']) ? $context['referenceProductNames'] : array(), self::MAX_PRODUCT_IDS);
        $last_multi_ids = $this->unique_ids(
            isset($context['lastMultiProductIds'])
                ? $context['lastMultiProductIds']
                : (!empty($legacy_reference_ids) ? $legacy_reference_ids : (count($product_ids) >= 2 ? $product_ids : array())),
            self::MAX_PRODUCT_IDS
        );
        $last_multi_names = $this->clean_names(
            isset($context['lastMultiProductNames'])
                ? $context['lastMultiProductNames']
                : (!empty($legacy_reference_names) ? $legacy_reference_names : (count($product_names) >= 2 ? $product_names : array())),
            self::MAX_PRODUCT_IDS
        );
        $legacy_analysis = !empty($context['analysis']) && is_array($context['analysis']) ? $context['analysis'] : array();
        $base_analysis = !empty($context['baseAnalysis']) && is_array($context['baseAnalysis']) ? $context['baseAnalysis'] : $legacy_analysis;
        $active_analysis = !empty($context['activeAnalysis']) && is_array($context['activeAnalysis']) ? $context['activeAnalysis'] : $legacy_analysis;
        $base_query = !empty($context['baseMissionQuery'])
            ? $this->clean_text($context['baseMissionQuery'])
            : (!empty($context['missionQuery']) ? $this->clean_text($context['missionQuery']) : '');

        return array(
            'version' => !empty($context['version']) ? absint($context['version']) : 1,
            'action' => !empty($context['action']) ? sanitize_key((string) $context['action']) : '',
            'lastMessage' => !empty($context['lastMessage']) ? $this->clean_text($context['lastMessage']) : '',
            'baseMissionQuery' => $base_query,
            'missionQuery' => $base_query,
            'productIds' => $product_ids,
            'productNames' => $product_names,
            'missionProductIds' => $mission_ids,
            'lastMultiProductIds' => $last_multi_ids,
            'lastMultiProductNames' => $last_multi_names,
            'referenceProductIds' => !empty($last_multi_ids) ? $last_multi_ids : $product_ids,
            'referenceProductNames' => !empty($last_multi_names) ? $last_multi_names : $product_names,
            'selectedProductId' => !empty($context['selectedProductId']) ? absint($context['selectedProductId']) : 0,
            'pendingProductSelection' => !empty($context['pendingProductSelection'])
                ? $this->sanitize_pending_selection($context['pendingProductSelection'])
                : (!empty($context['pendingProductClarification'])
                    ? $this->sanitize_pending_selection($context['pendingProductClarification'])
                    : array()),
            'baseAnalysis' => $this->public_analysis($base_analysis),
            'activeAnalysis' => $this->public_analysis($active_analysis),
            'analysis' => $this->public_analysis($active_analysis),
            'history' => $this->sanitize_history(!empty($context['history']) ? $context['history'] : array()),
        );
    }

    private function premium_query($mission_query, $analysis) {
        $terms = array();
        $analysis = is_array($analysis) ? $analysis : array();

        foreach ((array) (isset($analysis['core_terms']) ? $analysis['core_terms'] : array()) as $term) {
            $term = $this->normalize($term);
            if ($term !== '' && !in_array($term, array('product', 'products', 'option', 'options'), true)) {
                $terms[] = $term;
            }
        }
        foreach ((array) (isset($analysis['requested_color_labels']) ? $analysis['requested_color_labels'] : array()) as $term) {
            $terms[] = $this->normalize($term);
        }
        foreach ((array) (isset($analysis['requested_size_labels']) ? $analysis['requested_size_labels'] : array()) as $term) {
            $terms[] = $this->normalize($term);
        }
        foreach ((array) (isset($analysis['modifier_labels']) ? $analysis['modifier_labels'] : array()) as $term) {
            $term = $this->normalize($term);
            if ($term !== '' && !preg_match('/\b(?:budget|cheap|affordable|lower price|best value)\b/u', $term)) {
                $terms[] = $term;
            }
        }
        if (!empty($analysis['is_gift_request'])) {
            $terms[] = 'gift';
        }
        if (!empty($analysis['in_stock_only'])) {
            $terms[] = 'in stock';
        }

        $terms[] = 'premium';
        $terms = array_values(array_unique(array_filter($terms)));
        if (count($terms) <= 1) {
            return trim($this->remove_budget_language($mission_query) . ' premium');
        }
        return implode(' ', array_slice($terms, 0, 8));
    }

    private function remove_budget_language($query) {
        $query = $this->normalize($query);
        $patterns = array(
            '/\bnot too expensive\b/u',
            '/\bnot expensive\b/u',
            '/\bbudget friendly\b/u',
            '/\bbudget-friendly\b/u',
            '/\baffordable\b/u',
            '/\bcheap(?:er|est)?\b/u',
            '/\bunder\s*(?:\$|£|€)?\s*\d+(?:\.\d+)?\b/u',
            '/\bbelow\s*(?:\$|£|€)?\s*\d+(?:\.\d+)?\b/u',
        );
        $query = preg_replace($patterns, ' ', $query);
        return trim(preg_replace('/\s+/u', ' ', (string) $query));
    }

    private function public_analysis($analysis) {
        $keys = array(
            'intent', 'terms', 'core_terms', 'display_core_terms', 'color_terms', 'size_terms',
            'negative_color_terms', 'negative_size_terms', 'requested_color_labels',
            'requested_size_labels', 'negative_color_labels', 'negative_size_labels',
            'price_range', 'in_stock_only', 'modifier_terms', 'modifier_labels',
            'modifier_weights', 'decision_modes', 'audience', 'recipient', 'is_gift_request',
            'gift_signals', 'searchable', 'raw', 'lower', 'budget_sort', 'value_sort',
            // Product Discovery V1 family state must survive follow-up commands.
            // Without these fields, a short constraint update can lose the hard
            // product-family gate and silently widen to unrelated catalog items.
            'phrase', 'product_phrase', 'product_phrase_terms', 'product_family_term',
            'product_family_source', 'product_family_aliases', 'required_family_aliases',
            'family_gate_aliases', 'product_qualifier_terms', 'language',
        );
        $public = array();
        foreach ($keys as $key) {
            if (array_key_exists($key, (array) $analysis)) {
                $public[$key] = $analysis[$key];
            }
        }
        return $public;
    }

    private function snapshot_from_context($context) {
        if (!is_array($context) || empty($context)) {
            return array();
        }
        return $this->sanitize_snapshot(array(
            'baseMissionQuery' => isset($context['baseMissionQuery']) ? $context['baseMissionQuery'] : (isset($context['missionQuery']) ? $context['missionQuery'] : ''),
            'productIds' => isset($context['productIds']) ? $context['productIds'] : array(),
            'productNames' => isset($context['productNames']) ? $context['productNames'] : array(),
            'missionProductIds' => isset($context['missionProductIds']) ? $context['missionProductIds'] : array(),
            'lastMultiProductIds' => isset($context['lastMultiProductIds']) ? $context['lastMultiProductIds'] : (isset($context['referenceProductIds']) ? $context['referenceProductIds'] : array()),
            'lastMultiProductNames' => isset($context['lastMultiProductNames']) ? $context['lastMultiProductNames'] : (isset($context['referenceProductNames']) ? $context['referenceProductNames'] : array()),
            'selectedProductId' => isset($context['selectedProductId']) ? $context['selectedProductId'] : 0,
            'baseAnalysis' => isset($context['baseAnalysis']) ? $context['baseAnalysis'] : (isset($context['analysis']) ? $context['analysis'] : array()),
            'activeAnalysis' => isset($context['activeAnalysis']) ? $context['activeAnalysis'] : (isset($context['analysis']) ? $context['analysis'] : array()),
        ));
    }

    private function sanitize_snapshot($snapshot) {
        $snapshot = is_array($snapshot) ? $snapshot : array();
        $base_query = !empty($snapshot['baseMissionQuery'])
            ? $snapshot['baseMissionQuery']
            : (!empty($snapshot['missionQuery']) ? $snapshot['missionQuery'] : '');
        $last_multi_ids = isset($snapshot['lastMultiProductIds'])
            ? $snapshot['lastMultiProductIds']
            : (isset($snapshot['referenceProductIds']) ? $snapshot['referenceProductIds'] : array());
        $last_multi_names = isset($snapshot['lastMultiProductNames'])
            ? $snapshot['lastMultiProductNames']
            : (isset($snapshot['referenceProductNames']) ? $snapshot['referenceProductNames'] : array());
        $base_analysis = !empty($snapshot['baseAnalysis']) && is_array($snapshot['baseAnalysis'])
            ? $snapshot['baseAnalysis']
            : (!empty($snapshot['analysis']) && is_array($snapshot['analysis']) ? $snapshot['analysis'] : array());
        $active_analysis = !empty($snapshot['activeAnalysis']) && is_array($snapshot['activeAnalysis'])
            ? $snapshot['activeAnalysis']
            : (!empty($snapshot['analysis']) && is_array($snapshot['analysis']) ? $snapshot['analysis'] : array());

        return array(
            'baseMissionQuery' => $this->clean_text($base_query),
            'productIds' => $this->unique_ids(isset($snapshot['productIds']) ? $snapshot['productIds'] : array(), self::MAX_PRODUCT_IDS),
            'productNames' => $this->clean_names(isset($snapshot['productNames']) ? $snapshot['productNames'] : array(), self::MAX_PRODUCT_IDS),
            'missionProductIds' => $this->unique_ids(isset($snapshot['missionProductIds']) ? $snapshot['missionProductIds'] : array(), self::MAX_MISSION_PRODUCT_IDS),
            'lastMultiProductIds' => $this->unique_ids($last_multi_ids, self::MAX_PRODUCT_IDS),
            'lastMultiProductNames' => $this->clean_names($last_multi_names, self::MAX_PRODUCT_IDS),
            'selectedProductId' => !empty($snapshot['selectedProductId']) ? absint($snapshot['selectedProductId']) : 0,
            'baseAnalysis' => $this->public_analysis($base_analysis),
            'activeAnalysis' => $this->public_analysis($active_analysis),
            // Backward-compatible aliases used by older restore code.
            'missionQuery' => $this->clean_text($base_query),
            'referenceProductIds' => $this->unique_ids($last_multi_ids, self::MAX_PRODUCT_IDS),
            'referenceProductNames' => $this->clean_names($last_multi_names, self::MAX_PRODUCT_IDS),
            'analysis' => $this->public_analysis($active_analysis),
        );
    }

    private function append_history_snapshot($history, $snapshot) {
        $history = $this->sanitize_history($history);
        $signature = md5(wp_json_encode(array(
            $snapshot['baseMissionQuery'],
            $snapshot['productIds'],
            $snapshot['lastMultiProductIds'],
            $snapshot['selectedProductId'],
            $snapshot['activeAnalysis'],
        )));
        if (!empty($history)) {
            $last = end($history);
            $last_signature = md5(wp_json_encode(array(
                isset($last['baseMissionQuery']) ? $last['baseMissionQuery'] : '',
                isset($last['productIds']) ? $last['productIds'] : array(),
                isset($last['lastMultiProductIds']) ? $last['lastMultiProductIds'] : array(),
                isset($last['selectedProductId']) ? $last['selectedProductId'] : 0,
                isset($last['activeAnalysis']) ? $last['activeAnalysis'] : array(),
            )));
            if ($signature === $last_signature) {
                return $history;
            }
        }
        $history[] = $snapshot;
        return array_slice($history, -self::MAX_HISTORY);
    }

    private function sanitize_history($history) {
        $clean = array();
        foreach (array_slice((array) $history, -self::MAX_HISTORY) as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $snapshot = $this->sanitize_snapshot($snapshot);
            if (!empty($snapshot['productIds']) || !empty($snapshot['lastMultiProductIds'])) {
                $clean[] = $snapshot;
            }
        }
        return $clean;
    }

    private function sanitize_pending_selection($pending) {
        $pending = is_array($pending) ? $pending : array();
        $question = !empty($pending['originalQuestion'])
            ? $this->clean_text($pending['originalQuestion'])
            : '';
        $created_at = !empty($pending['createdAt']) ? absint($pending['createdAt']) : 0;
        $source = !empty($pending['source']) ? sanitize_key((string) $pending['source']) : 'conversation';
        $route = !empty($pending['route']) && is_array($pending['route'])
            ? $pending['route']
            : array();

        $candidates = array();
        foreach (array_slice((array) ($pending['candidates'] ?? array()), 0, 4) as $candidate) {
            $candidate = is_array($candidate) ? $candidate : array();
            $product_id = !empty($candidate['id']) ? absint($candidate['id']) : 0;
            $name = !empty($candidate['name']) ? wp_strip_all_tags((string) $candidate['name']) : '';
            if ($product_id && $name !== '') {
                $candidates[] = array('id' => $product_id, 'name' => $name);
            }
        }

        if (!$created_at || empty($candidates)) {
            return array();
        }

        return array(
            'source' => $source,
            'originalQuestion' => $question,
            'route' => array(
                'handledCandidate' => !empty($route['handledCandidate']),
                'facts' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($route['facts'] ?? array()))))),
                'normalizedMessage' => $this->clean_text($route['normalizedMessage'] ?? ''),
                'subjectText' => $this->clean_text($route['subjectText'] ?? ''),
                'asksAvailability' => !empty($route['asksAvailability']),
                'asksExactVariation' => !empty($route['asksExactVariation']),
                'asksAvailableOptions' => !empty($route['asksAvailableOptions']),
                'asksUnavailableOptions' => !empty($route['asksUnavailableOptions']),
                'fallbackToSearchWhenUnresolved' => !empty($route['fallbackToSearchWhenUnresolved']),
                'isQuestion' => !empty($route['isQuestion']),
            ),
            'candidates' => $candidates,
            'createdAt' => $created_at,
        );
    }

    private function sanitize_pending_clarification($pending) {
        return $this->sanitize_pending_selection($pending);
    }

    private function sanitize_command_args($args) {
        $clean = array();
        if (!is_array($args)) {
            return $clean;
        }
        if (isset($args['constraintType'])) {
            $clean['constraintType'] = sanitize_key($args['constraintType']);
        }
        if (isset($args['constraintTypes']) && is_array($args['constraintTypes'])) {
            $allowed_constraint_types = array('color', 'size', 'price', 'sale', 'stock', 'preference');
            $clean['constraintTypes'] = array_slice(
                array_values(array_unique(array_filter(array_map(function ($type) use ($allowed_constraint_types) {
                    $type = sanitize_key((string) $type);
                    return in_array($type, $allowed_constraint_types, true) ? $type : '';
                }, $args['constraintTypes'])))),
                0,
                6
            );
        }
        if (isset($args['constraintValues']) && is_array($args['constraintValues'])) {
            $clean['constraintValues'] = array();
            foreach ($args['constraintValues'] as $type => $value) {
                $type = sanitize_key((string) $type);
                if (in_array($type, array('color', 'size', 'price', 'sale', 'stock', 'preference'), true)) {
                    $clean['constraintValues'][$type] = sanitize_text_field((string) $value);
                }
            }
        }
        if (isset($args['value'])) {
            $clean['value'] = sanitize_text_field((string) $args['value']);
        }
        if (isset($args['selectionIndex'])) {
            $clean['selectionIndex'] = absint($args['selectionIndex']);
        }
        if (isset($args['productId'])) {
            $clean['productId'] = absint($args['productId']);
        }
        if (isset($args['productName'])) {
            $clean['productName'] = wp_strip_all_tags((string) $args['productName']);
        }
        // Commerce commands. This is an allowlist, so an argument the resolver
        // produces but this does not name is silently dropped -- which is how a
        // quantity or an attribute choice would reach the addon as nothing.
        if (isset($args['productQuery'])) {
            $clean['productQuery'] = wp_strip_all_tags((string) $args['productQuery']);
        }
        if (isset($args['quantity'])) {
            $clean['quantity'] = max(0, min(999, absint($args['quantity'])));
        }
        if (isset($args['attributesText'])) {
            $clean['attributesText'] = wp_strip_all_tags((string) $args['attributesText']);
        }
        if (isset($args['message'])) {
            $clean['message'] = wp_strip_all_tags((string) $args['message']);
        }
        if (isset($args['referenceIsPronoun'])) {
            $clean['referenceIsPronoun'] = !empty($args['referenceIsPronoun']);
        }
        if (isset($args['cartSelectionIndex']) && $args['cartSelectionIndex'] !== null) {
            $clean['cartSelectionIndex'] = absint($args['cartSelectionIndex']);
        }
        if (isset($args['priceReferences']) && is_array($args['priceReferences'])) {
            $clean['priceReferences'] = array_slice(
                array_values(array_filter(array_map(function ($marker) {
                    $marker = sanitize_key((string) $marker);
                    return in_array($marker, array('cheapest', 'priciest'), true) ? $marker : '';
                }, $args['priceReferences']))),
                0,
                2
            );
        }
        if (isset($args['productIds'])) {
            $clean['productIds'] = $this->unique_ids($args['productIds'], 4);
        }
        if (isset($args['comparisonQuestion'])) {
            $comparison_question = sanitize_key((string) $args['comparisonQuestion']);
            if (in_array($comparison_question, array('difference', 'cheaper', 'rating'), true)) {
                $clean['comparisonQuestion'] = $comparison_question;
            }
        }
        if (isset($args['positions'])) {
            $clean['positions'] = array_slice(array_values(array_unique(array_map('absint', (array) $args['positions']))), 0, 4);
        }
        if (isset($args['namedProducts'])) {
            $clean['namedProducts'] = array_slice(
                array_values(array_unique(array_filter(array_map(function ($name) {
                    return wp_strip_all_tags((string) $name);
                }, (array) $args['namedProducts'])))),
                0,
                4
            );
        }
        if (isset($args['explicitNames'])) {
            $clean['explicitNames'] = !empty($args['explicitNames']);
        }
        if (isset($args['resetOnly'])) {
            $clean['resetOnly'] = !empty($args['resetOnly']);
        }
        return $clean;
    }

    private function clean_names($names, $limit) {
        return array_slice(
            array_values(array_unique(array_filter(array_map('wp_strip_all_tags', (array) $names)))),
            0,
            max(1, absint($limit))
        );
    }

    private function unique_ids($ids, $limit) {
        return array_slice(array_values(array_unique(array_filter(array_map('absint', (array) $ids)))), 0, max(1, absint($limit)));
    }

    private function clean_text($text) {
        $text = wp_strip_all_tags((string) $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim((string) $text);
        return function_exists('mb_substr') ? mb_substr($text, 0, 1000) : substr($text, 0, 1000);
    }

    private function normalize($text) {
        $text = $this->clean_text($text);
        $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $text = str_replace(array('’', "'"), '', $text);
        $text = preg_replace('/[^\p{L}\p{N}\$£€\.\-\s]+/u', ' ', $text);
        return trim(preg_replace('/\s+/u', ' ', (string) $text));
    }
}

<?php
namespace GeekyBot\Services;

use GeekyBot\Search\SearchContextService;
use GeekyBot\ProductExpert\ProductExpertService;
use GeekyBot\Chat\ConversationActRouter;

if (!defined('ABSPATH')) {
    exit;
}

class ChatService {
    private $products;
    private $knowledge;
    private $ai;
    private $product_expert;
    private $conversation_acts;
    private $discovery_recovery;
    private $recommendations;

    public function __construct(?ProductService $products = null, ?KnowledgeService $knowledge = null, ?AiService $ai = null, ?ProductExpertService $product_expert = null, ?ConversationActRouter $conversation_acts = null, ?ProductDiscoveryRecoveryService $discovery_recovery = null, ?ProductRecommendationService $recommendations = null) {
        $this->products = $products ?: new ProductService();
        $this->knowledge = $knowledge ?: new KnowledgeService();
        $this->ai = $ai ?: new AiService();
        $this->product_expert = $product_expert ?: new ProductExpertService();
        $this->conversation_acts = $conversation_acts ?: new ConversationActRouter();
        $this->discovery_recovery = $discovery_recovery ?: new ProductDiscoveryRecoveryService();
        $this->recommendations = $recommendations ?: new ProductRecommendationService();
    }

    public function reply($message, $session_key = '') {
        Installer::maybe_cleanup_history();

        $message = $this->clean_message($message);
        $session_id = $this->ensure_session($session_key);

        if ($message === '') {
            $response = $this->response(__('Please type a product or store question so I can help.', 'geeky-bot'), array(), 'empty_message', $session_id, $session_key, array());
            $this->save_message($session_id, 'bot', $response['message'], $response);
            return $response;
        }

        $this->save_message($session_id, 'user', $message, null);

        $routing_message = $this->cart_product_search_message($message);
        if ($routing_message === '') {
            $routing_message = $message;
        }

        $context_service = new SearchContextService();
        $context_resolution = $context_service->resolve($routing_message, $session_id);
        $action = !empty($context_resolution['action']) ? sanitize_key($context_resolution['action']) : 'new_search';
        $discovery_resolution = !empty($context_resolution['discovery']) && is_array($context_resolution['discovery'])
            ? $context_resolution['discovery']
            : (new ProductDiscoveryIntentService())->analyze($routing_message, !empty($context_resolution['previousAnalysis']) ? $context_resolution['previousAnalysis'] : array());
        $named_comparison_error = array();

        $ordinal_comparison_error = $this->ordinal_comparison_error_reply($context_resolution);
        if ($ordinal_comparison_error !== '') {
            // Do not save a replacement search context for an invalid ordinal
            // comparison. The previous visible result set remains authoritative.
            $response = $this->response(
                $ordinal_comparison_error,
                array(),
                'product_comparison_position_unavailable',
                $session_id,
                $session_key,
                array()
            );
            $this->save_message($session_id, 'bot', $ordinal_comparison_error, $response);
            return $response;
        }

        if ($action === 'reset_search' && empty($context_resolution['effectiveQuery'])) {
            $search_context_payload = $context_service->build_payload($message, array(), array('analysis' => array()), $context_resolution);
            $reply = __('I cleared the previous shopping request. Tell me what you would like to find next.', 'geeky-bot');
            $response = $this->response($reply, array(), 'search_reset', $session_id, $session_key, array(), $search_context_payload);
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        $search_message = ($action === 'reset_search' && !empty($context_resolution['effectiveQuery']))
            ? $context_resolution['effectiveQuery']
            : $routing_message;

        // Store-level policy questions are resolved before Product Expert.
        // This keeps exchange, payment, shipping and store-warranty questions
        // from being mistaken for facts about one selected WooCommerce product.
        // Product-specific warranty questions remain with Product Expert.
        $policy_resolution = $this->knowledge->resolve_policy($search_message, 2);
        if (!empty($policy_resolution['isPolicyQuestion'])) {
            $knowledge_matches = !empty($policy_resolution['answerable'])
                ? (array) $policy_resolution['matches']
                : (array) $policy_resolution['sources'];
            $reply = !empty($policy_resolution['answerable'])
                ? $this->knowledge->local_policy_answer($search_message, $knowledge_matches)
                : $this->knowledge->missing_policy_answer($policy_resolution);
            $intent = !empty($policy_resolution['answerable'])
                ? 'policy_answer'
                : (!empty($policy_resolution['reason']) && $policy_resolution['reason'] === 'source_missing'
                    ? 'policy_source_missing'
                    : 'policy_detail_missing');

            if (empty($policy_resolution['answerable'])) {
                $policy_analysis = !empty($policy_resolution['analysis']) && is_array($policy_resolution['analysis'])
                    ? $policy_resolution['analysis']
                    : array();
                $this->log_unanswered(
                    $session_id,
                    $message,
                    $intent,
                    array(
                        'policy_type' => !empty($policy_analysis['primaryType']) ? $policy_analysis['primaryType'] : '',
                        'policy_label' => !empty($policy_analysis['label']) ? $policy_analysis['label'] : '',
                    )
                );
            }

            $policy_context_payload = $context_service->build_conversation_payload(
                $message,
                $context_resolution,
                0,
                '',
                'policy_question',
                array()
            );
            $response = $this->response(
                $reply,
                array(),
                $intent,
                $session_id,
                $session_key,
                $knowledge_matches,
                $policy_context_payload
            );
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        // Product Expert questions are answered before catalog search so shopper
        // questions such as "What material is this backpack made from?" do not
        // become keyword searches for "backpack made".
        if ($this->products->is_woocommerce_ready()) {
            $product_expert_result = $this->product_expert->handle($message, $context_resolution);
            if (!empty($product_expert_result['handled'])) {
                $product_id = !empty($product_expert_result['productId']) ? absint($product_expert_result['productId']) : 0;
                $products = $product_id
                    ? $this->products->products_by_ids(array($product_id), 1, array(), 'product_question')
                    : array();
                $product_name = !empty($product_expert_result['meta']['productName'])
                    ? wp_strip_all_tags((string) $product_expert_result['meta']['productName'])
                    : (!empty($products[0]['name']) ? wp_strip_all_tags((string) $products[0]['name']) : '');
                $pending_clarification = !empty($product_expert_result['meta']['pendingClarification'])
                    && is_array($product_expert_result['meta']['pendingClarification'])
                    ? $product_expert_result['meta']['pendingClarification']
                    : array();
                $search_context_payload = $context_service->build_product_question_payload(
                    $message,
                    $product_id,
                    $product_name,
                    $context_resolution,
                    $pending_clarification
                );
                $reply = !empty($product_expert_result['message'])
                    ? wp_strip_all_tags((string) $product_expert_result['message'])
                    : __("I couldn't find that information in this product's store details.", 'geeky-bot');
                $intent = !empty($product_expert_result['intent'])
                    ? sanitize_key($product_expert_result['intent'])
                    : 'product_fact_answer';
                $extra = array(
                    'product_expert' => !empty($product_expert_result['meta']) && is_array($product_expert_result['meta'])
                        ? $product_expert_result['meta']
                        : array(),
                );
                $response = $this->response(
                    $reply,
                    $products,
                    $intent,
                    $session_id,
                    $session_key,
                    array(),
                    $search_context_payload,
                    $extra
                );
                if (!empty($product_expert_result['missing'])) {
                    $this->log_unanswered(
                        $session_id,
                        $message,
                        $intent === 'product_question_unresolved' ? 'product_reference_unresolved' : 'product_fact_missing',
                        array(
                            'product_id' => $product_id,
                            'product_name' => $product_name,
                        )
                    );
                }
                $this->save_message($session_id, 'bot', $reply, $response);
                return $response;
            }
        }

        // Conversation acts are handled after Product Expert clarification/Q&A
        // but before search, AI, or Store Knowledge. This prevents acknowledgements,
        // shopper statements, and vague references from becoming accidental catalog
        // searches or page-knowledge queries.
        $conversation_act = $this->conversation_acts->route($message, $context_resolution);
        if (!empty($conversation_act['handled'])) {
            $product_id = !empty($conversation_act['productId']) ? absint($conversation_act['productId']) : 0;
            $products = ($product_id && !empty($conversation_act['showProduct']))
                ? $this->products->products_by_ids(array($product_id), 1, array(), 'conversation_reference')
                : array();
            $product_name = !empty($conversation_act['productName'])
                ? wp_strip_all_tags((string) $conversation_act['productName'])
                : (!empty($products[0]['name']) ? wp_strip_all_tags((string) $products[0]['name']) : '');
            $act = !empty($conversation_act['act'])
                ? sanitize_key((string) $conversation_act['act'])
                : 'conversation';
            $reply = !empty($conversation_act['message'])
                ? wp_strip_all_tags((string) $conversation_act['message'])
                : __('What would you like to check next?', 'geeky-bot');
            $pending_selection = !empty($conversation_act['pendingSelection']) && is_array($conversation_act['pendingSelection'])
                ? $conversation_act['pendingSelection']
                : array();
            $search_context_payload = $context_service->build_conversation_payload(
                $message,
                $context_resolution,
                $product_id,
                $product_name,
                'conversation_' . $act,
                $pending_selection
            );
            $response = $this->response(
                $reply,
                $products,
                'conversation_' . $act,
                $session_id,
                $session_key,
                array(),
                $search_context_payload,
                array('conversation_act' => array('type' => $act))
            );
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        $is_policy_question = false;

        $is_product_discovery = !empty($discovery_resolution['isProductDiscovery']);
        $is_product_question = $this->products->is_product_question($search_message)
            || $is_product_discovery
            || !empty($context_resolution['isFollowUp'])
            || in_array($action, array('reset_search', 'compare'), true);

        // Product discovery owns product/category browse messages. A no-match
        // must never fall through to Cart, Checkout, or policy-page content.
        $knowledge_matches = array();
        $products = array();
        $product_search_context = array();
        $recommendation_presentation = array();
        $context_actions = array(
            'best', 'best_discount', 'cheaper', 'filter_current', 'remove_constraint',
            'select', 'premium', 'similar', 'another_color', 'go_back', 'compare',
        );

        if (!$is_policy_question && $this->products->is_woocommerce_ready() && $is_product_question) {
            $limit = absint(Settings::get('max_products', 4));
            $previous_ids = !empty($context_resolution['previousProductIds']) ? $context_resolution['previousProductIds'] : array();
            $mission_ids = !empty($context_resolution['previousMissionProductIds'])
                ? $context_resolution['previousMissionProductIds']
                : $previous_ids;
            $previous_analysis = !empty($context_resolution['previousAnalysis']) ? $context_resolution['previousAnalysis'] : array();
            $command_args = !empty($context_resolution['commandArgs']) ? $context_resolution['commandArgs'] : array();

            if ($action === 'best') {
                $products = $this->products->context_products($previous_ids, 'best', 1, $previous_analysis);
            } elseif ($action === 'best_discount') {
                $products = $this->products->context_products($previous_ids, 'best_discount', 1, $previous_analysis);
            } elseif ($action === 'cheaper') {
                $products = $this->products->context_products($previous_ids, 'cheaper', $limit, $previous_analysis);
                if (empty($products)) {
                    $products = $this->products->search($context_resolution['effectiveQuery'], $limit);
                }
            } elseif ($action === 'filter_current') {
                $products = $this->products->products_after_constraint_update(
                    $previous_analysis,
                    $message,
                    $limit,
                    $mission_ids
                );
            } elseif ($action === 'remove_constraint') {
                $products = $this->products->products_after_constraint_removal(
                    $previous_analysis,
                    $command_args,
                    $limit,
                    $mission_ids
                );
            } elseif ($action === 'select') {
                $products = $this->products->context_products(
                    $previous_ids,
                    'select',
                    1,
                    $previous_analysis,
                    '',
                    $context_resolution['selectionIndex']
                );
            } elseif ($action === 'premium') {
                $context_products = $this->products->context_products(
                    $mission_ids,
                    'premium',
                    min(8, max($limit, 6)),
                    $previous_analysis
                );
                $expanded_products = $this->products->search(
                    $context_resolution['effectiveQuery'],
                    min(8, max($limit, 6))
                );
                $candidate_ids = array_merge(
                    $this->product_ids($context_products),
                    $this->product_ids($expanded_products)
                );
                $products = $this->products->context_products(
                    $candidate_ids,
                    'premium',
                    $limit,
                    $previous_analysis
                );
            } elseif ($action === 'similar') {
                $reference_id = !empty($context_resolution['referenceProductId'])
                    ? absint($context_resolution['referenceProductId'])
                    : (!empty($previous_ids[0]) ? absint($previous_ids[0]) : 0);
                $products = $this->products->similar_products($reference_id, $limit, $previous_analysis, $mission_ids);
            } elseif ($action === 'another_color') {
                $reference_id = !empty($context_resolution['referenceProductId'])
                    ? absint($context_resolution['referenceProductId'])
                    : (!empty($previous_ids[0]) ? absint($previous_ids[0]) : 0);
                $products = $this->products->color_options_or_alternatives($reference_id, $limit, $previous_analysis, $mission_ids);
            } elseif ($action === 'go_back') {
                $restore = !empty($context_resolution['restoreContext']) ? $context_resolution['restoreContext'] : array();
                $restore_ids = !empty($restore['productIds'])
                    ? $restore['productIds']
                    : (!empty($restore['lastMultiProductIds'])
                        ? $restore['lastMultiProductIds']
                        : (!empty($restore['referenceProductIds']) ? $restore['referenceProductIds'] : array()));
                $restore_analysis = !empty($restore['activeAnalysis'])
                    ? $restore['activeAnalysis']
                    : (!empty($restore['analysis']) ? $restore['analysis'] : array());
                $products = $this->products->context_products($restore_ids, 'restore', $limit, $restore_analysis);
            } elseif ($action === 'compare') {
                $compare_ids = !empty($context_resolution['compareProductIds'])
                    ? $context_resolution['compareProductIds']
                    : (!empty($command_args['productIds']) ? $command_args['productIds'] : array());

                if (!empty($command_args['namedProducts'])) {
                    $named_comparison = (new NamedProductResolver())->resolve_comparison(
                        $message,
                        (array) $command_args['namedProducts']
                    );
                    if (!empty($named_comparison['resolved'])) {
                        $compare_ids = (array) $named_comparison['productIds'];
                        $context_resolution['compareProductIds'] = $compare_ids;
                        $context_resolution['commandArgs']['productIds'] = $compare_ids;
                        $previous_analysis = array();
                    } else {
                        $named_comparison_error = $named_comparison;
                        $compare_ids = array();
                    }
                }

                $products = $this->products->products_by_ids($compare_ids, 4, $previous_analysis, 'compare');
            } else {
                $products = $this->products->search($search_message, $limit);
            }

            $product_search_context = $this->products->last_search_context();
            if (!empty($context_resolution['isFollowUp']) || $action === 'reset_search') {
                $product_search_context['conversationAction'] = $action;
            }

            // Recommendation presentation is a post-search layer. It can reorder
            // only products that already passed Product Discovery constraints and
            // cannot add, remove, or broaden catalog matches.
            if (!empty($products) && !in_array($action, $context_actions, true)) {
                $recommendation_presentation = $this->recommendations->prepare(
                    $search_message,
                    $products,
                    $product_search_context
                );
                if (!empty($recommendation_presentation['handled'])) {
                    $products = !empty($recommendation_presentation['products'])
                        ? (array) $recommendation_presentation['products']
                        : $products;
                    $product_search_context['recommendationPresentation'] = !empty($recommendation_presentation['meta'])
                        ? (array) $recommendation_presentation['meta']
                        : array();
                }
            }
        }

        if (!$this->products->is_woocommerce_ready() && empty($knowledge_matches)) {
            $reply = __('Product assistance is temporarily unavailable. Please contact the store if you need help.', 'geeky-bot');
            $response = $this->response($reply, array(), 'woocommerce_missing', $session_id, $session_key, array());
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        $catalog_note = !empty($product_search_context['note']) ? sanitize_key((string) $product_search_context['note']) : '';
        $has_named_catalog_state = in_array($catalog_note, array('named_product_match', 'named_product_not_visible'), true);
        $has_grounded_discovery_state = $is_product_discovery
            && !empty($product_search_context['analysis'])
            && is_array($product_search_context['analysis']);

        // Persist explicit Product Discovery missions even when the catalog has
        // no matching products. Without this state, a later command such as
        // "Remove the sale requirement" can reopen an older successful search
        // or have no mission to update, dropping still-active constraints such
        // as the shopper's price ceiling.
        $search_context_payload = (!empty($products)
            || $action === 'reset_search'
            || in_array($action, $context_actions, true)
            || $has_named_catalog_state
            || $has_grounded_discovery_state)
            ? $context_service->build_payload($message, $products, $product_search_context, $context_resolution)
            : array();

        if (empty($products) && $catalog_note === 'named_product_not_visible') {
            $reply = __("I couldn't find an available product with that name.", 'geeky-bot');
            $response = $this->response($reply, array(), 'named_product_not_visible', $session_id, $session_key, array(), $search_context_payload);
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        if ($action === 'compare' && !empty($named_comparison_error)) {
            $reply = $this->named_comparison_error_reply($named_comparison_error);
            $response = $this->response($reply, array(), 'product_comparison_unresolved', $session_id, $session_key, array(), $search_context_payload);
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        if ($action === 'compare') {
            $command_result = array();

            // Commerce Pro is authoritative for comparison. Call its service
            // directly first so callback order, cached hooks, or another filter
            // cannot replace a valid licensed comparison response.
            if (class_exists('GeekyBotCommercePro\Services\ChatCommandService')) {
                $command_service = new \GeekyBotCommercePro\Services\ChatCommandService();
                $command_result = $command_service->handle(
                    array(),
                    $action,
                    $context_resolution,
                    $products,
                    $session_id,
                    $session_key
                );
            }

            if (empty($command_result['handled'])) {
                $command_result = apply_filters(
                    'geekybot_shopping_command_result',
                    array(),
                    $action,
                    $context_resolution,
                    $products,
                    $session_id,
                    $session_key
                );
            }

            if (!empty($command_result['handled'])) {
                $reply = !empty($command_result['message'])
                    ? wp_strip_all_tags((string) $command_result['message'])
                    : __('Here is the product comparison.', 'geeky-bot');
                $intent = !empty($command_result['intent']) ? sanitize_key($command_result['intent']) : 'product_comparison';
                $extra = !empty($command_result['extra']) && is_array($command_result['extra']) ? $command_result['extra'] : array();
                $response = $this->response($reply, $products, $intent, $session_id, $session_key, array(), $search_context_payload, $extra);
                $this->save_message($session_id, 'bot', $reply, $response);
                return $response;
            }

            $reply = count($products) >= 2
                ? __("Product comparison isn't available right now. You can still open the products below to review their details.", 'geeky-bot')
                : __('Choose at least two shown products to compare.', 'geeky-bot');
            $response = $this->response($reply, $products, 'comparison_requires_pro', $session_id, $session_key, array(), $search_context_payload);
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        $deterministic_actions = array_values(array_diff($context_actions, array('compare')));

        if (empty($products) && in_array($action, $deterministic_actions, true)) {
            $reply = $this->empty_context_command_reply($action, $product_search_context);
            $response = $this->response($reply, array(), 'product_no_match', $session_id, $session_key, array(), $search_context_payload);
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        $deterministic_catalog_result = in_array($catalog_note, array('named_product_match', 'named_product_not_visible'), true);

        if ($is_product_discovery && empty($products)) {
            $reply = $this->local_reply($search_message, array(), array(), false, $product_search_context);
            $response = $this->response($reply, array(), 'product_no_match', $session_id, $session_key, array(), $search_context_payload);
            $this->log_unanswered(
                $session_id,
                $message,
                'product_discovery_no_match',
                array(
                    'requested_label' => !empty($product_search_context['requestedLabel']) ? $product_search_context['requestedLabel'] : '',
                    'note' => !empty($product_search_context['note']) ? $product_search_context['note'] : '',
                    'price_text' => !empty($product_search_context['priceText']) ? $product_search_context['priceText'] : '',
                    'constraint_text' => !empty($product_search_context['constraintText']) ? $product_search_context['constraintText'] : '',
                )
            );
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        if (!empty($recommendation_presentation['handled'])) {
            $reply = !empty($recommendation_presentation['message'])
                ? wp_strip_all_tags((string) $recommendation_presentation['message'])
                : __('Here are the best matches for your request.', 'geeky-bot');
            $extra = array(
                'recommendation' => !empty($recommendation_presentation['meta']) && is_array($recommendation_presentation['meta'])
                    ? $recommendation_presentation['meta']
                    : array(),
            );
            $response = $this->response($reply, $products, 'product_recommendation', $session_id, $session_key, array(), $search_context_payload, $extra);
            $this->save_message($session_id, 'bot', $reply, $response);
            return $response;
        }

        if (!in_array($action, $deterministic_actions, true) && !$deterministic_catalog_result) {
            $ai_reply = $this->ai->answer($search_message, $products, $knowledge_matches);
            if ($ai_reply !== '') {
                $intent = !empty($products) ? 'ai_product_answer' : 'ai_policy_answer';
                $response_knowledge = (!empty($products) && !$is_policy_question) ? array() : $knowledge_matches;
                $response = $this->response($ai_reply, $products, $intent, $session_id, $session_key, $response_knowledge, $search_context_payload);
                $this->save_message($session_id, 'bot', $ai_reply, $response);
                return $response;
            }
        }

        $reply = $this->local_reply($search_message, $products, $knowledge_matches, $is_policy_question, $product_search_context);
        $intent = $this->intent_from_result($products, $knowledge_matches, $is_policy_question);

        if ($intent === 'no_answer') {
            $this->log_unanswered($session_id, $message, 'no_catalog_or_policy_match', array());
        }

        $response_knowledge = (!empty($products) && !$is_policy_question) ? array() : $knowledge_matches;
        $response = $this->response($reply, $products, $intent, $session_id, $session_key, $response_knowledge, $search_context_payload);
        $this->save_message($session_id, 'bot', $reply, $response);
        return $response;
    }

    private function ordinal_comparison_error_reply($resolution) {
        $resolution = is_array($resolution) ? $resolution : array();
        if (empty($resolution['action']) || sanitize_key((string) $resolution['action']) !== 'compare') {
            return '';
        }

        $args = !empty($resolution['commandArgs']) && is_array($resolution['commandArgs'])
            ? $resolution['commandArgs']
            : array();
        if (empty($args['ordinalComparison'])) {
            return '';
        }

        $positions = array_values(array_unique(array_map('absint', (array) ($args['positions'] ?? array()))));
        $missing = array_values(array_unique(array_map('absint', (array) ($args['missingPositions'] ?? array()))));
        $available_count = isset($args['availableResultCount']) ? absint($args['availableResultCount']) : 0;

        if (count($positions) < 2) {
            return __('Choose two different result positions to compare, such as “compare the first and third.”', 'geeky-bot');
        }

        if (empty($missing)) {
            return '';
        }

        if ($available_count < 1) {
            return __('I do not have a current product list to compare. Show some products first, or name the two products you would like to compare.', 'geeky-bot');
        }

        $missing_label = $this->ordinal_position_label($missing[0]);
        if ($available_count === 1) {
            return sprintf(
                /* translators: %s: missing ordinal position such as third. */
                __('I only have one product in the current results, so there is no %s product to compare. Show more products or name the two products you would like to compare.', 'geeky-bot'),
                $missing_label
            );
        }

        return sprintf(
            /* translators: 1: number of visible products, 2: missing ordinal position such as third. */
            _n(
                'The current results contain only %1$d product, so there is no %2$s product to compare. Show more products or name the two products you would like to compare.',
                'The current results contain only %1$d products, so there is no %2$s product to compare. Show more products or name the two products you would like to compare.',
                $available_count,
                'geeky-bot'
            ),
            $available_count,
            $missing_label
        );
    }

    private function ordinal_position_label($index) {
        $labels = array(
            0 => __('first', 'geeky-bot'),
            1 => __('second', 'geeky-bot'),
            2 => __('third', 'geeky-bot'),
            3 => __('fourth', 'geeky-bot'),
            4 => __('fifth', 'geeky-bot'),
            5 => __('sixth', 'geeky-bot'),
            6 => __('seventh', 'geeky-bot'),
            7 => __('eighth', 'geeky-bot'),
            8 => __('ninth', 'geeky-bot'),
            9 => __('tenth', 'geeky-bot'),
        );
        $index = absint($index);
        return isset($labels[$index])
            ? $labels[$index]
            : sprintf(
                /* translators: %d: one-based product position. */
                __('#%d', 'geeky-bot'),
                $index + 1
            );
    }

    private function named_comparison_error_reply($resolution) {
        $problems = !empty($resolution['problems']) && is_array($resolution['problems'])
            ? $resolution['problems']
            : array();
        if (empty($problems)) {
            return __("I couldn't find two visible store products matching those names.", 'geeky-bot');
        }

        $problem = $problems[0];
        $requested = !empty($problem['requested'])
            ? wp_strip_all_tags((string) $problem['requested'])
            : __('that product', 'geeky-bot');
        $status = !empty($problem['status']) ? sanitize_key((string) $problem['status']) : 'unresolved';

        if ($status === 'ambiguous' && !empty($problem['candidates'])) {
            $names = array();
            foreach ((array) $problem['candidates'] as $candidate) {
                if (!empty($candidate['name'])) {
                    $names[] = wp_strip_all_tags((string) $candidate['name']);
                }
            }
            if (count($names) >= 2) {
                return sprintf(
                    /* translators: 1: requested phrase, 2: first product, 3: second product. */
                    __('I found more than one visible product for “%1$s”: %2$s and %3$s. Please use the full product name.', 'geeky-bot'),
                    $requested,
                    $names[0],
                    $names[1]
                );
            }
        }

        return sprintf(
            /* translators: %s: requested product name. */
            __("I couldn't find a visible store product matching “%s”.", 'geeky-bot'),
            $requested
        );
    }

    private function product_ids($products) {
        $ids = array();
        foreach ((array) $products as $product) {
            if (!empty($product['id'])) {
                $ids[] = absint($product['id']);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * When an add-to-cart command cannot be resolved by Commerce Pro's current
     * product context, search for its product subject instead of treating words
     * such as “add” and “cart” as catalog identity.
     */
    private function cart_product_search_message($message) {
        $message = trim(wp_strip_all_tags((string) $message));
        if ($message === '') {
            return '';
        }

        if (!preg_match(
            '/^(?:add|put|place)\s+(?:\d{1,2}\s+)?(.+?)\s+(?:to|in|into)\s+(?:(?:my|the)\s+)?(?:cart|basket|card)[.!?]*$/iu',
            $message,
            $matches
        )) {
            return '';
        }

        $subject = !empty($matches[1]) ? trim((string) $matches[1]) : '';
        return preg_match('/^(?:the\s+)?(?:first|second|third|fourth|1st|2nd|3rd|4th)(?:\s+product|\s+item|\s+one)?$/iu', $subject)
            ? ''
            : $subject;
    }

    private function empty_context_command_reply($action, $product_search_context = array()) {
        $command_meta = !empty($product_search_context['commandMeta']) && is_array($product_search_context['commandMeta'])
            ? $product_search_context['commandMeta']
            : array();
        $source_name = !empty($command_meta['sourceProductName'])
            ? wp_strip_all_tags((string) $command_meta['sourceProductName'])
            : __('that product', 'geeky-bot');

        if ($action === 'another_color') {
            return sprintf(
                /* translators: %s: product name. */
                __("I couldn't find another confirmed color for %s that also matches your remaining filters.", 'geeky-bot'),
                $source_name
            );
        }
        if ($action === 'similar') {
            return sprintf(
                /* translators: %s: product name. */
                __("I couldn't find a close alternative to %s that also matches your remaining filters.", 'geeky-bot'),
                $source_name
            );
        }
        if (in_array($action, array('remove_constraint', 'filter_current'), true)) {
            return $this->discovery_recovery->no_match_message($product_search_context, $action);
        }
        if ($action === 'go_back') {
            return __("There isn't an earlier product list to restore in this conversation.", 'geeky-bot');
        }
        return __("I couldn't find products matching that change.", 'geeky-bot');
    }

    private function local_reply($message, $products, $knowledge_matches, $is_policy_question, $product_search_context = array()) {
        if ($is_policy_question) {
            if (!empty($knowledge_matches)) {
                $policy_answer = $this->knowledge->local_policy_answer($message);
                if ($policy_answer !== '') {
                    return $policy_answer;
                }
            }

            return Settings::get('fallback_human_message', __("I couldn't find this information in the store’s published policies. Please contact the store for confirmation.", 'geeky-bot'));
        }

        if (!empty($products)) {
            $count = count($products);
            $note = isset($product_search_context['note']) ? $product_search_context['note'] : '';
            $conversation_action = !empty($product_search_context['conversationAction']) ? sanitize_key($product_search_context['conversationAction']) : '';

            if ($note === 'named_product_match' && !empty($products[0]['name'])) {
                return sprintf(
                    /* translators: %s: exact visible product name. */
                    __('Here is %s.', 'geeky-bot'),
                    $products[0]['name']
                );
            }

            $command_meta = !empty($product_search_context['commandMeta']) && is_array($product_search_context['commandMeta'])
                ? $product_search_context['commandMeta']
                : array();

            if ($conversation_action === 'best') {
                return sprintf(
                    /* translators: %s: strongest matching product name. */
                    __('From the products shown, %s is the closest match to your request. Open it to review the price, stock, and available options.', 'geeky-bot'),
                    $products[0]['name']
                );
            }

            if ($conversation_action === 'best_discount') {
                $discount = !empty($command_meta['discountPercent']) ? absint($command_meta['discountPercent']) : 0;
                if ($discount > 0) {
                    return sprintf(
                        /* translators: 1: product name, 2: discount percentage. */
                        __('%1$s has the largest confirmed discount among the products shown at about %2$d%% off. Open it to review the price, stock, and available options.', 'geeky-bot'),
                        $products[0]['name'],
                        $discount
                    );
                }

                return sprintf(
                    /* translators: %s: product name. */
                    __('%s is the best discounted option I could confirm from the products shown.', 'geeky-bot'),
                    $products[0]['name']
                );
            }

            if ($conversation_action === 'select') {
                return sprintf(
                    /* translators: %s: selected product name. */
                    __('You selected %s. Open it to review the price, stock, and available options.', 'geeky-bot'),
                    $products[0]['name']
                );
            }

            if ($conversation_action === 'another_color') {
                $colors = !empty($command_meta['colors']) ? array_values(array_filter(array_map('wp_strip_all_tags', (array) $command_meta['colors']))) : array();
                if (count($colors) > 1) {
                    return sprintf(
                        /* translators: 1: product name, 2: comma-separated color list. */
                        __('%1$s is available in these colors: %2$s. Open the product to choose an available option.', 'geeky-bot'),
                        $products[0]['name'],
                        implode(', ', $colors)
                    );
                }

                $reply = __("I couldn't find another confirmed color for that product.", 'geeky-bot');
            } elseif ($conversation_action === 'similar') {
                $source_name = !empty($command_meta['sourceProductName']) ? $command_meta['sourceProductName'] : __('the selected product', 'geeky-bot');
                $reply = sprintf(
                    /* translators: %s: source product name. */
                    __('Here are some related alternatives to %s.', 'geeky-bot'),
                    $source_name
                );
            } elseif ($conversation_action === 'go_back') {
                $reply = __('I restored the previous product options.', 'geeky-bot');
            } elseif ($conversation_action === 'remove_constraint') {
                $removed_count = !empty($command_meta['constraintCount'])
                    ? absint($command_meta['constraintCount'])
                    : (!empty($command_meta['constraintTypes']) && is_array($command_meta['constraintTypes'])
                        ? count($command_meta['constraintTypes'])
                        : 1);
                $reply = $removed_count > 1
                    ? __('I removed those filters and updated the results.', 'geeky-bot')
                    : __('I removed that filter and updated the results.', 'geeky-bot');
            } elseif ($conversation_action === 'cheaper') {
                $reply = __('Here are some lower-priced options that still match your request.', 'geeky-bot');
            } elseif ($conversation_action === 'filter_current') {
                $reply = __('I narrowed the results using your latest preference.', 'geeky-bot');
            } elseif ($conversation_action === 'premium') {
                $reply = __('Here are some more premium options that still match your request.', 'geeky-bot');
            } else {
                $reply = '';
            }
            $requested_label = !empty($product_search_context['requestedLabel']) ? $product_search_context['requestedLabel'] : __('that product', 'geeky-bot');
            $price_text = !empty($product_search_context['priceText']) ? $product_search_context['priceText'] : '';
            if ($reply !== '') {
                // Context-aware lead sentence is already prepared above.
            } elseif ($note === 'price_alternatives') {
                $reply = $price_text
                    ? sprintf(
                        /* translators: 1: requested product/search term, 2: price phrase */
                        __('I couldn\'t find %1$s %2$s. Here are other products in that price range.', 'geeky-bot'),
                        $requested_label,
                        $price_text
                    )
                    : sprintf(
                        /* translators: %s: requested product/search term */
                        __("I couldn't find %s at that price. Here are other products that may fit your budget.", 'geeky-bot'),
                        $requested_label
                    );
            } elseif ($note === 'facet_alternatives') {
                $constraint_text = !empty($product_search_context['constraintText']) ? $product_search_context['constraintText'] : '';
                $search_label = trim($requested_label . ($constraint_text ? ' ' . $constraint_text : '') . ($price_text ? ' ' . $price_text : ''));
                if ($constraint_text) {
                    $reply = sprintf(
                        /* translators: %s: requested constraints such as color/size. */
                        __("I couldn't find an exact match %s. Here are the closest available products.", 'geeky-bot'),
                        $constraint_text
                    );
                } else {
                    $reply = sprintf(
                        /* translators: 1: exact requested product phrase, 2: broader product name */
                        __('I couldn\'t find %1$s. Here are other %2$s options that may help.', 'geeky-bot'),
                        $search_label ? $search_label : $requested_label,
                        $requested_label
                    );
                }
            } else {
                $reply = sprintf(
                    /* translators: %d: product count */
                    _n('Here is %d product that matches your request.', 'Here are %d products that match your request.', $count, 'geeky-bot'),
                    $count
                );

            }

            $names = array();
            foreach (array_slice($products, 0, 3) as $product) {
                $names[] = $product['name'];
            }

            if (!empty($names)) {
                $reply .= ' ' . sprintf(
                    /* translators: %s: product names */
                    __('Top matches: %s.', 'geeky-bot'),
                    implode(', ', $names)
                );
            }

            if ($this->products_have_unconfirmed_search_parts($products)) {
                $reply .= ' ' . __('I marked any requested details that the store has not confirmed.', 'geeky-bot');
            }

            $reply .= ' ' . __('Open a product to review its price, stock, and available options.', 'geeky-bot');
            return $reply;
        }

        if (!empty($product_search_context['note']) && in_array($product_search_context['note'], array('price_no_match', 'product_no_match'), true)) {
            return $this->discovery_recovery->no_match_message($product_search_context);
        }

        if (!empty($knowledge_matches)) {
            $policy_answer = $this->knowledge->local_policy_answer($message);
            if ($policy_answer !== '') {
                return $policy_answer;
            }
        }

        if (!$is_policy_question && $this->products->is_product_question($message)) {
            return __("I couldn't find a matching product. Try a different color, size, price, or product name.", 'geeky-bot');
        }

        return Settings::get('fallback_human_message', __("The store hasn't provided enough information for me to answer that.", 'geeky-bot'));
    }


    private function products_have_unconfirmed_search_parts($products) {
        foreach ((array) $products as $product) {
            if (!empty($product['searchMatch']) && !empty($product['searchMatch']['notConfirmed'])) {
                return true;
            }
        }
        return false;
    }

    private function intent_from_result($products, $knowledge_matches, $is_policy_question) {
        if (!empty($products)) {
            return 'product_search';
        }
        if ($is_policy_question && !empty($knowledge_matches)) {
            return 'policy_answer';
        }
        if (!empty($knowledge_matches)) {
            return 'knowledge_answer';
        }
        return 'no_answer';
    }

    private function response($message, $products, $intent, $session_id, $session_key, $knowledge_matches, $search_context = array(), $extra = array()) {
        $payload = array(
            'message' => $message,
            'products' => $products,
            'intent' => $intent,
            'sessionKey' => $this->session_key_from_id($session_id, $session_key),
            'knowledge' => $this->public_knowledge_sources($knowledge_matches),
        );

        if (!empty($search_context)) {
            $payload['searchContext'] = $search_context;
        }

        foreach ((array) $extra as $key => $value) {
            $key = sanitize_key((string) $key);
            if ($key === '' || array_key_exists($key, $payload)) {
                continue;
            }
            $payload[$key] = $value;
        }

        /**
         * Allows trusted add-ons to append safe public metadata to chat responses.
         *
         * Add-ons must not remove core fields or expose secrets/internal settings.
         *
         * @param array  $payload Chat response payload.
         * @param string $message Shopper-facing answer.
         * @param array  $products Product payload.
         * @param string $intent Detected response intent.
         */
        return (array) apply_filters('geekybot_chat_response_payload', $payload, $message, $products, $intent);
    }

    private function public_knowledge_sources($knowledge_matches) {
        $sources = array();
        foreach ((array) $knowledge_matches as $page) {
            $sources[] = array(
                'id' => !empty($page['id']) ? absint($page['id']) : 0,
                'title' => isset($page['title']) ? wp_strip_all_tags($page['title']) : '',
                'url' => isset($page['url']) ? esc_url_raw($page['url']) : '',
            );
        }
        return $sources;
    }

    private function clean_message($message) {
        $message = wp_strip_all_tags((string) $message);
        $message = preg_replace('/\s+/', ' ', $message);
        $message = trim($message);
        return function_exists('mb_substr') ? mb_substr($message, 0, 1000) : substr($message, 0, 1000);
    }

    private function ensure_session($session_key) {
        global $wpdb;

        if (Settings::get('chat_history_enabled', 'yes') !== 'yes') {
            return 0;
        }
        if (!is_user_logged_in() && Settings::get('allow_guest_sessions', 'yes') !== 'yes') {
            return 0;
        }

        $table = $wpdb->prefix . 'geekybot_sessions';
        $session_key = $this->valid_session_key($session_key) ? $session_key : wp_generate_uuid4();
        $now = current_time('mysql');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed plugin table; session key is prepared and the live session lookup must not be stale.
        $session_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE session_key = %s", $session_key));
        if ($session_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- The chat service owns this custom session table and must update it immediately.
            $wpdb->update($table, array('updated_at' => $now), array('id' => absint($session_id)), array('%s'), array('%d'));
            return absint($session_id);
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The chat service owns this custom session table.
        $wpdb->insert(
            $table,
            array(
                'session_key' => $session_key,
                'user_id' => get_current_user_id(),
                'customer_email' => '',
                'ip_hash' => $ip ? hash_hmac('sha256', $ip, wp_salt('auth')) : '',
                'user_agent' => function_exists('mb_substr') ? mb_substr($ua, 0, 255) : substr($ua, 0, 255),
                'created_at' => $now,
                'updated_at' => $now,
                'ended_at' => null,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        return absint($wpdb->insert_id);
    }

    private function save_message($session_id, $direction, $message, $payload) {
        if (!$session_id || Settings::get('chat_history_enabled', 'yes') !== 'yes') {
            return;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The chat service owns this custom message table.
        $wpdb->insert(
            $wpdb->prefix . 'geekybot_messages',
            array(
                'session_id' => absint($session_id),
                'direction' => sanitize_key($direction),
                'message' => wp_kses_post((string) $message),
                'payload' => $payload ? wp_json_encode($payload) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    private function log_unanswered($session_id, $question, $reason, $context = array()) {
        if (!$session_id || Settings::get('chat_history_enabled', 'yes') !== 'yes') {
            return;
        }

        $context = $this->sanitize_unanswered_context($context);

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- The chat service owns this custom unanswered-question table.
        $wpdb->insert(
            $wpdb->prefix . 'geekybot_unanswered',
            array(
                'session_id' => absint($session_id),
                'question' => sanitize_textarea_field($question),
                'reason' => sanitize_key($reason),
                'context' => !empty($context) ? wp_json_encode($context) : null,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }

    private function sanitize_unanswered_context($context) {
        if (!is_array($context)) {
            return array();
        }

        $clean = array();
        $integer_keys = array('product_id');
        $text_keys = array('product_name', 'policy_type', 'policy_label', 'requested_label', 'note', 'price_text', 'constraint_text');

        foreach ($integer_keys as $key) {
            if (!empty($context[$key])) {
                $clean[$key] = absint($context[$key]);
            }
        }
        foreach ($text_keys as $key) {
            if (!isset($context[$key]) || $context[$key] === '') {
                continue;
            }
            $value = sanitize_text_field((string) $context[$key]);
            $clean[$key] = function_exists('mb_substr') ? mb_substr($value, 0, 190) : substr($value, 0, 190);
        }

        return $clean;
    }

    private function valid_session_key($session_key) {
        return is_string($session_key) && preg_match('/^[a-f0-9\-]{32,64}$/', $session_key);
    }

    private function session_key_from_id($session_id, $fallback) {
        if ($this->valid_session_key($fallback)) {
            return $fallback;
        }

        if (!$session_id) {
            return '';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'geekybot_sessions';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Fixed plugin table; ID is prepared and the live session lookup must not be stale.
        $key = $wpdb->get_var($wpdb->prepare("SELECT session_key FROM {$table} WHERE id = %d", absint($session_id)));
        return $key ? $key : '';
    }
}

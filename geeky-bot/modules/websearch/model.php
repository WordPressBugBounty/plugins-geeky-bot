<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

class GEEKYBOTwebsearchModel {

    function storeCustomPostType($data) {
        if (empty($data))
            return 1;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('posttypes');
        $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
        $data = $this->stripslashesFull($data);// remove slashes with quotes.
        if (!$row->bind($data)) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (!$row->store()) {
            return GEEKYBOT_SAVE_ERROR;
        }
        if (isset($data['id']) && $data['id'] == '') {
            if ($data['status'] == 1) {
                $this->geekybotEnableWebSearchByPostType($data['post_type']);
            }
        }
        return GEEKYBOT_SAVED;
    }

    function getWebSearchbyId($id) {
        if (!is_numeric($id))
            return false;

        do_action('geekybot_custom_listing_query');
        $query = "SELECT post_type.*  ".geekybot::$_addon_query['select']." FROM `" . geekybot::$_db->prefix . "geekybot_post_types` AS post_type ".geekybot::$_addon_query['join']." WHERE post_type.id = " . esc_sql($id);
        geekybot::$_data[0] = geekybotdb::GEEKYBOT_get_row($query);
        do_action('reset_geekybot_aadon_query');
        return;
    }

    function stripslashesFull($input){// testing this function/.
        if (is_array($input)) {
            $input = array_map(array($this,'stripslashesFull'), $input);
        } elseif (is_object($input)) {
            $vars = get_object_vars($input);
            foreach ($vars as $k=>$v) {
                $input->{$k} = stripslashesFull($v);
            }
        } else {
            $input = geekybotphplib::GEEKYBOT_stripslashes($input);
        }
        return $input;
    }

    function getAllWebSearch() {
        $websearchtitle = geekybot::$_search['websearch']['websearchtitle'];
        $inquery = '';
        if ($websearchtitle) {
            $inquery .= " WHERE  websearch.post_type LIKE '%" . esc_sql($websearchtitle) . "%' ";
            $inquery .= " OR websearch.post_label LIKE '%" . esc_sql($websearchtitle) . "%' ";
            $inquery .= " OR websearch.plugin_name LIKE '%" . esc_sql($websearchtitle) . "%' ";
        }
        geekybot::$_data['filter']['websearchtitle'] = $websearchtitle;
        // Pagination
        $query = "SELECT COUNT(websearch.id)
            FROM `" . geekybot::$_db->prefix . "geekybot_post_types` AS websearch";
        $query .= $inquery;
        $total = geekybotdb::GEEKYBOT_get_var($query);
        geekybot::$_data['total'] = $total;
        geekybot::$_data[1] = GEEKYBOTpagination::GEEKYBOT_getPagination($total);
        // Data
        $query = "SELECT websearch.* FROM `" . geekybot::$_db->prefix . "geekybot_post_types` AS websearch";
        $query .= $inquery;
        $query .= " ORDER BY websearch.status DESC, websearch.post_type ASC LIMIT " . GEEKYBOTpagination::$_offset . ", " . GEEKYBOTpagination::$_limit;
        geekybot::$_data[0] = geekybotdb::GEEKYBOT_get_results($query);
        return;
    }

    function changeStatus($status, $id) {
        // 0 -> disable
        // 1 -> active
        if (!is_numeric($status) || !is_numeric($id))
            return false;
        $row = GEEKYBOTincluder::GEEKYBOT_getTable('posttypes');
        if (!$row->update(array('id' => $id, 'status' => $status))) {
            return GEEKYBOT_SAVE_ERROR;
        } else {
            $query = "SELECT `post_type` FROM `" . geekybot::$_db->prefix . "geekybot_post_types` WHERE `id` = ".esc_sql($id);
            $post_type = geekybotdb::GEEKYBOT_get_var($query);
            if ($status == 1) {
                $this->geekybotEnableWebSearchByPostType($post_type);
            } elseif ($status == 0) {
                $this->geekybotDisableWebSearchByPostType($post_type);
            }
            return GEEKYBOT_STATUS_CHANGED;
        }
    }

    function geekybotEnableWebSearchByPostType($post_type) {
        // Get the custom post types
        $max_batch_size = 1000; // Maximum number of records to process at once
        $threshold = 10000; // Threshold to determine small vs. large datasets
        $max_batch_size_in_bytes = 5 * 1024 * 1024; // 5 MB per batch
        $batch_data = []; // Store data for the current batch
        $offset = 0; // Start from the first batch
        // Get total number of posts for the specified post type
        $query = "SELECT COUNT(ID) FROM `" . geekybot::$_db->prefix . "posts` WHERE post_type = '".esc_sql($post_type)."' AND post_status = 'publish'";
        $total_posts = geekybotdb::GEEKYBOT_get_var($query);
        // Calculate an appropriate limit based on the total number of posts
        if ($total_posts <= $threshold) {
            $limit = min($total_posts, $max_batch_size); // Higher limit for small datasets
        } else {
            $limit = min($max_batch_size, ceil($total_posts / 100)); // Lower limit for large datasets
        }
        do {
            // Modify the query arguments
            $args = array(
                'post_type'      => $post_type, // Use the filtered post types
                'post_status'    => 'publish',
                'posts_per_page' => $limit, // Limit number of posts
                'offset'         => $offset,
                'orderby'        => 'date', // Order by date
                'order'          => 'DESC', // Order by descending
            );

            $posts = get_posts( $args );
            if (empty($posts)) {
                break; // Exit the loop if no more posts are found
            }
            foreach($posts as $post){
                $post_text = $post->post_title.' ';
                $post_text .= $post->post_content.' ';
                // Calculate the size of current row in bytes
                $row_size = strlen($post_text);

                // If adding this row exceeds the batch size, insert current batch
                if ($row_size > $max_batch_size_in_bytes && !empty($batch_data)) {
                    // Insert the current batch
                    $insert_query = $this->geekybotPostTypeBuildQuery($batch_data);
                    geekybot::$_db->query($insert_query);
                    // Reset for the next batch
                    $batch_data = [];
                }
                // Add the current row to the batch
                $batch_data[] = $post->ID;
            }
            // Insert any remaining data in the last batch
            if (!empty($batch_data)) {
                $insert_query = $this->geekybotPostTypeBuildQuery($batch_data);
                geekybot::$_db->query($insert_query);
                $batch_data = [];
            }
            // Clear cache and free memory
            wp_cache_flush();
            gc_collect_cycles();
            $offset += $limit; // Move to the next batch
        } while ($offset < $total_posts);
        return 1;
    }

    function geekybotDisableWebSearchByPostType($post_type) {
        $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_posts` WHERE `post_type` = '".$post_type."'";
        geekybot::$_db->query($query);
    }

    function geekybotEnableDisableNewPostTypes($status = 0, $ajaxCall = 1) {
        if ($ajaxCall == 1) {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'post-types-status') ) {
                die( 'Security check Failed' );
            }
            $status = GEEKYBOTrequest::GEEKYBOT_getVar('status');
        }
        // change status in the config table
        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_config` SET `configvalue` = '".esc_sql($status)."' WHERE `configname`= 'is_new_post_type_enable'";
        if (false === geekybot::$_db->query($query)) {
            return 0;
        } else {
            return 1;
        }
    }
    
    function geekybotSynchronizeWebSearchData(){
        $this->geekybotDisableWebSearch(0);
        $data = $this->geekybotEnableWebSearch(0);
        if ($data == 1) {
            $result = GEEKYBOT_DATA_SYNCHRONIZE;
        } else {
            $result = GEEKYBOT_DATA_SYNCHRONIZE_ERROR;
        }
        $msg = GEEKYBOTMessages::GEEKYBOT_getMessage($result, 'websearch');
        GEEKYBOTMessages::GEEKYBOT_setLayoutMessage($msg['message'], $msg['status'],$this->getMessagekey());
        $url = admin_url("admin.php?page=geekybot_websearch&geekybotlt=websearch");
        wp_redirect($url);
        die();
    }

    function geekybotSynchronizePostTypeData($post_type) {
        // Count posts of the given post type
        $query = "SELECT COUNT(ID) FROM `" . geekybot::$_db->prefix . "posts` WHERE post_type = '".esc_sql($post_type)."' AND post_status = 'publish'";
        $total_posts = geekybotdb::GEEKYBOT_get_var($query);
        // Enable AI Web Search if posts are below threshold; otherwise, set sync flag
        if ($total_posts < 1000) {
            $this->geekybotEnableWebSearchByPostType($post_type);
        } else {
            update_option('geekybot_synchronize_available', 1);
        }
    }

    function geekybotSynchronizePostTypes() {
        $args = array(
            'public'             => true, // Post types available on the front-end
            'publicly_queryable' => true,  // Must be queryable via URLs
        );
        // Get all the custom post types
        $current_post_types = get_post_types($args, 'names');
        // Get all stored post types
        $query = "SELECT post_type  FROM `" . geekybot::$_db->prefix . "geekybot_post_types`";
        $stored_post_types = geekybotdb::GEEKYBOT_get_results($query);
        // Transform stored_post_types array into associative format (like current_post_types)
        $stored_post_types_transformed = array_column($stored_post_types, 'post_type', 'post_type');
        $new_post_types = array_diff_key(array_flip($current_post_types), $stored_post_types_transformed);
        // If there are related post types, update their status and remove data
        if (!empty($new_post_types)) {
            $post_type_status = geekybot::$_configuration['is_new_post_type_enable'];
            foreach ($new_post_types as $post_type) {
                // create a new record
                $post_type_object = get_post_type_object($post_type);
                $label = $post_type_object ? $post_type_object->labels->singular_name : $post_type;
                $data = [
                    'post_type'  => $post_type,
                    'post_label' => $label,
                    'status'     => $post_type_status,
                ];
                $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                $data = $this->stripslashesFull($data);// remove slashes with quotes.
                // Insert the record into the custom table
                $row = GEEKYBOTincluder::GEEKYBOT_getTable('posttypes');
                $row->bind($data);
                $row->store();
                if(in_array('customlistingstyle', geekybot::$_active_addons)){
                    // load the default listing style for this post type if available
                    apply_filters('geekybot_load_custom_listing_style_template', $post_type);
                }
            }
        }
        $extra_post_types = array_diff_key($stored_post_types_transformed, $current_post_types);
        // If there are related post types, update their status and remove data
        if (!empty($extra_post_types)) {
            foreach ($extra_post_types as $post_type) {
                //delete post type
                $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_post_types` WHERE `post_type` = '".$post_type."'";
                geekybot::$_db->query($query);
                if(in_array('customlistingstyle', geekybot::$_active_addons)){
                    // delete post type style
                    apply_filters('geekybot_delete_custom_listing_style', $post_type);
                }
                if(in_array('customlistingtext', geekybot::$_active_addons)){
                    // delete post type text
                    apply_filters('geekybot_delete_custom_listing_text', $post_type);
                }
            }
        }
    }

    function geekybotGetActivePostTypes() {
        $query = "SELECT post_type FROM `" . geekybot::$_db->prefix . "geekybot_post_types` WHERE status = 1";
        $post_types = geekybotdb::GEEKYBOT_get_results($query);
        $available_types = [];
        foreach ($post_types as $post_type) {
            $available_types[$post_type->post_type] = $post_type->post_type;
        }   
        return $available_types;
    }

    function geekybotEnsurePostTypeStatus($post_type){
        // Query to get the status of the given post type from the custom table.
        $query = "SELECT `status` FROM `" . geekybot::$_db->prefix . "geekybot_post_types` WHERE `post_type` = '".esc_sql($post_type)."'";
        $status = geekybotdb::GEEKYBOT_get_var($query);
        // If no status found, handle the new post type.
        if ($status == '') {
            $post_type_object = get_post_type_object($post_type);
            // Ensure the post type is public and queryable before proceeding.
            if ($post_type_object && $post_type_object->public && $post_type_object->publicly_queryable) {
                // Set status from configuration and prepare label.
                $status = geekybot::$_configuration['is_new_post_type_enable'];
                $label = $post_type_object->labels->singular_name ?: $post_type;
                // Prepare sanitized data for insertion.
                $data = [
                    'post_type'  => $post_type,
                    'post_label' => $label,
                    'status'     => $status,
                ];
                // Sanitize and clean data
                $data = geekybot::GEEKYBOT_sanitizeData($data);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                $data = $this->stripslashesFull($data);// remove slashes with quotes.
                // Insert the new post type record and handle errors.
                $row = GEEKYBOTincluder::GEEKYBOT_getTable('posttypes');
                $row->bind($data);
                $row->store();
                if(in_array('customlistingstyle', geekybot::$_active_addons)){
                    // load the default listing style for this post type if available
                    apply_filters('geekybot_load_custom_listing_style_template', $post_type);
                }
            }
        }
        return $status;
    }

    function getArticlesButton($msg, $type) {
        // get ids of all the matching post 
        $logdata = "\n\ngetArticlesButton";
        $inquery = '';
        if (class_exists('WooCommerce')) {
            $wc_search_status = geekybotdb::GEEKYBOT_get_var("SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_post_types` WHERE `post_type` = 'product'");
            if (!empty($wc_search_status)) {
                $wc_story_status = geekybotdb::GEEKYBOT_get_var("SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_stories` WHERE `story_type` = 2");
                if (!empty($wc_story_status)) {
                    $inquery .= " AND labels.post_type != 'product'";
                }
            }
        }

        // Escape the input message for SQL
        $msg = trim($msg);

        // Break the message into words
        $words = array_filter(explode(' ', $msg));
        $wordCount = count($words);

        // Construct the base query with full-text search
        $query = "(SELECT DISTINCT posts.id, posts.title, posts.post_type, labels.post_label, '0' AS custom_score, '999' AS score FROM `" . geekybot::$_db->prefix . "geekybot_posts` AS posts
            LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_post_types` AS labels ON posts.post_type = labels.post_type
            WHERE posts.title LIKE '%".esc_sql($msg)."%' AND posts.status = 'publish' AND labels.status = 1";
        $query .= $inquery;
        $query .= " ORDER BY score DESC LIMIT 100) ";

        $query .= ' UNION ';

        $query .= "(SELECT DISTINCT posts.id, posts.title, posts.post_type, labels.post_label, '0' AS custom_score, '888' AS score FROM `" . geekybot::$_db->prefix . "geekybot_posts` AS posts
            LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_post_types` AS labels ON posts.post_type = labels.post_type
            WHERE posts.taxonomy LIKE '%".esc_sql($msg)."%' AND posts.status = 'publish' AND labels.status = 1";
        $query .= $inquery;
        $query .= " ORDER BY score DESC LIMIT 100) ";

        $query .= ' UNION ';

        if ($wordCount > 1) {
            $query .= "(SELECT DISTINCT posts.id, posts.title, posts.post_type, labels.post_label, '0' AS custom_score, '777' AS score FROM `" . geekybot::$_db->prefix . "geekybot_posts` AS posts
                LEFT JOIN `" . geekybot::$_db->prefix . "geekybot_post_types` AS labels ON posts.post_type = labels.post_type
                WHERE posts.content LIKE '%".esc_sql($msg)."%' AND posts.status = 'publish' AND labels.status = 1";
            $query .= $inquery;
            $query .= " ORDER BY score DESC LIMIT 100) ";

            $query .= ' UNION ';
        }

        $query .= '(SELECT DISTINCT posts.id, posts.title, posts.post_type, labels.post_label, "0" AS custom_score, MATCH (posts.post_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_posts` AS posts
            LEFT JOIN `' . geekybot::$_db->prefix . 'geekybot_post_types` AS labels ON posts.post_type = labels.post_type
            WHERE MATCH (posts.post_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND posts.status = "publish" AND labels.status = 1';

        // Append additional conditions
        $query .= $inquery;

        // Order by score as a fallback
        $query .= " ORDER BY score DESC LIMIT 100) ";
        $query .= '; ';

        $posts = geekybotdb::GEEKYBOT_get_results($query);

        $highest_score = 0;

        // Process posts in PHP for prioritization
        foreach ($posts as &$post) {
            $custom_score = 0;
            // Exact match in title
            if($post->score == 999) {
                $custom_score += ($wordCount * 10) + 6; // Higher base custom_score for exact title matches
                $post->score = 0;
            }
            // Exact match in taxonomy
            elseif($post->score == 888) {
                $custom_score += ($wordCount * 10) + 4; // Moderate weight for taxonomy matches
                $post->score = 0;
            } 
            // Exact match in content
            elseif($post->score == 777) {
                $custom_score += ($wordCount * 10) + 2; // Moderate weight for content matches
                $post->score = 0;
            } 
            // Partial word combination matching in title
            else {
                for ($i = 0; $i < $wordCount - 1; $i++) {
                    $wordCombination = $words[$i] . ' ' . ($words[$i + 1] ?? '');
                    if (stripos($post->title, $wordCombination) !== false) {
                        $custom_score += 10;
                        // $post->score = 0;
                    }
                }
            }

            // Store the post with its calculated custom_score
            $post->custom_score = $custom_score;
            // track highest score
            if ($post->score > $highest_score) {
                $highest_score = $post->score;
            }
        }
        unset($post);

        // Sort posts by custom_score and score
        usort($posts, function ($a, $b) {
            if ($a->custom_score === $b->custom_score) {
                return $b->score <=> $a->score;
            }
            return $b->custom_score <=> $a->custom_score;
        });

        $posts = $this->applyThresholdOnWebSearch($posts, $highest_score);
        
        $post_types = array();
        foreach ($posts as $post) {
            if (!isset($post_types[$post->post_type]) || !in_array($post->id, $post_types[$post->post_type]['post_ids'])) { 
                $post_types[$post->post_type]['label'] = $post->post_label;
                $post_types[$post->post_type]['post_ids'][] = $post->id;
            }
        }
        // Return empty if no post type found
        if (empty($post_types)) {
            return '';
        }
        $btnHtml = '';
        $html = '';
        // Display results directly instead of buttons when data for only a single post type is found.
        if ((count($post_types) > 1) || (count($post_types) == 1 && $type != 4)) {

            if ($type == 2 || $type == 4) {
                $html .= '<div class="geekybot_article_message_wrp">';
                if ($type == 2) {
                    $html .= __('Additionally, here are other top matches for your search.', 'geeky-bot');
                } else {
                    $html .= __('Here are the best matches for your search.', 'geeky-bot');
                }
                $html .= '</div>';
            }
            foreach ($post_types as $index => $data) {
                $post_ids_count = count($data['post_ids']);
                if ($post_ids_count > 0) {
                    // Encrypt the data
                    $encrypted_post_ids = openssl_encrypt(json_encode($data['post_ids']), 'AES-128-ECB', 'geekybot_websearch');
                    $message = geekybotphplib::GEEKYBOT_htmlentities(geekybotphplib::GEEKYBOT_htmlspecialchars(geekybotphplib::GEEKYBOT_addslashes($msg), ENT_QUOTES, 'UTF-8'));
                    $btnHtml .= "
                    <div class='geekybot_article_bnt_wrp'>
                        <span onclick=\"showArticlesList('".$encrypted_post_ids."','".$message."','".$index."','".$data['label']."','".$post_ids_count."', 1);\" class='geekybot_article_bnt button'>" . $data['label'].' ('. $post_ids_count .')' ."<img src='". esc_url(GEEKYBOT_PLUGIN_URL) ."includes/images/chat-img/btn-arrow.png' /></span>
                    </div>";
                }
            }
            $html .= $btnHtml;
        } elseif (count($post_types) == 1) {
            // Get the first index of the main array
            $post_type = key($post_types);
            // Access the data of the first index
            $post_type_data = $post_types[$post_type];
            // Encrypt the data
            $encrypted_post_ids = openssl_encrypt(json_encode($post_type_data['post_ids']), 'AES-128-ECB', 'geekybot_websearch');
            $message = geekybotphplib::GEEKYBOT_htmlentities(geekybotphplib::GEEKYBOT_htmlspecialchars(geekybotphplib::GEEKYBOT_addslashes($msg), ENT_QUOTES, 'UTF-8'));
            $html = $this->showArticlesList($encrypted_post_ids,$message, $post_type, $post_type_data['label'], count($post_type_data['post_ids']), 1);
            $html = html_entity_decode($html);
        }
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        return $html;
    }

    function showArticlesList($post_ids = '', $msg = '', $type = '', $label = '', $total_posts = '', $current_page = '') {
        if ($type == '') {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'articles-list') ) {
                die( 'Security check Failed' ); 
            }
            $post_ids = GEEKYBOTrequest::GEEKYBOT_getVar('post_ids');
            $msg = GEEKYBOTrequest::GEEKYBOT_getVar('msg');
            $type = GEEKYBOTrequest::GEEKYBOT_getVar('type');
            $label = GEEKYBOTrequest::GEEKYBOT_getVar('label');
            $total_posts = GEEKYBOTrequest::GEEKYBOT_getVar('totalPosts');
            $current_page = GEEKYBOTrequest::GEEKYBOT_getVar('currentPage');
            $save_history = 1;
        }
        $msg = htmlspecialchars_decode($this->stripslashesFull($msg));
        $postsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        $offset = ($current_page - 1) * $postsPerPage;

        $decrypted_post_ids = openssl_decrypt($post_ids, 'AES-128-ECB', 'geekybot_websearch');
        $post_ids_array = json_decode($decrypted_post_ids, true);
        $escaped_post_ids = array_map('intval', $post_ids_array); // Convert to integers for safety
        $in_clause = "'" . implode("', '", $escaped_post_ids) . "'"; // Join with commas

        $query = 'SELECT `id`,`post_id`,`post_type`,`title`,`content` FROM `' . geekybot::$_db->prefix . 'geekybot_posts` WHERE id IN ('.$in_clause.') ORDER BY FIELD ( `id`, '.$in_clause.')';
        // $query .= " ORDER BY score DESC ";
        $query .= " LIMIT $postsPerPage OFFSET $offset";
        // Get paginated posts
        $posts = geekybotdb::GEEKYBOT_get_results($query);

        $text = '';
        if($posts){
            if(in_array('customlistingstyle', geekybot::$_active_addons)){
                $geekybot_custom_listing = apply_filters('geekybot_custom_listing_style', $type);
            }
            if ((empty($geekybot_custom_listing) || !in_array($geekybot_custom_listing->template_id, [1, 2, 3])) && in_array('customlistingtext', geekybot::$_active_addons)) {
                $geekybot_custom_listing = apply_filters('geekybot_custom_listing_text', $type);
            }
            $currentPostsCount = count($posts);
            $to_post = $offset + $currentPostsCount;
            $from_post = $offset + 1;
            $text = "<div class='geekybot_wc_post_heading geekybot_wc_post_title'>".__('Here are some', 'geeky-bot')." ".$label."."." <span class='geekybot_wc_post_heading_nums'>".__('Showing', 'geeky-bot')." ".$from_post." - ".$to_post." ".__('of', 'geeky-bot')." ".$total_posts."</span></div>";
            foreach ($posts as $post) {
                if (!empty($geekybot_custom_listing->template_id)) {
                    if (in_array($geekybot_custom_listing->template_id, [1, 2, 3])) {
                        // Check if template_id is 1, 2, or 3
                        $text .= apply_filters('geekybot_custom_listing_style_html', $geekybot_custom_listing, $post);
                    } elseif (in_array($geekybot_custom_listing->template_id, [4, 5, 6])) {
                        // Check if template_id is 3, 4, 5, or 6
                        $text .= apply_filters('geekybot_custom_listing_text_html', $geekybot_custom_listing, $post, $msg);
                    }
                } elseif(in_array('customlistingstyle', geekybot::$_active_addons) || in_array('customlistingtext', geekybot::$_active_addons)) {
                    $all_meta_keys = $this->geekybotGetAllMetaKeys($type);
                    $defaultFieldForLogo = $this->geekybotGetDefaultFieldForLogo($type, $all_meta_keys);
                    if (!empty($defaultFieldForLogo)) {
                        $image_url = $this->geekybotGetLogoForListing($post->post_id, $defaultFieldForLogo);
                    }
                    $meta_keys_for_listing = $this->geekybotGetMetaKeysForListing($type);
                    $permalink = get_permalink( $post->post_id );
                    $text .= '
                    <div class="geekybot_wc_article_wrp">
                        <div class="geekybot_wc_article_header geekybot_wc_article_title">';
                            if ( !empty($image_url) ) {
                            $text .= '<span class="geekybot-websearch-image-wrp"><img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( get_the_title() ) . '"></span>';
                            }
                            $text .= '
                            <a target="_blank" href="' . esc_url( $permalink ) . '">'.$post->title.'</a>';
                            foreach ($meta_keys_for_listing as $listing_key => $listing_value) {
                                $meta_val = get_post_meta($post->post_id, $listing_value, true);
                                if (is_array($meta_val)) {
                                    $meta_value = implode(', ', $meta_val);
                                } else {
                                    $meta_value = $meta_val;
                                }

                                $text .="
                                <div class='geekybot-websearch-field-wrp'>
                                    <span class='geekybot-websearch-field-title'>
                                        <b>
                                        ".esc_html(geekybot::GEEKYBOT_getVarValue(ucwords(geekybotphplib::GEEKYBOT_str_replace('_', ' ', $listing_value)))).":
                                        </b>
                                    </span>
                                    <span class='geekybot-websearch-field-value'>
                                        ". $meta_value ."
                                    </span>
                                </div>
                                ";
                            }
                            $text .= '
                        </div>
                    </div>';
                } else {
                    $permalink = get_permalink( $post->post_id );
                    $featured_image_url = get_the_post_thumbnail_url( $post->post_id, 'full' );
                    $text .= '
                    <div class="geekybot_wc_article_wrp">
                        <div class="geekybot_wc_article_header geekybot_wc_article_title">';
                            if ( $featured_image_url ) {
                            $text .= '<span class="geekybot-websearch-image-wrp"><img src="' . esc_url( $featured_image_url ) . '" alt="' . esc_attr( get_the_title() ) . '"></span>';
                            }
                            $text .= '
                            <a target="_blank" href="' . esc_url( $permalink ) . '">'.$post->title.'</a>
                        </div>
                    </div>';
                }
            }
            $text .= "<div class='geekybot_wc_post_load_more_wrp'>";
                if ($total_posts > ($current_page * $postsPerPage)) {
                    $next_page = $current_page + 1;
                    $message = geekybotphplib::GEEKYBOT_htmlspecialchars(geekybotphplib::GEEKYBOT_addslashes($msg), ENT_QUOTES, 'UTF-8');
                    $text .= "<span class='geekybot_wc_post_load_more' onclick=\"showArticlesList('".$post_ids."','".$message."','".$type."','".$label."','". $total_posts."','". $next_page."');\">".__('Show More', 'geeky-bot')."</span>";
                }
            $text .= "</div>";
        }
        $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        // save bot response to the session and chat history
        if (!empty($save_history)) {
            geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'bot');
            GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($text, 'bot', 4, '', $type);
        }
        return $text;
        wp_die();
    }

    function applyThresholdOnWebSearch($posts, $highest_score) {
        if (empty($posts)) {
            return $posts; // Return early if no posts
        }

        $threshold = 30; // Percentage threshold
        $highest_custom_score = $posts[0]->custom_score ?? 0;

        // Calculate threshold values
        $custom_score_threshold_value = ($threshold / 100) * $highest_custom_score;
        $score_threshold_value = ($threshold / 100) * $highest_score;

        // Filter posts based on threshold and ensure uniqueness by post_id
        $unique_posts = [];

        foreach ($posts as $post) {
            // Skip posts below the threshold (except the first post)
            if (
                ($post->custom_score <= $custom_score_threshold_value && $post !== $posts[0]) &&
                ($post->score <= $score_threshold_value && $post !== $posts[0])
            ) {
                continue;
            }

            // Ensure uniqueness by post id, keeping the highest custom_score and then the highest score
            if (
                !isset($unique_posts[$post->id]) ||
                $post->custom_score > $unique_posts[$post->id]->custom_score ||
                ($post->custom_score === $unique_posts[$post->id]->custom_score && $post->score > $unique_posts[$post->id]->score)
            ) {
                $unique_posts[$post->id] = $post;
            }
        }

        // Log the results
        // $logdata = "\n\napplyThresholdOnWebSearch";
        // $logdata .= "\highest_custom_score: $highest_custom_score";
        // $logdata .= "\ncustom_score_threshold_value: $custom_score_threshold_value";
        // $logdata .= "\highest_score: $highest_score";
        // $logdata .= "\nscore_threshold_value: $score_threshold_value";
        // GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);

        return array_values($unique_posts);
    }

    function geekybotEnableWebSearch($ajaxCall = 1) {
        if ($ajaxCall == 1) {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'enable-post') ) {
                die( 'Security check Failed' );
            }
        }
        $status = $this->geekybotEnableDisableWebSearch(1);
        // Get all posts
        if ($status == 1) {
            update_option('geekybot_synchronize_available', 0);
            $this->geekybotSynchronizePostTypes();
            $available_post_types = $this->geekybotGetActivePostTypes();
            if (empty($available_post_types)) {
                return 1;
            }
            $max_batch_size = 1000; // Maximum number of records to process at once
            $threshold = 10000; // Threshold to determine small vs. large datasets
            $max_batch_size_in_bytes = 5 * 1024 * 1024; // 5 MB per batch
            $batch_data = []; // Store data for the current batch
            $offset = 0; // Start from the first batch
            // Get total number of posts for the specified post type
            $post_types_list = "'" . implode("', '", $available_post_types) . "'";
            $query = "SELECT COUNT(ID) FROM `" . geekybot::$_db->prefix . "posts` WHERE post_type IN ($post_types_list) AND post_status = 'publish'";
            $total_posts = geekybotdb::GEEKYBOT_get_var($query);
            // Calculate an appropriate limit based on the total number of posts
            if ($total_posts <= $threshold) {
                $limit = min($total_posts, $max_batch_size); // Higher limit for small datasets
            } else {
                $limit = min($max_batch_size, ceil($total_posts / 100)); // Lower limit for large datasets
            }
            do {
                // Modify the query arguments
                $args = array(
                    'post_type'      => $available_post_types, // Use the filtered post types
                    'post_status'    => 'publish',
                    'posts_per_page' => $limit, // Limit number of posts
                    'offset'         => $offset,
                    'orderby'        => 'date', // Order by date
                    'order'          => 'DESC', // Order by descending
                );

                $posts = get_posts( $args );
                if (empty($posts)) {
                    break; // Exit the loop if no more posts are found
                }
                foreach($posts as $post){
                    $post_text = $post->post_title.' ';
                    $post_text .= $post->post_content.' ';
                    // Calculate the size of current row in bytes
                    $row_size = strlen($post_text);

                    // If adding this row exceeds the batch size, insert current batch
                    if ($row_size > $max_batch_size_in_bytes && !empty($batch_data)) {
                        // Insert the current batch
                        $insert_query = $this->geekybotPostTypeBuildQuery($batch_data);
                        geekybot::$_db->query($insert_query);
                        // Reset for the next batch
                        $batch_data = [];
                    }
                    // Add the current row to the batch
                    $batch_data[] = $post->ID;
                }
                // Insert any remaining data in the last batch
                if (!empty($batch_data)) {
                    $insert_query = $this->geekybotPostTypeBuildQuery($batch_data);
                    geekybot::$_db->query($insert_query);
                    $batch_data = [];
                }
                // Clear cache and free memory
                wp_cache_flush();
                gc_collect_cycles();
                $offset += $limit; // Move to the next batch
            } while ($offset < $total_posts);
            return 1;
        }
        return $status;
    }

    function geekybotPostTypeBuildQuery($batch_data) {
        // Increase GROUP_CONCAT limit
        $query = "SET SESSION group_concat_max_len = 5000000";
        geekybot::$_db->query($query);
        // Increase CONCAT limit
        //$query = "SET GLOBAL max_allowed_packet = 15000000";
        //geekybot::$_db->query($query);
        // Convert the array into a comma-separated string
        $post_ids_str = implode(',', array_map('intval', $batch_data));

        $exclude_meta_keys = array(
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_thumbnail_id',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
            '_pingme',
            '_encloseme',
            '_wp_attached_file',
            '_wp_attachment_metadata',
            '_wp_attachment_image_alt',
            '_wp_page_template',
            '_menu_item',
            '_wpb_vc_js_status',
            '_elementor_data'
        );

        $exclude_meta_keys_str = "'" . implode("','", $exclude_meta_keys) . "'";
        return "
            INSERT INTO " . geekybot::$_db->prefix . "geekybot_posts (ID, title, taxonomy, content, post_text, post_id, post_type, status)
            SELECT 
                p.ID, 
                p.post_title, 
                CONCAT(
                    IFNULL(
                        (SELECT 
                            GROUP_CONCAT(t.name SEPARATOR ' ')
                        FROM " . geekybot::$_db->prefix . "term_relationships AS tr 
                        INNER JOIN " . geekybot::$_db->prefix . "term_taxonomy AS tt 
                            ON tr.term_taxonomy_id = tt.term_taxonomy_id
                        INNER JOIN " . geekybot::$_db->prefix . "terms AS t 
                            ON tt.term_id = t.term_id
                        WHERE tr.object_id = p.ID), 
                        ''
                    ),
                    ' '
                ) AS post_taxonomy,
                p.post_content, 
                CONCAT(
                    p.post_title, ' ', 
                    p.post_content, ' ',
                    IFNULL(
                        (SELECT 
                            GROUP_CONCAT(CONCAT(pm.meta_key, ' ', pm.meta_value) SEPARATOR ' ') 
                         FROM " . geekybot::$_db->prefix . "postmeta AS pm 
                         WHERE pm.post_id = p.ID AND pm.meta_key NOT IN ({$exclude_meta_keys_str})), 
                         ''
                    ),
                    ' ',
                    IFNULL(
                        (SELECT 
                            GROUP_CONCAT(t.name SEPARATOR ' ')
                         FROM " . geekybot::$_db->prefix . "term_relationships AS tr 
                         INNER JOIN " . geekybot::$_db->prefix . "term_taxonomy AS tt 
                            ON tr.term_taxonomy_id = tt.term_taxonomy_id
                         INNER JOIN " . geekybot::$_db->prefix . "terms AS t 
                            ON tt.term_id = t.term_id
                         WHERE tr.object_id = p.ID), 
                         ''
                    ),
                    ' '
                ) AS post_text,
                p.ID,
                p.post_type,
                p.post_status
            FROM " . geekybot::$_db->prefix . "posts AS p
            WHERE p.ID IN ({$post_ids_str})
            ORDER BY p.ID ASC
            ON DUPLICATE KEY UPDATE 
                title = VALUES(title),
                taxonomy = VALUES(taxonomy),
                content = VALUES(content),
                post_text = VALUES(post_text),
                post_type = VALUES(post_type),
                status = VALUES(status);";
    }

    function geekybotDisableWebSearch($ajaxCall = 1) {
        if ($ajaxCall == 1) {
            $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
            if (! wp_verify_nonce( $nonce, 'disable-post') ) {
                die( 'Security check Failed' );
            }
        }
        $status = $this->geekybotEnableDisableWebSearch(0);
        // clean the post table
        if ($status == 1) {
            $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_posts`";
            if (false === geekybot::$_db->query($query)) {
                return 0;
            } else {
                return 1;
            }
        }
        return 0;
    }

    function geekybotEnableDisableWebSearch($status) {
        // change status in the config table
        $query = "UPDATE `" . geekybot::$_db->prefix . "geekybot_config` SET `configvalue` = '".esc_sql($status)."' WHERE `configname`= 'is_posts_enable'";
        if (false === geekybot::$_db->query($query)) {
            return 0;
        } else {
            return 1;
        }
    }

    function setSearchVariableForWebSearch($geekybot_search_array,$search_userfields){
        geekybot::$_search['websearch']['websearchtitle'] = isset($geekybot_search_array['websearchtitle']) ? $geekybot_search_array['websearchtitle'] : '';
    }

    function getAdminWebSearchSearchData($search_userfields){
        $geekybot_search_array = array();
        $geekybot_search_array['websearchtitle'] = GEEKYBOTrequest::GEEKYBOT_getVar('websearchtitle');
        return $geekybot_search_array;
    }

    function getCookiesSavedSearchDataWebSearch($search_userfields){
        $geekybot_search_array = array();
        $wpjp_search_cookie_data = '';
        if(isset($_COOKIE['geekybot_chatbot_search_data'])){
            $wpjp_search_cookie_data = $_COOKIE['geekybot_chatbot_search_data'];
            $wpjp_search_cookie_data = json_decode( geekybotphplib::GEEKYBOT_safe_decoding($wpjp_search_cookie_data) , true );
        }
        if($wpjp_search_cookie_data != '' && isset($wpjp_search_cookie_data['search_from_websearch']) && $wpjp_search_cookie_data['search_from_websearch'] == 1){
            $geekybot_search_array['websearchtitle'] = $wpjp_search_cookie_data['websearchtitle'];
        }
        return $geekybot_search_array;
    }

    function getMessagekey(){
        $key = 'websearch';if(is_admin()){$key = 'admin_'.$key;}return $key;
    }
    // addon query

    function storeCustomListing($data) {
        if (empty($data))
            return 1;

        $message = '';
        if(in_array('customlistingstyle', geekybot::$_active_addons)){
            $message = apply_filters('geekybot_store_custom_listing_style', $data);
        }
        if(in_array('customlistingtext', geekybot::$_active_addons)){
            $message = apply_filters('geekybot_store_custom_listing_text', $data);
        }
        return $message;
    }

    function geekybotGetDefaultSelectedTemplate($allFields, $logoField) {
        if ($allFields == 0) {
            $index = 6;
        } elseif(in_array('customlistingstyle', geekybot::$_active_addons) && $logoField == '') {
            $index = 3;
        } elseif(!in_array('customlistingstyle', geekybot::$_active_addons) && $logoField != '') {
            $index = 4;
        } elseif($logoField == '') {
            $index = 6;
        } else {
            $index = 1;
        }
        return $index;
    }

    function geekybotGetLogoForListing($post_id, $meta_key) {
        $image_url = '';
        // Get the attachment ID stored in post meta
        $image_meta = get_post_meta($post_id, $meta_key, true);
        if (is_numeric($image_meta) && $image_meta > 0) {
            // Case 1: If the image is stored as a single Attachment ID
            // Handle single attachment ID case (if the value is not array)
            $attachment_id = $image_meta;
            // Check if an attachment ID is found
            if ($attachment_id) {
                // Get the URL of the image
                $image_url = wp_get_attachment_image_url($attachment_id, 'full'); // 'full' is the image size
            }
        } elseif (filter_var($image_meta, FILTER_VALIDATE_URL)) {
            // Case 2: If the image URL is stored directly
            $image_url = $image_meta;
        } elseif (is_array($image_meta)) {
            // Case 3: If the post meta contains an array of Attachment IDs
            $attachment_id = $image_meta[0]; // get the first index of the array
            // if the first index of array found
            if ($attachment_id) {
                // Get the image URL for this attachment ID
                $image_url = wp_get_attachment_image_url($attachment_id, 'full');
            }
        } elseif (is_array($image_meta) && !empty($image_meta) && filter_var($image_meta[0], FILTER_VALIDATE_URL)) {
            // Case 4: If the post meta contains an array of Image URLs
            $attachment_id = $image_meta[0]; // get the first index of the array
            // if the first index of array found
            if ($attachment_id) {
                // Get the image URL for this attachment ID
                $image_url = $attachment_id;
            }
        }
        return $image_url;
    }

    function geekybotGetDefaultFieldForLogo($post_type, $meta_keys) {
        // List of common prefixes related to logos
        $logo_keywords = ['thumbnail', 'logo', 'image', 'header', 'brand', 'custom', 'icon', 'avatar', 'splash', 'cover'];

        // Loop through all meta keys to find a match for the logo
        foreach ($meta_keys as $key => $value) {
            // Check if the key contains any of the logo-related keywords
            foreach ($logo_keywords as $keyword) {
                if (strpos(strtolower($value->id), $keyword) !== false) {
                    // Return the first matching meta key
                    return $value->id;
                }
            }
        }
        return '';
    }
    
    function geekybotGetMetaKeysForListing($post_type) {
        // List of meta keys to exclude
        $exclude_meta_keys = array(
            '_edit_lock',
            '_edit_last',
            '_wp_old_slug',
            '_thumbnail_id',
            '_wp_trash_meta_status',
            '_wp_trash_meta_time',
            '_pingme',
            '_encloseme',
            '_wp_attached_file',
            '_wp_attachment_metadata',
            '_wp_attachment_image_alt',
            '_wp_page_template',
            '_menu_item',
            '_wpb_vc_js_status',
            '_elementor_data'
        );
        $all_meta_keys = [];
        // Fetch all post IDs for the given post type
        $post_ids = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => 100,  // Limit to 100 posts for performance
            'fields'         => 'ids',  // Only retrieve post IDs
        ]);

        if (empty($post_ids)) {
            return [];  // No posts found, return an empty array
        }

        // Fetch all meta keys and values for the given post IDs in a single query
        global $wpdb;
        $meta_data = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id IN (%s)",
            implode(',', $post_ids)
        ));
        // Initialize an array to store filtered meta keys
        $all_meta_keys = [];

        foreach ($meta_data as $meta) {
            // Skip excluded meta keys
            if (in_array($meta->meta_key, $exclude_meta_keys)) {
                continue;
            }
            // Skip keys with numeric values
            if (is_numeric($meta->meta_value)) {
                continue;
            }
            // Add to the list of meta keys
            $all_meta_keys[] = $meta->meta_key;
        }

        // Loop through post IDs to gather meta keys
        foreach ($post_ids as $post_id) {
            $meta_keys = array_keys(get_post_meta($post_id));
            $clean_meta_keys = array_diff($meta_keys, $exclude_meta_keys);
            // Merge unique meta keys
            $all_meta_keys = array_unique(array_merge($all_meta_keys, $clean_meta_keys));
            // 
            $taxonomies = get_object_taxonomies(get_post_type($post_id));
            // Merge unique meta keys
            $all_meta_keys = array_unique(array_merge($all_meta_keys, $taxonomies));
        }

        $logo_keywords = ['thumbnail', 'logo', 'image', 'header', 'brand', 'custom', 'icon', 'avatar', 'splash', 'cover'];
        // Loop through all meta keys to find a match for the logo
        foreach ($all_meta_keys as $key => $value) {
            // Check if the key contains any of the logo-related keywords
            foreach ($logo_keywords as $keyword) {
                if (strpos(strtolower($value), $keyword) !== false) {
                    // Return the first matching meta key
                    unset($all_meta_keys[$key]);
                }
            }
        }
        // Return the first 3 filtered meta keys
        return array_slice(array_values($all_meta_keys), 0, 3);
    }

    function geekybotGetAllMetaKeys($post_type) {
        $all_meta_keys = [];
        // Fetch all post IDs for the given post type
        $post_ids = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => 100,  // Limit to 100 posts for performance
            'fields'         => 'ids',  // Only retrieve post IDs
        ]);

        if (empty($post_ids)) {
            return [];  // No posts found, return an empty array
        }

        // Loop through post IDs to gather meta keys
        foreach ($post_ids as $post_id) {
            $meta_keys = array_keys(get_post_meta($post_id));
            // Merge unique meta keys
            $all_meta_keys = array_unique(array_merge($all_meta_keys, $meta_keys));
            // 
            $taxonomies = get_object_taxonomies(get_post_type($post_id));
            // Merge unique meta keys
            $all_meta_keys = array_unique(array_merge($all_meta_keys, $taxonomies));
        }

        // Format the meta keys for the combobox
        $formatted_keys = array_map(function ($key) {
            return (object) [
                'id'   => sanitize_text_field($key),
                'text' => esc_html__($key, 'geeky-bot'),
            ];
        }, $all_meta_keys);

        return $formatted_keys;
    }
}

?>

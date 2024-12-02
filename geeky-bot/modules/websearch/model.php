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
        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_post_types` WHERE id = " . esc_sql($id);
        geekybot::$_data[0] = geekybotdb::GEEKYBOT_get_row($query);
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
        $current_batch_size = 0; // Track batch size
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
                if ($current_batch_size + $row_size > $max_batch_size_in_bytes) {
                    // Insert the current batch
                    $insert_query = $this->geekybotPostTypeBuildQuery($batch_data);
                    geekybot::$_db->query($insert_query);
                    // Reset for the next batch
                    $batch_data = [];
                    $current_batch_size = 0;
                }
                // ---------------
                // Get all taxonomies associated with the post
                $taxonomies = get_object_taxonomies(get_post_type($post->ID));
                // Loop through each taxonomy to get the terms
                foreach ($taxonomies as $taxonomy) {
                    // Get terms for this taxonomy
                    $terms = get_the_terms($post->ID, $taxonomy);
                    if ( ! empty($terms) && ! is_wp_error($terms) ) {
                        foreach ( $terms as $term ) {
                            $post_text .= $term->name.' ';
                        }
                    }
                }
                // ---------------
                $skip_storing_process = 0;
                $meta_keys = array();
                if ($post->post_type == 'topic') {
                    $skip_storing_process = 1;
                    $bbp_forum_id = get_post_meta($post->ID, '_bbp_forum_id', true);
                    // check that the topic is assign to a forum
                    if (is_numeric($bbp_forum_id) && $bbp_forum_id != 0 && $bbp_forum_id != $post->ID) {
                        // if assign then get the forum data
                        $query = "SELECT * FROM `" . geekybot::$_db->prefix . "geekybot_posts` WHERE `post_id` = ".esc_sql($bbp_forum_id);
                        $bbp_forum = geekybotdb::GEEKYBOT_get_row($query);
                        // if forum data found then update forum
                        if ( isset($bbp_forum->post_text) && ($bbp_forum->post_type == 'forum') ) {
                            $p_id = $bbp_forum->id;
                            $p_title = $bbp_forum->title;
                            $p_content = $bbp_forum->content;
                            $p_post_type = $bbp_forum->post_type;
                            $p_post_id = $bbp_forum->post_id;
                            $p_status = $bbp_forum->status;
                            $post_text .= $bbp_forum->post_text.' ';

                            $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                            $post_text = $this->stripslashesFull($post_text);// remove slashes with quotes.
                            // Add the current row to the batch
                            $batch_data[] = '("'.esc_sql($p_id).'","'.esc_sql($p_title).'","'.esc_sql($p_content).'","'.esc_sql($post_text).'","'.esc_sql($p_post_id).'","'.esc_sql($p_post_type).'","'.esc_sql($p_status).'")';
                        }
                    }
                } elseif ($post->post_type == 'forum') {
                    $logdata = "\n forum";

                    $skip_storing_process = 1;
                    $p_id = $post->ID;
                    $p_title = $post->post_title;
                    $p_content = $post->post_content;
                    $p_post_type = $post->post_type;
                    $p_post_id = $post->ID;
                    $p_status = $post->post_status;
                    $bbp_forum_id = $post->ID;
                    $logdata .= "\n post_text: ".$post_text;

                    $store_topic = $this->geekybotCheckTopicStatusForBBpress();
                    $logdata .= "\n store_topic: ".$store_topic;
                    if ($store_topic == 1) {
                        $logdata .= "\n IN ";
                        // get all the topics related to this forum
                        $meta_key = '_bbp_forum_id';
                        $meta_value = $bbp_forum_id;
                        $args = array(
                            'post_type'  => 'topic',// Limit to post type 'topic'
                            'post_status'   => 'publish',// Only fetch published posts
                            'meta_key'   => $meta_key,
                            'meta_value' => $meta_value,
                            'posts_per_page' => -1, // Get all matching posts
                            'orderby' => 'ID', // Order by ID
                            'order' => 'ASC', // Order by assending
                        );
                        $topics = get_posts($args);
                        if (!empty($topics)) {
                            foreach ($topics as $topic) {
                                $post_text .= $topic->post_title . ' ' .$topic->post_content .' ';
                            }
                        }
                    }
                    $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    $post_text = $this->stripslashesFull($post_text);// remove slashes with quotes.
                    // Add the current row to the batch
                    $batch_data[] = '("'.esc_sql($p_id).'","'.esc_sql($p_title).'","'.esc_sql($p_content).'","'.esc_sql($post_text).'","'.esc_sql($p_post_id).'","'.esc_sql($p_post_type).'","'.esc_sql($p_status).'")';
                } else {
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
                    $post_meta = get_post_meta($post->ID); // Get all post meta
                    // Loop through the meta and filter useful data
                    if (!empty($meta_keys)) {
                        foreach ($meta_keys as $meta_key => $meta_value) {
                            // Filter out empty values
                            if (!empty($meta_value) && !in_array($meta_key, $exclude_meta_keys)) {
                                $post_text .= $meta_key.' ';
                                $post_text .= $meta_value[0].' ';
                            }
                        }
                    }
                }
                if ($skip_storing_process == 0) {
                    $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                    $post_text = $this->stripslashesFull($post_text);// remove slashes with quotes.
                    // Add the current row to the batch
                    $batch_data[] = '("'.esc_sql($post->ID).'","'.esc_sql($post->post_title).'","'.esc_sql($post->post_content).'","'.esc_sql($post_text).'","'.esc_sql($post->ID).'","'.esc_sql($post->post_type).'","'.esc_sql($post->post_status).'")';
                }
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
            }
        }
        $extra_post_types = array_diff_key($stored_post_types_transformed, $current_post_types);
        // If there are related post types, update their status and remove data
        if (!empty($extra_post_types)) {
            foreach ($extra_post_types as $post_type) {
                //delete post type
                $query = "DELETE FROM `".geekybot::$_db->prefix . "geekybot_post_types` WHERE `post_type` = '".$post_type."'";
                geekybot::$_db->query($query);
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
        if (empty($status)) {
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
            }
        }
        return $status;
    }

    function getArticlesButton($msg, $type) {
        // get ids of all the matching post 
        $logdata = "\n\ngetArticlesButton";
        $query = 'SELECT DISTINCT posts.id, posts.post_type, labels.post_label, MATCH (posts.post_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_posts` AS posts
            LEFT JOIN `' . geekybot::$_db->prefix . 'geekybot_post_types` AS labels ON posts.post_type = labels.post_type
            WHERE MATCH (posts.post_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND posts.status = "publish" AND labels.status = 1';
        $query .= " ORDER BY score DESC ";
        $logdata .="\n".$query;
        $posts = geekybotdb::GEEKYBOT_get_results($query);
        $posts = $this->applyThresholdOnWebSearch($posts);
        $highest_score = isset($posts[0]->score) ? $posts[0]->score : 0;  // Get the score of the first post
        // Initialize an empty array to store the counts
        $post_type_counts = array();
        // Iterate through the array to count post types
        foreach ($posts as $post) {
            if (isset($post_type_counts[$post->post_type])) {
                $post_type_counts[$post->post_type]['count']++;
            } else {
                $post_type_counts[$post->post_type]['type'] = $post->post_type;
                $post_type_counts[$post->post_type]['label'] = $post->post_label;
                $post_type_counts[$post->post_type]['count'] = 1;
            }
        }
        $titleHtml = '';
        $btnHtml = '';
        foreach ($post_type_counts as $post_type_count) {
            if ($post_type_count['count'] > 0) {
                $titleHtml .= ' '.$post_type_count['count'].' '.$post_type_count['label'].' ';
                $btnHtml .= "
                <div class='geekybot_article_bnt_wrp'>
                    <span onclick=\"showArticlesList('".$msg."','".$post_type_count['type']."','".$highest_score."','".$post_type_count['count']."', 1);\" class='geekybot_article_bnt' class='button'>" . __('Show', 'geeky-bot').' '. $post_type_count['label'] ."</span>
                </div>";
            }
        }
        $html = '';
        if (!empty($post_type_counts)) {
            $html = '<div class="geekybot_article_message_wrp">';
            if ($type == 1) {
                $html .= __('Also', 'geeky-bot').' ';
            }
            $html .= __('Found', 'geeky-bot');
            $html .= $titleHtml;
            $html .= '</div>';
            $html .= $btnHtml;
        }
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        return geekybotphplib::GEEKYBOT_htmlentities($html);
    }

    function showArticlesList() {
        $logdata = "\n\nshowArticlesList";
        $nonce = GEEKYBOTrequest::GEEKYBOT_getVar('_wpnonce');
        if (! wp_verify_nonce( $nonce, 'articles-list') ) {
            die( 'Security check Failed' ); 
        }
        $msg = GEEKYBOTrequest::GEEKYBOT_getVar('msg');
        $type = GEEKYBOTrequest::GEEKYBOT_getVar('type');
        $highest_score = GEEKYBOTrequest::GEEKYBOT_getVar('highestScore');
        $total_posts = GEEKYBOTrequest::GEEKYBOT_getVar('totalPosts');
        $current_page = GEEKYBOTrequest::GEEKYBOT_getVar('currentPage');
        $postsPerPage = geekybot::$_configuration['pagination_product_page_size'];
        $offset = ($current_page - 1) * $postsPerPage;

        $query = 'SELECT `id`,`post_id`,`post_type`,`title`, MATCH (post_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AS score FROM `' . geekybot::$_db->prefix . 'geekybot_posts` WHERE MATCH (post_text) AGAINST ("'.esc_sql($msg).'" IN NATURAL LANGUAGE MODE) AND post_type = "'.$type.'" AND status = "publish"';
        $query .= " ORDER BY score DESC ";
        $query .= " LIMIT $postsPerPage OFFSET $offset";
        // Get paginated posts
        $posts = geekybotdb::GEEKYBOT_get_results($query);
        $posts = $this->applyThresholdOnWebSearch($posts, $highest_score);
        $text = '';
        if($posts){
            $currentPostsCount = count($posts);
            $to_post = $offset + $currentPostsCount;
            $from_post = $offset + 1;
            $text = "<div class='geekybot_wc_post_heading geekybot_wc_post_title'>".__('Here are some suggestions.', 'geeky-bot')." <span class='geekybot_wc_post_heading_nums'>".__('Showing', 'geeky-bot')." ".$from_post." - ".$to_post." ".__('of', 'geeky-bot')." ".$total_posts."</span></div>";
            foreach ($posts as $post) {
                $permalink = get_permalink( $post->post_id );
                $featured_image_url = get_the_post_thumbnail_url( $post->post_id, 'full' );
                $logdata .= "\nTitle: ".$post->title." score: ".$post->score; 
                $text .= '
                    <div class="geekybot_wc_article_wrp">
                        <div class="geekybot_wc_article_header geekybot_wc_article_title">';
                            if ( $featured_image_url ) {
                            $text .= '<span class="geekybot_wc_article_title-image"><img src="' . esc_url( $featured_image_url ) . '" alt="' . esc_attr( get_the_title() ) . '"></span>';
                            }
                            $text .= '
                            <a target="_blank" href="' . esc_url( $permalink ) . '">'.$post->title.'</a>
                        </div>
                    </div>';
            }
            $text .= "<div class='geekybot_wc_post_load_more_wrp'>";
                if ($total_posts > ($current_page * $postsPerPage)) {
                    $next_page = $current_page + 1;
                    $text .= "<span class='geekybot_wc_post_load_more' onclick=\"showArticlesList('".$msg."','".$type."','". $highest_score."','". $total_posts."','". $next_page."');\">".__('Show More', 'geeky-bot')."</span>";
                }
            $text .= "</div>";
        }
        $text = geekybotphplib::GEEKYBOT_htmlentities($text);
        // save bot response to the session and chat history
        geekybot::$_geekybotsessiondata->geekybot_addChatHistoryToSession($text, 'bot');
        GEEKYBOTincluder::GEEKYBOT_getModel('chathistory')->SaveChathistoryFromchatServer($text, 'bot', 4);
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        return $text;
        wp_die();
    }

    function applyThresholdOnWebSearch($posts, $highest_score = ''){
        $logdata = "\n\napplyThresholdOnWebSearch";
        $first_post_score = "";
        $posts_count = 0;
        $threshold = 30;
        $threshold_value = 0; 
        if($posts){
            foreach ($posts as $key => $post) {
                if($posts_count == 0){
                    if ($highest_score != '') {
                        $first_post_score = $highest_score;
                        $logdata .= "\nfirst_post_score: ".$highest_score;
                    } else {
                        $first_post_score = $post->score;
                        $logdata .= "\nfirst_post_score: ".$first_post_score;
                    }
                }
                if($posts_count > 0){
                    if($first_post_score){
                        $threshold_value = ($threshold / 100) * $first_post_score;
                        $logdata .= "\nthreshold_value: ".$threshold_value;
                        $logdata .= "\npost_score: ".$post->score;
                        if($post->score <= $threshold_value){
                            unset($posts[$key]);
                            $logdata .= "\nremoving post";
                        }
                    }
                }
                $posts_count = $posts_count + 1;
            }
        }
        GEEKYBOTincluder::GEEKYBOT_getObjectClass('logging')->GEEKYBOTlwrite($logdata);
        return $posts;
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
            $current_batch_size = 0; // Track batch size
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
                    if ($current_batch_size + $row_size > $max_batch_size_in_bytes) {
                        // Insert the current batch
                        $insert_query = $this->geekybotPostTypeBuildQuery($batch_data);
                        geekybot::$_db->query($insert_query);
                        // Reset for the next batch
                        $batch_data = [];
                        $current_batch_size = 0;
                    }
                    // ---------------
                    // Get all taxonomies associated with the post
                    $taxonomies = get_object_taxonomies(get_post_type($post->ID));
                    // Loop through each taxonomy to get the terms
                    foreach ($taxonomies as $taxonomy) {
                        // Get terms for this taxonomy
                        $terms = get_the_terms($post->ID, $taxonomy);
                        if ( ! empty($terms) && ! is_wp_error($terms) ) {
                            foreach ( $terms as $term ) {
                                $post_text .= $term->name.' ';
                            }
                        }
                    }
                    // ---------------
                    $skip_storing_process = 0;
                    $meta_keys = array();
                    if ($post->post_type == 'topic') {
                        $skip_storing_process = 1;
                    } elseif ($post->post_type == 'forum') {
                        $skip_storing_process = 1;

                        $p_id = $post->ID;
                        $p_title = $post->post_title;
                        $p_content = $post->post_content;
                        $p_post_type = $post->post_type;
                        $p_post_id = $post->ID;
                        $p_status = $post->post_status;
                        $bbp_forum_id = $post->ID;
                        $store_topic = $this->geekybotCheckTopicStatusForBBpress();
                        if ($store_topic == 1) {
                            // get all the topics related to this forum
                            $meta_key = '_bbp_forum_id';
                            $meta_value = $bbp_forum_id;
                            $args = array(
                                'post_type'  => 'topic',// Limit to post type 'topic'
                                'post_status'   => 'publish',// Only fetch published posts
                                'meta_key'   => $meta_key,
                                'meta_value' => $meta_value,
                                'posts_per_page' => -1, // Get all matching posts
                                'orderby' => 'ID', // Order by ID
                                'order' => 'ASC', // Order by assending
                            );
                            $topics = get_posts($args);
                            if (!empty($topics)) {
                                foreach ($topics as $topic) {
                                    $post_text .= $topic->post_title . ' ' .$topic->post_content .' ';
                                }
                            }
                        }
                        $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                        $post_text = $this->stripslashesFull($post_text);// remove slashes with quotes.
                            // Add the current row to the batch
                        $batch_data[] = '("'.esc_sql($p_id).'","'.esc_sql($p_title).'","'.esc_sql($p_content).'","'.esc_sql($post_text).'","'.esc_sql($p_post_id).'","'.esc_sql($p_post_type).'","'.esc_sql($p_status).'")';
                    } else {
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
                        $post_meta = get_post_meta($post->ID); // Get all post meta
                        // Loop through the meta and filter useful data
                        if (!empty($meta_keys)) {
                            foreach ($meta_keys as $meta_key => $meta_value) {
                                // Filter out empty values
                                if (!empty($meta_value) && !in_array($meta_key, $exclude_meta_keys)) {
                                    $post_text .= $meta_key.' ';
                                    $post_text .= $meta_value[0].' ';
                                }
                            }
                        }
                    }
                    if ($skip_storing_process == 0) {
                        $post_text = geekybot::GEEKYBOT_sanitizeData($post_text);// GEEKYBOT_sanitizeData() function uses wordpress santize functions
                        $post_text = $this->stripslashesFull($post_text);// remove slashes with quotes.
                        // Add the current row to the batch
                        $batch_data[] = '("'.esc_sql($post->ID).'","'.esc_sql($post->post_title).'","'.esc_sql($post->post_content).'","'.esc_sql($post_text).'","'.esc_sql($post->ID).'","'.esc_sql($post->post_type).'","'.esc_sql($post->post_status).'")';
                    }
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
        return 'INSERT INTO ' . geekybot::$_db->prefix . 'geekybot_posts (ID, title, content, post_text, post_id, post_type, status) VALUES ' . 
            implode(', ', $batch_data) . 
            ' ON DUPLICATE KEY UPDATE 
                title = VALUES(title), 
                content = VALUES(content), 
                post_text = VALUES(post_text), 
                post_type = VALUES(post_type), 
                status = VALUES(status);';
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

    // sub model function

    function geekybotCheckTopicStatusForBBpress(){    
        $query = "SELECT status FROM `" . geekybot::$_db->prefix . "geekybot_post_types` WHERE `post_type` = 'topic'";
        $status = geekybotdb::GEEKYBOT_get_var($query);

        if ($status == 1) {
            // if the post type exists in table and is public
            return 1;
        } elseif ($status == 0) {
            // if the post type exists in table and is not public
            return 0;
        } else {
            // if the post type not exists in table
            $post_type_object = get_post_type_object('topic');
            // Check if the post type exists and is public
            if ($post_type_object && $post_type_object->public) {
                return 1;  // 'topic' exists and is public
            }
        }
        return 0; // 'topic' either doesn't exist or isn't public
    }
}

?>

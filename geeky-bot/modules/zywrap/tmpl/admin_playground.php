<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

// 1. Force load WordPress Dashicons
wp_enqueue_style('dashicons');

// We are leaving the plugin's default Select2 scripts disabled
wp_enqueue_style('geekybot-select2css', GEEKYBOT_PLUGIN_URL . 'includes/css/select2.min.css');
wp_enqueue_script('geekybot-select2js', GEEKYBOT_PLUGIN_URL . 'includes/js/select2.min.js');

// Get all the data we loaded in the controller
$geekybot_data = geekybot::$_data['playground_data'];
$geekybot_categories = $geekybot_data['categories'];
$geekybot_models = $geekybot_data['models'];
$geekybot_languages = $geekybot_data['languages'];
$geekybot_templates = $geekybot_data['templates'];
$geekybot_saved_key = get_option('geekybot_zywrap_api_key', '');
?>

<div id="geekybotadmin-wrapper" class="geekybot-admin-main-wrapper">
    <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/upper-nav', array('module' => 'zywrap_playground', 'layouts' => 'playground')); ?>
    <div class="geekybotadmin-body-main">
        <div id="geekybotadmin-leftmenu-main">
            <?php GEEKYBOTincluder::GEEKYBOT_getTemplate('templates/admin/leftmenue', array('module' => 'zywrap_playground')); ?>
        </div>
        <div id="geekybotadmin-data">
            <div class="geekybot-tab-nav">
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=zywrap','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Settings', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywrap&geekybotlt=playground','Zywrap'))?>" class="geekybot-tab-link active"><?php echo esc_html(__('AI Generate Text', 'geeky-bot')); ?></a>
              <a href="<?php echo esc_url(wp_nonce_url('admin.php?page=geekybot_zywraplogs&geekybotlt=logs','configuration'))?>" class="geekybot-tab-link"><?php echo esc_html(__('AI Logs', 'geeky-bot')); ?></a>
            </div>
            <div id="geekybot-head">
                <h1 class="geekybot-head-text">
                    <?php echo esc_html(__('AI Generate Text', 'geeky-bot')); ?>
                </h1>
            </div>
            
            <div style="clear: both;"> 
                <?php if(!$geekybot_saved_key): ?>
                    <div class="geekybot-setup-notice">
                        <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap")); ?>">
                            <?php echo esc_html(__('Setup Required: Please add your API Key in settings.', 'geeky-bot')); ?>
                        </a>
                    </div>
                <?php else:
                    $geekybot_categories_count = count($geekybot_categories);
                    if($geekybot_categories_count < 1):?>
                    <div class="geekybot-setup-notice">
                        <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap")); ?>">
                            <?php echo esc_html(__('Action Needed: Please sync the Wrapper Catalog (Step 2) in settings.', 'geeky-bot')); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div id="geekybot-admin-wrapper" class="geekybot-admin-config-wrapper" style="background: transparent; box-shadow: none; border: none; padding: 0;">
                
                <div class="zywrap-playground-grid">
                    
                    <div class="zywrap-controls-column">
                        <div class="geekybot-sidebar-section-title"><?php echo esc_html(__('Core Settings', 'geeky-bot')); ?></div>
                        <div class="geekybot-special-config-wrapper">
                            <!-- 1. Category -->
                            <div class="geekybot-input-group">
                                <label class="geekybot-input-label-s-case">
                                    <?php echo esc_html(__('Use Case', 'geeky-bot')); ?>
                                </label>
                                <div id="zywrap_category_parent">
                                    <select id="zywrap_category" name="zywrap_category" class="inputbox dark-select zywrap-select2">
                                        <option value=""><?php echo esc_html(__('-- Select Category --', 'geeky-bot')); ?></option>
                                        <?php foreach ($geekybot_categories as $geekybot_option) : ?>
                                            <option value="<?php echo esc_attr($geekybot_option->code); ?>"><?php echo esc_html($geekybot_option->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="geekybot-checkbox-group">
                                    <label class="geekybot-checkbox-label">
                                        <input type="checkbox" id="filter_base" /> <?php echo esc_html(__('Base Only', 'geeky-bot')); ?>
                                    </label>
                                    <label class="geekybot-checkbox-label">
                                        <input type="checkbox" id="filter_featured" /> <?php echo esc_html(__('Featured Only', 'geeky-bot')); ?>
                                    </label>
                                </div>
                            </div>

                            <!-- 2. Wrapper -->
                            <div class="geekybot-input-group">
                                <label class="geekybot-input-label-s-case">
                                    <?php echo esc_html(__('Wrapper', 'geeky-bot')); ?>
                                </label>
                                <div id="zywrap_wrapper_parent">
                                    <select id="zywrap_wrapper" name="zywrap_wrapper" class="inputbox dark-select zywrap-select2" disabled>
                                        <option value=""><?php echo esc_html(__('-- Select Category First --', 'geeky-bot')); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- 3. AI Model -->
                        <div class="geekybot-input-group">
                            <label class="input-label">
                                <?php echo esc_html(__('AI Model', 'geeky-bot')); ?><span>(<?php echo esc_html(__('Optional', 'geeky-bot')); ?>)</span>
                            </label>
                            <div id="zywrap_model_parent">
                                <select id="zywrap_model" name="zywrap_model" class="inputbox dark-select zywrap-select2">
                                    <option value=""><?php echo esc_html(__('-- Select Model --', 'geeky-bot')); ?></option>
                                    <?php 
                                    $geekybot_first_model = true; 
                                    foreach ($geekybot_models as $geekybot_option) : ?>
                                        <option value="<?php echo esc_attr($geekybot_option->code); ?>" <?php if ($geekybot_first_model) { echo 'selected'; $geekybot_first_model = false; } ?>>
                                            <?php echo esc_html($geekybot_option->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="geekybot-sidebar-section-title"><?php echo esc_html(__('Parameters', 'geeky-bot')); ?></div>

                        <!-- 8. Language -->
                        <div class="geekybot-input-group">
                            <label class="input-label"><?php echo esc_html(__('Language', 'geeky-bot')); ?><span>(<?php echo esc_html(__('Optional', 'geeky-bot')); ?>)</span></label>
                            <div id="zywrap_language_parent">
                                <select id="zywrap_language" name="zywrap_language" class="inputbox dark-select zywrap-select2">
                                    <option value=""><?php echo esc_html(__('-- Default (English) --', 'geeky-bot')); ?></option>
                                    <?php foreach ($geekybot_languages as $geekybot_option) : ?>
                                        <option value="<?php echo esc_attr($geekybot_option->code); ?>"><?php echo esc_html($geekybot_option->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <details class="modern-details">
                            <summary><?php echo esc_html(__('Advanced Constraints', 'geeky-bot')); ?></summary>
                            <div class="details-content">
                                <div>
                                    <div class="geekybot-input-group-scase">
                                        <label class="input-label"><?php echo esc_html(__('Context / Background Data', 'geeky-bot')); ?></label>
                                        <textarea id="zywrap_context" class="dark-textarea" placeholder="<?php echo esc_attr__('Paste reference text or context...', 'geeky-bot'); ?>"></textarea>
                                    </div>
                                    <label class="input-label"><?php echo esc_html(__('SEO Keywords', 'geeky-bot')); ?></label>
                                    <input type="text" id="zywrap_seo_keywords" class="dark-input" placeholder="<?php echo esc_attr__('e.g. AI, tech, future', 'geeky-bot'); ?>">
                                </div>
                                <div>
                                    <label class="input-label"><?php echo esc_attr__('Negative Words', 'geeky-bot'); ?></label>
                                    <input type="text" id="zywrap_negative_constraints" class="dark-input" placeholder="<?php echo esc_attr__('e.g. error, bias', 'geeky-bot'); ?>">
                                </div>
                                <div id="zywrap_overrides_parent" class="zywrap-overrides-grid">
                                    <!-- Advanced dropdowns mapped to grid -->
                                    <div class="zywrap_overrides_parent_wrp">
                                        <?php 
                                        $override_fields = [
                                            'toneCode' => ['label' => 'Tone', 'data' => $geekybot_templates['tones'] ?? []],
                                            'styleCode' => ['label' => 'Style', 'data' => $geekybot_templates['styles'] ?? []],
                                            'formatCode' => ['label' => 'Format', 'data' => $geekybot_templates['formattings'] ?? []],
                                            'complexityCode' => ['label' => 'Complexity', 'data' => $geekybot_templates['complexities'] ?? []],
                                            'lengthCode' => ['label' => 'Length', 'data' => $geekybot_templates['lengths'] ?? []],
                                            'audienceCode' => ['label' => 'Audience', 'data' => $geekybot_templates['audienceLevels'] ?? []],
                                            'responseGoalCode' => ['label' => 'Goal', 'data' => $geekybot_templates['responseGoals'] ?? []],
                                            'outputCode' => ['label' => 'Output', 'data' => $geekybot_templates['outputTypes'] ?? []],
                                        ];

                                        if (!empty($override_fields) && is_array($override_fields)):
                                            foreach($override_fields as $id => $field): 
                                        ?>
                                            <div id="<?php echo esc_attr($id); ?>_parent">
                                                <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" class="inputbox geekybot-form-select-field zywrap-select2">
                                                    <option value=""><?php echo esc_html($field['label'] . ' (Default)'); ?></option>
                                                    <?php if(!empty($field['data']) && is_array($field['data'])): ?>
                                                        <?php foreach ($field['data'] as $opt) : ?>
                                                            <option value="<?php echo esc_attr($opt['code']); ?>"><?php echo esc_html($opt['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </div>
                                </div>
                            </div>
                        </details>
                    </div>
                    <div class="geekybot-app-main">
                        <!-- Left: Prompting Area -->
                        <div class="geekybot-glass-panel" style="grid-column: 1;">
                            <div class="geekybot-panel-header">
                                <div class="geekybot-panel-title">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px; color:#6366f1;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                                    </svg>
                                    <?php echo esc_html(__('Input', 'geeky-bot')); ?>
                                </div>
                            </div>
                            <div class="geekybot-input-group" style="display: flex; flex-direction: column;">
                                <textarea id="zywrap_prompt" class="dark-textarea" style="margin-bottom: 16px;" placeholder="Enter your main prompt here..."></textarea>
                                <div class="zywrap-run-btn-wrapper">
                                    <button type="button" id="zywrap-run-button" class="btn btn-primary button-hero">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
                                        <?php echo esc_html(__('Generate Content', 'geeky-bot')); ?>
                                    </button>
                                </div>
                            </div>
                            <div id="zywrap-output-error" style="display: none; margin-bottom: 20px;" class="notice notice-error inline">
                                <p></p>
                            </div>
                            <div class="geekybot-geekybot-glass-panel-s" style="grid-column: 2; display: flex; flex-direction: column;">
                                <div class="geekybot-panel-header">
                                    <div class="geekybot-panel-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px; color:#10b981;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                                        </svg>
                                        <?php echo esc_html(__('Console Output', 'geeky-bot')); ?>
                                    </div>
                                    <div class="zywrap-toolbar-actions" style="display: flex; gap: 8px;">
                                        <!-- === NEW: Summarize Button === -->
                                        <button style="display: none;" type="button" id="zywrap-summarize-button" class="btn btn-icon"  title="<?php echo esc_attr__('Summarize', 'geeky-bot'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        </button>
                                        <button type="button" id="zywrap-clear-button" class="btn btn-icon" title="<?php echo esc_attr__('Clear Output', 'geeky-bot'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                        <button type="button" id="zywrap-copy-button" class="btn btn-icon" title="<?php echo esc_attr__('Copy Output', 'geeky-bot'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div id="zywrap-output-container" class="output-container">
                                    <pre id="zywrap-output"><?php echo esc_html( __('Ready to generate content. Select a wrapper and click Run.', 'geeky-bot') ); ?></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Add our custom JavaScript to the page
$geekybot_js = "
jQuery(document).ready(function($) {
    
    // Initialize Select2
    $('.zywrap-select2').select2({ width: '100%' });

    var ajaxurl = '" . esc_url(admin_url("admin-ajax.php")) . "';

    // 1. Dependent Dropdown (Category -> Wrappers)
    function updateWrapperList() {
        var categoryCode = $('#zywrap_category').val();
        var showFeatured = $('#filter_featured').is(':checked');
        var showBase = $('#filter_base').is(':checked');
        var wrapperSelect = $('#zywrap_wrapper');

        if (!categoryCode) {
            wrapperSelect.empty().append('<option value=\"\">". esc_html(__('-- Select Category First --', 'geeky-bot'))."</option>').prop('disabled', true).trigger('change');
            return;
        }

        wrapperSelect.prop('disabled', true).empty().append('<option value=\"\">" . esc_js(__('Loading...', 'geeky-bot')) . "</option>').trigger('change');

        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'get_wrappers_by_category',
            category_code: categoryCode,
            show_featured: showFeatured,
            show_base: showBase,
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_get_wrappers")) . "'
        }, function(response) {
            if (response.success) {
                wrapperSelect.empty().append('<option value=\"\">". esc_html(__('-- Select Wrapper --', 'geeky-bot'))."</option>');
                response.data.forEach(function(wrapper) {
                    wrapperSelect.append($('<option>', {
                        value: wrapper.code,
                        text: wrapper.name
                    }));
                });
                wrapperSelect.prop('disabled', false).trigger('change');
            } else {
                wrapperSelect.empty().append('<option value=\"\">". esc_html(__('-- Error Loading --', 'geeky-bot'))."</option>').trigger('change');
            }
        });
    }

    $('#zywrap_category, #filter_base, #filter_featured').on('change', updateWrapperList);

    // 2. Clear Button
    $('#zywrap-clear-button').on('click', function() {
        $('#zywrap-output').text('" . esc_js(__('Ready to generate content. Select a wrapper and click Run.', 'geeky-bot')) . "');
        $('#zywrap-output-error').hide();
    });

    // 3. Copy Button
    $('#zywrap-copy-button').on('click', function() {
        var outputText = $('#zywrap-output').text();
        var button = $(this);
        var originalHtml = button.html();
        
        navigator.clipboard.writeText(outputText).then(function() {
            button.html('<span class=\"dashicons dashicons-yes\"></span> " . esc_js(__('Copied!', 'geeky-bot')) . "');
            setTimeout(function() { button.html(originalHtml); }, 2000);
        });
    });

    // === NEW: Summarize Button Logic ===
    $('#zywrap-summarize-button').on('click', function() {
        var outputText = $('#zywrap-output').text();
        // Basic validation: ensure there is text to summarize
        if (outputText.length < 50 || outputText.includes('Ready to generate content')) {
            alert('" . esc_js(__('Please generate some text first before summarizing.', 'geeky-bot')) . "');
            return;
        }

        // Check if context has content and confirm overwrite
        if ($('#zywrap_context').val().trim() !== '') {
            if (!confirm('" . esc_js(__('This will replace your current text in the Context field. Continue?', 'geeky-bot')) . "')) {
                return;
            }
        }

        // Logic: Move current output to 'Context', set prompt to 'Summarize this', and trigger Run
        $('#zywrap_context').val(outputText);
        $('#zywrap_prompt').val('Summarize the text above into a concise TL;DR or bullet points.');
        
        // Scroll to context field to show user what happened
        $('html, body').animate({
            scrollTop: $('#zywrap_context').offset().top - 100
        }, 500);

        // Optional: Auto-click run? 
        // $('#zywrap-run-button').click(); 
        // Better to let user click Run so they can adjust prompt if needed.
    });

    // 4. Run Wrapper
    $('#zywrap-run-button').on('click', function() {
        var button = $(this);
        var outputPre = $('#zywrap-output');
        var errorDiv = $('#zywrap-output-error');
        var originalHtml = button.html();

        outputPre.text('" . esc_js(__('Generating content... Please wait...', 'geeky-bot')) . "');
        errorDiv.hide().find('p').empty();
        button.prop('disabled', true).html('<span class=\"spinner is-active\" style=\"float:none; margin:0 5px 0 0;\"></span> " . esc_js(__('Running...', 'geeky-bot')) . "');

        // Collect all override codes
        var overrides = {};
        var override_selects = ['toneCode', 'styleCode', 'formatCode', 'complexityCode', 'lengthCode', 'audienceCode', 'responseGoalCode', 'outputCode'];
        override_selects.forEach(function(key) {
            var value = $('#' + key).val();
            if (value) {
                overrides[key] = value;
            }
        });

        // Collect main data
        var data = {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'execute_zywrap_proxy',
            _wpnonce: '" . esc_attr(wp_create_nonce("zywrap_execute_proxy")) . "',
            model: $('#zywrap_model').val(),
            wrapperCode: $('#zywrap_wrapper').val(),
            language: $('#zywrap_language').val(),
            prompt: $('#zywrap_prompt').val(),
            context: $('#zywrap_context').val(),
            seo_keywords: $('#zywrap_seo_keywords').val(),
            negative_constraints: $('#zywrap_negative_constraints').val(),
            overrides: overrides
        };

        // Simple Validation
        if (!data.wrapperCode) {
            errorDiv.find('p').text('" . esc_js(__('Error: Wrapper is required.', 'geeky-bot')) . "');
            errorDiv.show();
            outputPre.text('Ready to generate content...');
            button.prop('disabled', false).html(originalHtml);
            return;
        }

        // Make the AJAX call to our model's function
        $.post(ajaxurl, data, function(response) {
            if (response.success) {

                // CHANGED: Parse the 'output' field
                var rawOutput = response.data.output;
                var finalDisplayOutput = rawOutput; // Default to raw output

                if (rawOutput) {
                    // Clean the string: remove markdown code fences
                    var cleanedOutput = rawOutput.replace(/^```json\s*/, '').replace(/\s*```$/, '');

                    // Check if the cleaned output is valid JSON
                    try {
                        // Try to parse it...
                        var jsonObject = JSON.parse(cleanedOutput);
                        // ...and re-stringify it beautifully.
                        finalDisplayOutput = JSON.stringify(jsonObject, null, 2);
                    } catch (e) {
                        // It's not JSON, just use the cleaned text
                        finalDisplayOutput = cleanedOutput;
                    }
                } else {
                    finalDisplayOutput = '" . esc_js(__("Received empty output.", "geeky-bot")) . "';
                }
                outputPre.text(finalDisplayOutput); // Set the text of the <pre> block
            } else {
                // The API call failed (401, 402, 500, etc)
                errorDiv.find('p').text(response.data.message);
                errorDiv.show();
                outputPre.text('Error occurred.');
            }
            button.prop('disabled', false).html(originalHtml);
        });
    });
});
";

// We are no longer using wp_add_inline_script as it was causing conflicts
wp_add_inline_script('geekybot-main-js', $geekybot_js);
// wp_add_inline_script('geekybot-select2js', $geekybot_js);

?>

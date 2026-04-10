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
                        <div class="geekybot-sidebar-section-title" style="display: flex; justify-content: space-between; align-items: center;">
                            <span><?php echo esc_html(__('Configuration', 'geeky-bot')); ?></span>
                            <label class="geekybot-checkbox-label" style="font-size: 12px; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px; color: #475569;">
                                <input type="checkbox" id="filter_featured" /> <?php echo esc_html(__('Featured Only', 'geeky-bot')); ?>
                            </label>
                        </div>
                        <div class="geekybot-special-config-wrapper">
                            
                            <div class="geekybot-input-group">
                                <label class="geekybot-input-label-s-case">
                                    <?php echo esc_html(__('1. Category', 'geeky-bot')); ?>
                                </label>
                                <div id="zywrap_category_parent">
                                    <select id="zywrap_category" name="zywrap_category" class="inputbox dark-select zywrap-select2">
                                        <option value=""><?php echo esc_html(__('-- Select Category --', 'geeky-bot')); ?></option>
                                        <?php foreach ($geekybot_categories as $geekybot_option) : ?>
                                            <option value="<?php echo esc_attr($geekybot_option->code); ?>"><?php echo esc_html($geekybot_option->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="geekybot-input-group">
                                <label class="geekybot-input-label-s-case">
                                    <?php echo esc_html(__('2. AI Solution', 'geeky-bot')); ?>
                                </label>
                                <div id="zywrap_usecase_parent">
                                    <select id="zywrap_usecase" name="zywrap_usecase" class="inputbox dark-select zywrap-select2" disabled>
                                        <option value=""><?php echo esc_html(__('-- Select Category First --', 'geeky-bot')); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="geekybot-input-group">
                                <label class="geekybot-input-label-s-case">
                                    <?php echo esc_html(__('3. Configuration Style', 'geeky-bot')); ?>
                                </label>
                                <div id="zywrap_wrapper_parent">
                                    <select id="zywrap_wrapper" name="zywrap_wrapper" class="inputbox dark-select zywrap-select2" disabled>
                                        <option value=""><?php echo esc_html(__('-- Select Solution First --', 'geeky-bot')); ?></option>
                                    </select>
                                </div>
                            </div>

                            <div class="geekybot-input-group">
                                <label class="input-label">
                                    <?php echo esc_html(__('4. AI Model', 'geeky-bot')); ?>
                                </label>
                                <div id="zywrap_model_parent">
                                    <select id="zywrap_model" name="zywrap_model" class="inputbox dark-select zywrap-select2">
                                        <option value=""><?php echo esc_html(__('-- Default Model --', 'geeky-bot')); ?></option>
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

                            <div class="geekybot-input-group">
                                <label class="input-label"><?php echo esc_html(__('5. Target Language', 'geeky-bot')); ?></label>
                                <div id="zywrap_language_parent">
                                    <select id="zywrap_language" name="zywrap_language" class="inputbox dark-select zywrap-select2">
                                        <option value=""><?php echo esc_html(__('-- English (Default) --', 'geeky-bot')); ?></option>
                                        <?php foreach ($geekybot_languages as $geekybot_option) : ?>
                                            <option value="<?php echo esc_attr($geekybot_option->code); ?>"><?php echo esc_html($geekybot_option->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                        </div>

                        <details class="modern-details">
                            <summary><?php echo esc_html(__('Advanced Overrides', 'geeky-bot')); ?></summary>
                            <div class="details-content" style="padding: 16px;">
                                <div style="display: flex; flex-direction: column; gap: 16px;">
                                    <?php 
                                    $override_fields = [
                                        'toneCode' => ['label' => 'Tone', 'data' => $geekybot_templates['tones'] ?? []],
                                        'styleCode' => ['label' => 'Style', 'data' => $geekybot_templates['styles'] ?? []],
                                        'formatCode' => ['label' => 'Formatting', 'data' => $geekybot_templates['formattings'] ?? []],
                                        'complexityCode' => ['label' => 'Complexity', 'data' => $geekybot_templates['complexities'] ?? []],
                                        'lengthCode' => ['label' => 'Length', 'data' => $geekybot_templates['lengths'] ?? []],
                                        'audienceCode' => ['label' => 'Audience', 'data' => $geekybot_templates['audienceLevels'] ?? []],
                                        'responseGoalCode' => ['label' => 'Goal', 'data' => $geekybot_templates['responseGoals'] ?? []],
                                        'outputCode' => ['label' => 'Output Type', 'data' => $geekybot_templates['outputTypes'] ?? []],
                                    ];

                                    if (!empty($override_fields) && is_array($override_fields)):
                                        foreach($override_fields as $id => $field): 
                                    ?>
                                        <div>
                                            <label style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;"><?php echo esc_html($field['label']); ?></label>
                                            <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" class="inputbox dark-select zywrap-select2" style="width: 100%;">
                                                <option value=""><?php echo esc_html('-- Default --'); ?></option>
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
                        </details>
                    </div>

                    <div class="geekybot-app-main">
                        <div class="geekybot-glass-panel" style="grid-column: 1;">
                            
                            <div class="geekybot-input-group" style="display: flex; flex-direction: column;">
                                <div id="dynamic-schema-container" style="margin-bottom: 15px;"></div>
                                
                                <div style="margin-bottom: 16px;">
                                    <label id="prompt-label-text" style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 8px;">
                                        <?php echo esc_html(__('Prompt / Additional Context', 'geeky-bot')); ?>
                                    </label>
                                    <textarea id="zywrap_prompt" class="dark-textarea" placeholder="Type your request or additional instructions here..."></textarea>
                                </div>
                                
                                <div class="zywrap-run-btn-wrapper">
                                    <button type="button" id="zywrap-run-button" class="btn btn-primary button-hero" style="width: 100%;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:18px;height:18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
                                        <?php echo esc_html(__('Generate Response', 'geeky-bot')); ?>
                                    </button>
                                </div>
                            </div>
                            
                            <div id="zywrap-output-error" style="display: none; margin-bottom: 20px;" class="notice notice-error inline">
                                <p></p>
                            </div>
                            
                            <div class="geekybot-geekybot-glass-panel-s" style="grid-column: 2; display: flex; flex-direction: column; margin-top: 20px;">
                                <div class="geekybot-panel-header">
                                    <div class="geekybot-panel-title">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:20px;height:20px; color:#10b981;">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                                        </svg>
                                        <?php echo esc_html(__('AI Response', 'geeky-bot')); ?>
                                    </div>
                                    <div class="zywrap-toolbar-actions" style="display: flex; gap: 8px;">
                                        <button type="button" id="zywrap-clear-button" class="btn btn-icon" title="<?php echo esc_attr__('Clear Output', 'geeky-bot'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg>
                                        </button>
                                        <button type="button" id="zywrap-copy-button" class="btn btn-icon" title="<?php echo esc_attr__('Copy Output', 'geeky-bot'); ?>">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <div id="zywrap-output-container" class="output-container">
                                    <pre id="zywrap-output"><?php echo esc_html( __('Output will appear here...', 'geeky-bot') ); ?></pre>
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

    // Global Featured Toggle Event
    $('#filter_featured').on('change', function() {
        if ($('#zywrap_category').val()) {
            $('#zywrap_category').trigger('change');
        }
    });

    // 1. Cascade Logic 1: Category -> Load Use Cases (AI Solutions)
    $('#zywrap_category').on('change', function() {
        var categoryCode = $(this).val();
        var showFeatured = $('#filter_featured').is(':checked');
        var usecaseSelect = $('#zywrap_usecase');
        var wrapperSelect = $('#zywrap_wrapper');
        
        // Reset down the chain
        $('#dynamic-schema-container').empty();
        $('#prompt-label-text').text('" . esc_js(__('Prompt / Additional Context', 'geeky-bot')) . "');
        wrapperSelect.prop('disabled', true).empty().append('<option value=\"\">". esc_html(__('-- Select Solution First --', 'geeky-bot'))."</option>').trigger('change');

        if (!categoryCode) {
            usecaseSelect.empty().append('<option value=\"\">". esc_html(__('-- Select Category First --', 'geeky-bot'))."</option>').prop('disabled', true).trigger('change');
            return;
        }

        usecaseSelect.prop('disabled', true).empty().append('<option value=\"\">" . esc_js(__('Loading...', 'geeky-bot')) . "</option>').trigger('change');

        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'get_use_cases_by_category',
            category_code: categoryCode,
            show_featured: showFeatured,
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_get_wrappers")) . "' 
        }, function(response) {
            if (response.success && response.data.length > 0) {
                usecaseSelect.empty().append('<option value=\"\">". esc_html(__('-- Select a Solution --', 'geeky-bot'))."</option>');
                response.data.forEach(function(uc) {
                    usecaseSelect.append($('<option>', { value: uc.code, text: uc.name }));
                });
                usecaseSelect.prop('disabled', false);
            } else {
                usecaseSelect.empty().append('<option value=\"\">". esc_html(__('-- No Solutions Found --', 'geeky-bot'))."</option>').trigger('change');
            }
        });
    });

    // 2. Cascade Logic 2: Use Case -> Load Wrappers (Configuration Styles)
    $('#zywrap_usecase').on('change', function() {
        var usecaseCode = $(this).val();
        var showFeatured = $('#filter_featured').is(':checked');
        var wrapperSelect = $('#zywrap_wrapper');
        
        // Reset dynamic schema UI
        $('#dynamic-schema-container').empty();
        $('#prompt-label-text').text('" . esc_js(__('Prompt / Additional Context', 'geeky-bot')) . "');

        if (!usecaseCode) {
            wrapperSelect.empty().append('<option value=\"\">". esc_html(__('-- Select Solution First --', 'geeky-bot'))."</option>').prop('disabled', true).trigger('change');
            return;
        }

        wrapperSelect.prop('disabled', true).empty().append('<option value=\"\">" . esc_js(__('Loading...', 'geeky-bot')) . "</option>').trigger('change');

        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'get_wrappers_by_usecase',
            usecase_code: usecaseCode,
            show_featured: showFeatured,
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_get_wrappers")) . "'
        }, function(response) {
            if (response.success && response.data.length > 0) {
                wrapperSelect.empty().append('<option value=\"\">". esc_html(__('-- Select a Style --', 'geeky-bot'))."</option>');
                
                var autoSelectCode = null;
                
                response.data.forEach(function(wrapper, index) {
                    var parts = wrapper.name.split('—');
                    var displayName = wrapper.base == 1 
                        ? '✨ Base Template - ' + $.trim(parts[0]) 
                        : '↳ Variation: ' + (parts.length > 1 ? $.trim(parts[1]) : wrapper.name);
                        
                    wrapperSelect.append($('<option>', {
                        value: wrapper.code,
                        text: displayName
                    }));
                    
                    if (wrapper.base == 1 && !autoSelectCode) {
                        autoSelectCode = wrapper.code;
                    } else if (index === 0 && !autoSelectCode) {
                        autoSelectCode = wrapper.code;
                    }
                });
                
                wrapperSelect.prop('disabled', false);
                
                // Auto-trigger schema load
                if (autoSelectCode) {
                    wrapperSelect.val(autoSelectCode).trigger('change');
                }
                
            } else {
                wrapperSelect.empty().append('<option value=\"\">". esc_html(__('-- No Styles Found --', 'geeky-bot'))."</option>').trigger('change');
            }
        });
    });

    // 3. Cascade Logic 3: Wrapper -> Fetch and Render Dynamic Schema
    $('#zywrap_wrapper').on('change', function() {
        var wrapperCode = $(this).val();
        var schemaContainer = $('#dynamic-schema-container');
        var promptLabelText = $('#prompt-label-text');

        schemaContainer.empty();
        promptLabelText.text('" . esc_js(__('Prompt / Additional Context', 'geeky-bot')) . "');

        if (!wrapperCode) return;

        $.post(ajaxurl, {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'get_wrapper_schema',
            wrapper_code: wrapperCode,
            '_wpnonce': '" . esc_attr(wp_create_nonce("zywrap_execute_proxy")) . "' 
        }, function(response) {
            if (response.success && response.data && (response.data.req || response.data.opt)) {
                var schema = response.data;
                var html = '';
                
                promptLabelText.text('" . esc_js(__('Additional Free-form Instructions', 'geeky-bot')) . "');

                // Function to build UI components matching 2-column image
                function buildSection(title, data) {
                    if (!data || Object.keys(data).length === 0) return '';
                    var sectionHtml = '<div style=\"margin-bottom: 20px; background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px;\">';
                    sectionHtml += '<h3 style=\"font-size: 13px; font-weight: 600; color: #475569; margin: 0 0 12px 0; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0;\">' + title + '</h3>';
                    
                    // Add 2-column grid container
                    sectionHtml += '<div style=\"display: grid; grid-template-columns: 1fr 1fr; gap: 12px;\">';

                    for (var key in data) {
                        var def = data[key];
                        var isPlaceholder = def.p !== undefined ? def.p : false;
                        var defaultVal = def.d !== undefined ? def.d : '';
                        
                        var placeholderAttr = isPlaceholder ? 'placeholder=\"' + defaultVal.replace(/\"/g, '&quot;') + '\"' : '';
                        var valueAttr = (!isPlaceholder && defaultVal) ? 'value=\"' + defaultVal.replace(/\"/g, '&quot;') + '\"' : '';
                        
                        var label = key.replace(/([A-Z])/g, ' $1').replace(/^./, function(str){ return str.toUpperCase(); });
                        
                        sectionHtml += '<div>';
                        sectionHtml += '<label style=\"display: block; font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 6px;\">' + label + '</label>';
                        sectionHtml += '<input type=\"text\" class=\"dark-input schema-input\" data-key=\"' + key + '\" ' + placeholderAttr + ' ' + valueAttr + ' style=\"width: 100%; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; font-size: 14px; background: #fff;\">';
                        sectionHtml += '</div>';
                    }
                    
                    sectionHtml += '</div></div>';
                    return sectionHtml;
                }

                html += buildSection('" . esc_js(__('Core Inputs', 'geeky-bot')) . "', schema.req);
                html += buildSection('" . esc_js(__('Additional Context', 'geeky-bot')) . "', schema.opt);
                
                schemaContainer.html(html);
            }
        });
    });

    // 4. Clear Button
    $('#zywrap-clear-button').on('click', function() {
        $('#zywrap-output').text('" . esc_js(__('Output will appear here...', 'geeky-bot')) . "');
        $('#zywrap-output-error').hide();
    });

    // 5. Copy Button
    $('#zywrap-copy-button').on('click', function() {
        var outputText = $('#zywrap-output').text();
        var button = $(this);
        var originalHtml = button.html();
        
        navigator.clipboard.writeText(outputText).then(function() {
            button.html('<span class=\"dashicons dashicons-yes\"></span> " . esc_js(__('Copied!', 'geeky-bot')) . "');
            setTimeout(function() { button.html(originalHtml); }, 2000);
        });
    });

    // 6. Run Wrapper Execute
    $('#zywrap-run-button').on('click', function() {
        var button = $(this);
        var outputPre = $('#zywrap-output');
        var errorDiv = $('#zywrap-output-error');
        var originalHtml = button.html();

        outputPre.text('" . esc_js(__('Generating content... Please wait...', 'geeky-bot')) . "');
        errorDiv.hide().find('p').empty();
        button.prop('disabled', true).html('<span class=\"spinner is-active\" style=\"float:none; margin:0 5px 0 0;\"></span> " . esc_js(__('Generating...', 'geeky-bot')) . "');

        // Collect Advanced Constraints Overrides
        var overrides = {};
        var override_selects = ['toneCode', 'styleCode', 'formatCode', 'complexityCode', 'lengthCode', 'audienceCode', 'responseGoalCode', 'outputCode'];
        override_selects.forEach(function(key) {
            var value = $('#' + key).val();
            if (value) {
                overrides[key] = value;
            }
        });
        
        // SDK PARITY: Merge Schema Fields into Prompt
        var finalPrompt = $('#zywrap_prompt').val().trim();
        var variables = {};
        var structuredTextParts = [];

        $('.schema-input').each(function() {
            var val = $(this).val().trim();
            if (val !== '') {
                var key = $(this).data('key');
                variables[key] = val; // Store variables to pass to payload just in case
                structuredTextParts.push(key + ': ' + val);
            }
        });

        var structuredText = structuredTextParts.join('\\n');
        
        if (finalPrompt && structuredText) {
            finalPrompt = finalPrompt + '\\n\\n' + structuredText;
        } else if (structuredText) {
            finalPrompt = structuredText;
        }

        var data = {
            action: 'geekybot_ajax',
            geekybotme: 'zywrap',
            task: 'execute_zywrap_proxy',
            _wpnonce: '" . esc_attr(wp_create_nonce("zywrap_execute_proxy")) . "',
            model: $('#zywrap_model').val(),
            wrapperCode: $('#zywrap_wrapper').val(),
            language: $('#zywrap_language').val(),
            prompt: finalPrompt,
            variables: variables, 
            overrides: overrides
        };

        if (!data.wrapperCode) {
            errorDiv.find('p').text('" . esc_js(__('Error: Wrapper is required.', 'geeky-bot')) . "');
            errorDiv.show();
            outputPre.text('Output will appear here...');
            button.prop('disabled', false).html(originalHtml);
            return;
        }

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                var rawOutput = response.data.output;
                var finalDisplayOutput = rawOutput;

                if (rawOutput) {
                    var cleanedOutput = rawOutput.replace(/^```json\s*/, '').replace(/\s*```$/, '');
                    try {
                        var jsonObject = JSON.parse(cleanedOutput);
                        finalDisplayOutput = JSON.stringify(jsonObject, null, 2);
                    } catch (e) {
                        finalDisplayOutput = cleanedOutput;
                    }
                } else {
                    finalDisplayOutput = '" . esc_js(__("Received empty output.", "geeky-bot")) . "';
                }
                outputPre.text(finalDisplayOutput); 
            } else {
                errorDiv.find('p').text(response.data.message);
                errorDiv.show();
                outputPre.text('Error occurred.');
            }
            button.prop('disabled', false).html(originalHtml);
        });
    });
});
";

wp_add_inline_script('geekybot-main-js', $geekybot_js);

?>
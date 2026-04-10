<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

$geekybot_saved_key = get_option('geekybot_zywrap_api_key', '');
global $wpdb;

wp_enqueue_script('geeky-bot-zywrap-marked-js', GEEKYBOT_PLUGIN_URL . 'modules/zywrap/js/marked.min.js', array(), '1.0.0', true);

// If there's no API key, abort immediately
if (empty($geekybot_saved_key)) {
    return; 
}

// Call the model securely to get all formatted data
$drawer_data = GEEKYBOTincluder::GEEKYBOT_getModel('zywrap')->get_editor_drawer_data();

// If data returns false, the database is not synced yet. Abort loading the drawer.
if (!$drawer_data) {
    return; 
}

// Extract variables for the HTML/JS below
$categories = $drawer_data['categories'];
$models     = $drawer_data['models'];
$languages  = $drawer_data['languages'];
$templates  = $drawer_data['templates'];

?>

<style>
    /* Modern Zywrap Copilot Drawer */
    :root {
        --zy-bg: #111827; /* bg-gray-900 */
        --zy-panel: #1f2937; /* bg-gray-800 */
        --zy-border: #374151; /* border-gray-700 */
        --zy-text: #f9fafb; /* text-gray-50 */
        --zy-text-muted: #9ca3af; /* text-gray-400 */
        --zy-accent: #6366f1; /* indigo-500 */
        --zy-accent-hover: #4f46e5; /* indigo-600 */
    }

    /* Floating Toggle Button */
    #zywrap-copilot-toggle {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: var(--zy-accent);
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        cursor: pointer;
        z-index: 99998;
        border: none;
        transition: transform 0.2s, background 0.2s;
    }
    #zywrap-copilot-toggle:hover {
        transform: scale(1.05);
        background: var(--zy-accent-hover);
    }
    #zywrap-copilot-toggle svg { width: 24px; height: 24px; }

    /* The Drawer */
    #zywrap-copilot-drawer {
        position: fixed;
        top: 0;
        right: -500px; /* Hidden by default */
        width: 480px; 
        height: 100vh;
        background: var(--zy-bg);
        color: var(--zy-text);
        box-shadow: -5px 0 30px rgba(0,0,0,0.5);
        z-index: 99999;
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }
    #zywrap-copilot-drawer.zywrap-drawer-open {
        right: 0;
    }

    /* Header */
    .zywrap-drawer-header {
        padding: 20px;
        border-bottom: 1px solid var(--zy-border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: var(--zy-bg);
    }
    .zywrap-drawer-header h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: var(--zy-text);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .zywrap-drawer-close {
        background: transparent;
        border: none;
        color: var(--zy-text-muted);
        cursor: pointer;
        padding: 5px;
    }
    .zywrap-drawer-close:hover { color: var(--zy-text); }

    /* Body */
    .zywrap-drawer-body {
        padding: 20px;
        overflow-y: auto;
        flex-grow: 1;
    }
    .zywrap-drawer-body::-webkit-scrollbar { width: 6px; }
    .zywrap-drawer-body::-webkit-scrollbar-thumb { background: var(--zy-border); border-radius: 4px; }

    .zywrap-input-group { margin-bottom: 16px; }
    .zywrap-input-group label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--zy-text-muted);
        margin-bottom: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .zywrap-input-group select, 
    .zywrap-input-group input, 
    .zywrap-input-group textarea {
        width: 100%;
        background: var(--zy-panel);
        border: 1px solid var(--zy-border);
        color: var(--zy-text);
        border-radius: 6px;
        padding: 10px 12px;
        font-size: 14px;
    }
    
    .zywrap-input-group select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%239ca3af%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
        background-repeat: no-repeat;
        background-position: right 12px top 50%;
        background-size: 12px auto;
        padding-right: 32px;
    }
    
    .zywrap-input-group select:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: rgba(31, 41, 55, 0.5);
    }

    .zywrap-input-group select:focus, 
    .zywrap-input-group input:focus, 
    .zywrap-input-group textarea:focus {
        outline: none;
        border-color: var(--zy-accent);
    }

    /* Advanced Overrides */
    .zywrap-overrides-details {
        margin-top: 20px;
        margin-bottom: 20px;
        border: 1px solid var(--zy-border);
        border-radius: 6px;
        overflow: hidden;
    }
    .zywrap-overrides-details summary {
        padding: 12px;
        background: rgba(0,0,0,0.2);
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: var(--zy-text);
        user-select: none;
    }
    .zywrap-overrides-details summary:focus { outline: none; }
    .zywrap-overrides-content {
        padding: 16px;
        background: var(--zy-bg);
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .zywrap-overrides-content .zywrap-input-group { margin-bottom: 0; }

    /* Actions */
    .zywrap-drawer-actions {
        padding: 20px;
        border-top: 1px solid var(--zy-border);
        background: var(--zy-panel);
    }
    .zywrap-btn-primary {
        width: 100%;
        background: var(--zy-accent);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
    .zywrap-btn-primary:hover { background: var(--zy-accent-hover); }
    .zywrap-btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

    /* Result Box */
    #zywrap-drawer-result-box {
        display: none;
        margin-top: 16px;
        padding: 16px;
        background: rgba(0,0,0,0.2);
        border: 1px solid var(--zy-border);
        border-radius: 6px;
    }
    #zywrap-drawer-result-text {
        font-size: 13px;
        line-height: 1.6;
        color: var(--zy-text);
        margin-bottom: 12px;
        max-height: 350px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-word;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .zywrap-btn-insert {
        background: #10b981;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        width: 100%;
    }
    .zywrap-btn-insert:hover { background: #059669; }
</style>

<button id="zywrap-copilot-toggle" title="Open Zywrap Copilot">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" /></svg>
</button>

<div id="zywrap-copilot-drawer">
    <div class="zywrap-drawer-header">
        <h2>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px; color: var(--zy-accent);"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
            Zywrap Copilot
        </h2>
        <button class="zywrap-drawer-close" id="zywrap-drawer-close">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 20px; height: 20px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
    </div>

    <div class="zywrap-drawer-body">
        
        <div style="margin-bottom: 20px; padding: 12px; background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.2); border-radius: 6px; font-size: 12px; color: #a5b4fc; text-align: center;">
            Call AI by Code. Zero Prompt Engineering.
        </div>

        <div class="zywrap-input-group">
            <label>Category</label>
            <select id="zywrap_drawer_category">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat) : ?>
                    <option value="<?php echo esc_attr($cat->code); ?>"><?php echo esc_html($cat->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="zywrap-input-group">
            <label>AI Solution</label>
            <select id="zywrap_drawer_usecase" disabled>
                <option value="">-- Select Category First --</option>
            </select>
        </div>

        <div class="zywrap-input-group">
            <label>Configuration Style</label>
            <select id="zywrap_drawer_wrapper" disabled>
                <option value="">-- Select Solution First --</option>
            </select>
        </div>

        <div class="zywrap-input-group">
            <label>AI Model</label>
            <select id="zywrap_drawer_model">
                <option value="">-- Default Model --</option>
                <?php foreach ($models as $model) : ?>
                    <option value="<?php echo esc_attr($model->code); ?>"><?php echo esc_html($model->name); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="zywrap_drawer_schema_container"></div>

        <div class="zywrap-input-group" style="margin-top: 20px;">
            <label style="display: flex; justify-content: space-between; align-items: center;">
                <span>Prompt / Additional Instructions</span>
                <a href="#" id="zywrap_drawer_read_editor" style="color: var(--zy-accent); text-decoration: none; font-size: 11px;">READ EDITOR TEXT</a>
            </label>
            <textarea id="zywrap_drawer_prompt" rows="4" placeholder="Type your request..."></textarea>
        </div>

        <details class="zywrap-overrides-details">
            <summary>Advanced Overrides</summary>
            <div class="zywrap-overrides-content">
                
                <div class="zywrap-input-group" style="grid-column: 1 / -1;">
                    <label>Target Language</label>
                    <select id="zywrap_drawer_language">
                        <option value="">-- English (Default) --</option>
                        <?php foreach ($languages as $lang) : ?>
                            <option value="<?php echo esc_attr($lang->code); ?>"><?php echo esc_html($lang->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php 
                $override_fields = [
                    'toneCode' => ['label' => 'Tone', 'data' => $templates['tones'] ?? []],
                    'styleCode' => ['label' => 'Style', 'data' => $templates['styles'] ?? []],
                    'formatCode' => ['label' => 'Formatting', 'data' => $templates['formattings'] ?? []],
                    'complexityCode' => ['label' => 'Complexity', 'data' => $templates['complexities'] ?? []],
                    'lengthCode' => ['label' => 'Length', 'data' => $templates['lengths'] ?? []],
                    'audienceCode' => ['label' => 'Audience', 'data' => $templates['audienceLevels'] ?? []],
                    'responseGoalCode' => ['label' => 'Goal', 'data' => $templates['responseGoals'] ?? []],
                    'outputCode' => ['label' => 'Output Type', 'data' => $templates['outputTypes'] ?? []],
                ];
                foreach($override_fields as $id => $field): 
                ?>
                    <div class="zywrap-input-group">
                        <label><?php echo esc_html($field['label']); ?></label>
                        <select id="<?php echo esc_attr($id); ?>" class="drawer-override-select">
                            <option value="">-- Default --</option>
                            <?php foreach ($field['data'] as $opt) : ?>
                                <option value="<?php echo esc_attr($opt->code); ?>"><?php echo esc_html($opt->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>

        <div id="zywrap-drawer-result-box">
            <div id="zywrap-drawer-result-text"></div>
            <button type="button" class="zywrap-btn-insert" id="zywrap-drawer-insert-btn">Insert into Editor</button>
        </div>
    </div>

    <div class="zywrap-drawer-actions">
        <button type="button" id="zywrap-drawer-run" class="zywrap-btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" /></svg>
            Generate Content
        </button>
    </div>
</div>



<script>
jQuery(document).ready(function($) {
    const ajaxurl = '<?php echo esc_url(admin_url("admin-ajax.php")); ?>';
    const drawer = $('#zywrap-copilot-drawer');
    const toggleBtn = $('#zywrap-copilot-toggle');
    const closeBtn = $('#zywrap-drawer-close');

    // UI Toggles
    toggleBtn.on('click', () => drawer.addClass('zywrap-drawer-open'));
    closeBtn.on('click', () => drawer.removeClass('zywrap-drawer-open'));

    // Cascade 1: Category -> Use Case
    $('#zywrap_drawer_category').on('change', function() {
        let catCode = $(this).val();
        let ucSelect = $('#zywrap_drawer_usecase');
        let wrapSelect = $('#zywrap_drawer_wrapper');
        
        $('#zywrap_drawer_schema_container').empty();
        wrapSelect.empty().append('<option value="">-- Select Solution First --</option>').prop('disabled', true);

        if (!catCode) {
            ucSelect.empty().append('<option value="">-- Select Category First --</option>').prop('disabled', true);
            return;
        }

        ucSelect.prop('disabled', true).empty().append('<option value="">Loading...</option>');

        $.post(ajaxurl, {
            action: 'geekybot_ajax', geekybotme: 'zywrap', task: 'get_use_cases_by_category',
            category_code: catCode,
            '_wpnonce': '<?php echo esc_attr(wp_create_nonce("zywrap_get_wrappers")); ?>'
        }, function(res) {
            if (res.success && res.data.length > 0) {
                ucSelect.empty().append('<option value="">-- Select a Solution --</option>');
                res.data.forEach(uc => ucSelect.append(new Option(uc.name, uc.code)));
                ucSelect.prop('disabled', false);
            } else {
                ucSelect.empty().append('<option value="">-- No Solutions Found --</option>');
            }
        });
    });

    // Cascade 2: Use Case -> Wrapper
    $('#zywrap_drawer_usecase').on('change', function() {
        let ucCode = $(this).val();
        let wrapSelect = $('#zywrap_drawer_wrapper');
        $('#zywrap_drawer_schema_container').empty();

        if (!ucCode) {
            wrapSelect.empty().append('<option value="">-- Select Solution First --</option>').prop('disabled', true);
            return;
        }

        wrapSelect.prop('disabled', true).empty().append('<option value="">Loading...</option>');

        $.post(ajaxurl, {
            action: 'geekybot_ajax', geekybotme: 'zywrap', task: 'get_wrappers_by_usecase',
            usecase_code: ucCode,
            '_wpnonce': '<?php echo esc_attr(wp_create_nonce("zywrap_get_wrappers")); ?>'
        }, function(res) {
            if (res.success && res.data.length > 0) {
                wrapSelect.empty().append('<option value="">-- Select a Style --</option>');
                let autoSelect = null;
                res.data.forEach((w, i) => {
                    let parts = w.name.split('—');
                    let name = w.base == 1 ? '✨ Base - ' + $.trim(parts[0]) : '↳ ' + (parts.length > 1 ? $.trim(parts[1]) : w.name);
                    wrapSelect.append(new Option(name, w.code));
                    if (w.base == 1 && !autoSelect) autoSelect = w.code;
                    else if (i === 0 && !autoSelect) autoSelect = w.code;
                });
                wrapSelect.prop('disabled', false);
                if (autoSelect) wrapSelect.val(autoSelect).trigger('change');
            } else {
                wrapSelect.empty().append('<option value="">-- No Styles Found --</option>');
            }
        });
    });

    // Cascade 3: Wrapper -> Schema
    $('#zywrap_drawer_wrapper').on('change', function() {
        let wCode = $(this).val();
        let container = $('#zywrap_drawer_schema_container');
        container.empty();

        if (!wCode) return;

        $.post(ajaxurl, {
            action: 'geekybot_ajax', geekybotme: 'zywrap', task: 'get_wrapper_schema',
            wrapper_code: wCode,
            '_wpnonce': '<?php echo esc_attr(wp_create_nonce("zywrap_execute_proxy")); ?>'
        }, function(res) {
            if (res.success && res.data && (res.data.req || res.data.opt)) {
                let html = '';
                const buildInputs = (data) => {
                    if (!data || Object.keys(data).length === 0) return '';
                    let str = '';
                    for (let key in data) {
                        let label = key.replace(/([A-Z])/g, ' $1').replace(/^./, s => s.toUpperCase());
                        let p = data[key].p ? `placeholder="${data[key].d || ''}"` : `value="${data[key].d || ''}"`;
                        str += `<div class="zywrap-input-group">
                            <label>${label}</label>
                            <input type="text" class="drawer-schema-input" data-key="${key}" ${p}>
                        </div>`;
                    }
                    return str;
                };
                html += buildInputs(res.data.req);
                html += buildInputs(res.data.opt);
                container.html(html);
            }
        });
    });

    // Helper: Universal Editor Text Reader
    $('#zywrap_drawer_read_editor').on('click', function(e) {
        e.preventDefault();
        let text = '';
        
        // Check Gutenberg
        if (window.wp && wp.data && wp.data.select('core/editor')) {
            let selected = wp.data.select('core/block-editor').getSelectedBlock();
            if (selected && selected.attributes && selected.attributes.content) {
                text = selected.attributes.content.replace(/(<([^>]+)>)/gi, "");
            } else {
                text = wp.data.select('core/editor').getEditedPostContent().replace(/(<([^>]+)>)/gi, "");
            }
        } 
        // Check Classic Editor (TinyMCE)
        else if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            text = tinyMCE.activeEditor.selection.getContent({format: 'text'});
            if (!text) text = tinyMCE.activeEditor.getContent({format: 'text'});
        } 
        // Check standard textarea fallback
        else if ($('#content').length) {
            text = $('#content').val();
        }

        if (text) {
            $('#zywrap_drawer_prompt').val($.trim(text));
        } else {
            alert('No text found in the editor.');
        }
    });

    // Execute Generation
    $('#zywrap-drawer-run').on('click', function() {
        let btn = $(this);
        let resBox = $('#zywrap-drawer-result-box');
        let resText = $('#zywrap-drawer-result-text');
        
        let wCode = $('#zywrap_drawer_wrapper').val();
        let mCode = $('#zywrap_drawer_model').val();
        let langCode = $('#zywrap_drawer_language').val();

        if (!wCode) { alert('Please select a Configuration Style.'); return; }

        btn.prop('disabled', true).html('Generating...');
        resBox.show();
        resText.text('Calling AI... please wait.');

        // Get Schema inputs
        let finalPrompt = $('#zywrap_drawer_prompt').val().trim();
        let variables = {};
        let struct = [];

        $('.drawer-schema-input').each(function() {
            let val = $(this).val().trim();
            if (val) {
                variables[$(this).data('key')] = val;
                struct.push($(this).data('key') + ': ' + val);
            }
        });

        let structStr = struct.join('\n');
        if (finalPrompt && structStr) finalPrompt += '\n\n' + structStr;
        else if (structStr) finalPrompt = structStr;

        // Get Overrides
        let overrides = {};
        $('.drawer-override-select').each(function() {
            let val = $(this).val();
            if (val) overrides[$(this).attr('id')] = val;
        });

        $.post(ajaxurl, {
            action: 'geekybot_ajax', geekybotme: 'zywrap', task: 'execute_zywrap_proxy',
            wrapperCode: wCode,
            model: mCode,
            language: langCode,
            prompt: finalPrompt,
            variables: variables,
            overrides: overrides,
            '_wpnonce': '<?php echo esc_attr(wp_create_nonce("zywrap_execute_proxy")); ?>'
        }, function(res) {
            if (res.success && res.data.output) {
                let rawOutput = res.data.output;
                let finalDisplayOutput = rawOutput;
                
                // Formatter: Auto-Parse and indent JSON for display
                if (rawOutput) {
                    let cleanedOutput = rawOutput.replace(/^```json\s*/i, '').replace(/\s*```$/i, '');
                    try {
                        let jsonObject = JSON.parse(cleanedOutput);
                        finalDisplayOutput = JSON.stringify(jsonObject, null, 2);
                    } catch (e) {
                        finalDisplayOutput = cleanedOutput;
                    }
                }
                
                resText.text(finalDisplayOutput);
            } else {
                resText.text(res.data.message || 'Error generating content.');
            }
            btn.prop('disabled', false).html('Generate Content');
        }).fail(function() {
            resText.text('Network or Server Error.');
            btn.prop('disabled', false).html('Generate Content');
        });
    });

    // Helper: Universal Editor Insert
    $('#zywrap-drawer-insert-btn').on('click', function() {
        let text = $('#zywrap-drawer-result-text').text();
        if (!text) return;

        let htmlText = '';
        let isJson = false;

        // 1. Check if the output is pure JSON
        try {
            let jsonObj = JSON.parse(text);
            isJson = true;
            let formattedJson = JSON.stringify(jsonObj, null, 2);
            htmlText = '<pre><code>' + formattedJson + '</code></pre>';
        } catch(e) {}

        // 2. If it's not JSON, parse the Markdown using marked.js
        if (!isJson) {
            if (typeof marked !== 'undefined') {
                htmlText = marked.parse(text);
            } else {
                // Fallback if marked fails to load (basic replacement)
                htmlText = '<p>' + text.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br />') + '</p>';
            }
        }

        // Insert into Gutenberg
        if (window.wp && wp.data && wp.data.dispatch('core/block-editor') && wp.blocks) {
            try {
                // Let Gutenberg's rawHandler parse the proper HTML tags into blocks!
                let blocks = wp.blocks.rawHandler({ HTML: htmlText });
                wp.data.dispatch('core/block-editor').insertBlocks(blocks);
            } catch(e) {
                // safe fallback block
                const block = wp.blocks.createBlock('core/html', { content: htmlText });
                wp.data.dispatch('core/block-editor').insertBlock(block);
            }
        } 
        // Insert into Classic Editor (TinyMCE)
        else if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            tinyMCE.activeEditor.execCommand('mceInsertContent', false, htmlText);
        } 
        // Standard textarea fallback
        else if ($('#content').length) {
            let current = $('#content').val();
            $('#content').val(current + '\n\n' + text);
        } 
        else {
            alert('Could not detect active editor. Text copied to clipboard instead!');
            navigator.clipboard.writeText(text);
        }

        // Close drawer after insert
        drawer.removeClass('zywrap-drawer-open');
    });
});
</script>

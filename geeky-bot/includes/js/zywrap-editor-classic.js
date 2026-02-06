/*
 * This is the new JavaScript file for the Classic Editor.
 * It is loaded by the function 'load_classic_editor_assets' in geeky-bot.php
 *
 */
(function($) {
    'use strict';

    // We will run this code when the ENTIRE window (including all scripts) has loaded,
    // not just the document. This is more robust.
    $(window).on('load', function() {

        // Get all the data passed from PHP
        const {
            categories,
            models,
            languages,
            templates,
            wrappers_nonce,
            execute_nonce,
            ajax_url,
            loading_text,
            generating_text,
            run_text,
            error_text,
            validation_text
        } = zywrapClassicData;

        // --- Cache jQuery Selectors ---
        const $modalBackdrop = $('#zywrap-classic-modal-backdrop');
        const $modalWrap = $('#zywrap-classic-modal-wrap');
        const $runButton = $('#zywrap-classic-run');
        const $spinner = $('#zywrap-classic-spinner');
        
        const $categorySelect = $('#zywrap-classic-category');
        const $showBaseCheck = $('#zywrap-classic-base');
        const $showFeaturedCheck = $('#zywrap-classic-featured');
        const $wrapperSelect = $('#zywrap-classic-wrapper');
        const $modelSelect = $('#zywrap-classic-model');
        const $languageSelect = $('#zywrap-classic-language');
        const $promptText = $('#zywrap-classic-prompt');
        const $overridesGrid = $('#zywrap-classic-modal-overrides-grid');

        // --- 1. Modal Open/Close Events ---
        
        const $openButton = $('#zywrap-open-modal-button');
        
        if ($openButton.length) {
            $openButton.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Stop other scripts from interfering
                $modalBackdrop.show();
                $modalWrap.show();
                return false; // Force stop
            });
        }

        // Close the modal
        $('#zywrap-classic-modal-close, #zywrap-classic-modal-backdrop').on('click', function(e) {
            e.preventDefault();
            $modalBackdrop.hide();
            $modalWrap.hide();
        });

        // --- 2. Populate Dropdowns ---
        function populateSelect($select, options, defaultLabel) {
            $select.empty();
            if (defaultLabel) {
                $select.append(new Option(defaultLabel, ''));
            }
            options.forEach(opt => {
                $select.append(new Option(opt.name, opt.code));
            });
            $select.trigger('change');
        }

        // Populate static dropdowns
        populateSelect($categorySelect, categories, 'Select Category...');
        populateSelect($modelSelect, models, 'Model (Default)'); 
        populateSelect($languageSelect, languages, 'Language (Optional)');

        // Populate override dropdowns
        const overrideMap = {
            tones: 'Tone', styles: 'Style', formattings: 'Format',
            complexities: 'Complexity', lengths: 'Length', audienceLevels: 'Audience',
            responseGoals: 'Goal', outputTypes: 'Output'
        };

        $.each(overrideMap, function(key, label) {
            const options = templates[key] || [];
            if (options.length > 0) {
                const $select = $('<select class="zywrap-classic-select2 zywrap-classic-override"></select>')
                    .attr('id', 'zywrap-classic-' + key)
                    .attr('data-key', key);
                
                $select.append(new Option(label + ' (Default)', ''));
                options.forEach(opt => {
                    $select.append(new Option(opt.name, opt.code));
                });
                $overridesGrid.append($('<div></div>').append($select));
            }
        });

        // Initialize all Select2 elements
        $('.zywrap-classic-select2').select2({
            // This is needed for Select2 to work inside a modal
            dropdownParent: $('#zywrap-classic-modal-wrap') 
        });


        // --- 3. AJAX: Get Wrappers on Category Change ---
        function fetchWrappers() {
            const categoryCode = $categorySelect.val();
            const showBase = $showBaseCheck.is(':checked');
            const showFeatured = $showFeaturedCheck.is(':checked');

            if (!categoryCode) {
                $wrapperSelect.empty().append(new Option('Select Category First', '')).prop('disabled', true).trigger('change');
                return;
            }

            $wrapperSelect.empty().append(new Option(loading_text, '')).prop('disabled', true).trigger('change');

            $.post(ajax_url, {
                action: 'geekybot_ajax',
                geekybotme: 'zywrap',
                task: 'get_wrappers_by_category',
                _wpnonce: wrappers_nonce,
                category_code: categoryCode,
                show_featured: showFeatured,
                show_base: showBase,
            })
            .done(function(response) {
                if (response.success) {
                    $wrapperSelect.empty().append(new Option('Select Wrapper...', ''));
                    response.data.forEach(w => {
                        $wrapperSelect.append(new Option(w.name, w.code));
                    });
                    $wrapperSelect.prop('disabled', false).trigger('change');
                }
            });
        }
        
        // Trigger the fetch function when category or checkboxes change
        $categorySelect.on('change', fetchWrappers);
        $showBaseCheck.on('change', fetchWrappers);
        $showFeaturedCheck.on('change', fetchWrappers);


        // --- 4. AJAX: Run Generation ---
        $runButton.on('click', function() {
            const model = $modelSelect.val();
            const wrapperCode = $wrapperSelect.val();
            const prompt = $promptText.val();

            if (!wrapperCode) {
                alert('Please select a Wrapper.');
                return;
            }

            $spinner.addClass('is-active');
            $runButton.prop('disabled', true).text(generating_text);

            // Collect overrides
            let overrides = {};
            $('.zywrap-classic-override').each(function() {
                const key = $(this).data('key');
                const value = $(this).val();
                if (value) {
                    overrides[key] = value;
                }
            });

            const payload = {
                model: model, // Will send empty string if default
                wrapperCode: wrapperCode,
                prompt: prompt, // Will send empty string if not filled
                language: $languageSelect.val(),
                overrides: overrides,
                action: 'geekybot_ajax',
                geekybotme: 'zywrap',
                task: 'execute_zywrap_proxy',
                _wpnonce: execute_nonce
            };

            $.post(ajax_url, payload)
            .done(function(response) {
                if (response.success && response.data.output) {
                    // Insert content into the Classic Editor
                    window.send_to_editor('<p>' + response.data.output.replace(/\n/g, '<br>') + '</p>');
                    
                    // Close modal
                    $modalBackdrop.hide();
                    $modalWrap.hide();
                } else {
                    alert(error_text + ' ' + (response.data.message || 'Unknown error'));
                }
            })
            .fail(function() {
                alert('An unknown AJAX error occurred.');
            })
            .always(function() {
                $spinner.removeClass('is-active');
                $runButton.prop('disabled', false).text(run_text);
            });
        });
    });

})(jQuery);
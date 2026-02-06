(function(wp) {
    // WordPress dependencies
    const { registerPlugin } = wp.plugins;
    const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
    const { PanelBody } = wp.components;
    const { __ } = wp.i18n;
    const { createElement, useState, useEffect } = wp.element;
    const { SelectControl, TextareaControl, Button, Spinner, CheckboxControl } = wp.components;
    const { dispatch } = wp.data;
    const { ajax } = wp;

    // Get all the data we passed from PHP
    const {
        categories,
        models,
        languages,
        templates,
        wrappers_nonce,
        execute_nonce,
        has_api_key,
        settings_url,
        warning_key,
        warning_sync,
        plugin_url
    } = zywrapEditorData;

    const ZywrapIcon = createElement('svg', { width: 24, height: 24, viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
        createElement('path', { 
            fill: 'currentColor', 
            d: 'M19 3H5C3.9 3 3 3.9 3 5v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM8 17H6v-2h2v2zm0-4H6v-2h2v2zm0-4H6V7h2v2zm10 8h-8v-2h8v2zm0-4h-8v-2h8v2zm0-4h-8V7h8v2z' 
        })
    );

    // --- 1. Build the React Component for the Sidebar ---
    const ZywrapEditorPanel = () => {

        // --- State Variables ---
        const [category, setCategory] = useState('');
        const [wrapper, setWrapper] = useState('');
        const [model, setModel] = useState('');
        const [language, setLanguage] = useState('');
        const [prompt, setPrompt] = useState('');
        const [isLoading, setIsLoading] = useState(false);
        const [isWrapperLoading, setIsWrapperLoading] = useState(false);
        const [wrappers, setWrappers] = useState([]);
        const [overrides, setOverrides] = useState({});
        const [showBase, setShowBase] = useState(false);
        const [showFeatured, setShowFeatured] = useState(false);

        // --- WARNING LOGIC ---
        if (!has_api_key) {
            return createElement(PanelBody, { title: __('Generate Content', 'geeky-bot'), initialOpen: true },
                createElement('div', { className: 'geekybot-setup-notice', style: { backgroundColor: '#fff', borderLeft: '4px solid #ffb900', boxShadow: '0 1px 1px rgba(0,0,0,.04)', padding: '12px', marginBottom: '15px' } },
                    createElement('a', { href: settings_url, style: { color: '#d63638', textDecoration: 'none', fontWeight: 'bold', fontSize: '13px', display: 'flex', alignItems: 'center' } },
                        createElement('img', { src: plugin_url + 'includes/images/admin-notification.png', style: { width: '20px', height: '20px', marginRight: '10px' } }),
                        warning_key
                    )
                )
            );
        }

        if (categories.length < 1) {
             return createElement(PanelBody, { title: __('Generate Content', 'geeky-bot'), initialOpen: true },
                createElement('div', { className: 'geekybot-setup-notice', style: { backgroundColor: '#fff', borderLeft: '4px solid #ffb900', boxShadow: '0 1px 1px rgba(0,0,0,.04)', padding: '12px', marginBottom: '15px' } },
                    createElement('a', { href: settings_url, style: { color: '#d63638', textDecoration: 'none', fontWeight: 'bold', fontSize: '13px', display: 'flex', alignItems: 'center' } },
                        createElement('img', { src: plugin_url + 'includes/images/admin-notification.png', style: { width: '20px', height: '20px', marginRight: '10px' } }),
                        warning_sync
                    )
                )
            );
        }
        // --- END WARNING LOGIC ---

        // --- Data for Dropdowns ---
        const categoryOptions = [
            { label: __('Select Category...'), value: '' },
            ...categories.map(c => ({ label: c.name, value: c.code }))
        ];
        const modelOptions = [
            { label: __('Model (Default)'), value: '' },
            ...models.map(m => ({ label: m.name, value: m.code }))
        ];
        const languageOptions = [
            { label: __('Language (Optional)'), value: '' },
            ...languages.map(l => ({ label: l.name, value: l.code }))
        ];
        
        // --- Helper to build override dropdowns ---
        const createOverrideSelect = (type, label) => {
            const options = templates[type] || [];
            
            return createElement(SelectControl, {
                label: __(label, 'geeky-bot'),
                value: overrides[type] || '',
                options: [
                    { label: `${label} (Default)`, value: '' },
                    ...options.map(o => ({ label: o.name, value: o.code }))
                ],
                onChange: (value) => setOverrides(prev => ({ ...prev, [type]: value }))
            });
        };

        // --- AJAX: Get Wrappers when Category changes ---
        useEffect(() => {
            if (!category) {
                setWrappers([]);
                return;
            }
            setIsWrapperLoading(true);

            ajax.post( 'geekybot_ajax', {
                geekybotme: 'zywrap',
                task: 'get_wrappers_by_category',
                _wpnonce: wrappers_nonce,
                category_code: category,
                show_featured: showFeatured,
                show_base: showBase,
            })
            // .done() receives the 'data' payload directly
            .done((wrappersArray) => {
                // 'wrappersArray' IS the array of wrappers
                setWrappers(wrappersArray.map(w => ({ label: w.name, value: w.code })));
                setIsWrapperLoading(false);
            })
            // .fail() receives the error object from wp_send_json_error
            .fail((error) => {
                alert(__('Error:', 'geeky-bot') + ' ' + (error.message || 'Unknown error'));
                setIsWrapperLoading(false);
            });
        }, [category, showBase, showFeatured]);

        // --- AJAX: Run the Zywrap API Call ---
        const handleGenerate = () => {
            if (!wrapper) {
                alert(__('Please select a Wrapper.', 'geeky-bot'));
                return;
            }

            setIsLoading(true);
            
            const payload = {
                model: model, 
                wrapperCode: wrapper,
                prompt: prompt, 
                language: language,
                overrides: {}
            };
            
            // Add non-empty overrides
            for (const key in overrides) {
                if (overrides[key]) {
                    payload[key] = overrides[key]; // Send overrides at the top level
                }
            }
            if (Object.keys(payload.overrides).length === 0) {
                delete payload.overrides;
            }

            ajax.post( 'geekybot_ajax', {
                geekybotme: 'zywrap',
                task: 'execute_zywrap_proxy',
                _wpnonce: execute_nonce,
                ...payload
            })
            // .done() receives the 'data' payload directly
            .done((apiResponse) => {
                // 'apiResponse' is the object from the proxy
                if (apiResponse && apiResponse.output) {
                    const newBlock = wp.blocks.createBlock('core/paragraph', {
                        content: apiResponse.output,
                    });
                    dispatch('core/editor').insertBlocks(newBlock);
                } else {
                    alert(__('Error:', 'geeky-bot') + ' ' + (apiResponse.message || 'Received empty output.'));
                }
                setIsLoading(false);
            })
            // .fail() receives the error object from wp_send_json_error
            .fail((error) => {
                alert(__('Error:', 'geeky-bot') + ' ' + (error.message || 'Unknown error'));
                setIsLoading(false);
            });
        };

        // --- Render the UI ---
        return createElement(PanelBody, { title: __('Generate Content', 'geeky-bot'), initialOpen: true },
            createElement(SelectControl, {
                label: __('1. Category', 'geeky-bot'),
                value: category,
                options: categoryOptions,
                onChange: (value) => {
                    setCategory(value);
                    setWrapper(''); // Reset wrapper dropdown
                }
            }),
            
            createElement('div', { style: { display: 'flex', gap: '10px', marginBottom: '15px', marginTop: '5px' } },
                createElement(CheckboxControl, {
                    label: __('Base Only', 'geeky-bot'),
                    checked: showBase,
                    onChange: setShowBase // This is shorthand for (val) => setShowBase(val)
                }),
                createElement(CheckboxControl, {
                    label: __('Featured Only', 'geeky-bot'),
                    checked: showFeatured,
                    onChange: setShowFeatured
                })
            ),

            createElement(SelectControl, {
                label: __('2. Wrapper', 'geeky-bot'),
                value: wrapper,
                options: [
                    { label: isWrapperLoading ? __('Loading...') : __('Select Wrapper...'), value: '' },
                    ...wrappers
                ],
                onChange: (value) => setWrapper(value),
                disabled: isWrapperLoading || !category
            }),
            createElement(SelectControl, {
                label: __('3. AI Model', 'geeky-bot'),
                value: model,
                options: modelOptions,
                onChange: (value) => setModel(value)
            }),
            createElement(SelectControl, {
                label: __('4. Language', 'geeky-bot'),
                value: language,
                options: languageOptions,
                onChange: (value) => setLanguage(value)
            }),
            createElement(TextareaControl, {
                label: __('5. Prompt (Optional)', 'geeky-bot'),
                value: prompt,
                onChange: (value) => setPrompt(value),
                rows: 5
            }),
            createElement(PanelBody, { title: __('6. Overrides (Optional)', 'geeky-bot'), initialOpen: false },
                createOverrideSelect('tones', 'Tone'),
                createOverrideSelect('styles', 'Style'),
                createOverrideSelect('formattings', 'Format'),
                createOverrideSelect('complexities', 'Complexity'),
                createOverrideSelect('lengths', 'Length'),
                createOverrideSelect('audienceLevels', 'Audience'),
                createOverrideSelect('responseGoals', 'Goal'),
                createOverrideSelect('outputTypes', 'Output')
            ),
            createElement(Button, {
                isPrimary: true,
                isBusy: isLoading,
                onClick: handleGenerate
            }, isLoading ? __('Generating...', 'geeky-bot') : __('Generate & Insert', 'geeky-bot'))
        );
    };

    // --- 2. Register the Plugin with WordPress ---
    registerPlugin('geekybot-zywrap-sidebar', {
        icon: ZywrapIcon,
        render: () => {
            return createElement(
                PluginSidebar,
                {
                    name: 'geekybot-zywrap-sidebar',
                    title: __('Content Generation', 'geeky-bot'),
                },
                createElement(ZywrapEditorPanel, {})
            );
        }
    });
})(window.wp);
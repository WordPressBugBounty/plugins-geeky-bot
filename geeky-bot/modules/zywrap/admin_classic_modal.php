<?php
if (!defined('ABSPATH'))
    die('Restricted Access');

// === CHECK STATUS ===
$geekybot_saved_key = get_option('geekybot_zywrap_api_key', '');
global $wpdb;

// Safe count check with prepare not strictly needed for count(*) but good practice handled here for simplicity
$table_name = $wpdb->prefix . 'geekybot_zywrap_categories';
// Ensure table exists to prevent fatal error on fresh install before sync
if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
    $geekybot_categories_count = 0;
} else {
    $geekybot_categories_count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
}
// ====================
?>

<style>
    #zywrap-classic-modal-backdrop {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: #000;
        opacity: 0.7;
        z-index: 100000;
    }
    #zywrap-classic-modal-wrap {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        background: #f9f9f9;
        border-radius: 8px;
        box-shadow: 0 5px 30px rgba(0,0,0,0.3);
        z-index: 100001;
    }
    #zywrap-classic-modal-header {
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    #zywrap-classic-modal-header h2 {
        margin: 0;
        font-size: 20px;
    }
    #zywrap-classic-modal-close {
        font-size: 24px;
        text-decoration: none;
        color: #666;
        line-height: 1;
    }
    #zywrap-classic-modal-content {
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    #zywrap-classic-modal-content label {
        font-weight: 600;
        margin-bottom: 5px;
        display: block;
    }
    #zywrap-classic-modal-content .select2-container {
        width: 100% !important;
    }
    #zywrap-classic-modal-content textarea {
        width: 100%;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
    #zywrap-classic-modal-overrides {
        padding: 10px 15px;
        background: #eee;
        border-radius: 4px;
    }
    #zywrap-classic-modal-overrides-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    #zywrap-classic-modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #ddd;
        text-align: right;
    }
    /* Style for the new checkboxes */
    .zywrap-classic-geekybot-checkbox-group {
        display: flex;
        gap: 15px;
        margin-top: -5px;
        margin-bottom: 5px;
        padding-left: 5px;
    }
    .zywrap-classic-geekybot-checkbox-group label {
        font-weight: normal;
        display: flex;
        align-items: center;
        gap: 5px;
        margin-bottom: 0;
    }

    /* === NEW: Warning CSS === */
    .geekybot-setup-notice {
        background-color: #fff;
        border-left: 4px solid #ffb900;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        margin: 10px 0;
        padding: 12px;
    }
    .geekybot-setup-notice a {
        color: #d63638;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px;
        display: flex;
        align-items: center;
    }
    .geekybot-setup-notice a:hover {
        text-decoration: underline;
    }
</style>

<div id="zywrap-classic-modal-backdrop"></div>
<div id="zywrap-classic-modal-wrap">
    <div id="zywrap-classic-modal-header">
        <h2><?php echo esc_html__('Generate Content', 'geeky-bot'); ?></h2>
        <a href="#" id="zywrap-classic-modal-close" title="<?php echo esc_attr__('Close', 'geeky-bot'); ?>">&times;</a>
    </div>
    <div id="zywrap-classic-modal-content">

        <?php if(empty($geekybot_saved_key)): ?>
            <div class="geekybot-setup-notice">
                <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap")); ?>">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/admin-notification.png" style="width: 20px; height: 20px; margin-right: 10px;" alt="Warning" />
                    <?php echo esc_html(__('Setup Required: Please add your API Key in settings.', 'geeky-bot')); ?>
                </a>
            </div>
        <?php elseif($geekybot_categories_count < 1): ?>
            <div class="geekybot-setup-notice">
                <a href="<?php echo esc_url(admin_url("admin.php?page=geekybot_zywrap&geekybotlt=zywrap")); ?>">
                    <img src="<?php echo esc_url(GEEKYBOT_PLUGIN_URL); ?>includes/images/admin-notification.png" style="width: 20px; height: 20px; margin-right: 10px;" alt="Warning" />
                    <?php echo esc_html(__('Action Needed: Please sync the Wrapper Catalog (Step 2) in settings.', 'geeky-bot')); ?>
                </a>
            </div>
        <?php else: ?>
        <div>
            <label for="zywrap-classic-category"><?php echo esc_html(__('1.', 'geeky-bot')).' '.esc_html__('Category', 'geeky-bot'); ?></label>
            <select id="zywrap-classic-category" class="zywrap-classic-select2"></select>
        </div>

        <div class="zywrap-classic-geekybot-checkbox-group">
            <label>
                <input type="checkbox" id="zywrap-classic-base" />
                <?php echo esc_html__('Base Only', 'geeky-bot'); ?>
            </label>
            <label>
                <input type="checkbox" id="zywrap-classic-featured" />
                <?php echo esc_html__('Featured Only', 'geeky-bot'); ?>
            </label>
        </div>

        <div>
            <label for="zywrap-classic-wrapper"><?php echo esc_html(__('2.', 'geeky-bot')).' '.esc_html__('Wrapper', 'geeky-bot'); ?></label>
            <select id="zywrap-classic-wrapper" class="zywrap-classic-select2" disabled></select>
        </div>
        <div>
            <label for="zywrap-classic-model"><?php echo esc_html(__('3.', 'geeky-bot')).' '.esc_html__('AI Model (Optional)', 'geeky-bot'); ?></label>
            <select id="zywrap-classic-model" class="zywrap-classic-select2">
            </select>
        </div>
        
        <div class="zywrap-classic-geekybot-checkbox-group" style="margin-top: 10px; background: #e8f0fe; padding: 10px; border-radius: 4px; border: 1px solid #d2e3fc;">
            <label style="color: #1a73e8; font-weight: 600;">
                <input type="checkbox" id="zywrap-classic-use-context" />
                <span class="dashicons dashicons-text-page" style="margin-right:3px;"></span>
                <?php echo esc_html__('Read Current Post/Selection as Context', 'geeky-bot'); ?>
            </label>
        </div>
        <div>
            <label for="zywrap-classic-language"><?php echo esc_html(__('4.', 'geeky-bot')).' '.esc_html__('Language (Optional)', 'geeky-bot'); ?></label>
            <select id="zywrap-classic-language" class="zywrap-classic-select2"></select>
        </div>
        <div>
            <label for="zywrap-classic-prompt"><?php echo esc_html(__('5.', 'geeky-bot')).' '.esc_html__('Prompt (Optional)', 'geeky-bot'); ?></label>
            <textarea id="zywrap-classic-prompt" rows="5" placeholder="<?php echo esc_attr__('Enter your prompt...', 'geeky-bot'); ?>"></textarea>
        </div>

        <details id="zywrap-classic-modal-overrides">
            <summary style="cursor: pointer; font-weight: 600;"><?php echo esc_html(__('6.', 'geeky-bot')).' '.esc_html__('Overrides (Optional)', 'geeky-bot'); ?></summary>
            <div id="zywrap-classic-modal-overrides-grid" style="margin-top: 15px;">
                </div>
        </details>

        <?php endif; ?> </div>

    <div id="zywrap-classic-modal-footer" <?php if(empty($geekybot_saved_key) || $geekybot_categories_count < 1) echo 'style="display:none;"'; ?>>
        <button type="button" id="zywrap-classic-run" class="button button-primary" style="font-size: 1.1em; padding: 5px 20px;">
            <?php echo esc_html__('Generate & Insert', 'geeky-bot'); ?>
        </button>
        <span id="zywrap-classic-spinner" class="spinner" style="float: left; margin-top: 5px;"></span>
    </div>
</div>

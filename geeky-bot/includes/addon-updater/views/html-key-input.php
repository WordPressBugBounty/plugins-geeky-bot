<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
?>
<tr id="<?php echo esc_attr( sanitize_title( $addon_slug . '_transaction_key_row' ) ); ?>" class="active plugin-update-tr geekybot-updater-licence-key-tr">
	<td class="plugin-update" colspan="3">
		<div class="geekybot-updater-licence-key">
			<label for="<?php echo esc_attr(sanitize_title( $addon_slug )); ?>_transaction_key"><?php echo esc_html(__( 'Transaction Key','geeky-bot' )); ?>:</label>
			<input type="text" id="<?php echo esc_attr(sanitize_title( $addon_slug )); ?>_transaction_key" name="<?php echo esc_attr( $addon_slug ); ?>_transaction_key" placeholder="XXXXXXXXXXXXXXXX" />
			<input type="submit" id="<?php echo esc_attr(sanitize_title( $addon_slug )); ?>_submit_button" name="<?php echo esc_attr( $addon_slug ); ?>_submit_button" value="Authenticate" />
			<input type="hidden" name="geekybot_addon_array_for_token[]" value="<?php echo esc_attr( $addon_slug ); ?>" />
			<?php $wpnonce = wp_create_nonce("update-plugins"); ?>
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $wpnonce ); ?>" />
			<div>
				<span class="description"><?php esc_html(__( 'Please select )','geeky-bot')).wp_kses(geekybotphplib::GEEKYBOT_strtoupper( geekybotphplib::GEEKYBOT_substr( $updateaddon_slug, 0, 2 ) ), GEEKYBOT_ALLOWED_TAGS).wp_kses(geekybotphplib::GEEKYBOT_substr(  geekybotphplib::GEEKYBOT_ucwords($updateaddon_slug), 2 ), GEEKYBOT_ALLOWED_TAGS).esc_html(__('</b> and Enter your license key and hit to authenticate. A valid key is required for updates.' )); ?> <?php printf( 'Lost your key? <a href="%s">Retrieve it here</a>.', esc_url( 'https://geekybot.com/' ) ); ?></span>
			</div>
		</div>
	</td>
</tr>
<tr>
</tr>

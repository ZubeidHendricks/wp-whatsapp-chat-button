<?php
/**
 * Uninstall cleanup.
 *
 * @package WhatsAppChatButton
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'whatsapp-chat-button_options' );

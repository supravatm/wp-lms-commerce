<?php

/**
 * Plugin Name: LearnUpon Integration
 * Description: Connects WordPress, WooCommerce and LearnDash with LearnUpon using its REST API.
 * Version: 0.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Integration Development
 * Text Domain: learnupon-integration
 */

if (!defined('ABSPATH')) {
    exit;
}
require_once ABSPATH . 'vendor/autoload.php';

define('LUI_VERSION', '0.1.0');
define('LUI_FILE', __FILE__);
define('LUI_PATH', plugin_dir_path(__FILE__));
define('LUI_URL', plugin_dir_url(__FILE__));


require_once LUI_PATH . 'includes/class-lui-logger.php';
require_once LUI_PATH . 'includes/class-lui-api-client.php';
require_once LUI_PATH . 'includes/class-lui-user-sync.php';
require_once LUI_PATH . 'includes/class-lui-product-sync.php';
require_once LUI_PATH . 'includes/class-lui-queue.php';
require_once LUI_PATH . 'includes/class-lui-woocommerce.php';
require_once LUI_PATH . 'includes/class-lui-admin.php';
require_once LUI_PATH . 'includes/class-lui-company-manager.php';
require_once LUI_PATH . 'includes/class-lui-group-manager.php';
require_once LUI_PATH . 'includes/class-lui-seat-allocation.php';
require_once LUI_PATH . 'includes/class-lui-enrollment-manager.php';
require_once LUI_PATH . 'includes/class-lui-manager-dashboard.php';
require_once LUI_PATH . 'includes/admin/class-lui-bulk-order-admin.php';
require_once LUI_PATH . 'includes/class-lui-plugin.php';


register_activation_hook(__FILE__, ['LUI_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['LUI_Plugin', 'deactivate']);
add_action('plugins_loaded', static function () {
    LUI_Plugin::instance()->init();
});

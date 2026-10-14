<?php

/**
 * Plugin Name: LearnUpon LMS
 * Description: Connects WordPress, WooCommerce and LearnDash with LearnUpon using its REST API..
 * Version: 1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Text Domain: learnupon-lms
 * Author: Integration Development
 */

defined('ABSPATH') || exit;

define('LEARNUPON_LMS_FILE', __FILE__);
define('LEARNUPON_LMS_PATH', plugin_dir_path(__FILE__));

$autoload = LEARNUPON_LMS_PATH . 'vendor/autoload.php';

if (!file_exists($autoload)) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        echo esc_html__(
            'LearnUpon LMS plugin requires Composer dependencies. Run composer install.' . LEARNUPON_LMS_FILE,
            'lui-learnuplon-lms'
        );
        echo '</p></div>';
    });

    return;
}

require_once $autoload;

register_activation_hook(
    __FILE__,
    [\LearnUponLMS\Plugin::class, 'activate']
);

register_deactivation_hook(
    __FILE__,
    [\LearnUponLMS\Plugin::class, 'deactivate']
);

add_action('plugins_loaded', static function (): void {
    \LearnUponLMS\Plugin::instance()->init();
});

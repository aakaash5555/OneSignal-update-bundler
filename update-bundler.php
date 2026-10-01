<?php
/**
 * Plugin Name: Update Bundler for OneSignal
 * Description: Queues new and major post updates, sends bundled OneSignal web-push notifications, and tracks delivery and click analytics.
 * Version: 3.0.0
 * Author: Custom build
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Text Domain: update-bundler
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UB3_VERSION', '3.0.0' );
define( 'UB3_FILE', __FILE__ );
define( 'UB3_DIR', plugin_dir_path( __FILE__ ) );
define( 'UB3_URL', plugin_dir_url( __FILE__ ) );

require_once UB3_DIR . 'includes/class-ub3-settings.php';
require_once UB3_DIR . 'includes/class-ub3-queue.php';
require_once UB3_DIR . 'includes/class-ub3-onesignal.php';
require_once UB3_DIR . 'includes/class-ub3-post-hooks.php';
require_once UB3_DIR . 'includes/class-ub3-scheduler.php';
require_once UB3_DIR . 'includes/class-ub3-admin.php';

final class UB3_Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        UB3_Settings::init();
        UB3_Post_Hooks::init();
        UB3_Scheduler::init();
        UB3_Admin::init();
    }

    public static function activate() {
        UB3_Settings::install_defaults();
        UB3_Scheduler::schedule();
    }

    public static function deactivate() {
        UB3_Scheduler::unschedule();
    }
}

register_activation_hook( __FILE__, array( 'UB3_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UB3_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'UB3_Plugin', 'instance' ) );

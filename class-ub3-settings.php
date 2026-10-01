<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UB3_Settings {
    const OPTION = 'ub3_settings';

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register' ) );
    }

    public static function defaults() {
        return array(
            'app_id'                  => '',
            'api_key'                 => '',
            'segment'                 => 'Subscribed Users',
            'schedule'                => 'daily',
            'daily_time'              => '19:00',
            'landing_url'             => home_url( '/' ),
            'title_template'          => '{count} new updates',
            'body_template'           => '{count} new updates are available now.',
            'single_title'            => '1 new update',
            'single_body'             => '1 new update is available now.',
            'auto_new_posts'          => 1,
            'auto_date_changes'       => 1,
            'post_types'              => array( 'post' ),
            'keep_history'            => 200,
            'notification_icon'       => '',
            'notification_image'      => '',
        );
    }

    public static function install_defaults() {
        if ( false === get_option( self::OPTION, false ) ) {
            add_option( self::OPTION, self::defaults(), '', false );
        }
        if ( false === get_option( 'ub3_queue', false ) ) add_option( 'ub3_queue', array(), '', false );
        if ( false === get_option( 'ub3_history', false ) ) add_option( 'ub3_history', array(), '', false );
    }

    public static function get_all() {
        return wp_parse_args( get_option( self::OPTION, array() ), self::defaults() );
    }

    public static function get( $key, $default = null ) {
        $all = self::get_all();
        return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
    }

    public static function register() {
        register_setting( 'ub3_settings_group', self::OPTION, array( __CLASS__, 'sanitize' ) );
    }

    public static function sanitize( $input ) {
        $old = self::get_all();
        $out = self::defaults();

        $out['app_id'] = isset( $input['app_id'] ) ? sanitize_text_field( $input['app_id'] ) : '';
        $new_key = isset( $input['api_key'] ) ? trim( wp_unslash( $input['api_key'] ) ) : '';
        $out['api_key'] = '' !== $new_key ? sanitize_text_field( $new_key ) : $old['api_key'];
        $out['segment'] = isset( $input['segment'] ) ? sanitize_text_field( $input['segment'] ) : 'Subscribed Users';

        $allowed_schedules = array( 'manual', 'hourly', 'daily' );
        $out['schedule'] = in_array( $input['schedule'] ?? '', $allowed_schedules, true ) ? $input['schedule'] : 'daily';

        $time = sanitize_text_field( $input['daily_time'] ?? '19:00' );
        $out['daily_time'] = preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ? $time : '19:00';
        $out['landing_url'] = esc_url_raw( $input['landing_url'] ?? home_url( '/' ) );
        $out['title_template'] = sanitize_text_field( $input['title_template'] ?? '{count} new updates' );
        $out['body_template'] = sanitize_textarea_field( $input['body_template'] ?? '{count} new updates are available now.' );
        $out['single_title'] = sanitize_text_field( $input['single_title'] ?? '1 new update' );
        $out['single_body'] = sanitize_textarea_field( $input['single_body'] ?? '1 new update is available now.' );
        $out['auto_new_posts'] = empty( $input['auto_new_posts'] ) ? 0 : 1;
        $out['auto_date_changes'] = empty( $input['auto_date_changes'] ) ? 0 : 1;
        $out['notification_icon'] = esc_url_raw( $input['notification_icon'] ?? '' );
        $out['notification_image'] = esc_url_raw( $input['notification_image'] ?? '' );

        $types = array_map( 'sanitize_key', (array) ( $input['post_types'] ?? array( 'post' ) ) );
        $public_types = get_post_types( array( 'public' => true ), 'names' );
        $out['post_types'] = array_values( array_intersect( $types, $public_types ) );
        if ( empty( $out['post_types'] ) ) $out['post_types'] = array( 'post' );

        $out['keep_history'] = min( 1000, max( 25, absint( $input['keep_history'] ?? 200 ) ) );
        return $out;
    }
}

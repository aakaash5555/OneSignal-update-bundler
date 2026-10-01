<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UB3_Scheduler {
    const HOOK = 'ub3_scheduler_tick';
    const ANALYTICS_HOOK = 'ub3_analytics_tick';

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'intervals' ) );
        add_action( self::HOOK, array( __CLASS__, 'tick' ) );
        add_action( self::ANALYTICS_HOOK, array( 'UB3_OneSignal', 'sync_history' ) );
        add_action( 'init', array( __CLASS__, 'ensure_scheduled' ) );
    }

    public static function intervals( $schedules ) {
        $schedules['ub3_fifteen_minutes'] = array( 'interval' => 900, 'display' => 'Every 15 minutes' );
        return $schedules;
    }

    public static function schedule() {
        if ( ! wp_next_scheduled( self::HOOK ) ) wp_schedule_event( time() + 300, 'ub3_fifteen_minutes', self::HOOK );
        if ( ! wp_next_scheduled( self::ANALYTICS_HOOK ) ) wp_schedule_event( time() + 3600, 'hourly', self::ANALYTICS_HOOK );
    }

    public static function ensure_scheduled() { self::schedule(); }

    public static function unschedule() {
        wp_clear_scheduled_hook( self::HOOK );
        wp_clear_scheduled_hook( self::ANALYTICS_HOOK );
    }

    public static function tick() {
        if ( UB3_Queue::count() < 1 ) return;
        $schedule = UB3_Settings::get( 'schedule', 'daily' );
        if ( 'manual' === $schedule ) return;

        if ( 'hourly' === $schedule ) {
            $last = absint( get_option( 'ub3_last_sent_at', 0 ) );
            if ( ! $last || ( time() - $last ) >= HOUR_IN_SECONDS ) UB3_OneSignal::send_bundle();
            return;
        }

        if ( 'daily' === $schedule ) {
            $today = current_time( 'Y-m-d' );
            if ( get_option( 'ub3_last_daily_date', '' ) === $today ) return;
            $now = current_time( 'H:i' );
            if ( $now >= UB3_Settings::get( 'daily_time', '19:00' ) ) UB3_OneSignal::send_bundle();
        }
    }
}

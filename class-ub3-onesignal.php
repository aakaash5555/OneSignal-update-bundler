<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UB3_OneSignal {
    const BASE = 'https://api.onesignal.com';

    private static function headers() {
        return array(
            'Authorization' => 'Key ' . UB3_Settings::get( 'api_key', '' ),
            'Content-Type'  => 'application/json; charset=utf-8',
            'Accept'        => 'application/json',
        );
    }

    public static function configured() {
        return (bool) ( UB3_Settings::get( 'app_id' ) && UB3_Settings::get( 'api_key' ) );
    }

    public static function send_bundle() {
        if ( ! self::configured() ) {
            return new WP_Error( 'ub3_not_configured', 'OneSignal App ID or API key is missing.' );
        }

        $queue = UB3_Queue::all();
        $count = count( $queue );
        if ( 0 === $count ) return new WP_Error( 'ub3_empty_queue', 'The queue is empty.' );

        $title = 1 === $count ? UB3_Settings::get( 'single_title' ) : str_replace( '{count}', (string) $count, UB3_Settings::get( 'title_template' ) );
        $body  = 1 === $count ? UB3_Settings::get( 'single_body' ) : str_replace( '{count}', (string) $count, UB3_Settings::get( 'body_template' ) );

        $payload = array(
            'app_id'            => UB3_Settings::get( 'app_id' ),
            'included_segments' => array( UB3_Settings::get( 'segment', 'Subscribed Users' ) ),
            'target_channel'    => 'push',
            'headings'          => array( 'en' => $title ),
            'contents'          => array( 'en' => $body ),
            'url'               => UB3_Settings::get( 'landing_url', home_url( '/' ) ),
            'name'              => 'Update Bundler ' . current_time( 'Y-m-d H:i:s' ),
        );

        $icon = UB3_Settings::get( 'notification_icon', '' );
        $image = UB3_Settings::get( 'notification_image', '' );
        if ( $icon ) $payload['chrome_web_icon'] = $icon;
        if ( $image ) $payload['chrome_web_image'] = $image;

        $response = wp_remote_post( self::BASE . '/notifications', array(
            'timeout' => 25,
            'headers' => self::headers(),
            'body'    => wp_json_encode( $payload ),
        ) );

        if ( is_wp_error( $response ) ) return $response;
        $code = wp_remote_retrieve_response_code( $response );
        $json = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code < 200 || $code >= 300 || empty( $json['id'] ) ) {
            $message = ! empty( $json['errors'] ) ? wp_json_encode( $json['errors'] ) : 'OneSignal returned HTTP ' . $code;
            return new WP_Error( 'ub3_send_failed', $message, $json );
        }

        UB3_Queue::add_history( array(
            'message_id' => sanitize_text_field( $json['id'] ),
            'sent_at'    => current_time( 'mysql' ),
            'count'      => $count,
            'title'      => $title,
            'body'       => $body,
            'post_ids'   => array_map( 'absint', array_keys( $queue ) ),
            'delivered'  => 0,
            'clicked'    => 0,
            'ctr'        => 0,
            'status'     => 'sent',
            'synced_at'  => '',
        ) );

        UB3_Queue::clear();
        update_option( 'ub3_last_sent_at', time(), false );
        update_option( 'ub3_last_daily_date', current_time( 'Y-m-d' ), false );
        return $json;
    }

    public static function sync_history() {
        if ( ! self::configured() ) return new WP_Error( 'ub3_not_configured', 'OneSignal is not configured.' );
        $history = UB3_Queue::history();
        $synced = 0;

        foreach ( array_slice( $history, 0, 50 ) as $item ) {
            if ( empty( $item['message_id'] ) ) continue;
            $sent_ts = isset( $item['sent_at'] ) ? strtotime( $item['sent_at'] ) : 0;
            if ( $sent_ts && $sent_ts < strtotime( '-30 days' ) ) continue;

            $url = add_query_arg(
                array(
                    'app_id'             => UB3_Settings::get( 'app_id' ),
                    'outcome_names'      => 'os__click.count,os__confirmed_delivery.count',
                    'outcome_time_range' => '1mo',
                ),
                self::BASE . '/notifications/' . rawurlencode( $item['message_id'] )
            );
            $response = wp_remote_get( $url, array( 'timeout' => 20, 'headers' => self::headers() ) );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) continue;
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! is_array( $data ) ) continue;

            $delivered = isset( $data['received'] ) ? absint( $data['received'] ) : absint( $data['successful'] ?? 0 );
            $clicked = absint( $data['converted'] ?? 0 );

            if ( ! empty( $data['outcomes'] ) && is_array( $data['outcomes'] ) ) {
                foreach ( $data['outcomes'] as $outcome ) {
                    $id = $outcome['id'] ?? '';
                    if ( 'os__click' === $id ) $clicked = absint( $outcome['value'] ?? $clicked );
                    if ( 'os__confirmed_delivery' === $id ) $delivered = absint( $outcome['value'] ?? $delivered );
                }
            }

            $ctr = $delivered > 0 ? round( ( $clicked / $delivered ) * 100, 2 ) : 0;
            UB3_Queue::update_history_item( $item['message_id'], array(
                'delivered' => $delivered,
                'clicked'   => $clicked,
                'ctr'       => $ctr,
                'synced_at' => current_time( 'mysql' ),
            ) );
            $synced++;
        }
        update_option( 'ub3_last_analytics_sync', time(), false );
        return $synced;
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UB3_Queue {
    const OPTION = 'ub3_queue';
    const HISTORY = 'ub3_history';

    public static function all() {
        $queue = get_option( self::OPTION, array() );
        return is_array( $queue ) ? $queue : array();
    }

    public static function count() {
        return count( self::all() );
    }

    public static function add( $post_id, $reason = 'manual' ) {
        $post_id = absint( $post_id );
        $post = get_post( $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) return false;
        if ( ! in_array( $post->post_type, UB3_Settings::get( 'post_types', array( 'post' ) ), true ) ) return false;

        $queue = self::all();
        $queue[ $post_id ] = array(
            'post_id'   => $post_id,
            'title'     => get_the_title( $post_id ),
            'url'       => get_permalink( $post_id ),
            'reason'    => sanitize_key( $reason ),
            'queued_at' => current_time( 'mysql' ),
        );
        update_option( self::OPTION, $queue, false );
        return true;
    }

    public static function remove( $post_id ) {
        $queue = self::all();
        unset( $queue[ absint( $post_id ) ] );
        update_option( self::OPTION, $queue, false );
    }

    public static function clear() {
        update_option( self::OPTION, array(), false );
    }

    public static function history() {
        $history = get_option( self::HISTORY, array() );
        return is_array( $history ) ? $history : array();
    }

    public static function add_history( $entry ) {
        $history = self::history();
        array_unshift( $history, $entry );
        $limit = UB3_Settings::get( 'keep_history', 200 );
        $history = array_slice( $history, 0, $limit );
        update_option( self::HISTORY, $history, false );
    }

    public static function update_history_item( $message_id, $data ) {
        $history = self::history();
        foreach ( $history as &$item ) {
            if ( isset( $item['message_id'] ) && $item['message_id'] === $message_id ) {
                $item = array_merge( $item, $data );
                break;
            }
        }
        unset( $item );
        update_option( self::HISTORY, $history, false );
    }
}

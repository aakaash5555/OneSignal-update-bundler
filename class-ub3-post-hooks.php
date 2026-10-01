<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UB3_Post_Hooks {
    public static function init() {
        add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
        add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
        add_action( 'save_post', array( __CLASS__, 'save_meta_box' ), 20, 2 );
    }

    private static function eligible( $post ) {
        return $post instanceof WP_Post && in_array( $post->post_type, UB3_Settings::get( 'post_types', array( 'post' ) ), true );
    }

    public static function on_transition( $new_status, $old_status, $post ) {
        if ( ! self::eligible( $post ) ) return;
        if ( 'publish' === $new_status && 'publish' !== $old_status && UB3_Settings::get( 'auto_new_posts', 1 ) ) {
            UB3_Queue::add( $post->ID, 'new_publish' );
        }
    }

    public static function on_post_updated( $post_id, $post_after, $post_before ) {
        if ( ! UB3_Settings::get( 'auto_date_changes', 1 ) || ! self::eligible( $post_after ) ) return;
        if ( 'publish' !== $post_after->post_status || 'publish' !== $post_before->post_status ) return;
        if ( $post_after->post_date_gmt !== $post_before->post_date_gmt ) {
            UB3_Queue::add( $post_id, 'publish_date_changed' );
        }
    }

    public static function add_meta_box() {
        foreach ( UB3_Settings::get( 'post_types', array( 'post' ) ) as $type ) {
            add_meta_box( 'ub3-major-update', 'Update notification', array( __CLASS__, 'render_meta_box' ), $type, 'side', 'default' );
        }
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'ub3_major_update', 'ub3_major_update_nonce' );
        echo '<label><input type="checkbox" name="ub3_queue_major_update" value="1"> ';
        echo esc_html__( 'Include this post in the next notification bundle', 'update-bundler' );
        echo '</label>';
        echo '<p class="description">' . esc_html__( 'Use this for a meaningful refresh. The checkbox resets after saving.', 'update-bundler' ) . '</p>';
    }

    public static function save_meta_box( $post_id, $post ) {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        if ( ! isset( $_POST['ub3_major_update_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ub3_major_update_nonce'] ) ), 'ub3_major_update' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( ! self::eligible( $post ) || 'publish' !== $post->post_status ) return;
        if ( ! empty( $_POST['ub3_queue_major_update'] ) ) UB3_Queue::add( $post_id, 'major_update' );
    }
}

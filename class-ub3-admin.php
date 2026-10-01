<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class UB3_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_ub3_send_now', array( __CLASS__, 'send_now' ) );
        add_action( 'admin_post_ub3_clear_queue', array( __CLASS__, 'clear_queue' ) );
        add_action( 'admin_post_ub3_remove_queue_item', array( __CLASS__, 'remove_queue_item' ) );
        add_action( 'admin_post_ub3_sync_analytics', array( __CLASS__, 'sync_analytics' ) );
    }

    public static function menu() {
        add_menu_page( 'Update Bundler', 'Update Bundler', 'manage_options', 'update-bundler', array( __CLASS__, 'page' ), 'dashicons-megaphone', 58 );
    }

    public static function assets( $hook ) {
        if ( 'toplevel_page_update-bundler' !== $hook ) return;
        wp_enqueue_style( 'ub3-admin', UB3_URL . 'assets/admin.css', array(), UB3_VERSION );
    }

    private static function redirect( $notice, $type = 'success' ) {
        wp_safe_redirect( add_query_arg( array( 'page' => 'update-bundler', 'ub3_notice' => rawurlencode( $notice ), 'ub3_type' => $type ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function send_now() {
        check_admin_referer( 'ub3_send_now' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        $result = UB3_OneSignal::send_bundle();
        if ( is_wp_error( $result ) ) self::redirect( $result->get_error_message(), 'error' );
        self::redirect( 'Bundled notification sent successfully.' );
    }

    public static function clear_queue() {
        check_admin_referer( 'ub3_clear_queue' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        UB3_Queue::clear();
        self::redirect( 'Queue cleared.' );
    }

    public static function remove_queue_item() {
        check_admin_referer( 'ub3_remove_queue_item' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        UB3_Queue::remove( absint( $_GET['post_id'] ?? 0 ) );
        self::redirect( 'Queue item removed.' );
    }

    public static function sync_analytics() {
        check_admin_referer( 'ub3_sync_analytics' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
        $result = UB3_OneSignal::sync_history();
        if ( is_wp_error( $result ) ) self::redirect( $result->get_error_message(), 'error' );
        self::redirect( sprintf( 'Analytics updated for %d notification(s).', $result ) );
    }

    private static function stats( $history ) {
        $delivered = $clicked = $bundles = $updates = 0;
        $hours = array_fill( 0, 24, array( 'sent' => 0, 'clicked' => 0, 'delivered' => 0 ) );
        $days = array_fill( 0, 7, array( 'sent' => 0, 'clicked' => 0, 'delivered' => 0 ) );
        foreach ( $history as $item ) {
            $bundles++;
            $updates += absint( $item['count'] ?? 0 );
            $d = absint( $item['delivered'] ?? 0 );
            $c = absint( $item['clicked'] ?? 0 );
            $delivered += $d;
            $clicked += $c;
            $ts = strtotime( $item['sent_at'] ?? '' );
            if ( $ts ) {
                $h = (int) wp_date( 'G', $ts );
                $w = (int) wp_date( 'w', $ts );
                $hours[$h]['sent']++; $hours[$h]['clicked'] += $c; $hours[$h]['delivered'] += $d;
                $days[$w]['sent']++; $days[$w]['clicked'] += $c; $days[$w]['delivered'] += $d;
            }
        }
        $best_hour = null; $best_hour_ctr = -1;
        foreach ( $hours as $h => $data ) {
            if ( $data['delivered'] < 10 ) continue;
            $ctr = ( $data['clicked'] / $data['delivered'] ) * 100;
            if ( $ctr > $best_hour_ctr ) { $best_hour_ctr = $ctr; $best_hour = $h; }
        }
        $best_day = null; $best_day_ctr = -1;
        foreach ( $days as $d => $data ) {
            if ( $data['delivered'] < 10 ) continue;
            $ctr = ( $data['clicked'] / $data['delivered'] ) * 100;
            if ( $ctr > $best_day_ctr ) { $best_day_ctr = $ctr; $best_day = $d; }
        }
        return compact( 'delivered', 'clicked', 'bundles', 'updates', 'best_hour', 'best_hour_ctr', 'best_day', 'best_day_ctr' );
    }

    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $settings = UB3_Settings::get_all();
        $queue = UB3_Queue::all();
        $history = UB3_Queue::history();
        $stats = self::stats( $history );
        $tabs = array( 'dashboard' => 'Dashboard', 'queue' => 'Queue', 'history' => 'History & analytics', 'settings' => 'Settings' );
        $tab = sanitize_key( $_GET['tab'] ?? 'dashboard' );
        if ( ! isset( $tabs[$tab] ) ) $tab = 'dashboard';

        echo '<div class="wrap ub3-wrap"><h1>Update Bundler 3.0</h1>';
        if ( isset( $_GET['ub3_notice'] ) ) {
            $type = ( $_GET['ub3_type'] ?? '' ) === 'error' ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( rawurldecode( wp_unslash( $_GET['ub3_notice'] ) ) ) . '</p></div>';
        }
        echo '<nav class="nav-tab-wrapper">';
        foreach ( $tabs as $key => $label ) {
            $url = add_query_arg( array( 'page' => 'update-bundler', 'tab' => $key ), admin_url( 'admin.php' ) );
            echo '<a class="nav-tab ' . ( $tab === $key ? 'nav-tab-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }
        echo '</nav>';

        if ( 'dashboard' === $tab ) self::dashboard( $queue, $history, $stats, $settings );
        elseif ( 'queue' === $tab ) self::queue_page( $queue );
        elseif ( 'history' === $tab ) self::history_page( $history, $stats );
        else self::settings_page( $settings );
        echo '</div>';
    }

    private static function dashboard( $queue, $history, $stats, $settings ) {
        $ctr = $stats['delivered'] > 0 ? round( ( $stats['clicked'] / $stats['delivered'] ) * 100, 2 ) : 0;
        echo '<div class="ub3-grid ub3-stats">';
        self::card( 'Queued updates', count( $queue ) );
        self::card( 'Bundles sent', $stats['bundles'] );
        self::card( 'Confirmed deliveries', number_format_i18n( $stats['delivered'] ) );
        self::card( 'Clicks / CTR', number_format_i18n( $stats['clicked'] ) . ' / ' . $ctr . '%' );
        echo '</div>';
        echo '<div class="ub3-panel"><h2>Quick actions</h2><div class="ub3-actions">';
        echo '<a class="button button-primary" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ub3_send_now' ), 'ub3_send_now' ) ) . '">Send queued updates now</a> ';
        echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ub3_sync_analytics' ), 'ub3_sync_analytics' ) ) . '">Sync analytics</a>';
        echo '</div><p class="description">Automatic schedule: <strong>' . esc_html( ucfirst( $settings['schedule'] ) ) . '</strong>';
        if ( 'daily' === $settings['schedule'] ) echo ' at ' . esc_html( $settings['daily_time'] );
        echo ' in the WordPress site timezone.</p></div>';

        echo '<div class="ub3-panel"><h2>Optimization summary</h2>';
        if ( null === $stats['best_hour'] ) echo '<p>Not enough confirmed-delivery data to identify a reliable best hour. At least 10 deliveries in a time bucket are required.</p>';
        else echo '<p>Best observed hour: <strong>' . esc_html( sprintf( '%02d:00', $stats['best_hour'] ) ) . '</strong> with ' . esc_html( round( $stats['best_hour_ctr'], 2 ) ) . '% CTR.</p>';
        if ( null !== $stats['best_day'] ) {
            $names = array( 'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday' );
            echo '<p>Best observed day: <strong>' . esc_html( $names[$stats['best_day']] ) . '</strong> with ' . esc_html( round( $stats['best_day_ctr'], 2 ) ) . '% CTR.</p>';
        }
        echo '<p class="description">These observations are descriptive, not proof that a particular hour or day causes higher clicks.</p></div>';
    }

    private static function card( $label, $value ) {
        echo '<div class="ub3-card"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></div>';
    }

    private static function queue_page( $queue ) {
        echo '<div class="ub3-panel"><div class="ub3-heading-row"><h2>Current queue</h2>';
        if ( $queue ) echo '<a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ub3_clear_queue' ), 'ub3_clear_queue' ) ) . '" onclick="return confirm(\'Clear the entire queue?\')">Clear queue</a>';
        echo '</div>';
        if ( ! $queue ) { echo '<p>The queue is empty.</p></div>'; return; }
        echo '<div class="ub3-table-wrap"><table class="widefat striped"><thead><tr><th>Post</th><th>Reason</th><th>Queued</th><th></th></tr></thead><tbody>';
        foreach ( $queue as $item ) {
            echo '<tr><td><a href="' . esc_url( get_edit_post_link( $item['post_id'] ) ) . '">' . esc_html( $item['title'] ) . '</a></td><td>' . esc_html( str_replace( '_', ' ', $item['reason'] ) ) . '</td><td>' . esc_html( $item['queued_at'] ) . '</td><td><a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ub3_remove_queue_item&post_id=' . absint( $item['post_id'] ) ), 'ub3_remove_queue_item' ) ) . '">Remove</a></td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function history_page( $history, $stats ) {
        echo '<div class="ub3-panel"><div class="ub3-heading-row"><h2>Notification history</h2><a class="button" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ub3_sync_analytics' ), 'ub3_sync_analytics' ) ) . '">Sync now</a></div>';
        echo '<p class="description">OneSignal API-sent message reports are generally available through the reporting API for about 30 days. This plugin keeps the last synced totals locally.</p>';
        if ( ! $history ) { echo '<p>No notifications have been sent by this plugin.</p></div>'; return; }
        echo '<div class="ub3-table-wrap"><table class="widefat striped"><thead><tr><th>Sent</th><th>Message</th><th>Updates</th><th>Delivered</th><th>Clicked</th><th>CTR</th></tr></thead><tbody>';
        foreach ( $history as $item ) {
            echo '<tr><td>' . esc_html( $item['sent_at'] ?? '' ) . '</td><td><strong>' . esc_html( $item['title'] ?? '' ) . '</strong><br><span class="description">' . esc_html( $item['body'] ?? '' ) . '</span></td><td>' . absint( $item['count'] ?? 0 ) . '</td><td>' . absint( $item['delivered'] ?? 0 ) . '</td><td>' . absint( $item['clicked'] ?? 0 ) . '</td><td>' . esc_html( $item['ctr'] ?? 0 ) . '%</td></tr>';
        }
        echo '</tbody></table></div></div>';
    }

    private static function settings_page( $s ) {
        $types = get_post_types( array( 'public' => true ), 'objects' );
        echo '<form method="post" action="options.php" class="ub3-panel">';
        settings_fields( 'ub3_settings_group' );
        echo '<h2>OneSignal connection</h2><table class="form-table" role="presentation">';
        self::row_text( 'App ID', 'app_id', $s['app_id'], 'Paste the OneSignal App ID.' );
        self::row_password( 'REST API key', 'api_key', 'Leave blank to keep the stored key. The key is never displayed back.' );
        self::row_text( 'Audience segment', 'segment', $s['segment'], 'Usually “Subscribed Users”. The name must exactly match a OneSignal segment.' );
        echo '</table><h2>Bundling and delivery</h2><table class="form-table" role="presentation">';
        echo '<tr><th><label for="ub3_schedule">Schedule</label></th><td><select id="ub3_schedule" name="' . UB3_Settings::OPTION . '[schedule]">';
        foreach ( array( 'manual' => 'Manual only', 'hourly' => 'Hourly', 'daily' => 'Daily' ) as $k => $v ) echo '<option value="' . esc_attr( $k ) . '" ' . selected( $s['schedule'], $k, false ) . '>' . esc_html( $v ) . '</option>';
        echo '</select></td></tr>';
        self::row_text( 'Daily send time', 'daily_time', $s['daily_time'], '24-hour format, using the WordPress site timezone.' );
        self::row_url( 'Notification landing URL', 'landing_url', $s['landing_url'], 'Where visitors go after clicking the bundled notification.' );
        self::row_text( 'Multiple-update title', 'title_template', $s['title_template'], 'Use {count} as the number placeholder.' );
        self::row_textarea( 'Multiple-update message', 'body_template', $s['body_template'], 'Use {count} as the number placeholder.' );
        self::row_text( 'Single-update title', 'single_title', $s['single_title'], '' );
        self::row_textarea( 'Single-update message', 'single_body', $s['single_body'], '' );
        self::row_url( 'Web notification icon URL', 'notification_icon', $s['notification_icon'], 'Optional.' );
        self::row_url( 'Web notification image URL', 'notification_image', $s['notification_image'], 'Optional. Browser support varies.' );
        echo '</table><h2>Queue rules</h2><table class="form-table" role="presentation">';
        self::row_checkbox( 'Automatically queue new publications', 'auto_new_posts', $s['auto_new_posts'] );
        self::row_checkbox( 'Queue when publication date changes', 'auto_date_changes', $s['auto_date_changes'] );
        echo '<tr><th>Post types</th><td>';
        foreach ( $types as $type ) {
            if ( 'attachment' === $type->name ) continue;
            echo '<label class="ub3-check"><input type="checkbox" name="' . UB3_Settings::OPTION . '[post_types][]" value="' . esc_attr( $type->name ) . '" ' . checked( in_array( $type->name, $s['post_types'], true ), true, false ) . '> ' . esc_html( $type->labels->singular_name ) . '</label>';
        }
        echo '</td></tr>';
        self::row_text( 'History records to keep', 'keep_history', $s['keep_history'], '25 to 1000.' );
        echo '</table>';
        submit_button( 'Save settings' );
        echo '<p class="description">The admin interface intentionally uses native WordPress components and avoids fixed light-background colors, making it friendlier to Night Eye and similar dark-mode extensions.</p></form>';
    }

    private static function row_text( $label, $key, $value, $help ) { self::input_row( $label, $key, $value, 'text', $help ); }
    private static function row_url( $label, $key, $value, $help ) { self::input_row( $label, $key, $value, 'url', $help ); }
    private static function row_password( $label, $key, $help ) { self::input_row( $label, $key, '', 'password', $help, 'new-password' ); }
    private static function input_row( $label, $key, $value, $type, $help, $autocomplete = '' ) {
        echo '<tr><th><label for="ub3_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="ub3_' . esc_attr( $key ) . '" type="' . esc_attr( $type ) . '" name="' . UB3_Settings::OPTION . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '"' . ( $autocomplete ? ' autocomplete="' . esc_attr( $autocomplete ) . '"' : '' ) . '>'; if ( $help ) echo '<p class="description">' . esc_html( $help ) . '</p>'; echo '</td></tr>';
    }
    private static function row_textarea( $label, $key, $value, $help ) {
        echo '<tr><th><label for="ub3_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><textarea class="large-text" rows="3" id="ub3_' . esc_attr( $key ) . '" name="' . UB3_Settings::OPTION . '[' . esc_attr( $key ) . ']">' . esc_textarea( $value ) . '</textarea>'; if ( $help ) echo '<p class="description">' . esc_html( $help ) . '</p>'; echo '</td></tr>';
    }
    private static function row_checkbox( $label, $key, $checked ) {
        echo '<tr><th>' . esc_html( $label ) . '</th><td><label><input type="checkbox" name="' . UB3_Settings::OPTION . '[' . esc_attr( $key ) . ']" value="1" ' . checked( $checked, 1, false ) . '> Enabled</label></td></tr>';
    }
}

<?php
/**
 * Analytics tracking for Gamut LUT Preview.
 *
 * Tracks LUT and image preview interactions via lightweight AJAX pings.
 * Provides an admin dashboard widget with top-viewed LUTs and images.
 *
 * @package Gamut_LUT_Preview
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Gamut_LUT_Analytics {

    const TABLE_NAME = 'gamut_lut_analytics';

    public function __construct() {
        add_action( 'wp_ajax_gamut_track_preview', array( $this, 'track_preview' ) );
        add_action( 'wp_ajax_nopriv_gamut_track_preview', array( $this, 'track_preview' ) );
        add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
        add_action( 'admin_menu', array( $this, 'add_analytics_page' ) );
    }

    /**
     * Create the analytics table on plugin activation.
     */
    public static function create_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(20) NOT NULL,
            object_id bigint(20) unsigned NOT NULL,
            object_title varchar(255) DEFAULT '',
            collection_slug varchar(100) DEFAULT '',
            session_id varchar(64) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_type (event_type),
            KEY idx_object_id (object_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * AJAX handler for tracking preview events.
     */
    public function track_preview() {
        // Verify nonce for logged-in users. Allow anonymous tracking (guests) without nonce.
        if ( is_user_logged_in() ) {
            check_ajax_referer( 'gamut_lut_nonce', 'nonce' );
        }

        $event_type = isset( $_POST['event_type'] ) ? sanitize_text_field( $_POST['event_type'] ) : '';
        $object_id  = isset( $_POST['object_id'] ) ? absint( $_POST['object_id'] ) : 0;
        $title      = isset( $_POST['title'] ) ? sanitize_text_field( $_POST['title'] ) : '';
        $collection = isset( $_POST['collection'] ) ? sanitize_text_field( $_POST['collection'] ) : '';
        $session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( $_POST['session_id'] ) : '';

        $valid_events = array( 'lut_preview', 'image_preview', 'share_click', 'cart_click' );
        if ( ! in_array( $event_type, $valid_events, true ) || ! $object_id ) {
            wp_send_json_error( array( 'message' => 'Invalid event.' ) );
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Deduplicate: skip if same session+event+object within last 30 seconds.
        if ( $session_id ) {
            $recent = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE session_id = %s AND event_type = %s AND object_id = %d AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)",
                $session_id,
                $event_type,
                $object_id
            ) );
            if ( $recent > 0 ) {
                wp_send_json_success( array( 'message' => 'Deduplicated.' ) );
            }
        }

        $wpdb->insert(
            $table_name,
            array(
                'event_type'      => $event_type,
                'object_id'       => $object_id,
                'object_title'    => $title,
                'collection_slug' => $collection,
                'session_id'      => $session_id,
            ),
            array( '%s', '%d', '%s', '%s', '%s' )
        );

        wp_send_json_success( array( 'message' => 'Tracked.' ) );
    }

    /**
     * Add dashboard widget.
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'gamut_lut_analytics_widget',
            __( 'LUT Preview Analytics', 'gamut-lut-preview' ),
            array( $this, 'render_dashboard_widget' )
        );
    }

    /**
     * Add full analytics page under the LUT Preview settings.
     */
    public function add_analytics_page() {
        add_submenu_page(
            'options-general.php',
            __( 'LUT Analytics', 'gamut-lut-preview' ),
            __( 'LUT Analytics', 'gamut-lut-preview' ),
            'manage_options',
            'gamut-lut-analytics',
            array( $this, 'render_analytics_page' )
        );
    }

    /**
     * Render the dashboard widget.
     */
    public function render_dashboard_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Check if table exists.
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        if ( ! $table_exists ) {
            echo '<p>' . esc_html__( 'Analytics table not yet created. Deactivate and reactivate the plugin.', 'gamut-lut-preview' ) . '</p>';
            return;
        }

        $top_luts = $wpdb->get_results(
            "SELECT object_title, object_id, COUNT(*) as view_count
             FROM {$table_name}
             WHERE event_type = 'lut_preview'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY object_id, object_title
             ORDER BY view_count DESC
             LIMIT 5"
        );

        $top_images = $wpdb->get_results(
            "SELECT object_title, object_id, COUNT(*) as view_count
             FROM {$table_name}
             WHERE event_type = 'image_preview'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY object_id, object_title
             ORDER BY view_count DESC
             LIMIT 5"
        );

        $total_previews = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE event_type = 'lut_preview'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        $total_shares = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE event_type = 'share_click'
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );

        echo '<p><strong>' . esc_html__( 'Last 30 Days', 'gamut-lut-preview' ) . '</strong></p>';
        echo '<p>' . sprintf( esc_html__( 'Total LUT Previews: %s | Shares: %s', 'gamut-lut-preview' ), '<strong>' . intval( $total_previews ) . '</strong>', '<strong>' . intval( $total_shares ) . '</strong>' ) . '</p>';

        if ( $top_luts ) {
            echo '<h4>' . esc_html__( 'Top LUTs', 'gamut-lut-preview' ) . '</h4>';
            echo '<ol>';
            foreach ( $top_luts as $row ) {
                echo '<li>' . esc_html( $row->object_title ?: '#' . $row->object_id ) . ' <span style="color:#999;">(' . intval( $row->view_count ) . ')</span></li>';
            }
            echo '</ol>';
        }

        if ( $top_images ) {
            echo '<h4>' . esc_html__( 'Top Sample Images', 'gamut-lut-preview' ) . '</h4>';
            echo '<ol>';
            foreach ( $top_images as $row ) {
                echo '<li>' . esc_html( $row->object_title ?: '#' . $row->object_id ) . ' <span style="color:#999;">(' . intval( $row->view_count ) . ')</span></li>';
            }
            echo '</ol>';
        }

        echo '<p><a href="' . esc_url( admin_url( 'options-general.php?page=gamut-lut-analytics' ) ) . '">' . esc_html__( 'View Full Analytics', 'gamut-lut-preview' ) . '</a></p>';
    }

    /**
     * Render the full analytics page.
     */
    public function render_analytics_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;

        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
        if ( ! $table_exists ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'LUT Analytics', 'gamut-lut-preview' ) . '</h1>';
            echo '<p>' . esc_html__( 'Analytics table not yet created. Deactivate and reactivate the plugin.', 'gamut-lut-preview' ) . '</p></div>';
            return;
        }

        $period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : '30';
        $valid_periods = array( '7', '30', '90', 'all' );
        if ( ! in_array( $period, $valid_periods, true ) ) {
            $period = '30';
        }

        $date_clause = 'all' === $period ? '' : $wpdb->prepare( 'AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)', intval( $period ) );

        $top_luts = $wpdb->get_results(
            "SELECT object_title, object_id, collection_slug, COUNT(*) as view_count
             FROM {$table_name}
             WHERE event_type = 'lut_preview' {$date_clause}
             GROUP BY object_id, object_title, collection_slug
             ORDER BY view_count DESC
             LIMIT 20"
        );

        $top_images = $wpdb->get_results(
            "SELECT object_title, object_id, COUNT(*) as view_count
             FROM {$table_name}
             WHERE event_type = 'image_preview' {$date_clause}
             GROUP BY object_id, object_title
             ORDER BY view_count DESC
             LIMIT 20"
        );

        $top_collections = $wpdb->get_results(
            "SELECT collection_slug, COUNT(*) as view_count
             FROM {$table_name}
             WHERE event_type = 'lut_preview' AND collection_slug != '' {$date_clause}
             GROUP BY collection_slug
             ORDER BY view_count DESC
             LIMIT 10"
        );

        $total_events = $wpdb->get_results(
            "SELECT event_type, COUNT(*) as total
             FROM {$table_name}
             WHERE 1=1 {$date_clause}
             GROUP BY event_type
             ORDER BY total DESC"
        );

        $page_url = admin_url( 'options-general.php?page=gamut-lut-analytics' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'LUT Preview Analytics', 'gamut-lut-preview' ); ?></h1>

            <div style="margin: 15px 0;">
                <?php foreach ( array( '7' => '7 Days', '30' => '30 Days', '90' => '90 Days', 'all' => 'All Time' ) as $key => $label ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( 'period', $key, $page_url ) ); ?>"
                       class="button <?php echo $period === $key ? 'button-primary' : ''; ?>"><?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ( $total_events ) : ?>
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <?php foreach ( $total_events as $event ) : ?>
                <div style="background: #fff; padding: 15px 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                    <div style="font-size: 24px; font-weight: 600;"><?php echo intval( $event->total ); ?></div>
                    <div style="color: #666; text-transform: capitalize;"><?php echo esc_html( str_replace( '_', ' ', $event->event_type ) ); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 300px;">
                    <h2><?php esc_html_e( 'Top LUTs', 'gamut-lut-preview' ); ?></h2>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e( 'LUT', 'gamut-lut-preview' ); ?></th><th><?php esc_html_e( 'Collection', 'gamut-lut-preview' ); ?></th><th><?php esc_html_e( 'Views', 'gamut-lut-preview' ); ?></th></tr></thead>
                        <tbody>
                        <?php if ( $top_luts ) : ?>
                            <?php foreach ( $top_luts as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->object_title ?: '#' . $row->object_id ); ?></td>
                                <td><?php echo esc_html( $row->collection_slug ); ?></td>
                                <td><?php echo intval( $row->view_count ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="3"><?php esc_html_e( 'No data yet.', 'gamut-lut-preview' ); ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex: 1; min-width: 300px;">
                    <h2><?php esc_html_e( 'Top Sample Images', 'gamut-lut-preview' ); ?></h2>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e( 'Image', 'gamut-lut-preview' ); ?></th><th><?php esc_html_e( 'Views', 'gamut-lut-preview' ); ?></th></tr></thead>
                        <tbody>
                        <?php if ( $top_images ) : ?>
                            <?php foreach ( $top_images as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row->object_title ?: '#' . $row->object_id ); ?></td>
                                <td><?php echo intval( $row->view_count ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'No data yet.', 'gamut-lut-preview' ); ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="flex: 1; min-width: 300px;">
                    <h2><?php esc_html_e( 'Top Collections', 'gamut-lut-preview' ); ?></h2>
                    <table class="widefat striped">
                        <thead><tr><th><?php esc_html_e( 'Collection', 'gamut-lut-preview' ); ?></th><th><?php esc_html_e( 'Views', 'gamut-lut-preview' ); ?></th></tr></thead>
                        <tbody>
                        <?php if ( $top_collections ) : ?>
                            <?php foreach ( $top_collections as $row ) : ?>
                            <tr>
                                <td style="text-transform: capitalize;"><?php echo esc_html( $row->collection_slug ); ?></td>
                                <td><?php echo intval( $row->view_count ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr><td colspan="2"><?php esc_html_e( 'No data yet.', 'gamut-lut-preview' ); ?></td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
}

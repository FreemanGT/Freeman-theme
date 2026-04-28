<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSN_Database {

    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'rsn_subscribers';
    }

    public static function create_tables() {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            customer_name varchar(255) NOT NULL DEFAULT '',
            customer_email varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'waiting',
            notified_at datetime DEFAULT NULL,
            unsubscribe_token varchar(64) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY customer_email (customer_email),
            KEY status (status),
            UNIQUE KEY unique_sub (customer_email, product_id, variation_id, status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public static function insert( $data ) {
        global $wpdb;
        // 32 hex chars from a CSPRNG; column is varchar(64).
        $data['unsubscribe_token'] = bin2hex( random_bytes( 16 ) );
        $data['created_at']        = current_time( 'mysql' );
        $result = $wpdb->insert( self::table_name(), $data );
        return false === $result ? false : $wpdb->insert_id;
    }

    public static function exists( $email, $product_id, $variation_id = 0 ) {
        global $wpdb;
        $table = self::table_name();
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE customer_email = %s AND product_id = %d AND variation_id = %d AND status = 'waiting'",
            $email, $product_id, $variation_id
        ) );
    }

    public static function get( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table_name() . " WHERE id = %d", $id ) );
    }

    public static function get_by_token( $token ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM " . self::table_name() . " WHERE unsubscribe_token = %s", $token ) );
    }

    public static function get_waiting_for_product( $product_id, $variation_id = 0 ) {
        global $wpdb;
        $table = self::table_name();
        if ( $variation_id > 0 ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$table} WHERE product_id = %d AND variation_id = %d AND status = 'waiting'",
                $product_id, $variation_id
            ) );
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE product_id = %d AND status = 'waiting'",
            $product_id
        ) );
    }

    public static function mark_notified( $id ) {
        global $wpdb;
        return $wpdb->update( self::table_name(),
            array( 'status' => 'notified', 'notified_at' => current_time( 'mysql' ) ),
            array( 'id' => $id ), array( '%s', '%s' ), array( '%d' )
        );
    }

    public static function unsubscribe( $id ) {
        global $wpdb;
        return $wpdb->update( self::table_name(), array( 'status' => 'unsubscribed' ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table_name(), array( 'id' => $id ), array( '%d' ) );
    }

    public static function bulk_delete( $ids ) {
        global $wpdb;
        $table = self::table_name();
        $ids   = array_map( 'absint', $ids );
        $ph    = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        return $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$ph})", $ids ) );
    }

    public static function query( $args = array() ) {
        global $wpdb;
        $table = self::table_name();
        $d = array( 'per_page' => 20, 'page' => 1, 'status' => '', 'product_id' => 0, 'search' => '', 'orderby' => 'created_at', 'order' => 'DESC' );
        $args = wp_parse_args( $args, $d );

        $where = array( '1=1' ); $values = array();
        if ( $args['status'] )     { $where[] = 'status = %s';     $values[] = $args['status']; }
        if ( $args['product_id'] ) { $where[] = 'product_id = %d'; $values[] = $args['product_id']; }
        if ( $args['search'] )     { $where[] = '(customer_name LIKE %s OR customer_email LIKE %s)'; $s = '%'.$wpdb->esc_like($args['search']).'%'; $values[] = $s; $values[] = $s; }

        $w  = implode( ' AND ', $where );
        $ob = in_array( $args['orderby'], array('created_at','customer_name','customer_email','status','product_id'), true ) ? $args['orderby'] : 'created_at';
        $o  = strtoupper($args['order'])==='ASC' ? 'ASC' : 'DESC';
        $offset = ( max(1,(int)$args['page']) - 1 ) * (int)$args['per_page'];

        $cnt_sql = "SELECT COUNT(*) FROM {$table} WHERE {$w}";
        $total   = ! empty($values) ? (int) $wpdb->get_var( $wpdb->prepare( $cnt_sql, $values ) ) : (int) $wpdb->get_var( $cnt_sql );

        $sql = "SELECT * FROM {$table} WHERE {$w} ORDER BY {$ob} {$o} LIMIT %d OFFSET %d";
        $values[] = (int)$args['per_page']; $values[] = $offset;
        $items = $wpdb->get_results( $wpdb->prepare( $sql, $values ) );

        return array( 'items' => $items, 'total' => $total, 'pages' => ceil( $total / $args['per_page'] ) );
    }

    public static function get_stats() {
        global $wpdb;
        $t = self::table_name();
        return array(
            'total_waiting'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status='waiting'" ),
            'total_notified'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE status='notified'" ),
            'total_all'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ),
            'unique_products' => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT product_id) FROM {$t} WHERE status='waiting'" ),
            'today_signups'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE DATE(created_at)=%s", current_time('Y-m-d') ) ),
            'top_products'    => $wpdb->get_results( "SELECT product_id,variation_id,COUNT(*) as demand FROM {$t} WHERE status='waiting' GROUP BY product_id,variation_id ORDER BY demand DESC LIMIT 10" ),
        );
    }

    public static function export_csv( $args = array() ) {
        global $wpdb;
        $t = self::table_name();
        $where = array('1=1'); $values = array();
        if ( ! empty($args['status']) )     { $where[] = 'status = %s';     $values[] = $args['status']; }
        if ( ! empty($args['product_id']) ) { $where[] = 'product_id = %d'; $values[] = $args['product_id']; }
        $w   = implode( ' AND ', $where );
        $sql = "SELECT * FROM {$t} WHERE {$w} ORDER BY created_at DESC";
        return ! empty($values) ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A );
    }
}

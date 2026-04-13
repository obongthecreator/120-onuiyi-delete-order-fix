<?php
/**
 * Product Summary Class
 * Handles product sales summary and analytics
 */

if (!defined('ABSPATH')) {
    exit;
}

class Stand120_Product_Summary {
    
    /**
     * Get product summary with filters
     */
    public static function get_summary($filters = array()) {
        global $wpdb;
        
        $orders_table = $wpdb->prefix . 'stand120_orders';
        $items_table = $wpdb->prefix . 'stand120_order_items';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        $date_from = $filters['date_from'] ?? date('Y-m-d');
        $date_to = $filters['date_to'] ?? date('Y-m-d');
        
        // Get totals
        $totals = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(oi.quantity) as total_quantity,
                SUM(oi.total) as total_revenue,
                COUNT(DISTINCT o.id) as total_orders
            FROM $items_table oi
            JOIN $orders_table o ON oi.order_id = o.id
            WHERE o.order_date BETWEEN %s AND %s",
            $date_from, $date_to
        ));
        
        // Get active staff count for today
        $today = date('Y-m-d');
        $active_staff = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT staff_id) FROM $orders_table WHERE order_date = %s",
            $today
        ));
        
        // Get detailed breakdown
        $sql = "SELECT 
                    o.order_time as time,
                    o.order_date as date,
                    oi.product_name as product,
                    s.full_name as staff,
                    oi.quantity,
                    oi.total as amount
                FROM $items_table oi
                JOIN $orders_table o ON oi.order_id = o.id
                LEFT JOIN $staff_table s ON o.staff_id = s.id
                WHERE o.order_date BETWEEN %s AND %s";
        $params = array($date_from, $date_to);
        
        if (!empty($filters['product_id'])) {
            $sql .= " AND oi.product_id = %d";
            $params[] = $filters['product_id'];
        }
        
        if (!empty($filters['staff_id'])) {
            $sql .= " AND o.staff_id = %d";
            $params[] = $filters['staff_id'];
        }
        
        $sql .= " ORDER BY o.order_date DESC, o.order_time DESC";
        
        // Pagination
        $page = max(1, intval($filters['page'] ?? 1));
        $per_page = max(1, min(100, intval($filters['per_page'] ?? 50)));
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = str_replace(
            "SELECT 
                    o.order_time as time,
                    o.order_date as date,
                    oi.product_name as product,
                    s.full_name as staff,
                    oi.quantity,
                    oi.total as amount",
            "SELECT COUNT(*)",
            $sql
        );
        
        $total = $wpdb->get_var($wpdb->prepare($count_sql, $params));
        
        $sql .= " LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;
        
        $records = $wpdb->get_results($wpdb->prepare($sql, $params));
        
        return array(
            'summary' => array(
                'total_products_sold' => intval($totals->total_quantity ?? 0),
                'total_revenue' => floatval($totals->total_revenue ?? 0),
                'total_orders' => intval($totals->total_orders ?? 0),
                'active_staff_today' => intval($active_staff ?? 0)
            ),
            'records' => $records,
            'total' => intval($total),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
            'filters' => array(
                'date_from' => $date_from,
                'date_to' => $date_to
            )
        );
    }
    
    /**
     * Get product sales breakdown
     */
    public static function get_product_breakdown($date_from, $date_to) {
        global $wpdb;
        
        $orders_table = $wpdb->prefix . 'stand120_orders';
        $items_table = $wpdb->prefix . 'stand120_order_items';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                oi.product_name,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.total) as revenue
            FROM $items_table oi
            JOIN $orders_table o ON oi.order_id = o.id
            WHERE o.order_date BETWEEN %s AND %s
            GROUP BY oi.product_id
            ORDER BY quantity_sold DESC",
            $date_from, $date_to
        ));
    }
    
    /**
     * Get staff performance summary
     */
    public static function get_staff_performance($date_from, $date_to) {
        global $wpdb;
        
        $orders_table = $wpdb->prefix . 'stand120_orders';
        $staff_table = $wpdb->prefix . 'stand120_staff';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT 
                s.full_name as staff_name,
                COUNT(o.id) as orders_count,
                SUM(o.grand_total) as total_sales
            FROM $staff_table s
            LEFT JOIN $orders_table o ON s.id = o.staff_id AND o.order_date BETWEEN %s AND %s
            WHERE s.status = 'active'
            GROUP BY s.id
            ORDER BY total_sales DESC",
            $date_from, $date_to
        ));
    }
}

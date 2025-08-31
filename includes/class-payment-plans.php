<?php
/**
 * WC_Credit_Payment_Plans Class
 *
 * A helper class for all functions related to credit plans.
 *
 * @package WooCommerceCreditPaymentSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Credit_Payment_Plans {

    /**
     * Get a single credit plan by its ID.
     *
     * @param int $plan_id The ID of the plan.
     * @return object|null The plan object or null if not found.
     */
    public static function get_plan( $plan_id ) {
        global $wpdb;
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$plans_table} WHERE id = %d", absint( $plan_id ) ) );
    }

    /**
     * Get all created credit plans.
     *
     * @return array An array of plan objects.
     */
    public static function get_all_plans() {
        global $wpdb;
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        return $wpdb->get_results( "SELECT * FROM {$plans_table} ORDER BY name ASC" );
    }

    /**
     * Get available plans for a specific product.
     *
     * This function checks for plans assigned directly to the product,
     * to its categories, or global plans (not assigned to anything).
     *
     * @param int $product_id The ID of the product.
     * @return array An array of available plan objects.
     */
    public static function get_available_plans_for_product( $product_id ) {
        global $wpdb;
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return [];
        }

        $category_ids = $product->get_category_ids();
        $category_ids_placeholders = ! empty( $category_ids ) ? implode( ',', array_fill( 0, count( $category_ids ), '%d' ) ) : '0';

        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';

        // This query finds plans that are active AND:
        // 1. Are assigned directly to this product's ID.
        // OR
        // 2. Are assigned to any of this product's categories.
        // OR
        // 3. Have no assignments at all, making them global.
        $query_args = array_merge( [ $product_id ], $category_ids );
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT p.* FROM {$plans_table} p
             LEFT JOIN {$assignments_table} a ON p.id = a.plan_id
             WHERE p.status = 'active'
             AND (
                ( a.assignment_type = 'product' AND a.assignment_id = %d ) OR
                ( a.assignment_type = 'category' AND a.assignment_id IN ( {$category_ids_placeholders} ) ) OR
                ( NOT EXISTS ( SELECT 1 FROM {$assignments_table} WHERE plan_id = p.id ) )
             )
             ORDER BY p.name ASC",
            $query_args
        );

        return $wpdb->get_results( $query );
    }
}

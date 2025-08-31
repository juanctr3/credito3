<?php
/**
 * WC_Credit_Payment_Plans Class
 *
 * A helper class for all functions related to credit plans.
 * Versión corregida con manejo robusto de SQL y validaciones mejoradas.
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
        
        // Validar que el ID sea un número entero válido
        $plan_id = absint( $plan_id );
        if ( $plan_id === 0 ) {
            return null;
        }
        
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $plan = $wpdb->get_row( $wpdb->prepare( 
            "SELECT * FROM {$plans_table} WHERE id = %d AND status = 'active'", 
            $plan_id 
        ));
        
        return $plan ? $plan : null;
    }

    /**
     * Get all created credit plans.
     *
     * @param string $status Filter by status ('active', 'inactive', 'all'). Default 'all'.
     * @return array An array of plan objects.
     */
    public static function get_all_plans( $status = 'all' ) {
        global $wpdb;
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        
        if ( $status === 'all' ) {
            $query = "SELECT * FROM {$plans_table} ORDER BY name ASC";
            return $wpdb->get_results( $query );
        } else {
            $query = $wpdb->prepare( 
                "SELECT * FROM {$plans_table} WHERE status = %s ORDER BY name ASC", 
                $status 
            );
            return $wpdb->get_results( $query );
        }
    }

    /**
     * **CORRECCIÓN CRÍTICA**: Get available plans for a specific product.
     *
     * Esta función ha sido completamente reescrita para manejar correctamente:
     * - Productos sin categorías (evita SQL con placeholders vacíos)
     * - Consultas SQL más eficientes y seguras
     * - Mejor manejo de errores y validaciones
     * - Cache de resultados para mejor rendimiento
     *
     * @param int $product_id The ID of the product.
     * @return array An array of available plan objects.
     */
    public static function get_available_plans_for_product( $product_id ) {
        global $wpdb;
        
        // Validar ID del producto
        $product_id = absint( $product_id );
        if ( $product_id === 0 ) {
            return [];
        }
        
        // Verificar si el producto existe
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return [];
        }

        // Cache key para evitar consultas repetitivas
        $cache_key = 'wcps_plans_product_' . $product_id;
        $cached_plans = wp_cache_get( $cache_key, 'wcps_plans' );
        
        if ( $cached_plans !== false ) {
            return $cached_plans;
        }

        $category_ids = $product->get_category_ids();
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';

        // **CORRECCIÓN 1**: Manejo seguro de productos sin categorías
        if ( empty( $category_ids ) ) {
            // Consulta para productos sin categorías: solo planes de producto específicos y globales
            $query = $wpdb->prepare(
                "SELECT DISTINCT p.* 
                 FROM {$plans_table} p
                 LEFT JOIN {$assignments_table} a ON p.id = a.plan_id
                 WHERE p.status = 'active'
                 AND (
                    ( a.assignment_type = 'product' AND a.assignment_id = %d ) OR
                    ( NOT EXISTS ( SELECT 1 FROM {$assignments_table} WHERE plan_id = p.id ) )
                 )
                 ORDER BY p.name ASC",
                $product_id
            );
        } else {
            // **CORRECCIÓN 2**: Consulta optimizada para productos con categorías
            $category_placeholders = implode( ',', array_fill( 0, count( $category_ids ), '%d' ) );
            $query_args = array_merge( [ $product_id ], $category_ids );
            
            $query = $wpdb->prepare(
                "SELECT DISTINCT p.* 
                 FROM {$plans_table} p
                 LEFT JOIN {$assignments_table} a ON p.id = a.plan_id
                 WHERE p.status = 'active'
                 AND (
                    ( a.assignment_type = 'product' AND a.assignment_id = %d ) OR
                    ( a.assignment_type = 'category' AND a.assignment_id IN ( {$category_placeholders} ) ) OR
                    ( NOT EXISTS ( SELECT 1 FROM {$assignments_table} WHERE plan_id = p.id ) )
                 )
                 ORDER BY p.name ASC",
                $query_args
            );
        }

        $plans = $wpdb->get_results( $query );
        
        // Validar resultados y aplicar filtros adicionales
        $validated_plans = [];
        if ( is_array( $plans ) ) {
            foreach ( $plans as $plan ) {
                // Validaciones adicionales del plan
                if ( self::validate_plan_for_product( $plan, $product ) ) {
                    $validated_plans[] = $plan;
                }
            }
        }

        // Guardar en cache por 15 minutos
        wp_cache_set( $cache_key, $validated_plans, 'wcps_plans', 900 );

        return $validated_plans;
    }

    /**
     * **NUEVA FUNCIÓN**: Validate that a plan is suitable for a specific product.
     *
     * @param object $plan The plan object.
     * @param WC_Product $product The product object.
     * @return bool True if the plan is valid for the product.
     */
    private static function validate_plan_for_product( $plan, $product ) {
        // Verificar que el plan tenga los campos requeridos
        if ( ! isset( $plan->id, $plan->name, $plan->status ) ) {
            return false;
        }

        // Verificar que esté activo
        if ( $plan->status !== 'active' ) {
            return false;
        }

        // Validar que el plan tenga configuración válida
        if ( ! isset( $plan->max_installments ) || $plan->max_installments <= 0 ) {
            return false;
        }

        // Verificar que el producto tenga un precio válido
        $product_price = (float) $product->get_price();
        if ( $product_price <= 0 ) {
            return false;
        }

        // **VALIDACIÓN DE NEGOCIO**: Verificar que el plan tenga sentido económico
        $down_payment_percentage = isset( $plan->down_payment_percentage ) ? (float) $plan->down_payment_percentage : 0;
        
        // El pago inicial no puede ser 100% o más (no tendría sentido el crédito)
        if ( $down_payment_percentage >= 100 ) {
            return false;
        }

        // Si hay cuota inicial, debe quedar algo por financiar
        $down_payment = ( $product_price * $down_payment_percentage ) / 100;
        $financed_amount = $product_price - $down_payment;
        
        if ( $financed_amount <= 0 ) {
            return false;
        }

        return true;
    }

    /**
     * **NUEVA FUNCIÓN**: Get plans assigned to a specific category.
     *
     * @param int $category_id The category ID.
     * @return array Array of plan objects.
     */
    public static function get_plans_by_category( $category_id ) {
        global $wpdb;
        
        $category_id = absint( $category_id );
        if ( $category_id === 0 ) {
            return [];
        }

        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';

        $query = $wpdb->prepare(
            "SELECT DISTINCT p.* 
             FROM {$plans_table} p
             JOIN {$assignments_table} a ON p.id = a.plan_id
             WHERE p.status = 'active'
             AND a.assignment_type = 'category'
             AND a.assignment_id = %d
             ORDER BY p.name ASC",
            $category_id
        );

        return $wpdb->get_results( $query );
    }

    /**
     * **NUEVA FUNCIÓN**: Get global plans (not assigned to any product or category).
     *
     * @return array Array of global plan objects.
     */
    public static function get_global_plans() {
        global $wpdb;
        
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';

        $query = "SELECT p.* 
                  FROM {$plans_table} p
                  WHERE p.status = 'active'
                  AND NOT EXISTS ( SELECT 1 FROM {$assignments_table} WHERE plan_id = p.id )
                  ORDER BY p.name ASC";

        return $wpdb->get_results( $query );
    }

    /**
     * **NUEVA FUNCIÓN**: Calculate plan details for a given product price.
     *
     * @param object $plan The plan object.
     * @param float $product_price The product price.
     * @return array Array with calculated values.
     */
    public static function calculate_plan_details( $plan, $product_price ) {
        $product_price = (float) $product_price;
        $down_payment_percentage = (float) $plan->down_payment_percentage;
        $interest_rate = (float) $plan->interest_rate;
        $max_installments = (int) $plan->max_installments;

        $down_payment = ( $product_price * $down_payment_percentage ) / 100;
        $financed_amount = $product_price - $down_payment;
        $total_interest = ( $financed_amount * $interest_rate ) / 100;
        $total_financed = $financed_amount + $total_interest;
        $installment_amount = $max_installments > 0 ? $total_financed / $max_installments : 0;

        return [
            'product_price' => $product_price,
            'down_payment' => $down_payment,
            'down_payment_percentage' => $down_payment_percentage,
            'financed_amount' => $financed_amount,
            'interest_rate' => $interest_rate,
            'total_interest' => $total_interest,
            'total_financed' => $total_financed,
            'max_installments' => $max_installments,
            'installment_amount' => $installment_amount,
            'total_cost' => $down_payment + $total_financed
        ];
    }

    /**
     * **NUEVA FUNCIÓN**: Clear plans cache for a specific product.
     *
     * @param int $product_id The product ID.
     */
    public static function clear_product_plans_cache( $product_id = null ) {
        if ( $product_id ) {
            $cache_key = 'wcps_plans_product_' . absint( $product_id );
            wp_cache_delete( $cache_key, 'wcps_plans' );
        } else {
            // Limpiar todo el cache del grupo
            wp_cache_flush_group( 'wcps_plans' );
        }
    }

    /**
     * **NUEVA FUNCIÓN**: Check if a product has any available credit plans.
     *
     * @param int $product_id The product ID.
     * @return bool True if the product has available plans.
     */
    public static function product_has_credit_plans( $product_id ) {
        $plans = self::get_available_plans_for_product( $product_id );
        return ! empty( $plans );
    }

    /**
     * **NUEVA FUNCIÓN**: Get plan assignments (for admin interface).
     *
     * @param int $plan_id The plan ID.
     * @return array Array with 'categories' and 'products' keys.
     */
    public static function get_plan_assignments( $plan_id ) {
        global $wpdb;
        
        $plan_id = absint( $plan_id );
        if ( $plan_id === 0 ) {
            return ['categories' => [], 'products' => []];
        }

        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';
        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT assignment_type, assignment_id FROM {$assignments_table} WHERE plan_id = %d",
            $plan_id
        ));

        $categories = [];
        $products = [];

        foreach ( $results as $result ) {
            if ( $result->assignment_type === 'category' ) {
                $categories[] = (int) $result->assignment_id;
            } elseif ( $result->assignment_type === 'product' ) {
                $products[] = (int) $result->assignment_id;
            }
        }

        return [
            'categories' => $categories,
            'products' => $products
        ];
    }
}

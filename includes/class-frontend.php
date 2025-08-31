<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Credit_Frontend {

    public static $endpoint = 'mis-creditos';

    public function __construct() {
        // Ubicación dinámica basada en configuración
        $location = get_option('wcps_plans_display_location', 'woocommerce_before_add_to_cart_button');
        add_action( $location, array( $this, 'display_credit_plans' ), 5 );

        // CORRECCIÓN: Validación mejorada con prioridad ajustada
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_plan_selection' ), 10, 3 );

        // Hooks para el funcionamiento del carrito y los pedidos
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_plan_to_cart_item' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_plan_in_cart' ), 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_item_price' ), 20, 1 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'create_credit_on_order_creation' ), 20, 2 );
        
        // SECCIÓN "MI CUENTA"
        add_action( 'init', array( $this, 'add_my_account_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_my_credits_link' ) );
        add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', array( $this, 'my_credits_endpoint_content' ) );
        add_filter( 'the_title', array( $this, 'my_credits_endpoint_title' ) );
        add_action( 'admin_post_wcps_add_credit_comment', array( $this, 'handle_add_credit_comment' ) );
        add_action( 'admin_post_nopriv_wcps_add_credit_comment', array( $this, 'handle_add_credit_comment' ) );
    }

    /**
     * CORRECCIÓN PRINCIPAL: Validación mejorada que maneja correctamente todos los casos
     */
    public function validate_plan_selection( $passed, $product_id, $quantity ) {
        // Solo validar si estamos en el proceso de añadir al carrito (no en actualizaciones)
        if ( ! isset( $_POST['add-to-cart'] ) ) {
            return $passed;
        }

        // Obtener planes disponibles para el producto
        $plans = WC_Credit_Payment_Plans::get_available_plans_for_product( $product_id );

        // Si no hay planes disponibles, permitir la compra normal
        if ( empty( $plans ) ) {
            return $passed;
        }

        // Si hay planes disponibles, verificar que se haya seleccionado una opción
        if ( ! isset( $_POST['wcps_selected_plan'] ) ) {
            wc_add_notice( __( 'Por favor, elige una opción de pago para continuar.', 'wc-credit-payment-system' ), 'error' );
            return false;
        }

        $selected_plan = sanitize_text_field( $_POST['wcps_selected_plan'] );
        
        // Verificar que no esté vacío
        if ( empty( $selected_plan ) ) {
            wc_add_notice( __( 'Por favor, elige una opción de pago para continuar.', 'wc-credit-payment-system' ), 'error' );
            return false;
        }

        // Si se seleccionó pago completo, permitir
        if ( $selected_plan === 'full_payment' ) {
            return $passed;
        }

        // Validar que el plan seleccionado sea válido
        $plan_id = absint( $selected_plan );
        if ( $plan_id > 0 ) {
            // Verificar que el plan existe y está disponible para este producto
            $plan_exists = false;
            foreach ( $plans as $plan ) {
                if ( $plan->id == $plan_id ) {
                    $plan_exists = true;
                    break;
                }
            }

            if ( ! $plan_exists ) {
                wc_add_notice( __( 'El plan seleccionado no es válido para este producto.', 'wc-credit-payment-system' ), 'error' );
                return false;
            }
        }

        return $passed;
    }

    /**
     * Muestra el bloque de planes de crédito.
     */
    public function display_credit_plans() {
        global $product;
        if ( ! is_a( $product, 'WC_Product' ) ) { 
            return; 
        }

        $plans = WC_Credit_Payment_Plans::get_available_plans_for_product( $product->get_id() );
        if ( empty( $plans ) ) { 
            return; 
        }

        wc_get_template( 
            'frontend/product-plans.php', 
            array( 'plans' => $plans, 'product' => $product ), 
            '', 
            WCPS_PLUGIN_DIR . 'templates/' 
        );
    }

    /**
     * Guarda la información del plan seleccionado en el item del carrito.
     * CORRECCIÓN: Mejorado el manejo de datos
     */
    public function add_plan_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
        if ( ! isset( $_POST['wcps_selected_plan'] ) ) {
            return $cart_item_data;
        }

        $selected_plan = sanitize_text_field( $_POST['wcps_selected_plan'] );
        
        // Si es pago completo, no hacer nada
        if ( empty( $selected_plan ) || $selected_plan === 'full_payment' ) {
            return $cart_item_data;
        }

        $plan_id = absint( $selected_plan );
        if ( $plan_id === 0 ) {
            return $cart_item_data;
        }

        // Obtener el plan y validar
        $plan = WC_Credit_Payment_Plans::get_plan( $plan_id );
        if ( ! $plan ) {
            return $cart_item_data;
        }

        // Obtener el producto correcto (puede ser variación)
        $product = wc_get_product( $variation_id ? $variation_id : $product_id );
        if ( ! $product ) {
            return $cart_item_data;
        }

        $price = (float) $product->get_price();
        if ( $price <= 0 ) {
            return $cart_item_data;
        }

        // Calcular cuota inicial
        $down_payment_percentage = isset( $plan->down_payment_percentage ) ? (float) $plan->down_payment_percentage : 0;
        $down_payment = ( $price * $down_payment_percentage ) / 100;
        
        // Guardar información del plan en el item del carrito
        $cart_item_data['wcps_plan_id'] = $plan_id;
        $cart_item_data['wcps_original_price'] = $price;
        $cart_item_data['wcps_down_payment'] = $down_payment;
        $cart_item_data['wcps_plan_name'] = $plan->name;

        return $cart_item_data;
    }

    /**
     * Actualiza el precio del producto en el carrito al valor de la cuota inicial.
     */
    public function update_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { 
            return; 
        }

        // Verificar que el carrito esté disponible
        if ( ! $cart ) {
            return;
        }
        
        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['wcps_plan_id'] ) && isset( $cart_item['wcps_down_payment'] ) ) {
                // Solo cambiar el precio si hay una cuota inicial
                if ( $cart_item['wcps_down_payment'] > 0 || $cart_item['wcps_down_payment'] === 0.0 ) {
                    $cart_item['data']->set_price( $cart_item['wcps_down_payment'] );
                }
            }
        }
    }

    /**
     * Muestra los detalles del plan en el carrito y en la página de pago.
     */
    public function display_plan_in_cart( $item_data, $cart_item ) {
        if ( isset( $cart_item['wcps_plan_id'] ) ) {
            $plan = WC_Credit_Payment_Plans::get_plan( $cart_item['wcps_plan_id'] );
            if ( $plan ) {
                $item_data[] = array( 
                    'key' => __( 'Plan de Crédito', 'wc-credit-payment-system' ), 
                    'value' => esc_html( $plan->name ) 
                );
                
                $item_data[] = array( 
                    'key' => __( 'Precio Original', 'wc-credit-payment-system' ), 
                    'value' => wc_price( $cart_item['wcps_original_price'] ) 
                );
                
                if ( isset( $cart_item['wcps_down_payment'] ) ) {
                    $item_data[] = array( 
                        'key' => __( 'Cuota Inicial', 'wc-credit-payment-system' ), 
                        'value' => wc_price( $cart_item['wcps_down_payment'] ) 
                    );
                    
                    $financed = $cart_item['wcps_original_price'] - $cart_item['wcps_down_payment'];
                    $item_data[] = array( 
                        'key' => __( 'Monto a Financiar', 'wc-credit-payment-system' ), 
                        'value' => wc_price( $financed ) 
                    );
                }
            }
        }
        return $item_data;
    }
    
    /**
     * Crea la cuenta de crédito y sus cuotas cuando se crea un pedido.
     * CORRECCIÓN: Mejorado el manejo de errores y validaciones
     */
    public function create_credit_on_order_creation( $order, $data ) {
        global $wpdb;
        
        if ( ! $order ) {
            return;
        }

        $cart = WC()->cart;
        if ( ! $cart ) {
            return;
        }

        $cart_contents = $cart->get_cart();
        if ( empty( $cart_contents ) ) {
            return;
        }
        
        foreach ( $cart_contents as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['wcps_plan_id'] ) ) {
                continue;
            }

            $plan_id = absint( $cart_item['wcps_plan_id'] );
            if ( $plan_id === 0 ) {
                continue;
            }

            $plan = WC_Credit_Payment_Plans::get_plan( $plan_id );
            if ( ! $plan ) {
                continue;
            }

            $product = $cart_item['data'];
            if ( ! $product ) {
                continue;
            }
            
            // Usar el precio original guardado
            $price = isset( $cart_item['wcps_original_price'] ) ? (float) $cart_item['wcps_original_price'] : (float) $product->get_price();
            
            if ( $price <= 0 ) {
                continue;
            }

            // Calcular montos
            $down_payment_percentage = isset( $plan->down_payment_percentage ) ? (float) $plan->down_payment_percentage : 0;
            $down_payment = ( $price * $down_payment_percentage ) / 100;
            $financed_amount = $price - $down_payment;
            
            if ( $financed_amount <= 0 ) {
                continue; // No hay nada que financiar
            }

            $interest_rate = isset( $plan->interest_rate ) ? (float) $plan->interest_rate : 0;
            $total_interest = ( $financed_amount * $interest_rate ) / 100;
            $total_financed = $financed_amount + $total_interest;
            
            $max_installments = isset( $plan->max_installments ) ? (int) $plan->max_installments : 0;
            if ( $max_installments <= 0 ) {
                continue; // No se pueden crear cuotas
            }

            $installment_amount = $total_financed / $max_installments;
            
            // Crear cuenta de crédito
            $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
            $result = $wpdb->insert( $accounts_table, [
                'order_id' => $order->get_id(),
                'user_id' => $order->get_user_id() ?: 0,
                'plan_id' => $plan_id,
                'product_id' => $product->get_id(),
                'total_amount' => $price,
                'down_payment' => $down_payment,
                'financed_amount' => $financed_amount,
                'installment_amount' => $installment_amount,
                'total_installments' => $max_installments,
                'paid_installments' => 0,
                'status' => 'active'
            ]);
            
            if ( $result === false ) {
                // Log del error pero no interrumpir el proceso
                error_log( 'WCPS Error: No se pudo crear la cuenta de crédito para el pedido ' . $order->get_id() );
                continue;
            }
            
            $credit_account_id = $wpdb->insert_id;
            if ( ! $credit_account_id ) {
                continue;
            }

            // Crear cuotas
            $installments_table = $wpdb->prefix . 'wc_credit_installments';
            $payment_frequency = isset( $plan->payment_frequency ) ? $plan->payment_frequency : 'monthly';
            
            for ( $i = 1; $i <= $max_installments; $i++ ) {
                $due_date = new DateTime();
                
                // Calcular intervalo según frecuencia
                switch ( $payment_frequency ) {
                    case 'weekly':
                        $interval_string = "P{$i}W";
                        break;
                    case 'biweekly':
                        $weeks = $i * 2;
                        $interval_string = "P{$weeks}W";
                        break;
                    case 'monthly':
                    default:
                        $interval_string = "P{$i}M";
                        break;
                }
                
                try {
                    $due_date->add( new DateInterval( $interval_string ) );
                } catch ( Exception $e ) {
                    // Si hay error con la fecha, usar un cálculo alternativo
                    $due_date = new DateTime();
                    $days_to_add = 30 * $i; // Aproximación mensual
                    $due_date->modify( "+{$days_to_add} days" );
                }
                
                $wpdb->insert( $installments_table, [
                    'credit_account_id' => $credit_account_id,
                    'installment_number' => $i,
                    'amount' => $installment_amount,
                    'due_date' => $due_date->format('Y-m-d'),
                    'status' => 'pending'
                ]);
            }
            
            // Añadir nota al pedido
            $order->add_order_note( sprintf( 
                __( 'Cuenta de crédito #%d creada para el producto "%s" con el plan "%s". Cuota inicial: %s, Financiado: %s en %d cuotas.', 'wc-credit-payment-system' ), 
                $credit_account_id, 
                $product->get_name(), 
                $plan->name,
                wc_price( $down_payment ),
                wc_price( $total_financed ),
                $max_installments
            ));
        }
    }

    // --- MÉTODOS DE LA SECCIÓN "MI CUENTA" ---

    public function add_my_account_endpoint() { 
        add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES ); 
    }
    
    public function add_my_credits_link( $menu_links ) { 
        return array_slice( $menu_links, 0, 2, true ) + 
               array( self::$endpoint => __( 'Mis Créditos', 'wc-credit-payment-system' ) ) + 
               array_slice( $menu_links, 2, null, true ); 
    }
    
    public function my_credits_endpoint_content() { 
        $view_credit_id = isset( $_GET['view-credit'] ) ? absint( $_GET['view-credit'] ) : 0; 
        
        if ( $view_credit_id > 0 ) { 
            wc_get_template( 'frontend/view-credit-details.php', ['credit_id' => $view_credit_id], '', WCPS_PLUGIN_DIR . 'templates/' ); 
        } else { 
            wc_get_template( 'frontend/my-account-credits.php', [], '', WCPS_PLUGIN_DIR . 'templates/' ); 
        } 
    }
    
    public function my_credits_endpoint_title( $title ) { 
        global $wp_query; 
        $is_endpoint = isset( $wp_query->query_vars[self::$endpoint] ); 
        
        if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) { 
            $title = __( 'Mis Créditos', 'wc-credit-payment-system' ); 
            remove_filter( 'the_title', array( $this, 'my_credits_endpoint_title' ) ); 
        } 
        return $title; 
    }
    
    /**
     * Manejo de comentarios en créditos
     */
    public function handle_add_credit_comment() {
        // Verificar nonce de seguridad
        if ( ! isset( $_POST['wcps_comment_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wcps_comment_nonce'] ), 'add_credit_comment' ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        
        // Verificar que el usuario esté logueado
        if ( ! is_user_logged_in() ) {
            wp_die( __( 'Debes iniciar sesión para comentar.', 'wc-credit-payment-system' ) );
        }
        
        // Obtener y sanitizar datos del formulario
        $credit_id = isset( $_POST['credit_id'] ) ? absint( $_POST['credit_id'] ) : 0;
        $comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( $_POST['comment'] ) : '';
        $user_id = get_current_user_id();
        
        // Validar datos requeridos
        if ( empty( $comment ) || $credit_id === 0 ) {
            wp_redirect( add_query_arg( 'comment_error', 'empty', wp_get_referer() ) );
            exit;
        }
        
        // Verificar que el usuario sea propietario del crédito
        global $wpdb;
        $is_owner = $wpdb->get_var( $wpdb->prepare( 
            "SELECT id FROM {$wpdb->prefix}wc_credit_accounts WHERE id = %d AND user_id = %d", 
            $credit_id, 
            $user_id 
        ));
        
        if ( ! $is_owner ) {
            wp_die( __( 'No tienes permiso para comentar en este crédito.', 'wc-credit-payment-system' ) );
        }
        
        // Insertar el comentario en la base de datos
        $result = $wpdb->insert( 
            $wpdb->prefix . 'wc_credit_comments', 
            [
                'credit_account_id' => $credit_id,
                'user_id' => $user_id,
                'comment' => $comment
            ]
        );
        
        // Verificar que se insertó correctamente
        if ( $result === false ) {
            wp_redirect( add_query_arg( 'comment_error', 'database', wp_get_referer() ) );
            exit;
        }
        
        // Enviar notificación al administrador
        try {
            $notifications = new WC_Credit_Notifications();
            $notifications->send_notification( 
                'Nuevo Comentario de Cliente', 
                'admin', 
                [
                    'credit_id' => $credit_id, 
                    'comment' => $comment
                ] 
            );
        } catch ( Exception $e ) {
            // Log del error pero no interrumpir el flujo
            error_log( 'Error enviando notificación de comentario: ' . $e->getMessage() );
        }
        
        // Redireccionar con mensaje de éxito
        wp_redirect( add_query_arg( 'comment_success', '1', wp_get_referer() ) );
        exit;
    }
}

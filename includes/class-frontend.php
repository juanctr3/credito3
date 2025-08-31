<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class WC_Credit_Frontend {

    public static $endpoint = 'mis-creditos';

    public function __construct() {
        // **NUEVA LÓGICA DE COMPATIBILIDAD CON TEMAS**
        // Este hook se asegura de que los planes se muestren en el lugar correcto (dentro del formulario),
        // sin importar la estructura del tema. La opción de los ajustes ya no es necesaria.
        add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'display_credit_plans' ), 5 );

        // Validación reforzada del lado del servidor. Se ejecuta con alta prioridad.
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_plan_selection' ), 99, 3 );

        // El resto de los hooks para el funcionamiento del carrito y los pedidos
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_plan_to_cart_item' ), 10, 3 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_plan_in_cart' ), 10, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_cart_item_price' ), 20, 1 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'create_credit_on_order_creation' ), 20, 2 );
        
        // --- SECCIÓN "MI CUENTA" ---
        add_action( 'init', array( $this, 'add_my_account_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( $this, 'add_my_credits_link' ) );
        add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', array( $this, 'my_credits_endpoint_content' ) );
        add_filter( 'the_title', array( $this, 'my_credits_endpoint_title' ) );
        add_action( 'admin_post_wcps_add_credit_comment', array( $this, 'handle_add_credit_comment' ) );
        add_action( 'admin_post_nopriv_wcps_add_credit_comment', array( $this, 'handle_add_credit_comment' ) );
    }

    /**
     * Valida que se haya seleccionado un plan si hay planes disponibles.
     */
    public function validate_plan_selection( $passed, $product_id, $quantity ) {
        $plans = WC_Credit_Payment_Plans::get_available_plans_for_product( $product_id );

        if ( ! empty( $plans ) ) {
            if ( ! isset( $_POST['wcps_selected_plan'] ) || empty( $_POST['wcps_selected_plan'] ) ) {
                wc_add_notice( __( 'Por favor, elige una opción de pago para continuar.', 'wc-credit-payment-system' ), 'error' );
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
        if ( ! is_a( $product, 'WC_Product' ) ) { return; }

        $plans = WC_Credit_Payment_Plans::get_available_plans_for_product( $product->get_id() );
        if ( empty( $plans ) ) { return; }

        wc_get_template( 
            'frontend/product-plans.php', 
            array( 'plans' => $plans, 'product' => $product ), 
            '', 
            WCPS_PLUGIN_DIR . 'templates/' 
        );
    }

    /**
     * Guarda la información del plan seleccionado en el item del carrito.
     */
    public function add_plan_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['wcps_selected_plan'] ) && ! empty( $_POST['wcps_selected_plan'] ) && $_POST['wcps_selected_plan'] !== 'full_payment' ) {
            $plan_id = absint( $_POST['wcps_selected_plan'] );
            $plan = WC_Credit_Payment_Plans::get_plan( $plan_id );
            $product = wc_get_product( $product_id );
            if ($plan && $product) {
                $price = (float) $product->get_price();
                $down_payment = ( $price * (float)$plan->down_payment_percentage ) / 100;
                $cart_item_data['wcps_plan_id'] = $plan_id;
                $cart_item_data['wcps_original_price'] = $price;
                $cart_item_data['wcps_down_payment'] = $down_payment;
            }
        }
        return $cart_item_data;
    }

    /**
     * Actualiza el precio del producto en el carrito al valor de la cuota inicial.
     */
    public function update_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
        foreach ( $cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['wcps_plan_id'] ) && isset($cart_item['wcps_down_payment']) ) {
                $cart_item['data']->set_price( $cart_item['wcps_down_payment'] );
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
                $item_data[] = array( 'key' => __( 'Plan de Crédito', 'wc-credit-payment-system' ), 'value' => esc_html( $plan->name ) );
                $item_data[] = array( 'key' => __( 'Precio Original', 'wc-credit-payment-system' ), 'value' => wc_price( $cart_item['wcps_original_price'] ) );
            }
        }
        return $item_data;
    }
    
    /**
     * Crea la cuenta de crédito y sus cuotas cuando se crea un pedido.
     */
    public function create_credit_on_order_creation( $order, $data ) {
        global $wpdb;
        $cart = WC()->cart->get_cart();
        foreach ( $cart as $cart_item ) {
            if ( isset( $cart_item['wcps_plan_id'] ) ) {
                $plan_id = $cart_item['wcps_plan_id'];
                $plan = WC_Credit_Payment_Plans::get_plan( $plan_id );
                $product = $cart_item['data'];
                $price = (float) $cart_item['wcps_original_price'];
                $down_payment = ( $price * (float)$plan->down_payment_percentage ) / 100;
                $financed_amount = $price - $down_payment;
                $total_interest = ( $financed_amount * (float)$plan->interest_rate ) / 100;
                $total_financed = $financed_amount + $total_interest;
                $installment_amount = $plan->max_installments > 0 ? $total_financed / $plan->max_installments : 0;
                $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
                $wpdb->insert( $accounts_table, [ 'order_id' => $order->get_id(), 'user_id' => $order->get_user_id(), 'plan_id' => $plan_id, 'product_id' => $product->get_id(), 'total_amount' => $price, 'down_payment' => $down_payment, 'financed_amount' => $financed_amount, 'installment_amount' => $installment_amount, 'total_installments' => $plan->max_installments, 'status' => 'active' ]);
                $credit_account_id = $wpdb->insert_id;
                $installments_table = $wpdb->prefix . 'wc_credit_installments';
                if ( $plan->max_installments > 0 && $credit_account_id ) {
                    for ( $i = 1; $i <= $plan->max_installments; $i++ ) {
                        $due_date = new DateTime();
                        $interval_string = match($plan->payment_frequency) { 'weekly' => "P{$i}W", 'biweekly' => "P" . ($i * 2) . "W", default => "P{$i}M", };
                        $due_date->add(new DateInterval($interval_string));
                        $wpdb->insert( $installments_table, [ 'credit_account_id' => $credit_account_id, 'installment_number' => $i, 'amount' => $installment_amount, 'due_date' => $due_date->format('Y-m-d'), 'status' => 'pending' ]);
                    }
                }
                $order->add_order_note( sprintf( __( 'Cuenta de crédito #%d creada para el producto "%s" con el plan "%s".', 'wc-credit-payment-system' ), $credit_account_id, $product->get_name(), $plan->name ) );
            }
        }
    }

    // --- MÉTODOS DE LA SECCIÓN "MI CUENTA" ---

    public function add_my_account_endpoint() { add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES ); }
    public function add_my_credits_link( $menu_links ) { return array_slice( $menu_links, 0, 2, true ) + array( self::$endpoint => __( 'Mis Créditos', 'wc-credit-payment-system' ) ) + array_slice( $menu_links, 2, null, true ); }
    public function my_credits_endpoint_content() { $view_credit_id = isset( $_GET['view-credit'] ) ? absint( $_GET['view-credit'] ) : 0; if ( $view_credit_id > 0 ) { wc_get_template( 'frontend/view-credit-details.php', ['credit_id' => $view_credit_id], '', WCPS_PLUGIN_DIR . 'templates/' ); } else { wc_get_template( 'frontend/my-account-credits.php', [], '', WCPS_PLUGIN_DIR . 'templates/' ); } }
    public function my_credits_endpoint_title( $title ) { global $wp_query; $is_endpoint = isset( $wp_query->query_vars[self::$endpoint] ); if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) { $title = __( 'Mis Créditos', 'wc-credit-payment-system' ); remove_filter( 'the_title', array( $this, 'my_credits_endpoint_title' ) ); } return $title; }
    public function handle_add_credit_comment() { if ( ! isset( $_POST['wcps_comment_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wcps_comment_nonce'] ), 'add_credit_comment' ) ) { wp_die( 'Error de seguridad.' ); } if ( ! is_user_logged_in() ) { wp_die( 'Debes iniciar sesión para comentar.' ); } $credit_id = isset( $_POST['credit_id'] ) ? absint( $_POST['credit_id'] ) : 0; $comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( $_POST['comment'] ) : ''; $user_id = get_current_user_id(); if ( empty( $comment ) || $credit_id === 0 ) { wp_redirect( add_query_arg('comment_error', 'empty', wp_get_referer() ) ); exit; } global $wpdb; $is_owner = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wc_credit_accounts WHERE id = %d AND user_id = %d", $credit_id, $user_id ) ); if ( ! $is_owner ) { wp_die('No tienes permiso para comentar en este crédito.'); } $wpdb->insert( $wpdb->prefix . 'wc_credit_comments', [ 'credit_account_id' => $credit_id, 'user_id' => $user_id, 'comment' => $comment ]); $notifications = new WC_Credit_Notifications(); $notifications->send_notification( 'Nuevo Comentario de Cliente', 'admin', ['credit_id' => $credit_id, 'comment' => $comment] ); wp_redirect( add_query_arg('comment_success', '1', wp_get_referer() ) ); exit; }
}
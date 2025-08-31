<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Credit_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_post_save_wc_credit_plan', array( $this, 'save_credit_plan' ) );
        add_action( 'admin_post_delete_wc_credit_plan', array( $this, 'delete_credit_plan' ) );
        add_action( 'admin_post_save_wcps_settings_templates', array( $this, 'save_settings_and_templates' ) );
        
        // **NUEVO**: Hook para limpiar cache cuando se modifiquen planes
        add_action( 'admin_post_save_wc_credit_plan', array( $this, 'clear_plans_cache' ), 20 );
        add_action( 'admin_post_delete_wc_credit_plan', array( $this, 'clear_plans_cache' ), 20 );
        
        // **NUEVO**: Añadir columnas personalizadas en listado de productos
        add_filter( 'manage_product_posts_columns', array( $this, 'add_product_credit_plans_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'display_product_credit_plans_column' ), 10, 2 );
    }

    public function add_admin_menu() {
        // Página principal de planes
        $plans_page = add_submenu_page(
            'woocommerce',
            __( 'Planes de Crédito', 'wc-credit-payment-system' ),
            __( 'Planes de Crédito', 'wc-credit-payment-system' ),
            'manage_woocommerce',
            'wc-credit-plans',
            array( $this, 'credit_plans_page_html' )
        );
        
        // Página de ajustes y plantillas
        $settings_page = add_submenu_page(
            'woocommerce',
            __( 'Ajustes de Crédito', 'wc-credit-payment-system' ),
            __( 'Ajustes de Crédito', 'wc-credit-payment-system' ),
            'manage_woocommerce',
            'wc-credit-settings',
            array( $this, 'settings_templates_page_html' )
        );

        // **NUEVO**: Página de gestión de cuentas de crédito
        $accounts_page = add_submenu_page(
            'woocommerce',
            __( 'Cuentas de Crédito', 'wc-credit-payment-system' ),
            __( 'Cuentas de Crédito', 'wc-credit-payment-system' ),
            'manage_woocommerce',
            'wc-credit-accounts',
            array( $this, 'credit_accounts_page_html' )
        );

        // Cargar assets solo en nuestras páginas
        add_action( 'load-' . $plans_page, array( $this, 'load_admin_assets' ) );
        add_action( 'load-' . $settings_page, array( $this, 'load_admin_assets' ) );
        add_action( 'load-' . $accounts_page, array( $this, 'load_admin_assets' ) );
    }

    /**
     * **NUEVO**: Cargar assets de admin solo cuando sea necesario
     */
    public function load_admin_assets() {
        wp_enqueue_style( 'woocommerce_admin_styles' );
        wp_enqueue_script( 'wc-enhanced-select' );
        wp_enqueue_script( 'wc-product-search' );
        
        // Estilos personalizados para el plugin
        wp_enqueue_style( 'wcps-admin-style', WCPS_PLUGIN_URL . 'assets/css/admin.css', array(), WCPS_VERSION );
    }

    public function credit_plans_page_html() {
        $action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : 'list';
        
        if ( $action === 'edit' || $action === 'new' ) {
            include_once WCPS_PLUGIN_DIR . 'templates/admin/credit-plan-form-page.php';
        } else {
            include_once WCPS_PLUGIN_DIR . 'templates/admin/credit-plans-list-page.php';
        }
    }

    public function settings_templates_page_html() {
        include_once WCPS_PLUGIN_DIR . 'templates/admin/settings-templates-page.php';
    }

    /**
     * **NUEVO**: Página para gestionar cuentas de crédito activas
     */
    public function credit_accounts_page_html() {
        include_once WCPS_PLUGIN_DIR . 'templates/admin/credit-accounts-page.php';
    }
    
    /**
     * **MEJORADO**: Función de guardado con mejor validación y manejo de errores
     */
    public function save_credit_plan() {
        // Verificaciones de seguridad
        if ( ! $this->verify_save_plan_security() ) {
            return;
        }
        
        global $wpdb;
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';
        
        $plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
        
        // **MEJORA**: Validación de datos más robusta
        $validation_result = $this->validate_plan_data( $_POST );
        if ( is_wp_error( $validation_result ) ) {
            wp_redirect( add_query_arg( 
                array( 
                    'error' => 'validation',
                    'message' => urlencode( $validation_result->get_error_message() )
                ), 
                wp_get_referer() 
            ));
            exit;
        }
        
        $data = array(
            'name'                      => sanitize_text_field( $_POST['name'] ),
            'description'               => sanitize_textarea_field( $_POST['description'] ),
            'down_payment_percentage'   => floatval( $_POST['down_payment_percentage'] ),
            'interest_rate'             => floatval( $_POST['interest_rate'] ),
            'max_installments'          => absint( $_POST['max_installments'] ),
            'payment_frequency'         => sanitize_key( $_POST['payment_frequency'] ),
            'notification_days_before'  => absint( $_POST['notification_days_before'] ),
            'status'                    => sanitize_key( $_POST['status'] ),
        );
        
        // Insertar o actualizar plan
        if ( $plan_id > 0 ) {
            $result = $wpdb->update( $plans_table, $data, array( 'id' => $plan_id ) );
        } else {
            $result = $wpdb->insert( $plans_table, $data );
            $plan_id = $wpdb->insert_id;
        }

        if ( $result === false ) {
            wp_redirect( add_query_arg( 'error', 'database', wp_get_referer() ) );
            exit;
        }

        // Gestionar asignaciones
        if ( $plan_id > 0 ) {
            $this->save_plan_assignments( $plan_id, $_POST );
        }

        wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&success=1' ) );
        exit;
    }

    /**
     * **NUEVA FUNCIÓN**: Validar datos del plan antes de guardar
     */
    private function validate_plan_data( $post_data ) {
        // Validar nombre (requerido)
        if ( empty( $post_data['name'] ) ) {
            return new WP_Error( 'missing_name', __( 'El nombre del plan es obligatorio.', 'wc-credit-payment-system' ) );
        }

        // Validar porcentaje de cuota inicial
        $down_payment = floatval( $post_data['down_payment_percentage'] );
        if ( $down_payment < 0 || $down_payment >= 100 ) {
            return new WP_Error( 'invalid_down_payment', __( 'La cuota inicial debe estar entre 0% y 99%.', 'wc-credit-payment-system' ) );
        }

        // Validar tasa de interés
        $interest_rate = floatval( $post_data['interest_rate'] );
        if ( $interest_rate < 0 || $interest_rate > 100 ) {
            return new WP_Error( 'invalid_interest', __( 'La tasa de interés debe estar entre 0% y 100%.', 'wc-credit-payment-system' ) );
        }

        // Validar número de cuotas
        $max_installments = absint( $post_data['max_installments'] );
        if ( $max_installments < 1 || $max_installments > 120 ) {
            return new WP_Error( 'invalid_installments', __( 'El número de cuotas debe estar entre 1 y 120.', 'wc-credit-payment-system' ) );
        }

        // Validar frecuencia de pago
        $valid_frequencies = array( 'weekly', 'biweekly', 'monthly' );
        if ( ! in_array( $post_data['payment_frequency'], $valid_frequencies ) ) {
            return new WP_Error( 'invalid_frequency', __( 'Frecuencia de pago inválida.', 'wc-credit-payment-system' ) );
        }

        // Validar estado
        $valid_statuses = array( 'active', 'inactive' );
        if ( ! in_array( $post_data['status'], $valid_statuses ) ) {
            return new WP_Error( 'invalid_status', __( 'Estado del plan inválido.', 'wc-credit-payment-system' ) );
        }

        return true;
    }

    /**
     * **NUEVA FUNCIÓN**: Verificar seguridad para guardado de planes
     */
    private function verify_save_plan_security() {
        if ( ! isset( $_POST['wcps_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wcps_nonce'] ), 'save_credit_plan' ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'wc-credit-payment-system' ) );
        }
        
        return true;
    }

    /**
     * **NUEVA FUNCIÓN**: Guardar asignaciones de planes de forma separada
     */
    private function save_plan_assignments( $plan_id, $post_data ) {
        global $wpdb;
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';
        
        // Eliminar asignaciones existentes
        $wpdb->delete( $assignments_table, array( 'plan_id' => $plan_id ) );
        
        // Guardar asignaciones de categorías
        if ( ! empty( $post_data['assigned_categories'] ) && is_array( $post_data['assigned_categories'] ) ) {
            $categories = array_map( 'absint', $post_data['assigned_categories'] );
            foreach ( $categories as $cat_id ) {
                if ( $cat_id > 0 ) {
                    $wpdb->insert( $assignments_table, array( 
                        'plan_id' => $plan_id, 
                        'assignment_type' => 'category', 
                        'assignment_id' => $cat_id 
                    ));
                }
            }
        }
        
        // Guardar asignaciones de productos
        if ( ! empty( $post_data['assigned_products'] ) && is_array( $post_data['assigned_products'] ) ) {
            $products = array_map( 'absint', $post_data['assigned_products'] );
            foreach ( $products as $prod_id ) {
                if ( $prod_id > 0 ) {
                    $wpdb->insert( $assignments_table, array( 
                        'plan_id' => $plan_id, 
                        'assignment_type' => 'product', 
                        'assignment_id' => $prod_id 
                    ));
                }
            }
        }
    }

    /**
     * **MEJORADO**: Eliminación de planes con mejor manejo de errores
     */
    public function delete_credit_plan() {
        $plan_id = isset( $_GET['plan_id'] ) ? absint( $_GET['plan_id'] ) : 0;
        
        // Verificaciones de seguridad
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wcps_delete_plan_' . $plan_id ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'wc-credit-payment-system' ) );
        }
        
        if ( $plan_id === 0 ) {
            wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&error=invalid_id' ) );
            exit;
        }

        // **NUEVA VALIDACIÓN**: Verificar si el plan tiene cuentas activas
        if ( $this->plan_has_active_accounts( $plan_id ) ) {
            wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&error=has_active_accounts' ) );
            exit;
        }
        
        global $wpdb;
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';
        
        // Eliminar asignaciones primero
        $wpdb->delete( $assignments_table, array( 'plan_id' => $plan_id ) );
        
        // Eliminar el plan
        $result = $wpdb->delete( $plans_table, array( 'id' => $plan_id ) );
        
        if ( $result === false ) {
            wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&error=database' ) );
            exit;
        }
        
        wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&deleted=1' ) );
        exit;
    }

    /**
     * **NUEVA FUNCIÓN**: Verificar si un plan tiene cuentas activas
     */
    private function plan_has_active_accounts( $plan_id ) {
        global $wpdb;
        $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$accounts_table} WHERE plan_id = %d AND status IN ('active', 'completed')",
            $plan_id
        ));
        
        return $count > 0;
    }

    /**
     * **MEJORADO**: Guardado de configuraciones con mejor sanitización
     */
    public function save_settings_and_templates() {
        if ( ! isset( $_POST['wcps_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wcps_settings_nonce'] ), 'save_wcps_settings_templates' ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'wc-credit-payment-system' ) );
        }

        // **CORRECCIÓN**: Guardar la ubicación de los planes
        if ( isset( $_POST['wcps_plans_display_location'] ) ) {
            $valid_locations = array(
                'woocommerce_single_product_summary',
                'woocommerce_product_meta_start', 
                'woocommerce_before_add_to_cart_button',
                'woocommerce_after_add_to_cart_form'
            );
            
            $location = sanitize_text_field( $_POST['wcps_plans_display_location'] );
            if ( in_array( $location, $valid_locations ) ) {
                update_option( 'wcps_plans_display_location', $location );
            }
        }

        // Configuraciones de API con validación mejorada
        if ( isset( $_POST['wcps_whatsapp_api_secret'] ) ) {
            $api_secret = sanitize_text_field( $_POST['wcps_whatsapp_api_secret'] );
            update_option( 'wcps_whatsapp_api_secret', $api_secret );
        }
        
        if ( isset( $_POST['wcps_whatsapp_account_id'] ) ) {
            $account_id = sanitize_text_field( $_POST['wcps_whatsapp_account_id'] );
            update_option( 'wcps_whatsapp_account_id', $account_id );
        }
        
        if ( isset( $_POST['wcps_admin_phone_number'] ) ) {
            $phone = sanitize_text_field( $_POST['wcps_admin_phone_number'] );
            // Validar formato de teléfono internacional
            if ( empty( $phone ) || preg_match( '/^\+?[1-9]\d{1,14}$/', $phone ) ) {
                update_option( 'wcps_admin_phone_number', $phone );
            }
        }

        // **MEJORADO**: Guardado de plantillas con mejor sanitización
        if ( isset( $_POST['templates'] ) && is_array( $_POST['templates'] ) ) {
            $this->save_notification_templates( $_POST['templates'] );
        }

        wp_redirect( add_query_arg( 'success', '1', admin_url( 'admin.php?page=wc-credit-settings' ) ) );
        exit;
    }

    /**
     * **NUEVA FUNCIÓN**: Guardar plantillas de notificación con sanitización mejorada
     */
    private function save_notification_templates( $templates ) {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'wc_credit_templates';
        
        foreach ( $templates as $template_id => $template_data ) {
            $template_id = absint( $template_id );
            if ( $template_id === 0 ) {
                continue;
            }

            // **MEJORA**: Sanitización más específica para plantillas
            $allowed_html = array(
                'p' => array(),
                'br' => array(),
                'strong' => array(),
                'em' => array(),
                'b' => array(),
                'i' => array(),
                'a' => array( 'href' => array() ),
                'span' => array( 'style' => array() ),
                'div' => array( 'style' => array() )
            );

            $subject = isset( $template_data['subject'] ) ? sanitize_text_field( $template_data['subject'] ) : '';
            $content = isset( $template_data['content'] ) ? wp_kses( $template_data['content'], $allowed_html ) : '';
            $is_active = isset( $template_data['is_active'] ) ? 1 : 0;

            $wpdb->update(
                $templates_table,
                array(
                    'subject' => $subject,
                    'content' => $content,
                    'is_active' => $is_active
                ),
                array( 'id' => $template_id ),
                array( '%s', '%s', '%d' ),
                array( '%d' )
            );
        }
    }

    /**
     * **NUEVA FUNCIÓN**: Limpiar cache de planes cuando se modifiquen
     */
    public function clear_plans_cache() {
        if ( class_exists( 'WC_Credit_Payment_Plans' ) ) {
            WC_Credit_Payment_Plans::clear_product_plans_cache();
        }
    }

    /**
     * **NUEVA FUNCIÓN**: Añadir columna de planes de crédito en listado de productos
     */
    public function add_product_credit_plans_column( $columns ) {
        $new_columns = array();
        
        foreach ( $columns as $key => $value ) {
            $new_columns[$key] = $value;
            
            // Insertar después de la columna de precio
            if ( $key === 'price' ) {
                $new_columns['credit_plans'] = __( 'Planes de Crédito', 'wc-credit-payment-system' );
            }
        }
        
        return $new_columns;
    }

    /**
     * **NUEVA FUNCIÓN**: Mostrar información de planes en la columna de productos
     */
    public function display_product_credit_plans_column( $column, $post_id ) {
        if ( $column === 'credit_plans' ) {
            if ( class_exists( 'WC_Credit_Payment_Plans' ) ) {
                $has_plans = WC_Credit_Payment_Plans::product_has_credit_plans( $post_id );
                
                if ( $has_plans ) {
                    echo '<span style="color: #46b450;">✓ ' . __( 'Disponible', 'wc-credit-payment-system' ) . '</span>';
                } else {
                    echo '<span style="color: #999;">— ' . __( 'No disponible', 'wc-credit-payment-system' ) . '</span>';
                }
            }
        }
    }
}

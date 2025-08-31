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
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Planes de Crédito', 'wc-credit-payment-system' ),
            __( 'Planes de Crédito', 'wc-credit-payment-system' ),
            'manage_woocommerce',
            'wc-credit-plans',
            array( $this, 'credit_plans_page_html' )
        );
        add_submenu_page(
            'woocommerce',
            __( 'Ajustes de Crédito', 'wc-credit-payment-system' ),
            __( 'Ajustes de Crédito', 'wc-credit-payment-system' ),
            'manage_woocommerce',
            'wc-credit-settings',
            array( $this, 'settings_templates_page_html' )
        );
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
    
    public function save_credit_plan() {
        if ( ! isset( $_POST['wcps_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wcps_nonce'] ), 'save_credit_plan' ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'wc-credit-payment-system' ) );
        }
        
        global $wpdb;
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';
        
        $plan_id = isset( $_POST['plan_id'] ) ? absint( $_POST['plan_id'] ) : 0;
        
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
        
        if ( $plan_id > 0 ) {
            $wpdb->update( $plans_table, $data, array( 'id' => $plan_id ) );
        } else {
            $wpdb->insert( $plans_table, $data );
            $plan_id = $wpdb->insert_id;
        }

        if ( $plan_id > 0 ) {
            $wpdb->delete( $assignments_table, array( 'plan_id' => $plan_id ) );
            if ( ! empty( $_POST['assigned_categories'] ) ) {
                $categories = array_map( 'absint', (array) $_POST['assigned_categories'] );
                foreach ( $categories as $cat_id ) {
                    $wpdb->insert( $assignments_table, array( 'plan_id' => $plan_id, 'assignment_type' => 'category', 'assignment_id' => $cat_id ) );
                }
            }
            if ( ! empty( $_POST['assigned_products'] ) ) {
                $products = array_map( 'absint', (array) $_POST['assigned_products'] );
                foreach ( $products as $prod_id ) {
                    $wpdb->insert( $assignments_table, array( 'plan_id' => $plan_id, 'assignment_type' => 'product', 'assignment_id' => $prod_id ) );
                }
            }
        }

        wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&success=1' ) );
        exit;
    }

    public function delete_credit_plan() {
        $plan_id = isset( $_GET['plan_id'] ) ? absint( $_GET['plan_id'] ) : 0;
        
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wcps_delete_plan_' . $plan_id ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'wc-credit-payment-system' ) );
        }
        if ( $plan_id > 0 ) {
            global $wpdb;
            $plans_table = $wpdb->prefix . 'wc_credit_plans';
            $wpdb->delete( $plans_table, array( 'id' => $plan_id ) );
        }
        
        wp_redirect( admin_url( 'admin.php?page=wc-credit-plans&deleted=1' ) );
        exit;
    }

    public function save_settings_and_templates() {
        if ( ! isset( $_POST['wcps_settings_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wcps_settings_nonce'] ), 'save_wcps_settings_templates' ) ) {
            wp_die( __( 'Error de seguridad.', 'wc-credit-payment-system' ) );
        }
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', 'wc-credit-payment-system' ) );
        }

        // **CORRECCIÓN AÑADIDA**: Guardar la ubicación de los planes
        if ( isset( $_POST['wcps_plans_display_location'] ) ) {
            update_option( 'wcps_plans_display_location', sanitize_text_field( $_POST['wcps_plans_display_location'] ) );
        }

        if ( isset( $_POST['wcps_whatsapp_api_secret'] ) ) {
            update_option( 'wcps_whatsapp_api_secret', sanitize_text_field( $_POST['wcps_whatsapp_api_secret'] ) );
        }
        if ( isset( $_POST['wcps_whatsapp_account_id'] ) ) {
            update_option( 'wcps_whatsapp_account_id', sanitize_text_field( $_POST['wcps_whatsapp_account_id'] ) );
        }
        if ( isset( $_POST['wcps_admin_phone_number'] ) ) {
            update_option( 'wcps_admin_phone_number', sanitize_text_field( $_POST['wcps_admin_phone_number'] ) );
        }

        if ( isset( $_POST['templates'] ) && is_array( $_POST['templates'] ) ) {
            global $wpdb;
            $templates_table = $wpdb->prefix . 'wc_credit_templates';
            foreach ( $_POST['templates'] as $template_id => $template_data ) {
                $wpdb->update(
                    $templates_table,
                    [
                        'subject' => sanitize_text_field( $template_data['subject'] ),
                        'content' => wp_kses_post( $template_data['content'] ),
                        'is_active' => isset( $template_data['is_active'] ) ? 1 : 0
                    ],
                    ['id' => absint( $template_id )]
                );
            }
        }

        wp_redirect( add_query_arg('success', '1', admin_url( 'admin.php?page=wc-credit-settings' ) ) );
        exit;
    }
}
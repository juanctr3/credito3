<?php
/**
 * WooCommerce Credit Payment System Uninstall
 *
 * Script que se ejecuta cuando el plugin es eliminado.
 * Se encarga de limpiar la base de datos de tablas y opciones.
 *
 * @package WooCommerceCreditPaymentSystem
 */

// Si no se está desinstalando el plugin, no hacer nada.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Definir los nombres de las tablas personalizadas.
$table_prefix = $wpdb->prefix;
$tables = [
    $table_prefix . 'wc_credit_installments',
    $table_prefix . 'wc_credit_comments',
    $table_prefix . 'wc_credit_accounts',
    $table_prefix . 'wc_credit_plan_assignments',
    $table_prefix . 'wc_credit_plans',
    $table_prefix . 'wc_credit_templates'
];

// 2. Eliminar cada tabla.
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 3. Definir y eliminar las opciones guardadas en la tabla wp_options.
$options = [
    'wcps_whatsapp_api_secret',
    'wcps_whatsapp_account_id',
    'wcps_admin_phone_number'
];

foreach ( $options as $option ) {
    delete_option( $option );
}

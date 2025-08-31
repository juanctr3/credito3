<?php
/**
 * Plantilla para el formulario de creación y edición de Planes de Crédito.
 *
 * @package WooCommerceCreditPaymentSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// -----------------------------------------------------------------------------
// 1. LÓGICA DE DATOS
// -----------------------------------------------------------------------------

// Obtener el ID del plan desde la URL para determinar si es una edición o un nuevo plan.
$plan_id = isset( $_GET['plan_id'] ) ? absint( $_GET['plan_id'] ) : 0;
$plan = null;
$is_new = true;

// Si es una edición (plan_id > 0), obtener los datos del plan.
if ( $plan_id > 0 ) {
    $plan = WC_Credit_Payment_Plans::get_plan( $plan_id );
    $is_new = ! $plan;
}

// Obtener las asignaciones actuales de categorías y productos para este plan.
$assigned_categories = [];
$assigned_products = [];

if ( ! $is_new ) {
    global $wpdb;
    $assignments_table = $wpdb->prefix . 'wc_credit_plan_assignments';
    $results = $wpdb->get_results( $wpdb->prepare( "SELECT assignment_type, assignment_id FROM $assignments_table WHERE plan_id = %d", $plan_id ) );
    
    foreach ( $results as $result ) {
        if ( $result->assignment_type === 'category' ) {
            $assigned_categories[] = $result->assignment_id;
        } else {
            $assigned_products[] = $result->assignment_id;
        }
    }
}

// -----------------------------------------------------------------------------
// 2. RENDERIZADO DEL FORMULARIO HTML
// -----------------------------------------------------------------------------
?>
<div class="wrap">
    <h1><?php echo $is_new ? esc_html__( 'Crear Nuevo Plan de Crédito', 'wc-credit-payment-system' ) : esc_html__( 'Editar Plan de Crédito', 'wc-credit-payment-system' ); ?></h1>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        
        <!-- Campos ocultos para seguridad y procesamiento -->
        <input type="hidden" name="action" value="save_wc_credit_plan">
        <input type="hidden" name="plan_id" value="<?php echo esc_attr( $plan_id ); ?>">
        <?php wp_nonce_field( 'save_credit_plan', 'wcps_nonce' ); ?>

        <table class="form-table">
            <tbody>
                <!-- Nombre del Plan -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="name"><?php esc_html_e( 'Nombre del Plan', 'wc-credit-payment-system' ); ?> <span class="description">(obligatorio)</span></label>
                    </th>
                    <td class="forminp forminp-text">
                        <input name="name" id="name" type="text" style="width: 350px;" value="<?php echo esc_attr( $plan->name ?? '' ); ?>" required>
                    </td>
                </tr>

                <!-- Descripción -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="description"><?php esc_html_e( 'Descripción', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-textarea">
                        <textarea name="description" id="description" style="width: 350px; height: 100px;"><?php echo esc_textarea( $plan->description ?? '' ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Descripción interna o para mostrar al cliente (opcional).', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>
                
                <!-- Cuota Inicial (%) -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="down_payment_percentage"><?php esc_html_e( 'Cuota Inicial (%)', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-number">
                        <input name="down_payment_percentage" id="down_payment_percentage" type="number" step="0.01" min="0" max="100" style="width: 100px;" value="<?php echo esc_attr( $plan->down_payment_percentage ?? '0' ); ?>">
                        <p class="description"><?php esc_html_e( 'Porcentaje del precio del producto a pagar al momento de la compra. Ej: 20.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>

                <!-- Tasa de Interés (%) -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="interest_rate"><?php esc_html_e( 'Tasa de Interés (%)', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-number">
                        <input name="interest_rate" id="interest_rate" type="number" step="0.01" min="0" style="width: 100px;" value="<?php echo esc_attr( $plan->interest_rate ?? '0' ); ?>">
                        <p class="description"><?php esc_html_e( 'Interés aplicado sobre el monto financiado. Ej: 5 para 5%.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>
                
                <!-- Cantidad de Cuotas Máximas -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="max_installments"><?php esc_html_e( 'Número de Cuotas', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-number">
                        <input name="max_installments" id="max_installments" type="number" step="1" min="1" style="width: 100px;" value="<?php echo esc_attr( $plan->max_installments ?? '12' ); ?>">
                        <p class="description"><?php esc_html_e( 'El número total de pagos que el cliente realizará.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>

                <!-- Período de Pago -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="payment_frequency"><?php esc_html_e( 'Frecuencia de Pago', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-select">
                        <select name="payment_frequency" id="payment_frequency" style="width: 200px;">
                            <option value="weekly" <?php selected( $plan->payment_frequency ?? 'monthly', 'weekly' ); ?>><?php esc_html_e( 'Semanal', 'wc-credit-payment-system' ); ?></option>
                            <option value="biweekly" <?php selected( $plan->payment_frequency ?? 'monthly', 'biweekly' ); ?>><?php esc_html_e( 'Quincenal', 'wc-credit-payment-system' ); ?></option>
                            <option value="monthly" <?php selected( $plan->payment_frequency ?? 'monthly', 'monthly' ); ?>><?php esc_html_e( 'Mensual', 'wc-credit-payment-system' ); ?></option>
                        </select>
                    </td>
                </tr>

                <!-- Días para Notificar antes del Vencimiento -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="notification_days_before"><?php esc_html_e( 'Días de Notificación Previa', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-number">
                        <input name="notification_days_before" id="notification_days_before" type="number" step="1" min="0" style="width: 100px;" value="<?php echo esc_attr( $plan->notification_days_before ?? '3' ); ?>">
                        <p class="description"><?php esc_html_e( 'Enviar recordatorio X días antes del vencimiento de una cuota.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>

                <!-- Estado -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="status"><?php esc_html_e( 'Estado', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-select">
                        <select name="status" id="status" style="width: 200px;">
                            <option value="active" <?php selected( $plan->status ?? 'active', 'active' ); ?>><?php esc_html_e( 'Activo', 'wc-credit-payment-system' ); ?></option>
                            <option value="inactive" <?php selected( $plan->status ?? 'active', 'inactive' ); ?>><?php esc_html_e( 'Inactivo', 'wc-credit-payment-system' ); ?></option>
                        </select>
                         <p class="description"><?php esc_html_e( 'Solo los planes activos se mostrarán a los clientes.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>

                <!-- Asignar a Categorías -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="assigned_categories"><?php esc_html_e( 'Asignar a Categorías', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-select">
                        <select name="assigned_categories[]" id="assigned_categories" multiple="multiple" class="wc-enhanced-select" style="width: 350px;" data-placeholder="<?php esc_attr_e( 'Seleccionar categorías...', 'wc-credit-payment-system' ); ?>">
                            <?php
                            $categories = get_terms( 'product_cat', array( 'hide_empty' => false ) );
                            foreach ( $categories as $category ) {
                                echo '<option value="' . esc_attr( $category->term_id ) . '"' . selected( in_array( $category->term_id, $assigned_categories ), true, false ) . '>' . esc_html( $category->name ) . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Si no seleccionas categorías ni productos, el plan será aplicable a TODA la tienda.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>

                <!-- Asignar a Productos Específicos -->
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="assigned_products"><?php esc_html_e( 'Asignar a Productos Específicos', 'wc-credit-payment-system' ); ?></label>
                    </th>
                    <td class="forminp forminp-select">
                         <select id="assigned_products" name="assigned_products[]" class="wc-product-search" multiple="multiple" style="width: 350px;" data-placeholder="<?php esc_attr_e( 'Buscar productos…', 'wc-credit-payment-system' ); ?>" data-action="woocommerce_json_search_products_and_variations">
                            <?php
                            // Cargar los productos ya seleccionados para que el campo de búsqueda los muestre.
                            if ( ! empty( $assigned_products ) ) {
                                foreach ( $assigned_products as $product_id ) {
                                    $product = wc_get_product( $product_id );
                                    if ( is_object( $product ) ) {
                                        echo '<option value="' . esc_attr( $product_id ) . '"' . selected( true, true, false ) . '>' . wp_kses_post( $product->get_formatted_name() ) . '</option>';
                                    }
                                }
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'La asignación a productos tiene prioridad sobre las categorías.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>

            </tbody>
        </table>

        <p class="submit">
            <button type="submit" class="button-primary woocommerce-save-button"><?php esc_html_e( 'Guardar Plan', 'wc-credit-payment-system' ); ?></button>
            <a href="<?php echo esc_url( admin_url('admin.php?page=wc-credit-plans') ); ?>" class="button-secondary"><?php esc_html_e( 'Cancelar', 'wc-credit-payment-system' ); ?></a>
        </p>

    </form>
</div>


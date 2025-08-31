<?php
/**
 * Plantilla para la página de Ajustes y Plantillas de Notificaciones.
 *
 * @package WooCommerceCreditPaymentSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

global $wpdb;
$templates_table = $wpdb->prefix . 'wc_credit_templates';
$all_templates = $wpdb->get_results( "SELECT * FROM {$templates_table} ORDER BY template_type, template_name" );

$grouped_templates = [];
if ( is_array( $all_templates ) ) {
    foreach ($all_templates as $template) {
        $grouped_templates[$template->template_type][] = $template;
    }
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Ajustes y Plantillas de Notificaciones', 'wc-credit-payment-system' ); ?></h1>

    <?php if ( isset( $_GET['success'] ) ): ?>
    <div id="message" class="updated notice is-dismissible">
        <p><?php esc_html_e( 'Ajustes guardados correctamente.', 'wc-credit-payment-system' ); ?></p>
    </div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper">
        <a href="?page=wc-credit-settings&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Ajustes Generales', 'wc-credit-payment-system' ); ?></a>
        <a href="?page=wc-credit-settings&tab=email_client" class="nav-tab <?php echo $active_tab == 'email_client' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Emails (Cliente)', 'wc-credit-payment-system' ); ?></a>
        <a href="?page=wc-credit-settings&tab=whatsapp_client" class="nav-tab <?php echo $active_tab == 'whatsapp_client' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'WhatsApp (Cliente)', 'wc-credit-payment-system' ); ?></a>
        <a href="?page=wc-credit-settings&tab=email_admin" class="nav-tab <?php echo $active_tab == 'email_admin' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Emails (Admin)', 'wc-credit-payment-system' ); ?></a>
        <a href="?page=wc-credit-settings&tab=whatsapp_admin" class="nav-tab <?php echo $active_tab == 'whatsapp_admin' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'WhatsApp (Admin)', 'wc-credit-payment-system' ); ?></a>
    </nav>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="save_wcps_settings_templates">
        <?php wp_nonce_field( 'save_wcps_settings_templates', 'wcps_settings_nonce' ); ?>

        <?php if ($active_tab === 'settings') : ?>
            <h3><?php esc_html_e( 'Ajustes de Visualización', 'wc-credit-payment-system' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="wcps_plans_display_location"><?php esc_html_e( 'Ubicación de los Planes', 'wc-credit-payment-system' ); ?></label></th>
                    <td>
                        <select id="wcps_plans_display_location" name="wcps_plans_display_location">
                            <?php
                                $locations = [
                                    'woocommerce_single_product_summary' => __( 'Sobre el precio y la descripción corta', 'wc-credit-payment-system' ),
                                    'woocommerce_product_meta_start' => __( 'Debajo de la descripción corta', 'wc-credit-payment-system' ),
                                    'woocommerce_after_add_to_cart_form' => __( 'Debajo del botón de añadir al carrito', 'wc-credit-payment-system' )
                                ];
                                $current_location = get_option('wcps_plans_display_location', 'woocommerce_single_product_summary');
                                foreach ( $locations as $hook => $label ) {
                                    echo '<option value="' . esc_attr( $hook ) . '" ' . selected( $current_location, $hook, false ) . '>' . esc_html( $label ) . '</option>';
                                }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Selecciona dónde quieres mostrar el bloque de planes de crédito en la página del producto.', 'wc-credit-payment-system' ); ?></p>
                    </td>
                </tr>
            </table>

            <hr/>

            <h3><?php esc_html_e( 'Configuración de API (SMSEnlinea.com)', 'wc-credit-payment-system' ); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="wcps_whatsapp_api_secret"><?php esc_html_e( 'API Secret', 'wc-credit-payment-system' ); ?></label></th>
                    <td><input type="text" id="wcps_whatsapp_api_secret" name="wcps_whatsapp_api_secret" value="<?php echo esc_attr( get_option('wcps_whatsapp_api_secret') ); ?>" class="regular-text"></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="wcps_whatsapp_account_id"><?php esc_html_e( 'Account ID de WhatsApp', 'wc-credit-payment-system' ); ?></label></th>
                    <td><input type="text" id="wcps_whatsapp_account_id" name="wcps_whatsapp_account_id" value="<?php echo esc_attr( get_option('wcps_whatsapp_account_id') ); ?>" class="regular-text"></td>
                </tr>
                 <tr valign="top">
                    <th scope="row"><label for="wcps_admin_phone_number"><?php esc_html_e( 'Teléfono del Admin', 'wc-credit-payment-system' ); ?></label></th>
                    <td><input type="text" id="wcps_admin_phone_number" name="wcps_admin_phone_number" value="<?php echo esc_attr( get_option('wcps_admin_phone_number') ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Número en formato internacional (Ej: 573001234567) para recibir notificaciones.', 'wc-credit-payment-system' ); ?></p></td>
                </tr>
            </table>
        <?php else : ?>
            <?php if (isset($grouped_templates[$active_tab])) : ?>
                <?php foreach ($grouped_templates[$active_tab] as $template) : ?>
                    <div class="postbox">
                        <h2 class="hndle"><span><?php echo esc_html( $template->template_name ); ?></span></h2>
                        <div class="inside">
                            <table class="form-table">
                                <input type="hidden" name="templates[<?php echo $template->id; ?>][id]" value="<?php echo $template->id; ?>">
                                <tr valign="top">
                                    <th scope="row"><label><?php esc_html_e( 'Activar esta notificación', 'wc-credit-payment-system' ); ?></label></th>
                                    <td><input type="checkbox" name="templates[<?php echo $template->id; ?>][is_active]" value="1" <?php checked($template->is_active, 1); ?>></td>
                                </tr>
                                <?php if ( strpos($template->template_type, 'email') !== false ) : ?>
                                <tr valign="top">
                                    <th scope="row"><label for="template_subject_<?php echo $template->id; ?>"><?php esc_html_e( 'Asunto', 'wc-credit-payment-system' ); ?></label></th>
                                    <td><input type="text" id="template_subject_<?php echo $template->id; ?>" name="templates[<?php echo $template->id; ?>][subject]" value="<?php echo esc_attr( $template->subject ); ?>" class="regular-text"></td>
                                </tr>
                                <?php else: ?>
                                    <input type="hidden" name="templates[<?php echo $template->id; ?>][subject]" value="">
                                <?php endif; ?>
                                <tr valign="top">
                                    <th scope="row"><label for="template_content_<?php echo $template->id; ?>"><?php esc_html_e( 'Contenido', 'wc-credit-payment-system' ); ?></label></th>
                                    <td><textarea id="template_content_<?php echo $template->id; ?>" name="templates[<?php echo $template->id; ?>][content]" rows="8" class="large-text"><?php echo esc_textarea( $template->content ); ?></textarea></td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php esc_html_e( 'Variables Disponibles', 'wc-credit-payment-system' ); ?></th>
                                    <td><p class="description"><code><?php echo esc_html($template->variables); ?></code></p></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p><?php esc_html_e( 'No hay plantillas para esta sección.', 'wc-credit-payment-system' ); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" class="button-primary"><?php esc_html_e( 'Guardar Cambios', 'wc-credit-payment-system' ); ?></button>
        </p>
    </form>
</div>


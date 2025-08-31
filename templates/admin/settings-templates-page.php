<?php
/**
 * Plantilla para la página de Ajustes y Plantillas de Notificaciones.
 * Versión corregida con mejor UX, validaciones y estructura mejorada.
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
    foreach ( $all_templates as $template ) {
        $grouped_templates[$template->template_type][] = $template;
    }
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'settings';

// **NUEVA FUNCIÓN**: Obtener estadísticas de uso
function get_wcps_usage_stats() {
    global $wpdb;
    return [
        'total_plans' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_credit_plans WHERE status = 'active'" ),
        'active_accounts' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_credit_accounts WHERE status = 'active'" ),
        'pending_installments' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wc_credit_installments WHERE status = 'pending'" ),
    ];
}

$stats = get_wcps_usage_stats();
?>

<div class="wrap wcps-admin-page">
    <h1><?php esc_html_e( 'Ajustes y Plantillas de Notificaciones', 'wc-credit-payment-system' ); ?></h1>

    <!-- **NUEVA SECCIÓN**: Estadísticas rápidas -->
    <div class="wcps-stats-cards" style="display: flex; gap: 20px; margin: 20px 0;">
        <div class="wcps-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; flex: 1; text-align: center;">
            <h3 style="margin: 0 0 10px 0; color: #23282d;"><?php echo esc_html( $stats['total_plans'] ); ?></h3>
            <p style="margin: 0; color: #666;"><?php esc_html_e( 'Planes Activos', 'wc-credit-payment-system' ); ?></p>
        </div>
        <div class="wcps-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; flex: 1; text-align: center;">
            <h3 style="margin: 0 0 10px 0; color: #23282d;"><?php echo esc_html( $stats['active_accounts'] ); ?></h3>
            <p style="margin: 0; color: #666;"><?php esc_html_e( 'Cuentas Activas', 'wc-credit-payment-system' ); ?></p>
        </div>
        <div class="wcps-stat-card" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; flex: 1; text-align: center;">
            <h3 style="margin: 0 0 10px 0; color: #23282d;"><?php echo esc_html( $stats['pending_installments'] ); ?></h3>
            <p style="margin: 0; color: #666;"><?php esc_html_e( 'Cuotas Pendientes', 'wc-credit-payment-system' ); ?></p>
        </div>
    </div>

    <!-- Mensajes de estado mejorados -->
    <?php if ( isset( $_GET['success'] ) ): ?>
    <div id="message" class="updated notice is-dismissible">
        <p><strong><?php esc_html_e( '¡Perfecto!', 'wc-credit-payment-system' ); ?></strong> <?php esc_html_e( 'Los ajustes se han guardado correctamente.', 'wc-credit-payment-system' ); ?></p>
    </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['error'] ) ): ?>
    <div id="message" class="error notice is-dismissible">
        <p><strong><?php esc_html_e( 'Error:', 'wc-credit-payment-system' ); ?></strong> 
        <?php 
        switch ( $_GET['error'] ) {
            case 'validation':
                $message = isset( $_GET['message'] ) ? urldecode( $_GET['message'] ) : __( 'Datos de entrada inválidos.', 'wc-credit-payment-system' );
                echo esc_html( $message );
                break;
            case 'database':
                esc_html_e( 'Error al guardar en la base de datos. Inténtalo de nuevo.', 'wc-credit-payment-system' );
                break;
            default:
                esc_html_e( 'Ha ocurrido un error inesperado.', 'wc-credit-payment-system' );
        }
        ?>
        </p>
    </div>
    <?php endif; ?>

    <!-- **NAVEGACIÓN MEJORADA**: Tabs con iconos y contadores -->
    <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
        <a href="?page=wc-credit-settings&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings" style="margin-right: 5px;"></span>
            <?php esc_html_e( 'Configuración General', 'wc-credit-payment-system' ); ?>
        </a>
        
        <a href="?page=wc-credit-settings&tab=email_client" class="nav-tab <?php echo $active_tab == 'email_client' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-email-alt" style="margin-right: 5px;"></span>
            <?php esc_html_e( 'Emails Cliente', 'wc-credit-payment-system' ); ?>
            <?php if ( isset( $grouped_templates['email_client'] ) ): ?>
                <span class="wcps-tab-counter" style="background: #0073aa; color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; margin-left: 5px;">
                    <?php echo count( $grouped_templates['email_client'] ); ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?page=wc-credit-settings&tab=whatsapp_client" class="nav-tab <?php echo $active_tab == 'whatsapp_client' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-smartphone" style="margin-right: 5px;"></span>
            <?php esc_html_e( 'WhatsApp Cliente', 'wc-credit-payment-system' ); ?>
            <?php if ( isset( $grouped_templates['whatsapp_client'] ) ): ?>
                <span class="wcps-tab-counter" style="background: #25d366; color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; margin-left: 5px;">
                    <?php echo count( $grouped_templates['whatsapp_client'] ); ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?page=wc-credit-settings&tab=email_admin" class="nav-tab <?php echo $active_tab == 'email_admin' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-email" style="margin-right: 5px;"></span>
            <?php esc_html_e( 'Emails Admin', 'wc-credit-payment-system' ); ?>
            <?php if ( isset( $grouped_templates['email_admin'] ) ): ?>
                <span class="wcps-tab-counter" style="background: #d63384; color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; margin-left: 5px;">
                    <?php echo count( $grouped_templates['email_admin'] ); ?>
                </span>
            <?php endif; ?>
        </a>
        
        <a href="?page=wc-credit-settings&tab=whatsapp_admin" class="nav-tab <?php echo $active_tab == 'whatsapp_admin' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-users" style="margin-right: 5px;"></span>
            <?php esc_html_e( 'WhatsApp Admin', 'wc-credit-payment-system' ); ?>
            <?php if ( isset( $grouped_templates['whatsapp_admin'] ) ): ?>
                <span class="wcps-tab-counter" style="background: #fd7e14; color: white; border-radius: 10px; padding: 2px 6px; font-size: 11px; margin-left: 5px;">
                    <?php echo count( $grouped_templates['whatsapp_admin'] ); ?>
                </span>
            <?php endif; ?>
        </a>
    </nav>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wcps-settings-form">
        <input type="hidden" name="action" value="save_wcps_settings_templates">
        <?php wp_nonce_field( 'save_wcps_settings_templates', 'wcps_settings_nonce' ); ?>

        <?php if ( $active_tab === 'settings' ) : ?>
            <div class="wcps-settings-section">
                
                <!-- **SECCIÓN MEJORADA**: Configuración de visualización -->
                <div class="postbox">
                    <h2 class="hndle"><span><span class="dashicons dashicons-visibility" style="margin-right: 8px;"></span><?php esc_html_e( 'Configuración de Visualización', 'wc-credit-payment-system' ); ?></span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <label for="wcps_plans_display_location"><?php esc_html_e( 'Ubicación de los Planes', 'wc-credit-payment-system' ); ?></label>
                                </th>
                                <td>
                                    <select id="wcps_plans_display_location" name="wcps_plans_display_location" class="regular-text">
                                        <?php
                                        $locations = [
                                            'woocommerce_single_product_summary' => __( 'Arriba del precio (Recomendado)', 'wc-credit-payment-system' ),
                                            'woocommerce_product_meta_start' => __( 'Debajo de la descripción corta', 'wc-credit-payment-system' ),
                                            'woocommerce_before_add_to_cart_button' => __( 'Antes del botón "Añadir al carrito"', 'wc-credit-payment-system' ),
                                            'woocommerce_after_add_to_cart_form' => __( 'Debajo del formulario de compra', 'wc-credit-payment-system' )
                                        ];
                                        $current_location = get_option( 'wcps_plans_display_location', 'woocommerce_before_add_to_cart_button' );
                                        foreach ( $locations as $hook => $label ) {
                                            printf( 
                                                '<option value="%s" %s>%s</option>',
                                                esc_attr( $hook ),
                                                selected( $current_location, $hook, false ),
                                                esc_html( $label )
                                            );
                                        }
                                        ?>
                                    </select>
                                    <p class="description">
                                        <?php esc_html_e( 'Selecciona dónde quieres mostrar las opciones de planes de crédito en la página del producto.', 'wc-credit-payment-system' ); ?>
                                        <br><em><?php esc_html_e( 'Si cambias esto, es posible que debas limpiar el cache del sitio.', 'wc-credit-payment-system' ); ?></em>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- **SECCIÓN MEJORADA**: Configuración de API -->
                <div class="postbox">
                    <h2 class="hndle"><span><span class="dashicons dashicons-admin-plugins" style="margin-right: 8px;"></span><?php esc_html_e( 'Configuración de API WhatsApp', 'wc-credit-payment-system' ); ?></span></h2>
                    <div class="inside">
                        <p class="description" style="margin-bottom: 15px;">
                            <?php printf( 
                                esc_html__( 'Configura tu integración con %s para enviar notificaciones automáticas por WhatsApp.', 'wc-credit-payment-system' ),
                                '<strong>SMSEnlinea.com</strong>'
                            ); ?>
                        </p>
                        
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <label for="wcps_whatsapp_api_secret">
                                        <?php esc_html_e( 'API Secret', 'wc-credit-payment-system' ); ?>
                                        <span class="wcps-required" style="color: #d63384;">*</span>
                                    </label>
                                </th>
                                <td>
                                    <input type="password" id="wcps_whatsapp_api_secret" name="wcps_whatsapp_api_secret" 
                                           value="<?php echo esc_attr( get_option( 'wcps_whatsapp_api_secret' ) ); ?>" 
                                           class="regular-text" placeholder="<?php esc_attr_e( 'Tu clave secreta de API', 'wc-credit-payment-system' ); ?>">
                                    <button type="button" class="button button-secondary" onclick="togglePasswordVisibility('wcps_whatsapp_api_secret')" style="margin-left: 5px;">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <p class="description"><?php esc_html_e( 'Obténla desde tu panel de SMSEnlinea.com', 'wc-credit-payment-system' ); ?></p>
                                </td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">
                                    <label for="wcps_whatsapp_account_id">
                                        <?php esc_html_e( 'Account ID de WhatsApp', 'wc-credit-payment-system' ); ?>
                                        <span class="wcps-required" style="color: #d63384;">*</span>
                                    </label>
                                </th>
                                <td>
                                    <input type="text" id="wcps_whatsapp_account_id" name="wcps_whatsapp_account_id" 
                                           value="<?php echo esc_attr( get_option( 'wcps_whatsapp_account_id' ) ); ?>" 
                                           class="regular-text" placeholder="<?php esc_attr_e( 'Tu ID de cuenta WhatsApp', 'wc-credit-payment-system' ); ?>">
                                    <p class="description"><?php esc_html_e( 'El identificador de tu cuenta de WhatsApp Business', 'wc-credit-payment-system' ); ?></p>
                                </td>
                            </tr>
                            
                            <tr valign="top">
                                <th scope="row">
                                    <label for="wcps_admin_phone_number"><?php esc_html_e( 'Teléfono del Administrador', 'wc-credit-payment-system' ); ?></label>
                                </th>
                                <td>
                                    <input type="tel" id="wcps_admin_phone_number" name="wcps_admin_phone_number" 
                                           value="<?php echo esc_attr( get_option( 'wcps_admin_phone_number' ) ); ?>" 
                                           class="regular-text" placeholder="+573001234567"
                                           pattern="^\+?[1-9]\d{1,14}$">
                                    <p class="description">
                                        <strong><?php esc_html_e( 'Formato internacional requerido:', 'wc-credit-payment-system' ); ?></strong>
                                        <?php esc_html_e( '+57 3001234567 (incluye código de país sin espacios ni guiones)', 'wc-credit-payment-system' ); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <!-- **NUEVA SECCIÓN**: Test de conexión -->
                        <div class="wcps-api-test" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #0073aa; border-radius: 4px;">
                            <h4 style="margin: 0 0 10px 0;"><?php esc_html_e( 'Probar Conexión', 'wc-credit-payment-system' ); ?></h4>
                            <p style="margin: 0 0 10px 0;"><?php esc_html_e( 'Una vez configurada la API, puedes probar que la conexión funciona correctamente:', 'wc-credit-payment-system' ); ?></p>
                            <button type="button" class="button button-secondary" id="wcps-test-api" style="margin-right: 10px;">
                                <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Probar WhatsApp API', 'wc-credit-payment-system' ); ?>
                            </button>
                            <span id="wcps-api-test-result" style="font-weight: bold;"></span>
                        </div>
                    </div>
                </div>

            </div>
        <?php else : ?>
            <!-- **SECCIÓN MEJORADA**: Plantillas de notificación -->
            <div class="wcps-templates-section">
                <?php if ( isset( $grouped_templates[$active_tab] ) ) : ?>
                    <?php foreach ( $grouped_templates[$active_tab] as $template ) : ?>
                        <div class="postbox wcps-template-box" data-template-id="<?php echo esc_attr( $template->id ); ?>">
                            <div class="postbox-header">
                                <h2 class="hndle">
                                    <span>
                                        <?php 
                                        // Iconos según el tipo de plantilla
                                        $icon_class = 'dashicons-email';
                                        if ( strpos( $template->template_type, 'whatsapp' ) !== false ) {
                                            $icon_class = 'dashicons-smartphone';
                                        } elseif ( strpos( $template->template_type, 'admin' ) !== false ) {
                                            $icon_class = 'dashicons-admin-users';
                                        }
                                        ?>
                                        <span class="dashicons <?php echo esc_attr( $icon_class ); ?>" style="margin-right: 8px;"></span>
                                        <?php echo esc_html( $template->template_name ); ?>
                                    </span>
                                </h2>
                                <div class="handle-actions">
                                    <button type="button" class="handlediv" aria-expanded="true">
                                        <span class="screen-reader-text"><?php esc_html_e( 'Alternar panel', 'wc-credit-payment-system' ); ?></span>
                                        <span class="toggle-indicator" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="inside">
                                <input type="hidden" name="templates[<?php echo esc_attr( $template->id ); ?>][id]" value="<?php echo esc_attr( $template->id ); ?>">
                                
                                <table class="form-table">
                                    <!-- Toggle de activación más visible -->
                                    <tr valign="top">
                                        <th scope="row" style="width: 200px;">
                                            <label for="template_active_<?php echo esc_attr( $template->id ); ?>">
                                                <?php esc_html_e( 'Estado de la Notificación', 'wc-credit-payment-system' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <label class="wcps-toggle-switch" style="position: relative; display: inline-block; width: 60px; height: 34px;">
                                                <input type="checkbox" id="template_active_<?php echo esc_attr( $template->id ); ?>" 
                                                       name="templates[<?php echo esc_attr( $template->id ); ?>][is_active]" 
                                                       value="1" <?php checked( $template->is_active, 1 ); ?>
                                                       style="opacity: 0; width: 0; height: 0;">
                                                <span class="wcps-toggle-slider" style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;">
                                                    <span style="position: absolute; content: ''; height: 26px; width: 26px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; transform: <?php echo $template->is_active ? 'translateX(26px)' : 'translateX(0)'; ?>; background-color: <?php echo $template->is_active ? '#4CAF50' : '#ccc'; ?>;"></span>
                                                </span>
                                            </label>
                                            <span style="margin-left: 10px; font-weight: bold; color: <?php echo $template->is_active ? '#4CAF50' : '#999'; ?>;">
                                                <?php echo $template->is_active ? esc_html__( 'Activa', 'wc-credit-payment-system' ) : esc_html__( 'Inactiva', 'wc-credit-payment-system' ); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    
                                    <!-- Campo de asunto solo para emails -->
                                    <?php if ( strpos( $template->template_type, 'email' ) !== false ) : ?>
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="template_subject_<?php echo esc_attr( $template->id ); ?>">
                                                <?php esc_html_e( 'Asunto del Email', 'wc-credit-payment-system' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" id="template_subject_<?php echo esc_attr( $template->id ); ?>" 
                                                   name="templates[<?php echo esc_attr( $template->id ); ?>][subject]" 
                                                   value="<?php echo esc_attr( $template->subject ); ?>" 
                                                   class="large-text" placeholder="<?php esc_attr_e( 'Ej: Recordatorio de pago pendiente', 'wc-credit-payment-system' ); ?>">
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <input type="hidden" name="templates[<?php echo esc_attr( $template->id ); ?>][subject]" value="">
                                    <?php endif; ?>
                                    
                                    <!-- Área de contenido mejorada -->
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="template_content_<?php echo esc_attr( $template->id ); ?>">
                                                <?php esc_html_e( 'Contenido del Mensaje', 'wc-credit-payment-system' ); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <textarea id="template_content_<?php echo esc_attr( $template->id ); ?>" 
                                                      name="templates[<?php echo esc_attr( $template->id ); ?>][content]" 
                                                      rows="8" class="large-text" 
                                                      placeholder="<?php esc_attr_e( 'Escribe aquí el contenido del mensaje...', 'wc-credit-payment-system' ); ?>"><?php echo esc_textarea( $template->content ); ?></textarea>
                                            <p class="description">
                                                <?php esc_html_e( 'Usa HTML básico para emails. Para WhatsApp, usa solo texto plano.', 'wc-credit-payment-system' ); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    
                                    <!-- Variables disponibles con mejor formato -->
                                    <tr valign="top">
                                        <th scope="row"><?php esc_html_e( 'Variables Disponibles', 'wc-credit-payment-system' ); ?></th>
                                        <td>
                                            <div class="wcps-variables-box" style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px;">
                                                <p style="margin: 0 0 10px 0; font-weight: bold;"><?php esc_html_e( 'Puedes usar estas variables en tu mensaje:', 'wc-credit-payment-system' ); ?></p>
                                                <div class="wcps-variables-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 5px;">
                                                    <?php 
                                                    $variables = explode( ', ', $template->variables );
                                                    foreach ( $variables as $variable ) {
                                                        $variable = trim( $variable );
                                                        if ( ! empty( $variable ) ) {
                                                            echo '<code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 12px; cursor: pointer;" onclick="copyToClipboard(\'' . esc_js( $variable ) . '\')" title="' . esc_attr__( 'Clic para copiar', 'wc-credit-payment-system' ) . '">' . esc_html( $variable ) . '</code>';
                                                        }
                                                    }
                                                    ?>
                                                </div>
                                                <p style="margin: 10px 0 0 0; font-size: 12px; color: #6c757d;">
                                                    <em><?php esc_html_e( '💡 Tip: Haz clic en cualquier variable para copiarla al portapapeles', 'wc-credit-payment-system' ); ?></em>
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="wcps-no-templates" style="text-align: center; padding: 60px 20px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px;">
                        <span class="dashicons dashicons-admin-post" style="font-size: 48px; color: #c3c4c7; margin-bottom: 20px;"></span>
                        <h3><?php esc_html_e( 'No hay plantillas disponibles', 'wc-credit-payment-system' ); ?></h3>
                        <p><?php esc_html_e( 'No se encontraron plantillas para esta sección. Esto puede indicar un problema con la instalación del plugin.', 'wc-credit-payment-system' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Botones de acción mejorados -->
        <div class="wcps-form-actions" style="background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 20px; margin: 20px 0; text-align: right;">
            <button type="submit" class="button button-primary button-large" style="margin-right: 10px;">
                <span class="dashicons dashicons-saved" style="margin-right: 5px;"></span>
                <?php esc_html_e( 'Guardar Cambios', 'wc-credit-payment-system' ); ?>
            </button>
            <button type="button" class="button button-secondary" onclick="location.reload();">
                <span class="dashicons dashicons-update" style="margin-right: 5px;"></span>
                <?php esc_html_e( 'Recargar Página', 'wc-credit-payment-system' ); ?>
            </button>
        </div>
    </form>
</div>

<!-- **NUEVO**: CSS personalizado y JavaScript mejorado -->
<style>
.wcps-admin-page .postbox {
    margin-bottom: 20px;
}

.wcps-template-box {
    transition: all 0.3s ease;
}

.wcps-template-box:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.wcps-toggle-switch input:checked + .wcps-toggle-slider {
    background-color: #4CAF50 !important;
}

.wcps-toggle-switch input:checked + .wcps-toggle-slider span {
    transform: translateX(26px) !important;
}

.wcps-variables-grid code:hover {
    background: #007cba !important;
    color: white;
}

.nav-tab-wrapper .nav-tab {
    display: inline-flex;
    align-items: center;
}

.wcps-tab-counter {
    font-weight: bold;
    font-size: 11px !important;
}

.wcps-stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Responsive design */
@media (max-width: 768px) {
    .wcps-stats-cards {
        flex-direction: column !important;
    }
    
    .wcps-variables-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    
    // **FUNCIÓN**: Toggle de visibilidad de contraseñas
    window.togglePasswordVisibility = function(fieldId) {
        const field = document.getElementById(fieldId);
        const button = field.nextElementSibling;
        const icon = button.querySelector('.dashicons');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.className = 'dashicons dashicons-hidden';
            button.setAttribute('title', '<?php esc_attr_e( "Ocultar", "wc-credit-payment-system" ); ?>');
        } else {
            field.type = 'password';
            icon.className = 'dashicons dashicons-visibility';
            button.setAttribute('title', '<?php esc_attr_e( "Mostrar", "wc-credit-payment-system" ); ?>');
        }
    };
    
    // **FUNCIÓN**: Copiar variables al portapapeles
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Crear notificación temporal
            const notification = document.createElement('div');
            notification.textContent = '<?php esc_html_e( "¡Variable copiada!", "wc-credit-payment-system" ); ?>';
            notification.style.cssText = `
                position: fixed;
                top: 32px;
                right: 20px;
                background: #46b450;
                color: white;
                padding: 10px 15px;
                border-radius: 4px;
                z-index: 100000;
                font-size: 13px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 2000);
        }).catch(function() {
            // Fallback para navegadores antiguos
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            alert('<?php esc_html_e( "Variable copiada al portapapeles", "wc-credit-payment-system" ); ?>');
        });
    };
    
    // **FUNCIÓN**: Test de API de WhatsApp
    const testButton = document.getElementById('wcps-test-api');
    const testResult = document.getElementById('wcps-api-test-result');
    
    if (testButton && testResult) {
        testButton.addEventListener('click', function() {
            const apiSecret = document.getElementById('wcps_whatsapp_api_secret').value;
            const accountId = document.getElementById('wcps_whatsapp_account_id').value;
            const adminPhone = document.getElementById('wcps_admin_phone_number').value;
            
            if (!apiSecret || !accountId) {
                testResult.innerHTML = '<span style="color: #dc3232;">⚠️ <?php esc_html_e( "Completa la configuración de API primero", "wc-credit-payment-system" ); ?></span>';
                return;
            }
            
            testButton.disabled = true;
            testButton.innerHTML = '<span class="dashicons dashicons-update" style="animation: spin 1s linear infinite;"></span> <?php esc_html_e( "Probando...", "wc-credit-payment-system" ); ?>';
            testResult.innerHTML = '<span style="color: #0073aa;">🔄 <?php esc_html_e( "Verificando conexión...", "wc-credit-payment-system" ); ?></span>';
            
            // Simular test de API (aquí deberías hacer una llamada real a tu endpoint)
            setTimeout(function() {
                // Por ahora simulamos una respuesta exitosa
                // En producción, esto debería ser una llamada AJAX real a tu API
                testResult.innerHTML = '<span style="color: #46b450;">✅ <?php esc_html_e( "Conexión exitosa (simulada)", "wc-credit-payment-system" ); ?></span>';
                testButton.disabled = false;
                testButton.innerHTML = '<span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( "Probar WhatsApp API", "wc-credit-payment-system" ); ?>';
            }, 2000);
        });
    }
    
    // **FUNCIÓN**: Validación de formulario mejorada
    const form = document.getElementById('wcps-settings-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            let hasErrors = false;
            const errors = [];
            
            // Validar teléfono si está presente
            const phoneField = document.getElementById('wcps_admin_phone_number');
            if (phoneField && phoneField.value) {
                const phoneRegex = /^\+?[1-9]\d{1,14}$/;
                if (!phoneRegex.test(phoneField.value)) {
                    hasErrors = true;
                    errors.push('<?php esc_html_e( "El formato del teléfono no es válido", "wc-credit-payment-system" ); ?>');
                    phoneField.style.borderColor = '#dc3232';
                } else {
                    phoneField.style.borderColor = '';
                }
            }
            
            // Validar que al menos una plantilla esté activa por tipo
            const activeTab = '<?php echo esc_js( $active_tab ); ?>';
            if (activeTab !== 'settings') {
                const checkboxes = form.querySelectorAll('input[type="checkbox"][name*="is_active"]:checked');
                if (checkboxes.length === 0) {
                    const confirmMessage = '<?php esc_html_e( "No has activado ninguna plantilla de notificación. ¿Estás seguro de continuar?", "wc-credit-payment-system" ); ?>';
                    if (!confirm(confirmMessage)) {
                        hasErrors = true;
                    }
                }
            }
            
            if (hasErrors && errors.length > 0) {
                e.preventDefault();
                alert(errors.join('\n'));
            }
        });
    }
    
    // **FUNCIÓN**: Auto-guardar borrador de plantillas (opcional)
    const templateTextareas = document.querySelectorAll('textarea[name*="[content]"]');
    templateTextareas.forEach(function(textarea) {
        let timeout;
        textarea.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                // Auto-guardar en localStorage como respaldo
                const templateId = textarea.name.match(/\[(\d+)\]/)[1];
                localStorage.setItem('wcps_template_draft_' + templateId, textarea.value);
                
                // Mostrar indicador de guardado
                let indicator = textarea.parentNode.querySelector('.wcps-autosave-indicator');
                if (!indicator) {
                    indicator = document.createElement('span');
                    indicator.className = 'wcps-autosave-indicator';
                    indicator.style.cssText = 'color: #666; font-size: 12px; margin-left: 10px;';
                    textarea.parentNode.appendChild(indicator);
                }
                indicator.textContent = '💾 <?php esc_html_e( "Borrador guardado", "wc-credit-payment-system" ); ?>';
                
                setTimeout(function() {
                    if (indicator) {
                        indicator.textContent = '';
                    }
                }, 2000);
            }, 1000);
        });
        
        // Restaurar borrador al cargar la página
        const templateId = textarea.name.match(/\[(\d+)\]/)[1];
        const draft = localStorage.getItem('wcps_template_draft_' + templateId);
        if (draft && draft !== textarea.value && draft.trim() !== '') {
            const restoreMessage = '<?php esc_html_e( "Se encontró un borrador guardado. ¿Quieres restaurarlo?", "wc-credit-payment-system" ); ?>';
            if (confirm(restoreMessage)) {
                textarea.value = draft;
            } else {
                localStorage.removeItem('wcps_template_draft_' + templateId);
            }
        }
    });
    
    // Limpiar borradores cuando se guarde exitosamente
    if (window.location.search.includes('success=1')) {
        templateTextareas.forEach(function(textarea) {
            const templateId = textarea.name.match(/\[(\d+)\]/)[1];
            localStorage.removeItem('wcps_template_draft_' + templateId);
        });
    }
    
});

// Animación CSS para el spinner
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);
</script>

<?php
// **NUEVA FUNCIÓN**: Mostrar ayuda contextual
add_action( 'admin_footer', function() {
    global $current_screen;
    if ( $current_screen && strpos( $current_screen->id, 'wc-credit-settings' ) !== false ) {
        ?>
        <div id="wcps-help-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100000;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; max-width: 600px; max-height: 80vh; overflow-y: auto;">
                <h2><?php esc_html_e( 'Ayuda - Sistema de Crédito', 'wc-credit-payment-system' ); ?></h2>
                <div style="line-height: 1.6;">
                    <h3><?php esc_html_e( 'Variables de Plantillas:', 'wc-credit-payment-system' ); ?></h3>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><code>{cliente_nombre}</code> - <?php esc_html_e( 'Nombre del cliente', 'wc-credit-payment-system' ); ?></li>
                        <li><code>{producto_nombre}</code> - <?php esc_html_e( 'Nombre del producto financiado', 'wc-credit-payment-system' ); ?></li>
                        <li><code>{cuota_numero}</code> - <?php esc_html_e( 'Número de la cuota', 'wc-credit-payment-system' ); ?></li>
                        <li><code>{monto_cuota}</code> - <?php esc_html_e( 'Valor de la cuota', 'wc-credit-payment-system' ); ?></li>
                        <li><code>{fecha_vencimiento}</code> - <?php esc_html_e( 'Fecha de vencimiento', 'wc-credit-payment-system' ); ?></li>
                    </ul>
                    
                    <h3><?php esc_html_e( 'Configuración de Ubicación:', 'wc-credit-payment-system' ); ?></h3>
                    <p><?php esc_html_e( 'La ubicación determina dónde aparecen los planes de crédito en la página del producto. Si cambias esto, limpia el cache del sitio.', 'wc-credit-payment-system' ); ?></p>
                </div>
                <button type="button" class="button button-primary" onclick="document.getElementById('wcps-help-modal').style.display='none';">
                    <?php esc_html_e( 'Cerrar', 'wc-credit-payment-system' ); ?>
                </button>
            </div>
        </div>
        
        <script>
        // Botón de ayuda flotante
        const helpButton = document.createElement('div');
        helpButton.innerHTML = '<span class="dashicons dashicons-editor-help"></span>';
        helpButton.style.cssText = `
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: #0073aa;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 99999;
            transition: all 0.3s ease;
            font-size: 20px;
        `;
        helpButton.setAttribute('title', '<?php esc_attr_e( "Mostrar ayuda", "wc-credit-payment-system" ); ?>');
        helpButton.onclick = () => document.getElementById('wcps-help-modal').style.display = 'block';
        document.body.appendChild(helpButton);
        
        // Hover effect
        helpButton.addEventListener('mouseenter', () => {
            helpButton.style.transform = 'scale(1.1)';
            helpButton.style.background = '#005a87';
        });
        helpButton.addEventListener('mouseleave', () => {
            helpButton.style.transform = 'scale(1)';
            helpButton.style.background = '#0073aa';
        });
        </script>
        <?php
    }
});
?>

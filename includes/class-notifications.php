<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Credit_Notifications {
    
    public function __construct() {
        // ... (otros hooks si son necesarios) ...
    }

    /**
     * **MÉTODO CORREGIDO Y CENTRALIZADO**
     * Envía una notificación basada en una plantilla de la base de datos.
     *
     * @param string $template_name El 'template_name' de la tabla de plantillas (Ej: 'Recordatorio de Cuota').
     * @param string $recipient_type 'client' o 'admin'.
     * @param array $data Datos para reemplazar las variables (Ej: ['credit_id' => 123, 'user_id' => 1]).
     */
    public function send_notification( $template_name, $recipient_type, $data ) {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'wc_credit_templates';

        // 1. Buscar plantillas activas para el evento (email y WhatsApp)
        $templates = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$templates_table} WHERE template_name = %s AND template_type LIKE %s AND is_active = 1",
            $template_name,
            '%' . $wpdb->esc_like( $recipient_type ) . '%'
        ) );

        if ( empty( $templates ) ) {
            return;
        }

        // 2. Obtener datos comunes
        $common_data = $this->get_common_data( $data );
        if ( empty( $common_data ) ) {
            return;
        }

        foreach ( $templates as $template ) {
            $subject = $this->replace_variables( $template->subject, $common_data );
            $content = $this->replace_variables( $template->content, $common_data );

            if ( strpos( $template->template_type, 'email' ) !== false ) {
                // Enviar Email
                $recipient_email = ( $recipient_type === 'admin' ) ? get_option('admin_email') : $common_data['{cliente_email}'];
                $this->send_email( $recipient_email, $subject, $content );
            }

            if ( strpos( $template->template_type, 'whatsapp' ) !== false ) {
                // Enviar WhatsApp
                $recipient_phone = ( $recipient_type === 'admin' ) ? get_option('wcps_admin_phone_number') : $common_data['{cliente_telefono}'];
                 if ( $recipient_phone ) {
                    $whatsapp_api = new WC_Credit_WhatsApp_API();
                    $whatsapp_api->send_message( $recipient_phone, $content );
                }
            }
        }
    }

    /**
     * Envía un email usando el sistema de WooCommerce.
     */
    private function send_email( $recipient, $subject, $content ) {
        $mailer = WC()->mailer();
        $headers = "Content-Type: text/html\r\n";
        $mailer->send( $recipient, $subject, $mailer->wrap_message( $subject, $content ), $headers, [] );
        error_log("Email de crédito enviado a: " . $recipient);
    }

    /**
     * Reemplaza las variables en el texto de la plantilla.
     */
    private function replace_variables( $text, $data ) {
        return str_replace( array_keys( $data ), array_values( $data ), $text );
    }

    /**
     * Obtiene y prepara los datos necesarios para las plantillas.
     */
    private function get_common_data( $data ) {
        global $wpdb;
        $prepared_data = [];

        if ( isset( $data['installment_id'] ) ) {
            $installment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_credit_installments WHERE id = %d", $data['installment_id'] ) );
            $credit_account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_credit_accounts WHERE id = %d", $installment->credit_account_id ) );
            $data['credit_id'] = $credit_account->id;
        } elseif ( isset( $data['credit_id'] ) ) {
             $credit_account = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wc_credit_accounts WHERE id = %d", $data['credit_id'] ) );
        } else {
            return []; // Datos insuficientes
        }

        $user = get_user_by( 'id', $credit_account->user_id );
        $product = wc_get_product( $credit_account->product_id );

        $prepared_data['{cliente_nombre}'] = $user->display_name;
        $prepared_data['{cliente_email}'] = $user->user_email;
        $prepared_data['{cliente_telefono}'] = get_user_meta($user->ID, 'billing_phone', true);
        $prepared_data['{producto_nombre}'] = $product ? $product->get_name() : '-';
        $prepared_data['{credito_id}'] = $credit_account->id;
        $prepared_data['{saldo_pendiente}'] = wc_price( $credit_account->financed_amount - ($credit_account->paid_installments * $credit_account->installment_amount) );

        if ( isset( $installment ) ) {
            $prepared_data['{cuota_numero}'] = $installment->installment_number;
            $prepared_data['{monto_cuota}'] = wc_price( $installment->amount );
            $prepared_data['{fecha_vencimiento}'] = date_i18n( get_option( 'date_format' ), strtotime( $installment->due_date ) );
        }
        
        if(isset($data['comment'])){
            $prepared_data['{comentario_cliente}'] = $data['comment'];
        }

        return $prepared_data;
    }

    /**
     * **MÉTODO CORREGIDO** para ser llamado por el CRON.
     * @param int $installment_id
     */
    public function send_reminder_notification( $installment_id ) {
        // Ahora simplemente llama al método centralizado
        $this->send_notification('Recordatorio de Cuota', 'client', ['installment_id' => $installment_id]);
    }
}
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Credit_WhatsApp_API {
    
    private $api_secret;
    private $account_id;
    private $api_url = 'https://api.smsenlinea.com/v1/whatsapp/messages'; // URL de ejemplo

    public function __construct() {
        // En una fase futura, estos valores se obtendrán de la configuración del plugin
        $this->api_secret = get_option('wcps_whatsapp_api_secret', '');
        $this->account_id = get_option('wcps_whatsapp_account_id', '');
    }

    /**
     * Envía un mensaje a través de la API.
     *
     * @param string $recipient Número de teléfono del destinatario en formato internacional.
     * @param string $message   El mensaje a enviar.
     * @param int    $priority  Prioridad del mensaje (1=Alta, 2=Normal).
     * @return bool|WP_Error True si el envío fue exitoso, WP_Error en caso contrario.
     */
    public function send_message( $recipient, $message, $priority = 2 ) {
        if ( empty( $this->api_secret ) || empty( $this->account_id ) ) {
            return new WP_Error( 'api_not_configured', __( 'La API de WhatsApp no está configurada.', 'wc-credit-payment-system' ) );
        }

        $body = [
            'accountId' => $this->account_id,
            'recipient' => $recipient,
            'message'   => $message,
            'priority'  => $priority
        ];

        $args = [
            'method'  => 'POST',
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $this->api_secret,
            ],
            'body'    => json_encode( $body ),
            'timeout' => 30,
        ];

        $response = wp_remote_post( $this->api_url, $args );

        if ( is_wp_error( $response ) ) {
            // Error de conexión de WordPress
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code >= 200 && $response_code < 300 ) {
            // Log de éxito (opcional)
            // error_log('Mensaje de WhatsApp enviado a ' . $recipient);
            return true;
        } else {
            // Error devuelto por la API
            // error_log('Error API WhatsApp: ' . wp_remote_retrieve_body($response));
            return new WP_Error( 'api_error', __( 'La API de WhatsApp devolvió un error.', 'wc-credit-payment-system' ), wp_remote_retrieve_body( $response ) );
        }
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$user_id = get_current_user_id();

// Seguridad: Verificar que el crédito pertenezca al usuario actual.
$credit_account = $wpdb->get_row( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wc_credit_accounts WHERE id = %d AND user_id = %d",
    $credit_id,
    $user_id
) );

if ( ! $credit_account ) {
    echo '<div class="woocommerce-error">' . esc_html__( 'Crédito no encontrado o no tienes permiso para verlo.', 'wc-credit-payment-system' ) . '</div>';
    return;
}

$installments = $wpdb->get_results( $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}wc_credit_installments WHERE credit_account_id = %d ORDER BY installment_number ASC",
    $credit_id
) );

?>
<p><?php echo sprintf(
    esc_html__( 'Mostrando detalles para el crédito #%d.', 'wc-credit-payment-system' ),
    esc_html( $credit_account->id )
); ?> <a href="<?php echo esc_url( wc_get_account_endpoint_url( WC_Credit_Frontend::$endpoint ) ); ?>"><?php esc_html_e( '&larr; Volver a mis créditos', 'wc-credit-payment-system' ); ?></a></p>

<h3><?php esc_html_e( 'Cronograma de Pagos', 'wc-credit-payment-system' ); ?></h3>

<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
    <thead>
        <tr>
            <th><span class="nobr"><?php esc_html_e( 'Cuota #', 'wc-credit-payment-system' ); ?></span></th>
            <th><span class="nobr"><?php esc_html_e( 'Monto', 'wc-credit-payment-system' ); ?></span></th>
            <th><span class="nobr"><?php esc_html_e( 'Fecha Vencimiento', 'wc-credit-payment-system' ); ?></span></th>
            <th><span class="nobr"><?php esc_html_e( 'Fecha Pago', 'wc-credit-payment-system' ); ?></span></th>
            <th><span class="nobr"><?php esc_html_e( 'Estado', 'wc-credit-payment-system' ); ?></span></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $installments as $installment ) : ?>
            <tr>
                <td><?php echo esc_html( $installment->installment_number ); ?></td>
                <td><?php echo wp_kses_post( wc_price( $installment->amount ) ); ?></td>
                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $installment->due_date ) ) ); ?></td>
                <td><?php echo $installment->paid_date ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $installment->paid_date ) ) ) : '—'; ?></td>
                <td><span class="status-<?php echo esc_attr( $installment->status ); ?>"><?php echo esc_html( ucfirst( $installment->status ) ); ?></span></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

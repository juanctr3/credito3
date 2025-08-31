<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
$user_id = get_current_user_id();

$accounts_table = $wpdb->prefix . 'wc_credit_accounts';
$plans_table = $wpdb->prefix . 'wc_credit_plans';

$credits = $wpdb->get_results( $wpdb->prepare(
    "SELECT ca.*, p.name as plan_name, pp.post_title as product_name
     FROM {$accounts_table} ca
     JOIN {$plans_table} p ON ca.plan_id = p.id
     JOIN {$wpdb->prefix}posts pp ON ca.product_id = pp.ID
     WHERE ca.user_id = %d
     ORDER BY ca.created_at DESC",
    $user_id
) );

?>
<h3><?php esc_html_e( 'Historial de Créditos', 'wc-credit-payment-system' ); ?></h3>

<?php if ( ! empty( $credits ) ) : ?>
<table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
    <thead>
        <tr>
            <th class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Crédito #', 'wc-credit-payment-system' ); ?></span></th>
            <th class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Producto', 'wc-credit-payment-system' ); ?></span></th>
            <th class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Cuotas', 'wc-credit-payment-system' ); ?></span></th>
            <th class="woocommerce-orders-table__header"><span class="nobr"><?php esc_html_e( 'Estado', 'wc-credit-payment-system' ); ?></span></th>
            <th class="woocommerce-orders-table__header"></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $credits as $credit ) : ?>
            <tr class="woocommerce-orders-table__row order">
                <td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Crédito #', 'wc-credit-payment-system' ); ?>">
                    <?php echo esc_html( $credit->id ); ?>
                </td>
                <td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Producto', 'wc-credit-payment-system' ); ?>">
                    <a href="<?php echo esc_url( get_permalink( $credit->product_id ) ); ?>"><?php echo esc_html( $credit->product_name ); ?></a>
                </td>
                <td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Cuotas', 'wc-credit-payment-system' ); ?>">
                    <?php echo sprintf( '%d / %d', $credit->paid_installments, $credit->total_installments ); ?>
                </td>
                <td class="woocommerce-orders-table__cell" data-title="<?php esc_attr_e( 'Estado', 'wc-credit-payment-system' ); ?>">
                    <span class="status-<?php echo esc_attr( $credit->status ); ?>"><?php echo esc_html( ucfirst( $credit->status ) ); ?></span>
                </td>
                <td class="woocommerce-orders-table__cell">
                    <a href="<?php echo esc_url( wc_get_account_endpoint_url( WC_Credit_Frontend::$endpoint ) . '?view-credit=' . $credit->id ); ?>" class="woocommerce-button button view"><?php esc_html_e( 'Ver Detalles', 'wc-credit-payment-system' ); ?></a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else : ?>
    <div class="woocommerce-message woocommerce-message--info woocommerce-Message woocommerce-Message--info woocommerce-info">
        <?php esc_html_e( 'Aún no tienes créditos activos.', 'wc-credit-payment-system' ); ?>
        <a class="woocommerce-Button button" href="<?php echo esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ); ?>"><?php esc_html_e( 'Ir a la tienda', 'wc-credit-payment-system' ); ?></a>
    </div>
<?php endif; ?>

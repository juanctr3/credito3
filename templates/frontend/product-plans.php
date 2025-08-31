<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wcps-plans-container">
    <h3><?php esc_html_e( 'Elige tu forma de pago', 'wc-credit-payment-system' ); ?></h3>
    <div class="wcps-accordion">
        
        <div class="wcps-plan wcps-plan-full-payment">
            <div class="wcps-plan-header">
                <input type="radio" name="wcps_selected_plan" id="plan-full-payment" value="full_payment">
                <label for="plan-full-payment">
                    <strong><?php esc_html_e( 'Pagar completo ahora', 'wc-credit-payment-system' ); ?></strong>
                </label>
            </div>
        </div>

        <?php foreach ( $plans as $plan ) : ?>
            <?php
            // Cálculos básicos para mostrar
            $price = (float) $product->get_price();
            $down_payment = ( $price * (float)$plan->down_payment_percentage ) / 100;
            $financed_amount = $price - $down_payment;
            $total_interest = ( $financed_amount * (float)$plan->interest_rate ) / 100;
            $total_financed = $financed_amount + $total_interest;
            $installment_amount = $plan->max_installments > 0 ? $total_financed / $plan->max_installments : 0;
            ?>
            <div class="wcps-plan">
                <div class="wcps-plan-header">
                    <input type="radio" name="wcps_selected_plan" id="plan-<?php echo esc_attr( $plan->id ); ?>" value="<?php echo esc_attr( $plan->id ); ?>">
                    <label for="plan-<?php echo esc_attr( $plan->id ); ?>">
                        <strong><?php echo esc_html( $plan->name ); ?>:</strong>
                        <?php echo sprintf( esc_html__( '%d cuotas de %s', 'wc-credit-payment-system' ), $plan->max_installments, wc_price( $installment_amount ) ); ?>
                    </label>
                    <span class="wcps-toggle-details">+</span>
                </div>
                <div class="wcps-plan-details" style="display:none;">
                    <ul>
                        <li><strong><?php esc_html_e( 'Precio del Producto:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $price ); ?></li>
                        <li><strong><?php esc_html_e( 'Cuota Inicial:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $down_payment ); ?> (<?php echo esc_html( $plan->down_payment_percentage ); ?>%)</li>
                        <li><strong><?php esc_html_e( 'Monto a Financiar:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $financed_amount ); ?></li>
                        <li><strong><?php esc_html_e( 'Interés Aplicado:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $total_interest ); ?> (<?php echo esc_html( $plan->interest_rate ); ?>%)</li>
                        <li><strong><?php esc_html_e( 'Total a Pagar (Financiado):', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $total_financed ); ?></li>
                        <li><strong><?php esc_html_e( 'Frecuencia de Pagos:', 'wc-credit-payment-system' ); ?></strong> <?php echo esc_html( ucfirst($plan->payment_frequency) ); ?></li>
                    </ul>

                    <?php if ( ! empty( $plan->description ) ) : ?>
                        <div class="plan-description">
                            <h4><?php esc_html_e( 'Descripción del Plan', 'wc-credit-payment-system' ); ?></h4>
                            <p><?php echo esc_html( $plan->description ); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="plan-schedule">
                        <h4><?php esc_html_e( 'Cronograma de Pagos Estimado', 'wc-credit-payment-system' ); ?></h4>
                        <table class="shop_table shop_table_responsive">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Cuota #', 'wc-credit-payment-system' ); ?></th>
                                    <th><?php esc_html_e( 'Fecha Estimada', 'wc-credit-payment-system' ); ?></th>
                                    <th><?php esc_html_e( 'Monto', 'wc-credit-payment-system' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ( $i = 1; $i <= $plan->max_installments; $i++ ) : 
                                    $due_date = new DateTime();
                                    switch($plan->payment_frequency) {
    case 'weekly':
        $interval_string = "P{$i}W";
        break;
    case 'biweekly':
        $interval_string = "P" . ($i * 2) . "W";
        break;
    default:
        $interval_string = "P{$i}M";
        break;
};
                                    $due_date->add(new DateInterval($interval_string));
                                ?>
                                <tr>
                                    <td data-title="<?php esc_attr_e( 'Cuota #', 'wc-credit-payment-system' ); ?>"><?php echo esc_html( $i ); ?></td>
                                    <td data-title="<?php esc_attr_e( 'Fecha Estimada', 'wc-credit-payment-system' ); ?>"><?php echo esc_html( date_i18n( get_option('date_format'), $due_date->getTimestamp() ) ); ?></td>
                                    <td data-title="<?php esc_attr_e( 'Monto', 'wc-credit-payment-system' ); ?>"><?php echo wc_price( $installment_amount ); ?></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

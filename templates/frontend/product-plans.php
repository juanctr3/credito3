<?php
/**
 * Plantilla para mostrar los planes de crédito en la página del producto
 * Versión corregida - Sin CSS/JS inline problemático
 *
 * @package WooCommerceCreditPaymentSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<!-- IMPORTANTE: Este div debe estar DENTRO del form.cart -->
<div class="wcps-plans-container" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>">
    <h3><?php esc_html_e( 'Elige tu forma de pago', 'wc-credit-payment-system' ); ?></h3>
    
    <!-- Campo hidden de respaldo para asegurar que el valor se envíe -->
    <input type="hidden" id="wcps_selected_plan_backup" name="wcps_selected_plan_backup" value="">
    
    <div class="wcps-accordion">
        
        <!-- Opción de pago completo -->
        <div class="wcps-plan wcps-plan-full-payment">
            <div class="wcps-plan-header">
                <input type="radio" name="wcps_selected_plan" id="plan-full-payment" value="full_payment">
                <label for="plan-full-payment">
                    <strong><?php esc_html_e( 'Pagar completo ahora', 'wc-credit-payment-system' ); ?></strong>
                </label>
            </div>
        </div>

        <!-- Planes de crédito disponibles -->
        <?php foreach ( $plans as $plan ) : ?>
            <?php
            // Cálculos básicos para mostrar información del plan
            $price = (float) $product->get_price();
            $down_payment = ( $price * (float)$plan->down_payment_percentage ) / 100;
            $financed_amount = $price - $down_payment;
            $total_interest = ( $financed_amount * (float)$plan->interest_rate ) / 100;
            $total_financed = $financed_amount + $total_interest;
            $installment_amount = $plan->max_installments > 0 ? $total_financed / $plan->max_installments : 0;
            ?>
            <div class="wcps-plan" data-plan-id="<?php echo esc_attr( $plan->id ); ?>">
                <div class="wcps-plan-header">
                    <input type="radio" name="wcps_selected_plan" id="plan-<?php echo esc_attr( $plan->id ); ?>" value="<?php echo esc_attr( $plan->id ); ?>">
                    <label for="plan-<?php echo esc_attr( $plan->id ); ?>">
                        <strong><?php echo esc_html( $plan->name ); ?>:</strong>
                        <?php echo sprintf( esc_html__( '%d cuotas de %s', 'wc-credit-payment-system' ), $plan->max_installments, wc_price( $installment_amount ) ); ?>
                    </label>
                    <span class="wcps-toggle-details" title="<?php esc_attr_e( 'Ver detalles del plan', 'wc-credit-payment-system' ); ?>">+</span>
                </div>
                
                <div class="wcps-plan-details" style="display:none;">
                    <!-- Información financiera del plan -->
                    <div class="wcps-plan-summary">
                        <ul>
                            <li><strong><?php esc_html_e( 'Precio del Producto:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $price ); ?></li>
                            <li><strong><?php esc_html_e( 'Cuota Inicial:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $down_payment ); ?> (<?php echo esc_html( $plan->down_payment_percentage ); ?>%)</li>
                            <li><strong><?php esc_html_e( 'Monto a Financiar:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $financed_amount ); ?></li>
                            <li><strong><?php esc_html_e( 'Interés Aplicado:', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $total_interest ); ?> (<?php echo esc_html( $plan->interest_rate ); ?>%)</li>
                            <li><strong><?php esc_html_e( 'Total a Pagar (Financiado):', 'wc-credit-payment-system' ); ?></strong> <?php echo wc_price( $total_financed ); ?></li>
                            <li><strong><?php esc_html_e( 'Frecuencia de Pagos:', 'wc-credit-payment-system' ); ?></strong> <?php echo esc_html( ucfirst($plan->payment_frequency) ); ?></li>
                        </ul>
                    </div>

                    <!-- Descripción del plan si existe -->
                    <?php if ( ! empty( $plan->description ) ) : ?>
                        <div class="plan-description">
                            <h4><?php esc_html_e( 'Descripción del Plan', 'wc-credit-payment-system' ); ?></h4>
                            <p><?php echo esc_html( $plan->description ); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Cronograma de pagos estimado (simplificado) -->
                    <div class="plan-schedule">
                        <h4><?php esc_html_e( 'Cronograma de Pagos Estimado', 'wc-credit-payment-system' ); ?></h4>
                        <p class="wcps-schedule-summary">
                            <?php 
                            printf( 
                                esc_html__( '%d pagos %s de %s cada uno', 'wc-credit-payment-system' ),
                                $plan->max_installments,
                                $plan->payment_frequency === 'weekly' ? __('semanales', 'wc-credit-payment-system') : 
                                ($plan->payment_frequency === 'biweekly' ? __('quincenales', 'wc-credit-payment-system') : 
                                __('mensuales', 'wc-credit-payment-system')),
                                wc_price( $installment_amount )
                            );
                            ?>
                        </p>
                    </div>

                    <!-- Resumen financiero mejorado -->
                    <div class="wcps-plan-totals">
                        <div class="wcps-total-comparison">
                            <div class="wcps-total-item">
                                <span class="wcps-total-label"><?php esc_html_e( 'Pago Inmediato:', 'wc-credit-payment-system' ); ?></span>
                                <span class="wcps-total-value"><?php echo wc_price( $down_payment ); ?></span>
                            </div>
                            <div class="wcps-total-item">
                                <span class="wcps-total-label"><?php esc_html_e( 'Total del Crédito:', 'wc-credit-payment-system' ); ?></span>
                                <span class="wcps-total-value"><?php echo wc_price( $down_payment + $total_financed ); ?></span>
                            </div>
                            <?php if ( $total_interest > 0 ) : ?>
                            <div class="wcps-total-item wcps-interest">
                                <span class="wcps-total-label"><?php esc_html_e( 'Interés Total:', 'wc-credit-payment-system' ); ?></span>
                                <span class="wcps-total-value"><?php echo wc_price( $total_interest ); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Información adicional -->
                    <div class="wcps-plan-info">
                        <p class="wcps-info-note">
                            <small>
                                <strong><?php esc_html_e( 'Nota:', 'wc-credit-payment-system' ); ?></strong>
                                <?php esc_html_e( 'Las fechas son estimadas y pueden variar según la fecha exacta de la compra.', 'wc-credit-payment-system' ); ?>
                            </small>
                        </p>
                        
                        <?php if ( $plan->notification_days_before > 0 ) : ?>
                        <p class="wcps-info-reminder">
                            <small>
                                <?php printf( 
                                    esc_html__( '📧 Recibirás recordatorios %d días antes de cada vencimiento.', 'wc-credit-payment-system' ),
                                    $plan->notification_days_before
                                ); ?>
                            </small>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Mensaje de ayuda -->
    <div class="wcps-help-text">
        <p><small>
            <strong><?php esc_html_e( '💡 Consejo:', 'wc-credit-payment-system' ); ?></strong>
            <?php esc_html_e( 'Haz clic en el "+" para ver los detalles completos de cada plan de financiamiento.', 'wc-credit-payment-system' ); ?>
        </small></p>
    </div>
</div>

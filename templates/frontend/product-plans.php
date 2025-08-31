<?php
/**
 * Plantilla para mostrar los planes de crédito en la página del producto
 * Versión corregida compatible con todas las versiones de PHP
 *
 * @package WooCommerceCreditPaymentSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wcps-plans-container">
    <h3><?php esc_html_e( 'Elige tu forma de pago', 'wc-credit-payment-system' ); ?></h3>
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

                    <!-- Cronograma de pagos estimado -->
                    <div class="plan-schedule">
                        <h4><?php esc_html_e( 'Cronograma de Pagos Estimado', 'wc-credit-payment-system' ); ?></h4>
                        <table class="shop_table shop_table_responsive wcps-schedule-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Cuota #', 'wc-credit-payment-system' ); ?></th>
                                    <th><?php esc_html_e( 'Fecha Estimada', 'wc-credit-payment-system' ); ?></th>
                                    <th><?php esc_html_e( 'Monto', 'wc-credit-payment-system' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // Generar cronograma de pagos
                                for ( $i = 1; $i <= $plan->max_installments; $i++ ) : 
                                    $due_date = new DateTime();
                                    
                                    // **CORRECCIÓN CRÍTICA**: Reemplazar match() por switch compatible
                                    switch ( $plan->payment_frequency ) {
                                        case 'weekly':
                                            $interval_string = "P{$i}W";
                                            break;
                                        case 'biweekly':
                                            $interval_string = "P" . ($i * 2) . "W";
                                            break;
                                        default:
                                            $interval_string = "P{$i}M";
                                            break;
                                    }
                                    
                                    try {
                                        $due_date->add( new DateInterval( $interval_string ) );
                                        $formatted_date = date_i18n( get_option('date_format'), $due_date->getTimestamp() );
                                    } catch ( Exception $e ) {
                                        // Fallback en caso de error con las fechas
                                        $formatted_date = __( 'A calcular', 'wc-credit-payment-system' );
                                    }
                                ?>
                                <tr>
                                    <td data-title="<?php esc_attr_e( 'Cuota #', 'wc-credit-payment-system' ); ?>">
                                        <?php echo esc_html( $i ); ?>
                                    </td>
                                    <td data-title="<?php esc_attr_e( 'Fecha Estimada', 'wc-credit-payment-system' ); ?>">
                                        <?php echo esc_html( $formatted_date ); ?>
                                    </td>
                                    <td data-title="<?php esc_attr_e( 'Monto', 'wc-credit-payment-system' ); ?>">
                                        <?php echo wc_price( $installment_amount ); ?>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
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
                                <?php esc_html_e( 'Las fechas son estimadas y pueden variar según la fecha exacta de la compra. El cronograma definitivo se confirmará después del pedido.', 'wc-credit-payment-system' ); ?>
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

<!-- CSS adicional embebido para mejor presentación -->
<style>
.wcps-plan-totals {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}

.wcps-total-comparison {
    display: grid;
    gap: 10px;
}

.wcps-total-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.wcps-total-item:last-child {
    border-bottom: none;
}

.wcps-total-item.wcps-interest {
    color: #dc3545;
    font-weight: bold;
}

.wcps-total-label {
    font-weight: 500;
}

.wcps-total-value {
    font-weight: bold;
    font-size: 1.1em;
}

.wcps-schedule-table {
    width: 100%;
    border-collapse: collapse;
    margin: 10px 0;
}

.wcps-schedule-table th,
.wcps-schedule-table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.wcps-schedule-table th {
    background: #f8f9fa;
    font-weight: bold;
}

.wcps-info-note {
    background: #e7f3ff;
    border-left: 4px solid #0073aa;
    padding: 10px 15px;
    margin: 15px 0;
    border-radius: 0 4px 4px 0;
}

.wcps-info-reminder {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 8px 15px;
    margin: 10px 0;
    border-radius: 0 4px 4px 0;
}

.wcps-help-text {
    text-align: center;
    margin-top: 15px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.wcps-plan-summary ul {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
}

@media (min-width: 768px) {
    .wcps-plan-summary ul {
        grid-template-columns: 1fr 1fr;
    }
}

/* Indicador visual para el plan seleccionado */
.wcps-plan-selected {
    background-color: #f0f8ff;
    border: 2px solid #0073aa !important;
    box-shadow: 0 0 10px rgba(0,115,170,0.3);
}

.wcps-plan-selected .wcps-plan-header label {
    color: #0073aa;
    font-weight: bold;
}

/* Mejoras para dispositivos móviles */
@media (max-width: 767px) {
    .wcps-schedule-table {
        font-size: 14px;
    }
    
    .wcps-schedule-table th,
    .wcps-schedule-table td {
        padding: 6px 8px;
    }
    
    .wcps-total-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    
    .wcps-total-value {
        font-size: 1.2em;
    }
}
</style>

<!-- JavaScript adicional para mejor UX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mejorar la experiencia del acordeón
    document.querySelectorAll('.wcps-toggle-details').forEach(function(toggle) {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const plan = this.closest('.wcps-plan');
            const details = plan.querySelector('.wcps-plan-details');
            const isVisible = details.style.display !== 'none';
            
            // Cerrar otros planes abiertos (opcional)
            document.querySelectorAll('.wcps-plan-details').forEach(function(otherDetails) {
                if (otherDetails !== details) {
                    otherDetails.style.display = 'none';
                    otherDetails.closest('.wcps-plan').querySelector('.wcps-toggle-details').textContent = '+';
                }
            });
            
            // Toggle del plan actual
            details.style.display = isVisible ? 'none' : 'block';
            this.textContent = isVisible ? '+' : '-';
            
            // Scroll suave hacia el plan expandido
            if (!isVisible) {
                setTimeout(() => {
                    plan.scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'nearest' 
                    });
                }, 100);
            }
        });
    });
    
    // Selección automática al hacer clic en cualquier parte del plan
    document.querySelectorAll('.wcps-plan-header').forEach(function(header) {
        header.addEventListener('click', function(e) {
            if (e.target.classList.contains('wcps-toggle-details')) {
                return; // No interferir con el toggle
            }
            
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
                
                // Disparar evento change manualmente
                const event = new Event('change', { bubbles: true });
                radio.dispatchEvent(event);
            }
        });
    });
});
</script>

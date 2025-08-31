<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Credit_Cron {

    public function __construct() {
        // Hook para ejecutar la tarea programada.
        add_action( 'wc_credit_check_due_installments', array( $this, 'send_due_installment_reminders' ) );
    }

    /**
     * Tarea principal que se ejecuta diariamente.
     * Busca cuotas próximas a vencer y envía recordatorios.
     */
    public static function send_due_installment_reminders() {
        global $wpdb;
        
        $installments_table = $wpdb->prefix . 'wc_credit_installments';
        $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
        $plans_table = $wpdb->prefix . 'wc_credit_plans';
        
        // La consulta busca cuotas pendientes cuya fecha de vencimiento coincida
        // con la fecha de hoy + los días de notificación definidos en su plan.
        $query = "
            SELECT i.id as installment_id, i.due_date, i.amount, a.user_id, p.notification_days_before
            FROM {$installments_table} i
            JOIN {$accounts_table} a ON i.credit_account_id = a.id
            JOIN {$plans_table} p ON a.plan_id = p.id
            WHERE i.status = 'pending'
              AND a.status = 'active'
              AND i.due_date = CURDATE() + INTERVAL p.notification_days_before DAY
        ";
        
        $installments_to_notify = $wpdb->get_results( $query );

        if ( empty( $installments_to_notify ) ) {
            return; // No hay nada que notificar hoy.
        }

        $notifications = new WC_Credit_Notifications();
        foreach ( $installments_to_notify as $installment ) {
            $notifications->send_reminder_notification( $installment->installment_id );
        }
    }
}

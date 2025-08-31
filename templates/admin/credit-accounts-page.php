<?php
/**
 * Añadir estas funciones al final de la clase WC_Credit_Admin
 * O crear una nueva clase para manejar las operaciones AJAX
 */

// Añadir estos métodos a la clase WC_Credit_Admin:

public function __construct() {
    // ... código existente ...
    
    // **NUEVOS HOOKS AJAX**
    add_action( 'wp_ajax_wcps_update_installment_status', array( $this, 'ajax_update_installment_status' ) );
    add_action( 'wp_ajax_wcps_update_account_status', array( $this, 'ajax_update_account_status' ) );
    add_action( 'admin_post_wcps_add_admin_comment', array( $this, 'handle_add_admin_comment' ) );
}

/**
 * **NUEVA FUNCIÓN AJAX**: Actualizar estado de una cuota
 */
public function ajax_update_installment_status() {
    // Verificar nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wcps_update_installment' ) ) {
        wp_send_json_error( array( 'message' => __( 'Error de seguridad', 'wc-credit-payment-system' ) ) );
    }
    
    // Verificar permisos
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes', 'wc-credit-payment-system' ) ) );
    }
    
    $installment_id = isset( $_POST['installment_id'] ) ? absint( $_POST['installment_id'] ) : 0;
    $new_status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
    
    if ( $installment_id === 0 || empty( $new_status ) ) {
        wp_send_json_error( array( 'message' => __( 'Datos inválidos', 'wc-credit-payment-system' ) ) );
    }
    
    // Validar estado
    $valid_statuses = array( 'pending', 'paid', 'overdue' );
    if ( ! in_array( $new_status, $valid_statuses ) ) {
        wp_send_json_error( array( 'message' => __( 'Estado inválido', 'wc-credit-payment-system' ) ) );
    }
    
    global $wpdb;
    $installments_table = $wpdb->prefix . 'wc_credit_installments';
    $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
    
    // Obtener información actual de la cuota
    $installment = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$installments_table} WHERE id = %d",
        $installment_id
    ) );
    
    if ( ! $installment ) {
        wp_send_json_error( array( 'message' => __( 'Cuota no encontrada', 'wc-credit-payment-system' ) ) );
    }
    
    // Preparar datos para actualizar
    $update_data = array( 'status' => $new_status );
    
    if ( $new_status === 'paid' ) {
        $update_data['paid_date'] = current_time( 'Y-m-d' );
    } else {
        $update_data['paid_date'] = null;
    }
    
    // Actualizar la cuota
    $result = $wpdb->update(
        $installments_table,
        $update_data,
        array( 'id' => $installment_id )
    );
    
    if ( $result === false ) {
        wp_send_json_error( array( 'message' => __( 'Error al actualizar la cuota', 'wc-credit-payment-system' ) ) );
    }
    
    // Actualizar contador de cuotas pagadas en la cuenta
    $this->update_account_paid_installments( $installment->credit_account_id );
    
    // Registrar la acción en los comentarios
    $this->log_installment_status_change( $installment->credit_account_id, $installment->installment_number, $installment->status, $new_status );
    
    $message = $new_status === 'paid' ? 
        __( 'Cuota marcada como pagada correctamente', 'wc-credit-payment-system' ) :
        __( 'Cuota marcada como pendiente correctamente', 'wc-credit-payment-system' );
    
    wp_send_json_success( array( 'message' => $message ) );
}

/**
 * **NUEVA FUNCIÓN AJAX**: Actualizar estado de una cuenta
 */
public function ajax_update_account_status() {
    // Verificar nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wcps_update_account' ) ) {
        wp_send_json_error( array( 'message' => __( 'Error de seguridad', 'wc-credit-payment-system' ) ) );
    }
    
    // Verificar permisos
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Sin permisos suficientes', 'wc-credit-payment-system' ) ) );
    }
    
    $account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
    $new_status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
    
    if ( $account_id === 0 || empty( $new_status ) ) {
        wp_send_json_error( array( 'message' => __( 'Datos inválidos', 'wc-credit-payment-system' ) ) );
    }
    
    // Validar estado
    $valid_statuses = array( 'active', 'completed', 'defaulted' );
    if ( ! in_array( $new_status, $valid_statuses ) ) {
        wp_send_json_error( array( 'message' => __( 'Estado inválido', 'wc-credit-payment-system' ) ) );
    }
    
    global $wpdb;
    $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
    
    // Obtener información actual de la cuenta
    $account = $wpdb->get_row( $wpdb->prepare(
        "SELECT * FROM {$accounts_table} WHERE id = %d",
        $account_id
    ) );
    
    if ( ! $account ) {
        wp_send_json_error( array( 'message' => __( 'Cuenta no encontrada', 'wc-credit-payment-system' ) ) );
    }
    
    // Actualizar el estado
    $result = $wpdb->update(
        $accounts_table,
        array( 'status' => $new_status ),
        array( 'id' => $account_id )
    );
    
    if ( $result === false ) {
        wp_send_json_error( array( 'message' => __( 'Error al actualizar la cuenta', 'wc-credit-payment-system' ) ) );
    }
    
    // Si se marca como completada, marcar todas las cuotas pendientes como pagadas
    if ( $new_status === 'completed' ) {
        $this->complete_all_pending_installments( $account_id );
    }
    
    // Registrar el cambio en los comentarios
    $this->log_account_status_change( $account_id, $account->status, $new_status );
    
    // Enviar notificación al cliente
    $this->send_status_change_notification( $account_id, $new_status );
    
    $messages = array(
        'completed' => __( 'Cuenta marcada como completada', 'wc-credit-payment-system' ),
        'defaulted' => __( 'Cuenta marcada en mora', 'wc-credit-payment-system' ),
        'active' => __( 'Cuenta reactivada', 'wc-credit-payment-system' )
    );
    
    wp_send_json_success( array( 'message' => $messages[ $new_status ] ) );
}

/**
 * **NUEVA FUNCIÓN**: Manejar comentarios de administrador
 */
public function handle_add_admin_comment() {
    // Verificar nonce
    if ( ! isset( $_POST['wcps_comment_nonce'] ) || ! wp_verify_nonce( $_POST['wcps_comment_nonce'], 'wcps_admin_comment' ) ) {
        wp_die( __( 'Error de seguridad', 'wc-credit-payment-system' ) );
    }
    
    // Verificar permisos
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( __( 'Sin permisos suficientes', 'wc-credit-payment-system' ) );
    }
    
    $account_id = isset( $_POST['account_id'] ) ? absint( $_POST['account_id'] ) : 0;
    $comment = isset( $_POST['comment'] ) ? sanitize_textarea_field( $_POST['comment'] ) : '';
    
    if ( $account_id === 0 || empty( $comment ) ) {
        wp_redirect( add_query_arg( 'comment_error', 'empty', wp_get_referer() ) );
        exit;
    }
    
    global $wpdb;
    
    // Insertar el comentario
    $result = $wpdb->insert(
        $wpdb->prefix . 'wc_credit_comments',
        array(
            'credit_account_id' => $account_id,
            'user_id' => get_current_user_id(),
            'comment' => $comment
        )
    );
    
    if ( $result === false ) {
        wp_redirect( add_query_arg( 'comment_error', 'database', wp_get_referer() ) );
        exit;
    }
    
    wp_redirect( add_query_arg( 'comment_success', '1', wp_get_referer() ) );
    exit;
}

/**
 * **FUNCIÓN AUXILIAR**: Actualizar contador de cuotas pagadas
 */
private function update_account_paid_installments( $account_id ) {
    global $wpdb;
    
    $paid_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_credit_installments WHERE credit_account_id = %d AND status = 'paid'",
        $account_id
    ) );
    
    $wpdb->update(
        $wpdb->prefix . 'wc_credit_accounts',
        array( 'paid_installments' => $paid_count ),
        array( 'id' => $account_id )
    );
}

/**
 * **FUNCIÓN AUXILIAR**: Completar todas las cuotas pendientes
 */
private function complete_all_pending_installments( $account_id ) {
    global $wpdb;
    
    $wpdb->update(
        $wpdb->prefix . 'wc_credit_installments',
        array(
            'status' => 'paid',
            'paid_date' => current_time( 'Y-m-d' )
        ),
        array(
            'credit_account_id' => $account_id,
            'status' => 'pending'
        )
    );
    
    // Actualizar el contador
    $this->update_account_paid_installments( $account_id );
}

/**
 * **FUNCIÓN AUXILIAR**: Registrar cambio de estado de cuota
 */
private function log_installment_status_change( $account_id, $installment_number, $old_status, $new_status ) {
    global $wpdb;
    
    $user = wp_get_current_user();
    $comment = sprintf(
        __( 'Cuota #%d cambiada de "%s" a "%s" por %s', 'wc-credit-payment-system' ),
        $installment_number,
        $old_status,
        $new_status,
        $user->display_name
    );
    
    $wpdb->insert(
        $wpdb->prefix . 'wc_credit_comments',
        array(
            'credit_account_id' => $account_id,
            'user_id' => get_current_user_id(),
            'comment' => $comment
        )
    );
}

/**
 * **FUNCIÓN AUXILIAR**: Registrar cambio de estado de cuenta
 */
private function log_account_status_change( $account_id, $old_status, $new_status ) {
    global $wpdb;
    
    $user = wp_get_current_user();
    $comment = sprintf(
        __( 'Estado de cuenta cambiado de "%s" a "%s" por %s', 'wc-credit-payment-system' ),
        $old_status,
        $new_status,
        $user->display_name
    );
    
    $wpdb->insert(
        $wpdb->prefix . 'wc_credit_comments',
        array(
            'credit_account_id' => $account_id,
            'user_id' => get_current_user_id(),
            'comment' => $comment
        )
    );
}

/**
 * **FUNCIÓN AUXILIAR**: Enviar notificación por cambio de estado
 */
private function send_status_change_notification( $account_id, $new_status ) {
    try {
        $template_names = array(
            'completed' => 'Crédito Completado',
            'defaulted' => 'Cuenta en Mora',
            'active' => 'Cuenta Reactivada'
        );
        
        if ( isset( $template_names[ $new_status ] ) ) {
            $notifications = new WC_Credit_Notifications();
            $notifications->send_notification(
                $template_names[ $new_status ],
                'client',
                array( 'credit_id' => $account_id )
            );
        }
    } catch ( Exception $e ) {
        // Log del error pero no interrumpir el flujo
        error_log( 'Error enviando notificación de cambio de estado: ' . $e->getMessage() );
    }
}

/**
 * **NUEVA FUNCIÓN**: Generar reporte de cuenta para exportar
 */
public function generate_account_report( $account_id ) {
    // Esta función puede ser expandida para generar reportes PDF o CSV
    // Por ahora, retorna datos estructurados
    
    global $wpdb;
    
    $account = $wpdb->get_row( $wpdb->prepare(
        "SELECT ca.*, pl.name as plan_name, u.display_name as customer_name, p.post_title as product_name
         FROM {$wpdb->prefix}wc_credit_accounts ca
         LEFT JOIN {$wpdb->prefix}wc_credit_plans pl ON ca.plan_id = pl.id
         LEFT JOIN {$wpdb->prefix}users u ON ca.user_id = u.ID
         LEFT JOIN {$wpdb->prefix}posts p ON ca.product_id = p.ID
         WHERE ca.id = %d",
        $account_id
    ) );
    
    if ( ! $account ) {
        return false;
    }
    
    $installments = $wpdb->get_results( $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}wc_credit_installments WHERE credit_account_id = %d ORDER BY installment_number",
        $account_id
    ) );
    
    return array(
        'account' => $account,
        'installments' => $installments,
        'summary' => array(
            'total_amount' => $account->total_amount,
            'paid_amount' => $account->paid_installments * $account->installment_amount,
            'remaining_amount' => ($account->total_installments - $account->paid_installments) * $account->installment_amount,
            'progress_percentage' => ($account->paid_installments / $account->total_installments) * 100,
            'next_due_date' => $wpdb->get_var( $wpdb->prepare(
                "SELECT MIN(due_date) FROM {$wpdb->prefix}wc_credit_installments WHERE credit_account_id = %d AND status = 'pending'",
                $account_id
            ) )
        )
    );
}

/**
 * **NUEVA FUNCIÓN**: Obtener estadísticas del dashboard
 */
public function get_dashboard_stats() {
    global $wpdb;
    
    $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
    $installments_table = $wpdb->prefix . 'wc_credit_installments';
    
    // Estadísticas básicas
    $stats = array(
        'total_accounts' => $wpdb->get_var( "SELECT COUNT(*) FROM {$accounts_table}" ),
        'active_accounts' => $wpdb->get_var( "SELECT COUNT(*) FROM {$accounts_table} WHERE status = 'active'" ),
        'completed_accounts' => $wpdb->get_var( "SELECT COUNT(*) FROM {$accounts_table} WHERE status = 'completed'" ),
        'defaulted_accounts' => $wpdb->get_var( "SELECT COUNT(*) FROM {$accounts_table} WHERE status = 'defaulted'" ),
        'total_financed' => $wpdb->get_var( "SELECT SUM(financed_amount) FROM {$accounts_table}" ),
        'total_collected' => $wpdb->get_var( "SELECT SUM(amount) FROM {$installments_table} WHERE status = 'paid'" ),
        'pending_installments' => $wpdb->get_var( "SELECT COUNT(*) FROM {$installments_table} WHERE status = 'pending'" ),
        'overdue_installments' => $wpdb->get_var( "SELECT COUNT(*) FROM {$installments_table} WHERE status = 'overdue' OR (status = 'pending' AND due_date < CURDATE())" )
    );
    
    // Próximos vencimientos (siguientes 30 días)
    $upcoming_due = $wpdb->get_results( "
        SELECT i.*, ca.user_id, u.display_name as customer_name, p.post_title as product_name
        FROM {$installments_table} i
        JOIN {$accounts_table} ca ON i.credit_account_id = ca.id
        LEFT JOIN {$wpdb->prefix}users u ON ca.user_id = u.ID
        LEFT JOIN {$wpdb->prefix}posts p ON ca.product_id = p.ID
        WHERE i.status = 'pending' 
        AND i.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY i.due_date ASC
        LIMIT 10
    " );
    
    $stats['upcoming_due'] = $upcoming_due;
    
    return $stats;
}

/**
 * **NUEVA FUNCIÓN**: Procesar vencimientos automáticamente (para CRON)
 */
public function process_overdue_installments() {
    global $wpdb;
    
    // Marcar cuotas vencidas
    $result = $wpdb->query( "
        UPDATE {$wpdb->prefix}wc_credit_installments 
        SET status = 'overdue' 
        WHERE status = 'pending' 
        AND due_date < CURDATE()
    " );
    
    if ( $result > 0 ) {
        // Log del proceso
        error_log( "WCPS: Se marcaron {$result} cuotas como vencidas." );
        
        // Obtener cuentas afectadas para enviar notificaciones
        $overdue_accounts = $wpdb->get_results( "
            SELECT DISTINCT ca.id, ca.user_id 
            FROM {$wpdb->prefix}wc_credit_accounts ca
            JOIN {$wpdb->prefix}wc_credit_installments i ON ca.id = i.credit_account_id
            WHERE i.status = 'overdue'
            AND ca.status = 'active'
        " );
        
        // Enviar notificaciones de mora
        foreach ( $overdue_accounts as $account ) {
            try {
                $notifications = new WC_Credit_Notifications();
                $notifications->send_notification(
                    'Notificación de Mora',
                    'client',
                    array( 'credit_id' => $account->id )
                );
            } catch ( Exception $e ) {
                error_log( 'Error enviando notificación de mora para cuenta ' . $account->id . ': ' . $e->getMessage() );
            }
        }
    }
    
    return $result;
}

/**
 * **NUEVA FUNCIÓN**: Exportar datos de cuenta a CSV
 */
public function export_account_to_csv( $account_id ) {
    $report_data = $this->generate_account_report( $account_id );
    
    if ( ! $report_data ) {
        return false;
    }
    
    $account = $report_data['account'];
    $installments = $report_data['installments'];
    
    // Preparar contenido CSV
    $csv_content = array();
    
    // Encabezados de información de cuenta
    $csv_content[] = array( 'INFORMACIÓN DE CUENTA' );
    $csv_content[] = array( 'ID de Cuenta', $account->id );
    $csv_content[] = array( 'Cliente', $account->customer_name );
    $csv_content[] = array( 'Producto', $account->product_name );
    $csv_content[] = array( 'Plan', $account->plan_name );
    $csv_content[] = array( 'Total', wc_price( $account->total_amount ) );
    $csv_content[] = array( 'Estado', $account->status );
    $csv_content[] = array( '' ); // Línea vacía
    
    // Encabezados de cuotas
    $csv_content[] = array( 'CRONOGRAMA DE PAGOS' );
    $csv_content[] = array( 'Cuota #', 'Monto', 'Fecha Vencimiento', 'Fecha Pago', 'Estado' );
    
    // Datos de cuotas
    foreach ( $installments as $installment ) {
        $csv_content[] = array(
            $installment->installment_number,
            wc_price( $installment->amount ),
            date_i18n( get_option( 'date_format' ), strtotime( $installment->due_date ) ),
            $installment->paid_date ? date_i18n( get_option( 'date_format' ), strtotime( $installment->paid_date ) ) : 'Pendiente',
            ucfirst( $installment->status )
        );
    }
    
    // Generar nombre de archivo
    $filename = sprintf( 'cuenta_credito_%d_%s.csv', $account_id, date( 'Y-m-d' ) );
    
    // Configurar headers para descarga
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=' . $filename );
    header( 'Pragma: no-cache' );
    header( 'Expires: 0' );
    
    // Generar CSV
    $output = fopen( 'php://output', 'w' );
    
    // BOM para UTF-8
    fprintf( $output, chr(0xEF).chr(0xBB).chr(0xBF) );
    
    foreach ( $csv_content as $row ) {
        fputcsv( $output, $row, ';' );
    }
    
    fclose( $output );
    exit;
}

/**
 * **NUEVA FUNCIÓN**: Buscar cuentas con filtros avanzados
 */
public function advanced_search_accounts( $filters ) {
    global $wpdb;
    
    $accounts_table = $wpdb->prefix . 'wc_credit_accounts';
    $plans_table = $wpdb->prefix . 'wc_credit_plans';
    $users_table = $wpdb->prefix . 'users';
    $posts_table = $wpdb->prefix . 'posts';
    
    $where_conditions = array( '1=1' );
    $query_params = array();
    
    // Filtro por rango de fechas
    if ( ! empty( $filters['date_from'] ) ) {
        $where_conditions[] = 'ca.created_at >= %s';
        $query_params[] = $filters['date_from'] . ' 00:00:00';
    }
    
    if ( ! empty( $filters['date_to'] ) ) {
        $where_conditions[] = 'ca.created_at <= %s';
        $query_params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    // Filtro por monto
    if ( ! empty( $filters['amount_min'] ) ) {
        $where_conditions[] = 'ca.total_amount >= %f';
        $query_params[] = floatval( $filters['amount_min'] );
    }
    
    if ( ! empty( $filters['amount_max'] ) ) {
        $where_conditions[] = 'ca.total_amount <= %f';
        $query_params[] = floatval( $filters['amount_max'] );
    }
    
    // Filtro por estado
    if ( ! empty( $filters['status'] ) && $filters['status'] !== 'all' ) {
        $where_conditions[] = 'ca.status = %s';
        $query_params[] = $filters['status'];
    }
    
    // Filtro por plan
    if ( ! empty( $filters['plan_id'] ) ) {
        $where_conditions[] = 'ca.plan_id = %d';
        $query_params[] = absint( $filters['plan_id'] );
    }
    
    // Filtro por mora
    if ( ! empty( $filters['overdue_only'] ) ) {
        $where_conditions[] = 'EXISTS (SELECT 1 FROM ' . $wpdb->prefix . 'wc_credit_installments WHERE credit_account_id = ca.id AND status = "overdue")';
    }
    
    $where_clause = implode( ' AND ', $where_conditions );
    
    $query = "
        SELECT 
            ca.*,
            pl.name as plan_name,
            u.display_name as customer_name,
            u.user_email as customer_email,
            p.post_title as product_name,
            (SELECT COUNT(*) FROM {$wpdb->prefix}wc_credit_installments WHERE credit_account_id = ca.id AND status = 'overdue') as overdue_count
        FROM {$accounts_table} ca
        LEFT JOIN {$plans_table} pl ON ca.plan_id = pl.id
        LEFT JOIN {$users_table} u ON ca.user_id = u.ID
        LEFT JOIN {$posts_table} p ON ca.product_id = p.ID
        WHERE {$where_clause}
        ORDER BY ca.created_at DESC
        LIMIT 100
    ";
    
    if ( ! empty( $query_params ) ) {
        return $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ) );
    } else {
        return $wpdb->get_results( $query );
    }
}

/**
 * **NUEVA FUNCIÓN**: Validar integridad de datos
 */
public function validate_data_integrity() {
    global $wpdb;
    
    $issues = array();
    
    // Verificar cuentas sin cuotas
    $accounts_without_installments = $wpdb->get_var( "
        SELECT COUNT(*) 
        FROM {$wpdb->prefix}wc_credit_accounts ca
        LEFT JOIN {$wpdb->prefix}wc_credit_installments i ON ca.id = i.credit_account_id
        WHERE i.id IS NULL
    " );
    
    if ( $accounts_without_installments > 0 ) {
        $issues[] = sprintf( 
            __( '%d cuentas sin cuotas generadas', 'wc-credit-payment-system' ),
            $accounts_without_installments
        );
    }
    
    // Verificar inconsistencias en contadores
    $inconsistent_counters = $wpdb->get_results( "
        SELECT 
            ca.id,
            ca.paid_installments,
            COUNT(i.id) as actual_paid
        FROM {$wpdb->prefix}wc_credit_accounts ca
        LEFT JOIN {$wpdb->prefix}wc_credit_installments i ON ca.id = i.credit_account_id AND i.status = 'paid'
        GROUP BY ca.id
        HAVING ca.paid_installments != COUNT(i.id)
    " );
    
    if ( ! empty( $inconsistent_counters ) ) {
        $issues[] = sprintf(
            __( '%d cuentas con contadores de cuotas pagadas incorrectos', 'wc-credit-payment-system' ),
            count( $inconsistent_counters )
        );
    }
    
    // Verificar cuotas vencidas no marcadas
    $unmarked_overdue = $wpdb->get_var( "
        SELECT COUNT(*)
        FROM {$wpdb->prefix}wc_credit_installments
        WHERE status = 'pending'
        AND due_date < CURDATE()
    " );
    
    if ( $unmarked_overdue > 0 ) {
        $issues[] = sprintf(
            __( '%d cuotas vencidas no marcadas como morosas', 'wc-credit-payment-system' ),
            $unmarked_overdue
        );
    }
    
    return $issues;
}

/**
 * **NUEVA FUNCIÓN**: Reparar inconsistencias de datos
 */
public function repair_data_inconsistencies() {
    global $wpdb;
    
    $repairs = array();
    
    // Reparar contadores de cuotas pagadas
    $updated_counters = $wpdb->query( "
        UPDATE {$wpdb->prefix}wc_credit_accounts ca
        SET paid_installments = (
            SELECT COUNT(*)
            FROM {$wpdb->prefix}wc_credit_installments i
            WHERE i.credit_account_id = ca.id
            AND i.status = 'paid'
        )
    " );
    
    if ( $updated_counters > 0 ) {
        $repairs[] = sprintf(
            __( 'Se corrigieron %d contadores de cuotas pagadas', 'wc-credit-payment-system' ),
            $updated_counters
        );
    }
    
    // Marcar cuotas vencidas
    $marked_overdue = $wpdb->query( "
        UPDATE {$wpdb->prefix}wc_credit_installments
        SET status = 'overdue'
        WHERE status = 'pending'
        AND due_date < CURDATE()
    " );
    
    if ( $marked_overdue > 0 ) {
        $repairs[] = sprintf(
            __( 'Se marcaron %d cuotas como vencidas', 'wc-credit-payment-system' ),
            $marked_overdue
        );
    }
    
    return $repairs;
}

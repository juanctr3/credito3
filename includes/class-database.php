<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Credit_Database {

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table_prefix = $wpdb->prefix;
        
        $sql = "
        CREATE TABLE {$table_prefix}wc_credit_plans (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            down_payment_percentage DECIMAL(5,2) DEFAULT 0,
            interest_rate DECIMAL(5,2) DEFAULT 0,
            max_installments INT DEFAULT 12,
            payment_frequency ENUM('weekly', 'biweekly', 'monthly') DEFAULT 'monthly',
            notification_days_before INT DEFAULT 3,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;

        CREATE TABLE {$table_prefix}wc_credit_plan_assignments (
            id INT NOT NULL AUTO_INCREMENT,
            plan_id INT NOT NULL,
            assignment_type ENUM('product', 'category') NOT NULL,
            assignment_id INT NOT NULL,
            PRIMARY KEY  (id),
            KEY plan_id (plan_id)
        ) $charset_collate;

        CREATE TABLE {$table_prefix}wc_credit_accounts (
            id INT NOT NULL AUTO_INCREMENT,
            order_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            plan_id INT NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            down_payment DECIMAL(10,2) DEFAULT 0,
            financed_amount DECIMAL(10,2) NOT NULL,
            installment_amount DECIMAL(10,2) NOT NULL,
            total_installments INT NOT NULL,
            paid_installments INT DEFAULT 0,
            status ENUM('active', 'completed', 'defaulted') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY user_id (user_id)
        ) $charset_collate;

        CREATE TABLE {$table_prefix}wc_credit_installments (
            id INT NOT NULL AUTO_INCREMENT,
            credit_account_id INT NOT NULL,
            installment_number INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            due_date DATE NOT NULL,
            paid_date DATE NULL,
            status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            transaction_id VARCHAR(100) NULL,
            PRIMARY KEY  (id),
            KEY credit_account_id (credit_account_id)
        ) $charset_collate;

        CREATE TABLE {$table_prefix}wc_credit_comments (
            id INT NOT NULL AUTO_INCREMENT,
            credit_account_id INT NOT NULL,
            installment_id INT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY credit_account_id (credit_account_id)
        ) $charset_collate;

        CREATE TABLE {$table_prefix}wc_credit_templates (
            id INT NOT NULL AUTO_INCREMENT,
            template_type ENUM('email_client', 'email_admin', 'whatsapp_client', 'whatsapp_admin') NOT NULL,
            template_name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NULL,
            content TEXT NOT NULL,
            variables TEXT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            PRIMARY KEY  (id),
            KEY template_type (template_type)
        ) $charset_collate;
        ";

        dbDelta( $sql );

        self::insert_default_templates();
    }

    public static function insert_default_templates() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_credit_templates';

        $default_templates = [
            // Plantillas para el Cliente
            ['email_client', 'Recordatorio de Cuota', 'Recordatorio: Tu cuota está por vencer', 'Hola {cliente_nombre}, te recordamos que tu cuota nro. {cuota_numero} por un valor de {monto_cuota} para el producto {producto_nombre} vence el {fecha_vencimiento}.', '{cliente_nombre}, {producto_nombre}, {cuota_numero}, {fecha_vencimiento}, {monto_cuota}, {saldo_pendiente}'],
            ['whatsapp_client', 'Recordatorio de Cuota', null, 'Hola {cliente_nombre}, te recordamos que tu cuota nro. {cuota_numero} por {monto_cuota} para {producto_nombre} vence el {fecha_vencimiento}.', '{cliente_nombre}, {producto_nombre}, {cuota_numero}, {fecha_vencimiento}, {monto_cuota}, {saldo_pendiente}'],
            ['email_client', 'Confirmación de Pago de Cuota', 'Hemos recibido tu pago', 'Hola {cliente_nombre}, confirmamos que hemos recibido el pago de tu cuota nro. {cuota_numero} por un valor de {monto_cuota}. ¡Gracias!', '{cliente_nombre}, {cuota_numero}, {monto_cuota}'],
            // Plantillas para el Admin
            ['email_admin', 'Nuevo Comentario de Cliente', 'Nuevo comentario en crédito #{credito_id}', 'El cliente {cliente_nombre} ha dejado un nuevo comentario en el crédito #{credito_id}:\n\n"{comentario_cliente}"', '{cliente_nombre}, {credito_id}, {comentario_cliente}'],
            ['whatsapp_admin', 'Nuevo Comentario de Cliente', null, 'Nuevo comentario del cliente {cliente_nombre} en el crédito #{credito_id}: "{comentario_cliente}"', '{cliente_nombre}, {credito_id}, {comentario_cliente}'],
        ];

        foreach ( $default_templates as $template ) {
            // Verificar si la plantilla ya existe
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_name WHERE template_type = %s AND template_name = %s", $template[0], $template[1] ) );
            if ( ! $exists ) {
                $wpdb->insert( $table_name, [
                    'template_type' => $template[0],
                    'template_name' => $template[1],
                    'subject'       => $template[2],
                    'content'       => $template[3],
                    'variables'     => $template[4],
                    'is_active'     => 1
                ]);
            }
        }
    }
}


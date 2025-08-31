<div class="wrap">
    <h1 class="wp-heading-inline"><?php esc_html_e( 'Planes de Crédito', 'wc-credit-payment-system' ); ?></h1>
    <a href="<?php echo esc_url( admin_url('admin.php?page=wc-credit-plans&action=new') ); ?>" class="page-title-action"><?php esc_html_e( 'Añadir Nuevo Plan', 'wc-credit-payment-system' ); ?></a>

    <?php if ( isset( $_GET['success'] ) && $_GET['success'] == '1' ): ?>
    <div id="message" class="updated notice is-dismissible">
        <p><?php esc_html_e( 'Plan guardado correctamente.', 'wc-credit-payment-system' ); ?></p>
    </div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) && $_GET['deleted'] == '1' ): ?>
    <div id="message" class="updated notice is-dismissible">
        <p><?php esc_html_e( 'Plan eliminado correctamente.', 'wc-credit-payment-system' ); ?></p>
    </div>
    <?php endif; ?>

    <hr class="wp-header-end">

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column"><?php esc_html_e( 'Nombre del Plan', 'wc-credit-payment-system' ); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e( 'Interés (%)', 'wc-credit-payment-system' ); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e( 'Cuotas Máximas', 'wc-credit-payment-system' ); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e( 'Frecuencia', 'wc-credit-payment-system' ); ?></th>
                <th scope="col" class="manage-column"><?php esc_html_e( 'Estado', 'wc-credit-payment-system' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php
            // Incluimos la clase si no está disponible, para asegurar que la función exista.
            if ( ! class_exists('WC_Credit_Payment_Plans') ) {
                require_once WCPS_PLUGIN_DIR . 'includes/class-payment-plans.php';
            }
            $plans = WC_Credit_Payment_Plans::get_all_plans();
            if ( $plans ) {
                foreach ( $plans as $plan ) {
                    $delete_nonce = wp_create_nonce( 'wcps_delete_plan_' . $plan->id );
                    $delete_url = admin_url( 'admin-post.php?action=delete_wc_credit_plan&plan_id=' . $plan->id . '&_wpnonce=' . $delete_nonce );
                    ?>
                    <tr>
                        <td>
                            <strong><a href="<?php echo esc_url( admin_url('admin.php?page=wc-credit-plans&action=edit&plan_id=' . $plan->id) ); ?>"><?php echo esc_html($plan->name); ?></a></strong>
                            <div class="row-actions">
                                <span class="edit"><a href="<?php echo esc_url( admin_url('admin.php?page=wc-credit-plans&action=edit&plan_id=' . $plan->id) ); ?>"><?php esc_html_e('Editar', 'wc-credit-payment-system'); ?></a> | </span>
                                <span class="trash"><a href="<?php echo esc_url($delete_url); ?>" class="submitdelete" onclick="return confirm('<?php esc_attr_e('¿Estás seguro de que quieres eliminar este plan? Esta acción no se puede deshacer.', 'wc-credit-payment-system'); ?>');"><?php esc_html_e('Eliminar', 'wc-credit-payment-system'); ?></a></span>
                            </div>
                        </td>
                        <td><?php echo esc_html($plan->interest_rate); ?>%</td>
                        <td><?php echo esc_html($plan->max_installments); ?></td>
                        <td><?php echo esc_html( ucfirst($plan->payment_frequency) ); ?></td>
                        <td>
                            <span class="dashicons <?php echo $plan->status === 'active' ? 'dashicons-yes-alt' : 'dashicons-no-alt'; ?>"></span>
                            <?php echo $plan->status === 'active' ? esc_html__( 'Activo', 'wc-credit-payment-system' ) : esc_html__( 'Inactivo', 'wc-credit-payment-system' ); ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No se encontraron planes de crédito.', 'wc-credit-payment-system' ); ?></td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>


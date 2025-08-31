<?php
/**
 * Plugin Name:       WooCommerce Credit Payment System
 * Plugin URI:        https://example.com/
 * Description:       Un sistema de crédito completo para WooCommerce que permite la venta de productos mediante planes de pago personalizables.
 * Version:           1.0.1
 * Author:            Your Name
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wc-credit-payment-system
 * Domain Path:       /languages
 * WC requires at least: 4.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Definir constantes del plugin para un acceso fácil y seguro a rutas y versiones.
define( 'WCPS_VERSION', '1.0.1' );
define( 'WCPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCPS_PLUGIN_FILE', __FILE__ );


// **NUEVO CÓDIGO AÑADIDO PARA COMPATIBILIDAD CON HPOS**
// Esto le dice a WooCommerce que el plugin es compatible con el nuevo sistema de almacenamiento de pedidos.
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );


/**
 * Clase principal del Plugin
 */
final class WC_Credit_Payment_System {

    private static $_instance = null;

    /**
     * Asegura que solo una instancia de la clase sea creada (Singleton Pattern).
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor.
     * Registra los hooks principales del plugin.
     */
    private function __construct() {
        register_activation_hook( WCPS_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( WCPS_PLUGIN_FILE, array( $this, 'deactivate' ) );
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
    }

    /**
     * Hook de activación. Se ejecuta una sola vez cuando el plugin es activado.
     * Crea las tablas y programa las tareas cron.
     */
    public function activate() {
        require_once WCPS_PLUGIN_DIR . 'includes/class-database.php';
        WC_Credit_Database::create_tables();

        // Programar el evento cron diario para verificar vencimientos.
        if ( ! wp_next_scheduled( 'wc_credit_check_due_installments' ) ) {
            wp_schedule_event( time(), 'daily', 'wc_credit_check_due_installments' );
        }
    }

    /**
     * Hook de desactivación. Limpia las tareas programadas.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'wc_credit_check_due_installments' );
    }

    /**
     * Inicializa el plugin una vez que todos los plugins están cargados.
     */
    public function init() {
        // Verificar si WooCommerce está activo. Si no, no cargar el plugin.
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }

        // Cargar todos los archivos de clases necesarios.
        $this->includes();

        // Inicializar clases principales según el contexto (admin o frontend).
        if ( is_admin() ) {
            new WC_Credit_Admin();
        }
        
        if ( ! is_admin() || wp_doing_ajax() ) {
            new WC_Credit_Frontend();
        }
        
        new WC_Credit_Notifications();
        new WC_Credit_Cron();
    }
    
    /**
     * Incluye los archivos PHP necesarios para el funcionamiento del plugin.
     */
    public function includes() {
        require_once WCPS_PLUGIN_DIR . 'includes/class-database.php';
        require_once WCPS_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WCPS_PLUGIN_DIR . 'includes/class-payment-plans.php';
        require_once WCPS_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once WCPS_PLUGIN_DIR . 'includes/class-notifications.php';
        require_once WCPS_PLUGIN_DIR . 'includes/class-whatsapp-api.php';
        require_once WCPS_PLUGIN_DIR . 'includes/class-cron.php';
    }
    
    /**
     * Carga los scripts y estilos para el frontend.
     */
    public function enqueue_scripts() {
        // Cargar los assets solo en la página de producto para optimizar el rendimiento.
        if ( is_product() ) {
            wp_enqueue_style( 'wcps-style', WCPS_PLUGIN_URL . 'assets/css/frontend.css', array(), WCPS_VERSION );
            wp_enqueue_script( 'wcps-script', WCPS_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), WCPS_VERSION, true );
            
            // Pasar datos de PHP a JavaScript de forma segura.
            wp_localize_script( 'wcps-script', 'wcps_data', array(
                'ajax_url' => admin_url( 'admin-ajax.php' )
            ));
        }
    }

    /**
     * Carga los scripts y estilos para el backend (área de administración).
     */
    public function admin_enqueue_scripts( $hook ) {
        // Cargar solo en nuestras páginas de administración.
        if ( 'woocommerce_page_wc-credit-plans' !== $hook && 'woocommerce_page_wc-credit-settings' !== $hook) {
            return;
        }
        // Habilitar los estilos y scripts de WooCommerce para usar selectores mejorados y búsqueda de productos.
        wp_enqueue_style( 'woocommerce_admin_styles' );
        wp_enqueue_script( 'wc-enhanced-select' );
        wp_enqueue_script( 'wc-product-search' );
    }

    /**
     * Muestra una notificación de error en el admin si WooCommerce no está activo.
     */
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p>';
        /* translators: %s: WooCommerce */
        printf( esc_html__( 'WooCommerce Credit Payment System requiere que %s esté instalado y activo.', 'wc-credit-payment-system' ), '<strong>WooCommerce</strong>' );
        echo '</p></div>';
    }
}

/**
 * Función global para acceder a la instancia principal del plugin.
 * @return WC_Credit_Payment_System
 */
function WC_Credit_Payment_System_init() {
    return WC_Credit_Payment_System::instance();
}

// Iniciar el plugin.
WC_Credit_Payment_System_init();
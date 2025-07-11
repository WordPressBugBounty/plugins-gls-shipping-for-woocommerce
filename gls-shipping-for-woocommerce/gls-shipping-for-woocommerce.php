<?php

/**
 * Plugin Name: GLS Shipping for WooCommerce
 * Description: Offical GLS Shipping for WooCommerce plugin
 * Version: 1.2.5
 * Author: Inchoo
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Author URI: https://inchoo.hr
 * Text Domain: gls-shipping-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.9
 * Tested up to: 6.7
 * Requires PHP: 7.1
 *
 * WC requires at least: 4.3
 * WC tested up to: 9.1
 */

defined('ABSPATH') || exit;

final class GLS_Shipping_For_Woo
{
    private static $instance;

    private $version = '1.2.5';

    private function __construct()
    {
        $this->define_constants();
        spl_autoload_register(array($this, 'autoloader'));

        $this->includes();
        $this->init_hooks();
    }

    private function includes()
    {
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-assets.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-checkout.php');
        require_once(GLS_SHIPPING_ABSPATH . 'includes/public/class-gls-shipping-my-account.php');

        if (is_admin()) {
            require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-order.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/admin/class-gls-shipping-bulk.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/api/class-gls-shipping-api-data.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/api/class-gls-shipping-api-service.php');
        }

        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-zones.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-shop.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-shop-zones.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-locker.php');
            require_once(GLS_SHIPPING_ABSPATH . 'includes/methods/class-gls-shipping-method-parcel-locker-zones.php');
        }
    }
    /**
     * Define RAF Constants.
     * @since 1.0.0
     */
    private function define_constants()
    {
        $this->define('GLS_SHIPPING_URL', plugin_dir_url(__FILE__));
        $this->define('GLS_SHIPPING_ABSPATH', dirname(__FILE__) . '/');
        $this->define('GLS_SHIPPING_VERSION', $this->get_version());

        $this->define('GLS_SHIPPING_METHOD_ID', 'gls_shipping_method');
        $this->define('GLS_SHIPPING_METHOD_ZONES_ID', 'gls_shipping_method_zones');
        $this->define('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID', 'gls_shipping_method_parcel_locker');
        $this->define('GLS_SHIPPING_METHOD_PARCEL_SHOP_ID', 'gls_shipping_method_parcel_shop');

        $this->define('GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID', 'gls_shipping_method_parcel_locker_zones');
        $this->define('GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID', 'gls_shipping_method_parcel_shop_zones');
    }

    /**
     * Returns Plugin version for global
     * @since  1.0.0
     */
    private function get_version()
    {
        return $this->version;
    }

    private function autoloader($class)
    {
        $class = strtolower($class);
        // Remove prefix
        $class = str_replace('gls_croatia\\', '', $class);

        if (false === strpos($class, 'gls_croatia')) {
            return;
        }

        $class = str_replace('_', '-', $class);
        $class = str_replace('\\', '/', $class);

        $class_parts = explode('/', $class);
        $class_name = end($class_parts);

        $file = GLS_SHIPPING_ABSPATH . str_replace($class_name, 'class-' . $class_name, $class) . '.php';

        // Check if the file exists and require it if found.
        if (file_exists($file)) {
            require $file;
        }
    }

    private function init_hooks()
    {
        add_filter('woocommerce_shipping_methods', array($this, 'add_gls_shipping_methods'));
        add_action('init', array($this, 'load_textdomain'));
    }


    public function load_textdomain()
    {
        load_plugin_textdomain('gls-shipping-for-woocommerce', false, basename(dirname(__FILE__)) . '/languages/');
    }

    public function add_gls_shipping_methods($methods)
    {
        $methods[GLS_SHIPPING_METHOD_ID] = 'GLS_Shipping_Method';
        $methods[GLS_SHIPPING_METHOD_ZONES_ID] = 'GLS_Shipping_Method_Zones';
        $methods[GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID] = 'GLS_Shipping_Method_Parcel_Shop';
        $methods[GLS_SHIPPING_METHOD_PARCEL_SHOP_ID] = 'GLS_Shipping_Method_Parcel_Locker';
        $methods[GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID] = 'GLS_Shipping_Method_Parcel_Locker_Zones';
        $methods[GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID] = 'GLS_Shipping_Method_Parcel_Shop_Zones';

        return $methods;
    }

    /**
     * Define constant if not already set.
     *
     * @since  1.0.0
     * @param  string $name
     * @param  string|bool $value
     */
    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Declare HPOS Compatibility
add_action(
    'before_woocommerce_init',
    function () {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
);

GLS_Shipping_For_Woo::get_instance();

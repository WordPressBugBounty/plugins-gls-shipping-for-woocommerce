<?php

/**
 * Handle frontend scripts for GLS Shipping Checkout.
 *
 * This class handles adding a GLS button to the shipping method selection
 * and saving GLS Parcel Shop info as order meta data.
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class GLS_Shipping_Checkout
 *
 * Handles the actions and filters related to the GLS shipping method during WooCommerce checkout.
 */
class GLS_Shipping_Checkout
{
    /**
     * Array of allowed GLS shipping methods.
     *
     * @var array
     */
    protected $map_selection_methods;

    /**
     * Constructor for the GLS_Shipping_Checkout class.
     *
     * Sets up hooks for modifying shipping method labels, saving order meta, and displaying pickup information.
     */
    public function __construct()
    {
        $this->map_selection_methods = array(
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ID,
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID
        );

        add_filter('woocommerce_cart_shipping_method_full_label', array($this, 'add_gls_button_to_shipping_method'), 10, 2);
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_gls_parcel_shop_info'));
        add_action('woocommerce_review_order_after_shipping', array($this, 'display_pickup_information'));
        add_action('woocommerce_checkout_process', array($this, 'validate_gls_parcel_shop_selection'));
    }


    /**
     * Validates that Parcel is selected
     *
     * Makes sure taht parcel or shop is selected on the map, if one of these shipping methods is selected.
     */
    public function validate_gls_parcel_shop_selection()
    {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        if (!is_array($chosen_shipping_methods)) {
            $chosen_shipping_methods = [];
        }
        
        // Check if GLS shipping method is selected
        if (array_intersect($this->map_selection_methods, $chosen_shipping_methods)) {
            // Check if the required GLS info is set and not empty
            if (empty($_POST['gls_pickup_info'])) {
                wc_add_notice(__('Please select a parcel locker/shop by clicking on Select Parcel button.', 'gls-shipping-for-woocommerce'), 'error');
            }
        }
    }

    /**
     * Display pickup information.
     *
     * Outputs a hidden div for displaying pickup information once a GLS shipping method is selected.
     */
    public function display_pickup_information()
    {
        echo '<div id="gls-pickup-info" style="display:none;border: 1px solid #ddd;padding: 20px;margin-bottom: 24px;"></div>';
    }

    /**
     * Add a GLS button to the shipping method label.
     *
     * Appends a button to select GLS Parcel after the shipping method label if a GLS shipping method is chosen.
     *
     * @param string $label The shipping method label.
     * @param WC_Shipping_Rate $method The shipping method object.
     * @return string Modified label with or without the GLS button.
     */
    public function add_gls_button_to_shipping_method($label, $method)
    {
        if (is_cart()) {
            return $label;
        }

        $chosen_methods = WC()->session->get('chosen_shipping_methods');

        if (in_array($method->id, $this->map_selection_methods) && is_array($chosen_methods) && in_array($method->id, $chosen_methods)) {
            if ($method->id === GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID || $method->id === GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID) {
                $label .= '<br/><button type="button" id="gls-map-button" class="dugme-gls_shipping_method_parcel_locker">' . __('Select Parcel Locker', 'gls-shipping-for-woocommerce') . '</button>';
            } elseif ($method->id === GLS_SHIPPING_METHOD_PARCEL_SHOP_ID || $method->id === GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID) {
                $label .= '<br/><button type="button" id="gls-map-button" class="dugme-gls_shipping_method_parcel_shop">' . __('Select Parcel Shop', 'gls-shipping-for-woocommerce') . '</button>';
            }
        }

        return $label;
    }

    /**
     * Save GLS Parcel Shop info as order meta.
     *
     * When an order is placed with a GLS shipping method, saves the GLS Parcel Shop information to order meta.
     *
     * @param int $order_id The ID of the order being processed.
     */
    public function save_gls_parcel_shop_info($order_id)
    {
        $order = wc_get_order($order_id);
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            if (in_array($shipping_method->get_method_id(), $this->map_selection_methods)) {
                if (!empty($_POST['gls_pickup_info'])) {
                    $gls_pickup_info = sanitize_text_field(stripslashes($_POST['gls_pickup_info']));
                    $order->update_meta_data('_gls_pickup_info', $gls_pickup_info);
                    $order->save();
                }
                break;
            }
        }
    }
}

new GLS_Shipping_Checkout();

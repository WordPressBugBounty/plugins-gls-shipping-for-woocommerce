<?php

/**
 * Handles showing of order Information
 *
 * @since     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Order
{

    public function __construct()
    {
        add_action('add_meta_boxes', array($this, 'add_gls_shipping_info_meta_box'));
        add_action('wp_ajax_gls_generate_label', array($this, 'generate_label_and_tracking_number'));
    }

    public function add_gls_shipping_info_meta_box()
    {
        $screen = 'shop_order';

        if (class_exists('Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $screen = wc_get_container()->get(Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled() ? wc_get_page_screen_id('shop-order') : 'shop_order';
        }

        add_meta_box(
            'gls_shipping_info_meta_box',
            esc_html__('GLS Shipping Info', 'gls-shipping-for-woocommerce'),
            array($this, 'gls_shipping_info_meta_box_content'),
            $screen,
            'side',
            'default'
        );
    }

    private function display_gls_pickup_info($order_id)
    {
        $order = wc_get_order($order_id);
    
        $gls_pickup_info = $order->get_meta('_gls_pickup_info', true);
        $tracking_codes  = $order->get_meta('_gls_tracking_codes', true);
        
        // Legacy support, should be removed later on.
        $tracking_code  = $order->get_meta('_gls_tracking_code', true);
    
        if (!empty($gls_pickup_info)) {
            $pickup_info = json_decode($gls_pickup_info);
    
            echo '<strong>' . esc_html__('GLS Pickup Location:', 'gls-shipping-for-woocommerce') . '</strong><br/>';
            echo '<strong>' . esc_html__('ID:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->id) . '<br>';
            echo '<strong>' . esc_html__('Name:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->name) . '<br>';
            echo '<strong>' . esc_html__('Address:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->address) . ', ' . esc_html($pickup_info->contact->city) . ', ' . esc_html($pickup_info->contact->postalCode) . '<br>';
            echo '<strong>' . esc_html__('Country:', 'gls-shipping-for-woocommerce') . '</strong> ' . esc_html($pickup_info->contact->countryCode) . '<br>';
        }
    
        if (!empty($tracking_codes) && is_array($tracking_codes)) {
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            echo '<br/><strong>' . esc_html__('GLS Tracking Numbers:', 'gls-shipping-for-woocommerce') . '</strong><br>';
            foreach ($tracking_codes as $tracking_code) {
                $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;
                echo '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a><br>';
            }
        } else if (!empty($tracking_code)) {
            // Legacy support, should be removed later on.
            $gls_shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");
            $tracking_url = "https://gls-group.eu/" . $gls_shipping_method_settings['country'] . "/en/parcel-tracking/?match=" . $tracking_code;
            echo '<br/><strong>' . esc_html__('GLS Tracking Number: ', 'gls-shipping-for-woocommerce') . '<a href="' . esc_url($tracking_url) . '" target="_blank">' . esc_html($tracking_code) . '</a></strong><br>';
        }
    }
    

    public function gls_shipping_info_meta_box_content($order_or_post_id)
    {

        $order = ($order_or_post_id instanceof WP_Post)
            ? wc_get_order($order_or_post_id->ID)
            : $order_or_post_id;

        $gls_print_label = $order->get_meta('_gls_print_label', true);

        $this->display_gls_pickup_info($order->get_id(), false);
?>
        <h4 style="margin-bottom:0px;">
            <div style="margin-top:10px;">
                <?php if ($gls_print_label) { ?>
                    <a class="button primary" href="<?php echo esc_url($gls_print_label); ?>" target="_blank"><?php esc_html_e("Print Label", "gls-shipping-for-woocommerce"); ?></a>
                    <div style="margin-top:10px;display: flex; flex-direction: column;">
                        <div style="margin-bottom: 10px;">
                            <?php esc_html_e("Number of Packages:", "gls-shipping-for-woocommerce"); ?>
                            <input type="number" id="gls_label_count" name="gls_label_count" min="1" value="1" style="width: 50px; margin-right: 10px;">
                        </div>
                        <button type="button" class="button gls-print-label" order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php esc_html_e("Regenerate Shipping Label", "gls-shipping-for-woocommerce"); ?>
                        </button>
                    </div>
                <?php } else { ?>
                    <div style="margin-top:10px;display: flex; flex-direction: column;">
                        <div style="margin-bottom: 10px;">
                            <?php esc_html_e("Number of Packages:", "gls-shipping-for-woocommerce"); ?>
                            <input type="number" id="gls_label_count" name="gls_label_count" min="1" value="1" style="width: 50px; margin-right: 10px;">
                        </div>
                        <button type="button" class="button gls-print-label" order-id="<?php echo esc_attr($order->get_id()); ?>">
                            <?php esc_html_e("Generate Shipping Label", "gls-shipping-for-woocommerce"); ?>
                        </button>
                    </div>
                <?php } ?>
            </div>
            <div id="gls-info"></div>
        </h4>
<?php
    }

    public function generate_label_and_tracking_number()
    {
        if (!wp_verify_nonce(sanitize_text_field($_POST['postNonce']), 'import-nonce')) {
            die('Busted!');
        }

        $order_id = sanitize_text_field($_POST['orderId']);

        $count = intval($_POST['count']) ?: 1;
        
        try {
            $prepare_data = new GLS_Shipping_API_Data($order_id);
            $data = $prepare_data->generate_post_fields($count);
    
            $api = new GLS_Shipping_API_Service();
            $result = $api->send_order($data);
            $this->save_label_and_tracking_info($result['body'], $order_id);
    
            wp_send_json_success(array('success' => true));
        } catch (Exception $e) {
            error_log($e->getMessage());
            wp_send_json_error(array('success' => false, 'error' => $e->getMessage()));
            return;
        }
    }

    public function save_label_and_tracking_info($body, $order_id)
    {
        $order = wc_get_order($order_id);
        if (!empty($body['Labels'])) {
            $this->save_print_labels($body['Labels'], $order_id, $order);
        }

        if (!empty($body['PrintLabelsInfoList'])) {
            $this->save_tracking_info($body['PrintLabelsInfoList'], $order_id, $order);
        }
    }

    public function save_print_labels($labels, $order_id, $order)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    
        WP_Filesystem();
        global $wp_filesystem;
    
        $label_print = implode(array_map('chr', $labels));
        $upload_dir = wp_upload_dir();
        
        $timestamp = current_time('YmdHis');
        $file_name = 'shipping_label_' . $order_id . '_' . $timestamp . '.pdf';
        
        $file_url = $upload_dir['url'] . '/' . $file_name;
        $file_path = $upload_dir['path'] . '/' . $file_name;

        if ($wp_filesystem->put_contents($file_path, $label_print)) {
            $order->update_meta_data('_gls_print_label', $file_url);
            $order->save();
        }
    }
    

    public function save_tracking_info($printLabelsInfoList, $order_id, $order)
    {
        $tracking_codes = array();
        $parcel_ids = array();
    
        foreach ($printLabelsInfoList as $labelInfo) {
            if (isset($labelInfo['ParcelNumber'])) {
                $tracking_codes[] = $labelInfo['ParcelNumber'];
            }
            if (isset($labelInfo['ParcelId'])) {
                $parcel_ids[] = $labelInfo['ParcelId'];
            }
        }
    
        if (!empty($tracking_codes)) {
            $order->update_meta_data('_gls_tracking_codes', $tracking_codes);
        }
    
        if (!empty($parcel_ids)) {
            $order->update_meta_data('_gls_parcel_ids', $parcel_ids);
        }
    
        $order->save();
    }
}

new GLS_Shipping_Order();

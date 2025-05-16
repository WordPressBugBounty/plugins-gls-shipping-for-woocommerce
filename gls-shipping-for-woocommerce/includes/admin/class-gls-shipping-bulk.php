<?php

/**
 * Handles Bulk Orders
 *
 * @since     1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class GLS_Shipping_Bulk
{
    private $order_handler;

    public function __construct()
    {
        // Initialize order handler
        $this->order_handler = new GLS_Shipping_Order();

        // Add bulk actions for GLS label generation
        add_filter('bulk_actions-edit-shop_order', array($this, 'register_gls_bulk_actions'));
        add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'register_gls_bulk_actions'));

        // Handle bulk action for GLS label generation
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'process_bulk_gls_label_generation'), 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'process_bulk_gls_label_generation'), 10, 3);

        // Display admin notice after bulk action
        add_action('admin_notices', array($this, 'gls_bulk_action_admin_notice'));

        // Add GLS order actions
        add_filter('woocommerce_admin_order_actions', array($this, 'add_gls_order_actions'), 10, 2);

        // Enqueue admin styles
        add_action('admin_print_styles', array($this, 'admin_enqueue_styles'));
    }

    // Add GLS-specific order actions
    public function add_gls_order_actions($actions, $order) {
        $order_id = $order->get_id();
        $gls_print_label = $order->get_meta('_gls_print_label', true);
    
        if ($gls_print_label) {
            // Action to download existing GLS label
            $actions['gls_download_label'] = array(
                'url'    => $gls_print_label,
                'target' => '_blank',
                'name'   => __('Download GLS Label', 'gls-shipping-for-woocommerce'),
                'action' => 'gls-download-label',
            );
        } else {
            // Action to generate new GLS label
            $actions['gls_generate_label'] = array(
                'url'    => '#',
                'name'   => __('Generate GLS Label', 'gls-shipping-for-woocommerce'),
                'action' => 'gls-generate-label',
            );
        }
    
        return $actions;
    }

    // Register GLS bulk action
    public function register_gls_bulk_actions($bulk_actions) {
        $bulk_actions['generate_gls_labels'] = __('Bulk Generate GLS Labels', 'gls-shipping-for-woocommerce');
        $bulk_actions['print_gls_labels'] = __('Bulk Print GLS Labels', 'gls-shipping-for-woocommerce');
        return $bulk_actions;
    }
    
    // Process bulk GLS label generation
    public function process_bulk_gls_label_generation($redirect, $doaction, $order_ids)
    {
        if ('generate_gls_labels' === $doaction) {
            $processed = 0;
            $failed_orders = array();
            foreach ($order_ids as $order_id) {
                try {
                    // Prepare data for API request
                    $count = 1;
                    $prepare_data = new GLS_Shipping_API_Data($order_id);
                    $data = $prepare_data->generate_post_fields($count);

                    // Send order to GLS API
                    $api = new GLS_Shipping_API_Service();
                    $body = $api->send_order($data);

                    // Save label and tracking information
                    $this->order_handler->save_label_and_tracking_info($body['body'], $order_id);

                    $processed++;
                } catch (Exception $e) {
                    // Log any errors and add to failed orders
                    error_log("Failed to generate GLS label for order $order_id: " . $e->getMessage());
                    $failed_orders[] = $order_id;
                }
            }
    
            // Add query args to URL for displaying notices
            $redirect = add_query_arg(
                array(
                    'bulk_action' => 'generate_gls_labels',
                    'gls_labels_generated' => $processed,
                    'gls_labels_failed' => count($failed_orders),
                    'failed_orders' => implode(',', $failed_orders),
                    'changed' => count($order_ids),
                ),
                $redirect
            );
        }
        // Bulk print labels, dont generate for each but just print in single PDF
        if ('print_gls_labels' === $doaction) {
            $prepare_data = new GLS_Shipping_API_Data($order_ids);
            $data = $prepare_data->generate_post_fields_multi();
    
            // Send order to GLS API
            $is_multi = true;
            $api = new GLS_Shipping_API_Service();
            $result = $api->send_order($data, $is_multi);

            $body = $result['body'];
            $failed_orders = $result['failed_orders'];
    
            $pdf_url = $this->bulk_create_print_labels($body);
    
            if ($pdf_url) {
                // Add query args to URL for displaying notices and providing PDF link
                $redirect = add_query_arg(
                    array(
                        'bulk_action' => 'print_gls_labels',
                        'gls_labels_printed' => count($order_ids) - count($failed_orders),
                        'gls_labels_failed' => count($failed_orders),
                        'gls_pdf_url' => urlencode($pdf_url),
                        'failed_orders' => implode(',', array_column($failed_orders, 'order_id')),
                    ),
                    $redirect
                );
            } else {
                // Handle error case
                $redirect = add_query_arg(
                    array(
                        'bulk_action' => 'print_gls_labels',
                        'gls_labels_printed_error' => 'true',
                    ),
                    $redirect
                );
            }
        }
    
        return $redirect;
    }

    public function bulk_create_print_labels($body)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    
        WP_Filesystem();
        global $wp_filesystem;
    
        $label_print = implode(array_map('chr', $body['Labels']));
        $upload_dir = wp_upload_dir();
        $file_name = 'shipping_label_bulk_' . time() . '.pdf';
        $file_url = $upload_dir['url'] . '/' . $file_name;
        $file_path = $upload_dir['path'] . '/' . $file_name;
        
        if ($wp_filesystem->put_contents($file_path, $label_print)) {
            return $file_url;
        }
        return false;
    }

    // Display admin notice after bulk action
    public function gls_bulk_action_admin_notice() {
        if (isset($_REQUEST['bulk_action'])) {
            if ('generate_gls_labels' == $_REQUEST['bulk_action']) {
                $generated = intval($_REQUEST['gls_labels_generated']);
                $failed = intval($_REQUEST['gls_labels_failed']);
                $failed_orders = isset($_REQUEST['failed_orders']) ? explode(',', $_REQUEST['failed_orders']) : [];

                // Prepare success message
                $message = sprintf(
                    _n(
                        '%s GLS label was successfully generated.',
                        '%s GLS labels were successfully generated.',
                        $generated,
                        'gls-shipping-for-woocommerce'
                    ),
                    number_format_i18n($generated)
                );
                
                // Add failure message if any labels failed to generate
                if ($failed > 0) {
                    $message .= ' ' . sprintf(
                        _n(
                            '%s label failed to generate.',
                            '%s labels failed to generate.',
                            $failed,
                            'gls-shipping-for-woocommerce'
                        ),
                        number_format_i18n($failed)
                    );
                    $message .= ' ' . sprintf(
                        __('Failed order IDs: %s', 'gls-shipping-for-woocommerce'),
                        implode(', ', $failed_orders)
                    );
                }

                // Display the notice
                printf('<div id="message" class="updated notice is-dismissible"><p>' . $message . '</p></div>');
            } elseif ('print_gls_labels' == $_REQUEST['bulk_action']) {
                if (isset($_REQUEST['gls_labels_printed']) && isset($_REQUEST['gls_pdf_url'])) {
                    $printed = intval($_REQUEST['gls_labels_printed']);
                    $failed = intval($_REQUEST['gls_labels_failed']);
                    $pdf_url = urldecode($_REQUEST['gls_pdf_url']);
                    $failed_orders = isset($_REQUEST['failed_orders']) ? explode(',', $_REQUEST['failed_orders']) : [];
                    
                    // Prepare success message
                    $message = sprintf(
                        _n(
                            'GLS label for %s order has been generated. ',
                            'GLS labels for %s orders have been generated. ',
                            $printed,
                            'gls-shipping-for-woocommerce'
                        ),
                        number_format_i18n($printed)
                    );

                    // Add failure message if any labels failed to generate
                    if ($failed > 0) {
                        $message .= sprintf(
                            _n(
                                '%s label failed to generate. ',
                                '%s labels failed to generate. ',
                                $failed,
                                'gls-shipping-for-woocommerce'
                            ),
                            number_format_i18n($failed)
                        );
                        $message .= sprintf(
                            __('Failed order IDs: %s', 'gls-shipping-for-woocommerce'),
                            implode(', ', $failed_orders)
                        );
                    }
                    
                    $message .= sprintf(
                        __('<br><a href="%s" target="_blank">Click here to download the PDF</a>', 'gls-shipping-for-woocommerce'),
                        esc_url($pdf_url)
                    );

                    // Display the notice
                    printf('<div id="message" class="updated notice is-dismissible"><p>' . $message . '</p></div>');
                } elseif (isset($_REQUEST['gls_labels_printed_error'])) {
                    $message = __('An error occurred while generating the GLS labels PDF.', 'gls-shipping-for-woocommerce');
                    printf('<div id="message" class="error notice is-dismissible"><p>' . $message . '</p></div>');
                }
            }
        }
    }

    // Enqueue bulk styles
    public function admin_enqueue_styles()
    {
        $currentScreen = get_current_screen();
        $screenID = $currentScreen->id;
        if ($screenID === "shop_order" || $screenID === "woocommerce_page_wc-orders" || $screenID === "edit-shop_order") {
             // Add inline CSS for GLS buttons
             $custom_css = "
                a.button.gls-download-label::after {
                    content: '\\f316';
                }
                a.button.gls-generate-label::after {
                    content: '\\f502';
                }
            ";
            wp_add_inline_style('woocommerce_admin_styles', $custom_css);
        }
    }
}

new GLS_Shipping_Bulk();

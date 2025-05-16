<?php

function gls_shipping_method_init()
{
	if (!class_exists('GLS_Shipping_Method')) {
		class GLS_Shipping_Method extends WC_Shipping_Method
		{

			/**
			 * Constructor for the GLS Shipping Method.
			 *
			 * Sets up the GLS shipping method by initializing the method ID, title, and description.
			 * Also sets the default values for 'enabled' and 'title' settings and calls the init method.
			 * @access public
			 * @return void
			 */
			public function __construct()
			{
				$this->id                 = GLS_SHIPPING_METHOD_ID;
				$this->method_title       = __('GLS Delivery to Address', 'gls-shipping-for-woocommerce');
				$this->method_description = __('Parcels are shipped to the customer’s address.', 'gls-shipping-for-woocommerce');

				$this->init();

				$this->enabled = isset($this->settings['enabled']) ? $this->settings['enabled'] : 'yes';
				$this->title = isset($this->settings['title']) ? $this->settings['title'] : __('Delivery to Address', 'gls-shipping-for-woocommerce');
			}

			/**
			 * Init settings
			 *
			 * Loads the WooCommerce settings API, sets up form fields for the shipping method,
			 * and registers an action hook for updating shipping options.
			 * @access public
			 * @return void
			 */
			function init()
			{
				// Load the settings API
				$this->init_form_fields();
				$this->init_settings();

				add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
			}

			/**
			 * Initializes form fields for the GLS Shipping Method settings.
			 *
			 * Defines the structure and default values for the settings form fields
			 * used in the WooCommerce admin panel.
			 * @access public
			 * @return void
			 */
			public function init_form_fields()
			{
				$weight_unit = get_option('woocommerce_weight_unit');

				$this->form_fields = array(
					'enabled' => array(
						'title' => __('Enable', 'gls-shipping-for-woocommerce'),
						'type' => 'checkbox',
						'description' => __('Enable this shipping globally.', 'gls-shipping-for-woocommerce'),
						'default' => 'yes'
					),
					'title' => array(
						'title' => __('Title', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Title to be display on site', 'gls-shipping-for-woocommerce'),
						'default' => __('Delivery to Address', 'gls-shipping-for-woocommerce')
					),
					'shipping_price' => array(
						'title'       => __('Shipping Price', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter the shipping price for this method.', 'gls-shipping-for-woocommerce'),
						'default'     => 0,
						'desc_tip'    => true,
					),
					'weight_based_rates' => array(
						'title'       => __('Weight Based Rates: max_weight|cost', 'gls-shipping-for-woocommerce'),
						'type'        => 'textarea',
						'description' => sprintf(__('Optional: Enter weight based rates (one per line). Format: max_weight|cost. Example: 1|100 means up to 1 %s costs 100. Leave empty to use default price.', 'gls-shipping-for-woocommerce'), $weight_unit),
						'default'     => '',
						'placeholder' => 'max_weight|cost
max_weight|cost',
						'css'         => 'width:300px; height: 150px;',
					),
					'free_shipping_threshold' => array(
						'title'       => __('Free Shipping Threshold', 'gls-shipping-for-woocommerce'),
						'type'        => 'number',
						'description' => __('Cart total amount above which shipping will be free. Set to 0 to disable.', 'gls-shipping-for-woocommerce'),
						'default'     => '0',
						'desc_tip'    => true,
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '0.01'
						)
					),
					'supported_countries' => array(
						'title'   => __('Supported Countries', 'gls-shipping-for-woocommerce'),
						'type'    => 'multiselect',
						'class'   => 'wc-enhanced-select',
						'css'     => 'width: 400px;',
						'options' => WC()->countries->get_countries(),
						'default' => array('HR', 'CZ', 'HU', 'RO', 'SI', 'SK', 'RS'),
						'desc_tip' => true,
						'description' => __('Select countries to support for this shipping method.', 'gls-shipping-for-woocommerce'),
					),
					'main_section' => array(
						'title'       => __('GLS API Settings', 'gls-shipping-for-woocommerce'),
						'type'        => 'title',
						'description' => __('API Settings for all of the GLS Shipping Options.', 'gls-shipping-for-woocommerce'),
					),
					'client_id' => array(
						'title'       => __('Client ID', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter your GLS Client ID.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'username' => array(
						'title'       => __('Username', 'gls-shipping-for-woocommerce'),
						'type'        => 'text',
						'description' => __('Enter your GLS Username.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'password' => array(
						'title'       => __('Password', 'gls-shipping-for-woocommerce'),
						'type'        => 'password',
						'description' => __('Enter your GLS Password.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
					),
					'country' => array(
						'title'       => __('Country', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the country for the GLS service.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							'HR' => __('Croatia', 'gls-shipping-for-woocommerce'),
							'CZ' => __('Czech Republic', 'gls-shipping-for-woocommerce'),
							'HU' => __('Hungary', 'gls-shipping-for-woocommerce'),
							'RO' => __('Romania', 'gls-shipping-for-woocommerce'),
							'SI' => __('Slovenia', 'gls-shipping-for-woocommerce'),
							'SK' => __('Slovakia', 'gls-shipping-for-woocommerce'),
							'RS' => __('Serbia', 'gls-shipping-for-woocommerce'),
						),
					),
					'mode' => array(
						'title'       => __('Mode', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the mode for the GLS API.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							'production' => __('Production', 'gls-shipping-for-woocommerce'),
							'sandbox'    => __('Sandbox', 'gls-shipping-for-woocommerce'),
						),
					),
					'logging' => array(
						'title'       => __('Enable Logging', 'gls-shipping-for-woocommerce'),
						'type'        => 'checkbox',
						'label'       => __('Enable logging of GLS API requests and responses', 'gls-shipping-for-woocommerce'),
						'default'     => 'no',
					),
					'sender_identity_card_number' => array(
						'title' => __('Sender Identity Card Number. Required for Serbia.', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Only in Serbia! REQUIRED', 'gls-shipping-for-woocommerce'),
						'default' => '',
						'desc_tip' => true,
					),
					'content' => array(
						'title' => __('Content. Only for Serbia.', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Parcel info printed on label. Only for Serbia.', 'gls-shipping-for-woocommerce'),
						'default' => '',
						'desc_tip' => true,
					),
					'client_reference_format' => array(
						'title' => __('Order Reference Format', 'gls-shipping-for-woocommerce'),
						'type' => 'text',
						'description' => __('Enter the format for order reference. Use {{order_id}} where you want the order ID to be inserted.', 'gls-shipping-for-woocommerce'),
						'default' => 'Order:{{order_id}}',
						'desc_tip' => true,
					),
					'print_position' => array(
						'title'       => __('Print Position', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the Print Position.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							1 => '1',
							2 => '2',
							3 => '3',
							4 => '4',
						),
					),
					'type_of_printer' => array(
						'title'       => __('Type of Printer', 'gls-shipping-for-woocommerce'),
						'type'        => 'select',
						'description' => __('Select the Printer Type.', 'gls-shipping-for-woocommerce'),
						'desc_tip'    => true,
						'options'     => array(
							'A4_2x2'  => 'A4_2x2', 
							'A4_4x1'  => 'A4_4x1', 
							'Connect' => 'Connect', 
							'Thermo'  => 'Thermo',
						),
					),
					'sub_section' => array(
						'title'       => __('GLS Services', 'gls-shipping-for-woocommerce'),
						'type'        => 'title',
						'description' => __('Enable/Disable each of GLS Services for your store.', 'gls-shipping-for-woocommerce'),
					),
					'service_24h' => array(
						'title'   => __('Guaranteed 24h Service (24H)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable 24H', 'gls-shipping-for-woocommerce'),
						'description' => __('Not available in Serbia.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'express_delivery_service' => array(
						'title'   => __('Express Delivery Service (T09, T10, T12)', 'gls-shipping-for-woocommerce'),
						'type'    => 'select',
						'label'   => __('Express Delivery Service Time', 'gls-shipping-for-woocommerce'),
						'description' => __('Availability depends on the country. Can’t be used with FDS and FSS services.', 'gls-shipping-for-woocommerce'),
						'options'     => array(
							'' => 'Disabled',
							'T09' => '09:00',
							'T10' => '10:00',
							'T12' => '12:00',
						),
						'desc_tip' => true,
					),
					'contact_service' => array(
						'title'   => __('Contact Service (CS1)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable CS1', 'gls-shipping-for-woocommerce'),
						'default' => 'no',
					),
					'flexible_delivery_service' => array(
						'title'   => __('Flexible Delivery Service (FDS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable FDS', 'gls-shipping-for-woocommerce'),
						'description' => __('Can’t be used with T09, T10, and T12 services.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'flexible_delivery_sms_service' => array(
						'title'   => __('Flexible Delivery SMS Service (FSS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable FSS', 'gls-shipping-for-woocommerce'),
						'description' => __('Not available without FDS service.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'sms_service' => array(
						'title'   => __('SMS Service (SM1)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable SM1', 'gls-shipping-for-woocommerce'),
						'description' => __('SMS service with a maximum text length of 130.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'sms_service_text' => array(
						'title'   => __('SMS Service Text', 'gls-shipping-for-woocommerce'),
						'type'    => 'text',
						'label'   => __('SM1 Service Text', 'gls-shipping-for-woocommerce'),
						'description' => __('SMS Service Text. Variables that can be used in the text of the SMS: #ParcelNr#, #COD#, #PickupDate#, #From_Name#, #ClientRef#.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
					),
					'sms_pre_advice_service' => array(
						'title'   => __('SMS Pre-advice Service (SM2)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable SM2', 'gls-shipping-for-woocommerce'),
						'default' => 'no',
					),
					'addressee_only_service' => array(
						'title'   => __('Addressee Only Service (AOS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable AOS', 'gls-shipping-for-woocommerce'),
						'default' => 'no',
					),
					'insurance_service' => array(
						'title'   => __('Insurance Service (INS)', 'gls-shipping-for-woocommerce'),
						'type'    => 'checkbox',
						'label'   => __('Enable INS', 'gls-shipping-for-woocommerce'),
						'description' => __('Available within specific limits based on the country.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
						'default' => 'no',
					),
					'phone_number' => array(
						'title'   => __('Store Phone Number', 'gls-shipping-for-woocommerce'),
						'type'    => 'text',
						'label'   => __('Store Phone Number', 'gls-shipping-for-woocommerce'),
						'description' => __('Store Phone number that will be sent to GLS as a contact information.', 'gls-shipping-for-woocommerce'),
						'desc_tip' => true,
					),
					'sub_section2' => array(
						'title'       => '',
						'type'        => 'title',
					),
				);
			}

			/**
			 * Calculates the shipping rate based on the package details.
			 *
			 * Determines if the destination country is supported and applies the set shipping rate.
			 *
			 * @param array $package Details of the package being shipped.
			 */
			public function calculate_shipping($package = array())
			{
				$supported_countries = $this->get_option('supported_countries', []);
				$weight_based_rates_raw = $this->get_option('weight_based_rates', '');
				$default_price = $this->get_option('shipping_price', '0');
				$free_shipping_threshold = $this->get_option('free_shipping_threshold', '0');

				if (in_array($package['destination']['country'], $supported_countries)) {
					
					$cart_total = WC()->cart->get_subtotal() - WC()->cart->get_discount_total();
					$shipping_price = $default_price;

					if ($free_shipping_threshold > 0 && $cart_total >= $free_shipping_threshold) {
						$shipping_price = 0;
					// Check if weight-based rates are set and valid
					} else if (!empty(trim($weight_based_rates_raw))) {
						$weight_based_rates = array();
						$lines = explode("\n", $weight_based_rates_raw);
						foreach ($lines as $line) {
							$rate = explode('|', trim($line));
							$weight = str_replace(',', '.', $rate[0]);
							$price = str_replace(',', '.', $rate[1]);
							if (count($rate) == 2 && is_numeric($weight) && is_numeric($price)) {
								$weight_based_rates[] = array(
									'weight' => floatval($weight),
									'price' => floatval($price)
								);
							}
						}

						// If we have valid weight-based rates, use them
						if (!empty($weight_based_rates)) {
							$cart_weight = WC()->cart->get_cart_contents_weight();
							// Sort rates by weight in ascending order
							usort($weight_based_rates, function($a, $b) {
								return $a['weight'] <=> $b['weight'];
							});
							// Find the appropriate rate based on cart weight
							foreach ($weight_based_rates as $rate) {
								if ($cart_weight <= $rate['weight']) {
									$shipping_price = $rate['price'];
									break;
								}
							}

							// If no matching rate found, use the highest rate
							if ($shipping_price === $default_price && !empty($weight_based_rates)) {
								$shipping_price = end($weight_based_rates)['price'];
							}
						}
					}

					$rate = array(
						'id'       => $this->id,
						'label'    => $this->title,
						'cost'     => str_replace(',', '.', $shipping_price),
						'calc_tax' => 'per_order'
					);

					// Register the rate
					$this->add_rate($rate);
				}
			}
			
		}
	}
}

add_action('woocommerce_shipping_init', 'gls_shipping_method_init');

<?php

/**
 * Class GLS_Shipping_API_Data
 *
 * Handles data formatting for API calls to GLS Shipping service.
 */
class GLS_Shipping_API_Data
{
    /**
     * @var array $orders Array of WooCommerce order instances and their data.
     */
    private $orders = [];

    /**
     * @var array $shipping_method_settings Stores GLS shipping method settings.
     */
    private $shipping_method_settings;

    /**
     * Constructor for GLS_Shipping_API_Data.
     *
     * @param int|array $order_ids Single order ID or array of order IDs.
     */
    public function __construct($order_ids)
    {
        $this->shipping_method_settings = get_option("woocommerce_gls_shipping_method_settings");

        if (is_array($order_ids)) {
            foreach ($order_ids as $order_id) {
                $this->add_order($order_id);
            }
        } else {
            $this->add_order($order_ids);
        }
    }

    /**
     * Adds an order to the orders array.
     *
     * @param int $order_id The WooCommerce order ID.
     */
    private function add_order($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order) {
            $this->orders[] = [
                'order' => $order,
                'is_parcel_delivery_service' => $this->check_parcel_delivery_service($order),
                'pickup_info' => $this->get_pickup_info($order)
            ];
        }
    }

    /**
     * Checks if the order is for parcel delivery service.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return bool
     */
    private function check_parcel_delivery_service($order)
    {
        $shipping_methods = $order->get_shipping_methods();
        $gls_shipping_methods = [
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ID,
            GLS_SHIPPING_METHOD_PARCEL_LOCKER_ZONES_ID,
            GLS_SHIPPING_METHOD_PARCEL_SHOP_ZONES_ID
        ];

        foreach ($shipping_methods as $shipping_method) {
            if (in_array($shipping_method->get_method_id(), $gls_shipping_methods)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Gets pickup information for an order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return array|null
     */
    private function get_pickup_info($order)
    {
        $pickup_info = $order->get_meta('_gls_pickup_info', true);
        return $pickup_info ? json_decode($pickup_info, true) : null;
    }

    /**
     * Retrieves a specific setting option.
     *
     * @param string $key The key of the option to retrieve.
     * @return mixed|null The value of the specified setting option.
     */
    public function get_option($key)
    {
        return isset($this->shipping_method_settings[$key]) ? $this->shipping_method_settings[$key] : null;
    }

    /**
     * Generates the service list for a specific order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @param bool $is_parcel_delivery_service Flag to check if the order is for parcel delivery service.
     * @param array|null $pickup_info Pickup information for the order.
     * @return array List of services included in the shipping.
     */
    public function get_service_list($order, $is_parcel_delivery_service, $pickup_info)
    {
        $express_service_is_valid = false;
        $service_list = [];

        // Parcel Shop Delivery Service
        if ($is_parcel_delivery_service) {
            if (!$pickup_info) {
                throw new Exception("Pickup information not found!");
            }

            $service_list[] = [
                'Code' => 'PSD',
                'PSDParameter' => [
                    'StringValue' => $pickup_info['id'] ?? ''
                ]
            ];
        }

        // Guaranteed 24h Service
        if ($this->get_option('service_24h') === 'yes' && $order->get_shipping_country() !== 'RS') {
            $service_list[] = ['Code' => '24H'];
        }

        // Express Delivery Service
        $expressDeliveryTime = $this->get_option('express_delivery_service');
        if (!$is_parcel_delivery_service && $expressDeliveryTime && $this->isExpressDeliverySupported($expressDeliveryTime, $order)) {
            $express_service_is_valid = true;
            $service_list[] = ['Code' => $expressDeliveryTime];
        }

        // Contact Service
        if (!$is_parcel_delivery_service && $this->get_option('contact_service') === 'yes') {
            $recipientPhoneNumber = $order->get_billing_phone();
            $service_list[] = [
                'Code' => 'CS1',
                'CS1Parameter' => [
                    'Value' => $recipientPhoneNumber
                ]
            ];
        }

        // Flexible Delivery Service
        if (!$is_parcel_delivery_service && $this->get_option('flexible_delivery_service') === 'yes' && !$express_service_is_valid) {
            $recipientEmail = $order->get_billing_email();
            $service_list[] = [
                'Code' => 'FDS',
                'FDSParameter' => [
                    'Value' => $recipientEmail
                ]
            ];
        }

        // Flexible Delivery SMS Service
        if (!$is_parcel_delivery_service && $this->get_option('flexible_delivery_sms_service') === 'yes' && $this->get_option('flexible_delivery_service') === 'yes' && !$express_service_is_valid) {
            $recipientPhoneNumber = $order->get_billing_phone();
            $service_list[] = [
                'Code' => 'FSS',
                'FSSParameter' => [
                    'Value' => $recipientPhoneNumber
                ]
            ];
        }

        // SMS Service
        if ($this->get_option('sms_service') === 'yes') {
            $sm1Text = $this->get_option('sms_service_text');
            $recipientPhoneNumber = $order->get_billing_phone();
            $service_list[] = [
                'Code' => 'SM1',
                'SM1Parameter' => [
                    'Value' => "{$recipientPhoneNumber}|$sm1Text"
                ]
            ];
        }

        // SMS Pre-advice Service
        if ($this->get_option('sms_pre_advice_service') === 'yes') {
            $recipientPhoneNumber = $order->get_billing_phone();
            $service_list[] = [
                'Code' => 'SM2',
                'SM2Parameter' => [
                    'Value' => $recipientPhoneNumber
                ]
            ];
        }

        // Addressee Only Service
        if (!$is_parcel_delivery_service && $this->get_option('addressee_only_service') === 'yes') {
            $recipientName = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
            $service_list[] = [
                'Code' => 'AOS',
                'AOSParameter' => [
                    'Value' => $recipientName
                ]
            ];
        }

        // Insurance Service
        if ($this->get_option('insurance_service') === 'yes' && $this->isInsuranceAllowed($order)) {
            $service_list[] = [
                'Code' => 'INS',
                'INSParameter' => [
                    'Value' => $order->get_total()
                ]
            ];
        }

        return $service_list;
    }

    /**
     * Checks if express delivery is supported for the order.
     *
     * @param string $expressDeliveryTime The express delivery time option.
     * @param \WC_Order $order The WooCommerce order instance.
     * @return bool
     */
    public function isExpressDeliverySupported($expressDeliveryTime, $order)
    {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;

        $countryToCheck = $this->get_option('country');
        $zipcodeToCheck = $order->get_shipping_postcode();

        $file_path = GLS_SHIPPING_ABSPATH . "includes/api/express-service.csv";
        $csv_data = $wp_filesystem->get_contents($file_path);

        if ($csv_data) {
            $lines = explode("\n", $csv_data);
            array_shift($lines);

            foreach ($lines as $line) {
                $data = str_getcsv($line);

                if (!empty($data)) {
                    $country = $data[0];
                    $zipcode = $data[1];

                    if ($country === $countryToCheck && $zipcode === $zipcodeToCheck) {
                        if ($expressDeliveryTime === "T12") {
                            return $data[2] === "x";
                        }
                        if ($expressDeliveryTime === "T09") {
                            return $data[3] === "x";
                        }
                        if ($expressDeliveryTime === "T10") {
                            return $data[4] === "x";
                        }
                        return false;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks if insurance is allowed for the order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return bool Returns true if insurance is allowed, false otherwise.
     */
    public function isInsuranceAllowed($order)
    {
        $packageValue = $order->get_total();
        $originCountry = $this->get_option('country');
        $destinationCountry = $order->get_shipping_country();

        return $this->checkInsuranceCriteria($packageValue, $originCountry, $destinationCountry);
    }

    /**
     * Checks if the package meets the criteria for insurance based on value and origin/destination countries.
     *
     * @param float $packageValue Value of the package.
     * @param string $originCountry Country of origin.
     * @param string $destinationCountry Destination country.
     * @return bool True if criteria are met, otherwise false.
     */
    private function checkInsuranceCriteria($packageValue, $originCountry, $destinationCountry)
    {
        $type = $originCountry === $destinationCountry ? 'country_domestic_insurance' : 'country_export_insurance';

        $minMax = $this->getCode($type, $originCountry);

        if (!$minMax) {
            return false;
        }

        if ($packageValue >= $minMax['min'] && $packageValue <= $minMax['max']) {
            return true;
        }
        return false;
    }

    /**
     * Retrieves GLS carrier configuration data.
     *
     * @param string $type Type of configuration data to retrieve.
     * @param int|string|null $code Specific code to retrieve data for.
     * @return mixed Configuration data.
     */
    public function getCode($type, $code = null)
    {
        $data = [
            'country_calling_code' => [
                'CZ' => '+420',
                'HR' => '+385',
                'HU' => '+36',
                'RO' => '+40',
                'SI' => '+386',
                'SK' => '+421',
                'RS' => '+381',
            ],
            'country_domestic_insurance' => [
                'CZ' => ['min' => 20000, 'max' => 100000], // CZK
                'HR' => ['min' => 165.9, 'max' => 1659.04], // EUR
                'HU' => ['min' => 50000, 'max' => 500000], // HUF
                'RO' => ['min' => 2000, 'max' => 7000], // RON
                'SI' => ['min' => 200, 'max' => 2000], // EUR
                'SK' => ['min' => 332, 'max' => 2655], // EUR
                'RS' => ['min' => 40000, 'max' => 200000] // RSD
            ],
            'country_export_insurance' => [
                'CZ' => ['min' => 20000, 'max' => 100000], // CZK
                'HR' => ['min' => 165.91, 'max' => 663.61], // EUR
                'HU' => ['min' => 50000, 'max' => 200000], // HUF
                'RO' => ['min' => 2000, 'max' => 7000], // RON
                'SI' => ['min' => 200, 'max' => 2000], // EUR
                'SK' => ['min' => 332, 'max' => 1000] // EUR
            ]
        ];

        if ($code === null) {
            return $data[$type] ?? [];
        }

        return $data[$type][$code] ?? null;
    }

    /**
     * Gets the pickup address for the shipment.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return array The pickup address information.
     */
    public function get_pickup_address($order)
    {
        $store_address = get_option('woocommerce_store_address');
        $store_address_2 = get_option('woocommerce_store_address_2');
        $store_city = get_option('woocommerce_store_city');
        $store_postcode = get_option('woocommerce_store_postcode');
        $store_raw_country = get_option('woocommerce_default_country');

        // Split the country and state
        $split_country = explode(":", $store_raw_country);
        $store_country = isset($split_country[0]) ? $split_country[0] : '';

        $pickup_address = [
            'Name' => get_bloginfo('name'),
            'Street' => $store_address . ' ' . $store_address_2,
            'City' => $store_city,
            'ZipCode' => $store_postcode,
            'CountryIsoCode' => $store_country,
            'ContactName' => get_bloginfo('name'),
            'ContactPhone' => $this->get_option('phone_number'),
            'ContactEmail' => get_option('admin_email')
        ];

        return apply_filters('gls_shipping_for_woocommerce_api_get_pickup_address', $pickup_address, $order);
    }

    /**
     * Gets the delivery address for a specific order.
     *
     * @param \WC_Order $order The WooCommerce order instance.
     * @return array The delivery address information.
     */
    public function get_delivery_address($order)
    {
        $delivery_address = [
            'Name' => $order->get_shipping_company() ?: $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'Street' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
            'City' => $order->get_shipping_city(),
            'ZipCode' => $order->get_shipping_postcode(),
            'CountryIsoCode' => $order->get_shipping_country(),
            'ContactName' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'ContactPhone' => $order->get_billing_phone(),
            'ContactEmail' => $order->get_billing_email()
        ];

        return $delivery_address;
    }

    /**
     * Generates post fields for the API request for multiple orders.
     *
     * @return array The generated post fields for the API request.
     */
    public function generate_post_fields_multi()
    {
        $parcel_list = [];

        foreach ($this->orders as $order_data) {
            $order = $order_data['order'];
            $is_parcel_delivery_service = $order_data['is_parcel_delivery_service'];
            $pickup_info = $order_data['pickup_info'];

            $clientReferenceFormat = $this->get_option('client_reference_format');
            $senderIdentityCardNumber = $this->get_option('sender_identity_card_number');
            $content = $this->get_option('content');
            $orderId = $order->get_id();
            $clientReference = str_replace('{{order_id}}', $orderId, $clientReferenceFormat);

            $parcel = [
                'ClientNumber' => (int)$this->get_option("client_id"),
                'ClientReference' => $clientReference,
                'Count' => 1
            ];
            $parcel['PickupAddress'] = $this->get_pickup_address($order);
            $parcel['DeliveryAddress'] = $this->get_delivery_address($order);
            $parcel['ServiceList'] = $this->get_service_list($order, $is_parcel_delivery_service, $pickup_info);

            if ($order->get_shipping_country() === 'RS') {
                $parcel['SenderIdentityCardNumber'] = $senderIdentityCardNumber;
                $parcel['Content'] = $content;
            }

            if ($order->get_payment_method() === 'cod') {
                $parcel['CODAmount'] = $order->get_total();
                $parcel['CODReference'] = $orderId;
            }

            $parcel_list[] = $parcel;
        }

        $params = [
            'WebshopEngine' => 'woocommercehr',
            'ParcelList' => $parcel_list,
            'PrintPosition' => (int)$this->get_option("print_position") ?: 1,
            'TypeOfPrinter' => $this->get_option("type_of_printer") ?: 'A4_2x2',
            'ShowPrintDialog' => false
        ];

        return $params;
    }

    /**
     * Generates post fields for the API request for a single order.
     *
     * @param int $count Number of packages.
     * @return array The generated post fields for the API request.
     */
    public function generate_post_fields($count = 1)
    {
        if (empty($this->orders)) {
            throw new Exception("No orders available.");
        }

        $order_data = $this->orders[0];
        $order = $order_data['order'];
        $is_parcel_delivery_service = $order_data['is_parcel_delivery_service'];
        $pickup_info = $order_data['pickup_info'];

        $clientReferenceFormat = $this->get_option('client_reference_format');
        $senderIdentityCardNumber = $this->get_option('sender_identity_card_number');
        $content = $this->get_option('content');
        $orderId = $order->get_id();
        $clientReference = str_replace('{{order_id}}', $orderId, $clientReferenceFormat);

        $parcel = [
            'ClientNumber' => (int)$this->get_option("client_id"),
            'ClientReference' => $clientReference,
            'Count' => $count
        ];
        $parcel['PickupAddress'] = $this->get_pickup_address($order);
        $parcel['DeliveryAddress'] = $this->get_delivery_address($order);
        $parcel['ServiceList'] = $this->get_service_list($order, $is_parcel_delivery_service, $pickup_info);

        if ($order->get_shipping_country() === 'RS') {
            $parcel['SenderIdentityCardNumber'] = $senderIdentityCardNumber;
            $parcel['Content'] = $content;
        }

        if ($order->get_payment_method() === 'cod') {
            $parcel['CODAmount'] = $order->get_total();
            $parcel['CODReference'] = $orderId;
        }

        $params = [
            'WebshopEngine' => 'woocommercehr',
            'ParcelList' => [$parcel],
            'PrintPosition' => (int)$this->get_option("print_position") ?: 1,
            'TypeOfPrinter' => $this->get_option("type_of_printer") ?: 'A4_2x2',
            'ShowPrintDialog' => false
        ];

        return $params;
    }
}
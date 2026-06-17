<?php

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * GLS Blocks Integration
 *
 * Integrates GLS Shipping with WooCommerce Checkout Blocks.
 * Registers scripts, passes config to frontend, extends Store API,
 * and handles validation + order meta saving for pickup locations.
 *
 * @since 1.5.0
 */
class GLS_Blocks_Integration implements IntegrationInterface {

	/**
	 * Shipping methods that require map/pickup selection.
	 *
	 * @var array
	 */
	private static $pickup_methods = array(
		'gls_shipping_method_parcel_locker',
		'gls_shipping_method_parcel_shop',
		'gls_shipping_method_parcel_locker_zones',
		'gls_shipping_method_parcel_shop_zones',
	);

	/**
	 * Instance-level alias for interface methods.
	 *
	 * @var array
	 */
	private $map_selection_methods = array(
		'gls_shipping_method_parcel_locker',
		'gls_shipping_method_parcel_shop',
		'gls_shipping_method_parcel_locker_zones',
		'gls_shipping_method_parcel_shop_zones',
	);

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'gls-shipping';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		// Register the external DPM (map widget) script.
		wp_register_script(
			'gls-shipping-dpm',
			'https://map.gls-croatia.com/widget/gls-dpm.js',
			array(),
			GLS_SHIPPING_VERSION,
			true
		);

		// Register the built checkout script.
		$asset_file = GLS_SHIPPING_ABSPATH . 'blocks/build/checkout.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => GLS_SHIPPING_VERSION,
		);

		wp_register_script(
			'gls-shipping-blocks-checkout',
			GLS_SHIPPING_URL . 'blocks/build/checkout.js',
			array_merge( $asset['dependencies'], array( 'gls-shipping-dpm' ) ),
			$asset['version'],
			true
		);

		wp_register_style(
			'gls-shipping-blocks-checkout',
			GLS_SHIPPING_URL . 'blocks/build/checkout.css',
			array(),
			$asset['version']
		);

	}

	/**
	 * Enqueue the block stylesheet on the cart/checkout frontend.
	 *
	 * WooCommerce auto-enqueues the script returned by get_script_handles(),
	 * but not the integration's stylesheet, so we enqueue it here. Gated to
	 * cart/checkout pages (the conditionals are true for block pages too).
	 */
	public static function enqueue_block_styles() {
		if ( ! function_exists( 'is_cart' ) || ( ! is_cart() && ! is_checkout() ) ) {
			return;
		}

		if ( ! wp_style_is( 'gls-shipping-blocks-checkout', 'registered' ) ) {
			$asset_file = GLS_SHIPPING_ABSPATH . 'blocks/build/checkout.asset.php';
			$asset      = file_exists( $asset_file ) ? require $asset_file : array( 'version' => GLS_SHIPPING_VERSION );

			wp_register_style(
				'gls-shipping-blocks-checkout',
				GLS_SHIPPING_URL . 'blocks/build/checkout.css',
				array(),
				$asset['version']
			);
		}

		wp_enqueue_style( 'gls-shipping-blocks-checkout' );
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'gls-shipping-blocks-checkout' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$gls_settings    = get_option( 'woocommerce_gls_shipping_method_settings', array() );
		$show_logo       = isset( $gls_settings['show_gls_logo'] ) && $gls_settings['show_gls_logo'] === 'yes';
		$filter_sat      = GLS_Shipping_Assets::get_locker_filter_saturation();

		return array(
			'mapSelectionMethods' => $this->map_selection_methods,
			'i18n'               => array(
				'selectParcelLocker' => __( 'Select Parcel Locker', 'gls-shipping-for-woocommerce' ),
				'selectParcelShop'   => __( 'Select Parcel Shop', 'gls-shipping-for-woocommerce' ),
				'pickupLocation'     => __( 'Pickup Location', 'gls-shipping-for-woocommerce' ),
				'name'               => __( 'Name', 'gls-shipping-for-woocommerce' ),
				'address'            => __( 'Address', 'gls-shipping-for-woocommerce' ),
				'country'            => __( 'Country', 'gls-shipping-for-woocommerce' ),
				'validationError'    => __( 'Please select a parcel locker/shop by clicking on Select Parcel button.', 'gls-shipping-for-woocommerce' ),
			),
			'logoUrl'            => GLS_SHIPPING_URL . 'assets/img/gls_logo.svg',
			'showLogo'           => $show_logo,
			'filterSaturation'   => $filter_sat,
		);
	}

	/**
	 * Register Store API endpoint and order processing hooks.
	 * Called from woocommerce_blocks_loaded so hooks are available during REST requests.
	 */
	public static function register_store_api() {
		self::register_store_api_endpoint();
		self::register_order_hooks();
	}

	/**
	 * Register the Store API endpoint extension for pickup data.
	 */
	private static function register_store_api_endpoint() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => 'checkout',
				'namespace'       => 'gls-shipping/pickup-info',
				'schema_callback' => function () {
					return array(
						'pickup_data' => array(
							'description' => 'GLS pickup location data',
							'type'        => array( 'string', 'null' ),
						),
					);
				},
				'data_callback'   => function () {
					return array( 'pickup_data' => '' );
				},
			)
		);
	}

	/**
	 * Register hooks for order processing.
	 */
	private static function register_order_hooks() {
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( __CLASS__, 'validate_pickup_selection' ),
			5,
			2
		);

		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array( __CLASS__, 'save_pickup_info_from_request' ),
			10,
			2
		);
	}

	/**
	 * Validate that a pickup location has been selected when a parcel method is chosen.
	 *
	 * @param \WC_Order        $order   The order being processed.
	 * @param \WP_REST_Request $request The checkout request.
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException If validation fails.
	 */
	public static function validate_pickup_selection( $order, $request ) {
		// Only validate on actual checkout submission (POST), not intermediate updates (PUT).
		if ( $request->get_method() !== 'POST' ) {
			return;
		}

		$shipping_methods = $order->get_shipping_methods();
		$needs_pickup     = false;

		foreach ( $shipping_methods as $shipping_method ) {
			if ( in_array( $shipping_method->get_method_id(), self::$pickup_methods, true ) ) {
				$needs_pickup = true;
				break;
			}
		}

		if ( ! $needs_pickup ) {
			return;
		}

		$extensions = $request->get_param( 'extensions' );
		$gls_data   = $extensions['gls-shipping/pickup-info'] ?? array();

		if ( empty( $gls_data['pickup_data'] ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'gls_pickup_required',
				esc_html__( 'Please select a parcel locker/shop by clicking on Select Parcel button.', 'gls-shipping-for-woocommerce' ),
				400
			);
		}
	}

	/**
	 * Save pickup info from the Store API checkout request to order meta.
	 *
	 * @param \WC_Order        $order   The order being processed.
	 * @param \WP_REST_Request $request The checkout request.
	 */
	public static function save_pickup_info_from_request( $order, $request ) {
		$extensions = $request->get_param( 'extensions' );
		$gls_data   = $extensions['gls-shipping/pickup-info'] ?? array();

		if ( ! empty( $gls_data['pickup_data'] ) ) {
			$order->update_meta_data(
				'_gls_pickup_info',
				sanitize_text_field( $gls_data['pickup_data'] )
			);
			$order->save();
		}
	}
}

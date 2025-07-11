=== GLS Shipping for WooCommerce ===
Contributors: goran87
Tags: gls, shipping, woocommerce shipping
Requires at least: 4.4
Tested up to: 6.7
Stable tag: 1.2.5
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

GLS Shipping plugin for WooCommerce

== Description ==

This plugin seamlessly integrates GLS Shipping into your website, supporting both global shipping methods and custom Shipping Zones. It includes features for generating shipping labels and bulk label creation, streamlining your shipping process.

## Introduction ##

This WooCommerce shipping plugin integrates with GLS Group to provide direct shipping capabilities within your WooCommerce store. This plugin uses external services to handle shipping processes and tracking effectively.

## Supported Countries ##
- Croatia
- Czech Republic
- Hungary
- Romania
- Slovenia
- Slovakia
- Serbia

## External Services ##
This plugin makes use of the following third-party services:

### GLS Group APIs ###
- **Service:** GLS Shipping Tracking
- **Purpose:** Allows users to track their shipments directly through WooCommerce.
- **URL:** [GLS Group](https://gls-group.com/HR/en/)
- **Privacy Policy:** [GLS Privacy Policy](https://gls-group.com/HR/en/privacy-policy)

### OpenStreetMap ###
- **Service:** OpenStreetMap API
- **Purpose:** Used to provide map functionalities in the shipping plugin.
- **URL:** [OpenStreetMap](https://openstreetmap.org)
- **Privacy Policy:** [OpenStreetMap Privacy](https://wiki.osmfoundation.org/wiki/Privacy_Policy)

## Data Handling and Privacy ##
When using our plugin, certain data such as tracking numbers and geographical locations may be transmitted to third-party services mentioned above. We do not store this data on our servers. Please review the privacy policies of the respective services (linked above) to understand how they manage your data.

= Links and Additional Information =
For more details about GLS Shipping plugin for WooCommerce and how it integrates with your WordPress site, please visit our website: [GLS Group](https://gls-group.com/HR/en/)
To understand how we handle and protect your data, please review our Terms of Use and Privacy Policies available at the following links:
* [Terms of Service](https://gls-group.com/HR/en/terms-conditions/) 
* [Privacy Policy](https://gls-group.com/HR/en/privacy-policy/)

== Installation ==

To install and configure this plugin:
1. Download and activate the plugin in your WooCommerce store.
2. Navigate to WooCommerce Settings > Shipping and select GLS Shipping.
3. Enter your GLS API credentials and configure the necessary settings to enable the shipping and tracking functionalities.

== Frequently Asked Questions ==

== Screenshots ==

== Changelog ==

= 1.2.5 =
* Fix: Fatal error on email previews.

= 1.2.4 =
* Fixed issue when changing shipping method would leave pickup info

= 1.2.3 =
* Added WebshopEngine in request logs.

= 1.2.2 =
* Removed second Street Address field from content.

= 1.2.1 =
* Added support for Street Address second field

= 1.2.0 =
* Added support for Shipping Zones
* Refactored script from jQuery to Vanilla JS
* Added support for Free Shipping
* Bulk Label Generation on the order listing screen
* Bulk Label Printing on the order listing screen
* Introduced weight-based pricing support
* Added the ability to set the number of packages

= 1.1.4 =
* Tax support

= 1.1.3 =
* Support for WordPress 6.6

= 1.1.2 =
* Support for SenderIdentityCardNumber and Content fields.
* Support for Print Position and Printer Type

= 1.1.1 =
* Updated readme file and additional sanitization. 

= 1.1.0 =
* Sanitization and escaping

= 1.0.1 =
* HPOS Support fix.

= 1.0.0 =
* Initial version

== Upgrade Notice ==

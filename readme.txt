=== Plugin Name ===
Contributors: mRova
Tags: WooCommerce, Payment Gateway, CCAvenue
Requires at least: 3.8
Tested up to: 3.8
Stable tag: 1.2.3

Allows you to use CCAvenue payment gateway with the WooCommerce plugin.

== Description ==

This is the CCAvenue payment gateway for WooCommerce. Allows you to use CCAvenue payment gateway with the WooCommerce plugin. It uses the redirect method, the user is redirected to ccavenue so that you don't have to install an SSL certificate on your site.

Visit [http://www.mrova.com/?p=748](http://www.mrova.com/?p=748)
== Installation ==
1. Ensure you have latest version of WooCommerce plugin installed
2. Unzip and upload contents of the plugin to your /wp-content/plugins/ directory
3. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==
1. WooCommerce payment gateway setting page
2. CCAvenue setting page
3. Success message
4. Info message if the payment for this transaction has been made by an American Express Card
5. Transaction declined Message
6. CCAvenue Payment gateway option at the checkout page


== Configuration ==

1. Visit the WooCommerce settings page, and click on the Payment Gateways tab.
2. Click on CCAvenue to edit the settings. If you do not see CCAvenue in the list at the top of the screen make sure you have activated the plugin in the WordPress Plugin Manager.
3. Enable the Payment Method, name it Credit Card / Debit Card / Internet Banking (this will show up on the payment page your customer sees), add in your merchand id and working key and select redirect url(URL you want ccavenue to redirect after payment). Click Save.

== Changelog ==

= 1.0 =
* First Public Release.
= 1.1 =
* Some Optimizations.
= 1.2 =
* Added WooCommerce 2.0.0 compatiblity.
= 1.2.1 =
* changed woocommerce_order with new WC_Order($order_id);
= 1.2.2 =
* Removed bug preventing auto order updation
= 1.2.3 =
* WooCommerce 2.1.0 compatibility.. Update only if you are using WooCommerce 2.1.0


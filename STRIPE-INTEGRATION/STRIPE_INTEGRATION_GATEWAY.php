<?php
/*
 * Plugin Name: Stripe Integration WooCommerce
 * Author: Misha Lestev
 * Version: 1.0.0
 */

require 'vendor/autoload.php';

// Registration PHP-class as payment gateway in WooCommerce
add_filter('woocommerce_payment_gateways', 'register_stripe_gateway');
function register_stripe_gateway($gateways)
{
    $gateways[] = 'stripe_gateway'; // adding class name to the WooCommerce array
    return $gateways;
}

// Custom Class
add_action('plugins_loaded', 'stripe_gateway_class');
function stripe_gateway_class()
{
    class stripe_gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'stripe_integration'; // Payment gateway ID
            $this->icon = ''; // URL of the icon displayed on the checkout page next to this payment method
            $this->has_fields = true; // Whether a custom form for entering card details is needed
            $this->method_title = 'Stripe Integration WooCommerce'; // Display title in the WooCommerce settings
            $this->method_description = 'This integration allows Stripe to open on a new page (Misha Lestev v.1.0.0)'; // Displayed in the admin interface

            // Payment plugins may support subscriptions, saved cards, refunds, etc.
            // For now, let's focus on simple payments, though more details will be covered below
            $this->supports = array(
                'products'
            );

            // Store all setting fields here
            $this->init_form_fields();

            // Initialize settings
            $this->init_settings();
            // Gateway title
            $this->title = $this->get_option('title');
            // Description
            $this->description = $this->get_option('description');
            // Enabled or disabled
            $this->enabled = $this->get_option('enabled');
            // Test mode (sandbox) or not
            $this->testmode = 'yes' === $this->get_option('testmode');
            // Separate keys for test and live modes
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');
            $this->webhook_secret_endpoint = $this->testmode ? $this->get_option('test_webhook_secret_endpoint') : $this->get_option('webhook_secret_endpoint');

            // Hook to save all settings; you can create a custom method process_admin_options() as well
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }


        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enabled/Disabled',
                    'label' => 'Enable payment gateway Vassa to Stripe',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),

                'testmode' => array(
                    'title' => 'Test Mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Do you want to test with API test keys first?',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_publishable_key' => array(
                    'title' => 'Test Publishable Key',
                    'type' => 'text'
                ),
                'test_private_key' => array(
                    'title' => 'Test Private Key',
                    'type' => 'password',
                ),
                'publishable_key' => array(
                    'title' => 'Publishable Key',
                    'type' => 'text'
                ),
                'private_key' => array(
                    'title' => 'Private Key',
                    'type' => 'password'
                ),
                'test_webhook_secret_endpoint' => array(
                    'title' => 'Test Webhook Secret Endpoint',
                    'type' => 'text'
                ),
                'webhook_secret_endpoint' => array(
                    'title' => 'Webhook Secret Endpoint',
                    'type' => 'password'
                )
            );
        }


        // public function payment_fields(){}
        // public function payment_scripts() {}


        public function process_payment($order_id)
        {
            $this->webhook_secret_endpoint = $this->testmode ? $this->get_option('test_webhook_secret_endpoint') : $this->get_option('webhook_secret_endpoint');
            $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
            $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');

            $stripe = new \Stripe\StripeClient($this->private_key);

            // Get the order object
            $order = wc_get_order($order_id);

            // Get the items in the order
            $items = $order->get_items();

            // Assume the first item in the order determines the product
            $first_item = reset($items);
            $product_id = $first_item->get_product_id();

            // Get line items
            $line_items = $order->get_items();

            // Check if line items exist and iterate through them
            $line_item_data = [];
            if (!empty($line_items)) {
                foreach ($line_items as $item_id => $item) {
                    // Check if the item is an instance of WC_Order_Item_Product
                    if ($item instanceof WC_Order_Item_Product) {
                        // Access the 'name' field
                        $product_name = $item->get_name();
                        $product_price = $item->get_total();
                        $quantity = $item->get_quantity();
            
                        // Create a new Price in Stripe based on your product logic
                        $price = $stripe->prices->create([
                            'currency' => 'aed',
                            'unit_amount' => ($product_price / $quantity) * 100, // Set the desired amount in cents
                            'product_data' => ['name' => $product_name],
                        ]);
            
                        // Add line item data to the array
                        $line_item_data[] = [
                            'price' => $price->id,
                            'quantity' => $quantity,
                        ];
                    }
                }
            }

            // Assuming $order is your order object
            $order_id = $order->get_id();
            $order_key = $order->get_order_key();

            // Construct the URL
            $order_received_url = wc_get_checkout_url() . 'order-received/' . $order_id . '/?key=' . $order_key;
            $session = $stripe->checkout->sessions->create([
                'payment_method_types' => ['card'],
                'line_items' => $line_item_data,
                'mode' => 'payment',
                'success_url' => $order_received_url . "&session_id={CHECKOUT_SESSION_ID}", // Corrected to use the return URL
                'cancel_url' => wc_get_checkout_url(),
            ]);

            wc_add_notice('Redirecting to the payment page...', 'success');

            return array(
                'result' => 'success',
                'redirect' => $session->url,
            );
        }

    }
}



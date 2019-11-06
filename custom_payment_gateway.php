<?php
/*
Plugin Name: Custom Payment Gateway
Description: Custom payment gateway example
Author: Lafif Astahdziq
Author URI: https://lafif.me
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_custom_gateway_class');
function init_custom_gateway_class(){

    class WC_Gateway_Custom extends WC_Payment_Gateway {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct() {

            $this->domain = 'custom_payment';

            $this->id                 = 'custom';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Custom', $this->domain );
            $this->method_description = __( 'Allows payments with custom gateway.', $this->domain );

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
	        $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
            
            $this->order_status = $this->get_option( 'order_status', 'completed' );
           
            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
            // add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );

            // // Customer Emails
            // add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', $this->domain ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Custom Payment', $this->domain ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __( 'Title', $this->domain ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', $this->domain ),
                    'default'     => __( 'Custom Payment', $this->domain ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', $this->domain ),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', $this->domain ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', $this->domain ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'test_publishable_key' => array(
                    'title'       => 'Test Publishable Key',
                    'type'        => 'text'
                ),
                'test_private_key' => array(
                    'title'       => 'Test Private Key',
                    'type'        => 'password',
                ),
                'publishable_key' => array(
                    'title'       => 'Live Publishable Key',
                    'type'        => 'text'
                ),
                'private_key' => array(
                    'title'       => 'Live Private Key',
                    'type'        => 'password'
                )
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page() {
            if ( $this->instructions )
                echo wpautop( wptexturize( $this->instructions ) );
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            
            //Gets Order Details
            $order = wc_get_order( $order_id );

            //Gets Total Amount
            $amount=$order->get_total();
            
            $status = 'wc-' === substr( $this->order_status, 0, 3 ) ? substr( $this->order_status, 3 ) : $this->order_status;

            // Set order status
            $order->update_status( $status, __( 'Checkout with custom payment. ', $this->domain ) );

            // Reduce stock levels
            $order->reduce_order_stock();
            
            // Remove cart
            WC()->cart->empty_cart();

            //Converts Object Into Array
            $order_object = json_decode($order);

            //Converts Object Into Array
            $arr = (array) $order_object;

            //Converts Billing Object Into Array
            $customer=(array) $arr['billing'];
           
           // $paypal_request->get_request_url( $order, $this->testmode )
            // return array(
            //     'result'   => 'success',
            //     'redirect' => "http://googel.com",
            // );

            $curl = curl_init();

            //url to go to after payment
            $callback_url = $this->get_return_url( $order );
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => json_encode([
                'amount'=> $amount,
                'email'=> $customer['email'],
                'callback_url' => $callback_url
              ]),
              CURLOPT_HTTPHEADER => [
                "authorization: Bearer ".$this->get_option( 'test_private_key' ), //replace this with your own test key
                "content-type: application/json",
                "cache-control: no-cache"
              ],
            ));
            
            $response = curl_exec($curl);
            $response = json_decode($response);
            print_r($response->data->authorization_url); 
             return array(
                'result'   => 'success',
                'redirect' => $response->data->authorization_url,
            );
           
            $err = curl_error($curl);
            
            if($err){
              // there was an error contacting the Payment Gateway API
              die('Curl returned error: ' . $err);
            }
            
            $tranx = json_decode($response, true);
            
          
        }
    }
}


//This action hook registers our PHP class as a WooCommerce payment gateway
add_filter( 'woocommerce_payment_gateways', 'add_custom_gateway_class' );
function add_custom_gateway_class( $methods ) {
    $methods[] = 'WC_Gateway_Custom'; 
    return $methods;
}




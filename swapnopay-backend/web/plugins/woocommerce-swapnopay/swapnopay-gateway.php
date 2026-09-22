<?php
/**
 * Plugin Name: SwapnoPay Payment Gateway for WooCommerce
 * Plugin URI: https://swapnopay.com
 * Description: Self-hosted automated payment verification gateway for bKash, Nagad, Rocket, and Upay.
 * Version: 1.0.0
 * Author: SwapnoPay
 * Author URI: https://swapnopay.com
 * License: GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'init_swapnopay_gateway_class' );

function init_swapnopay_gateway_class() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    class WC_Gateway_SwapnoPay extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'swapnopay';
            $this->icon               = apply_filters( 'woocommerce_swapnopay_icon', '' );
            $this->has_fields         = false;
            $this->method_title       = __( 'SwapnoPay Gateway', 'woocommerce-swapnopay' );
            $this->method_description = __( 'Accept automated MFS payments (bKash, Nagad, Rocket, Upay) with instant SMS verification.', 'woocommerce-swapnopay' );

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define variables
            $this->title          = $this->get_option( 'title' );
            $this->description    = $this->get_option( 'description' );
            $this->supabase_url   = $this->get_option( 'supabase_url' );
            $this->anon_key       = $this->get_option( 'anon_key' );
            $this->merchant_name  = $this->get_option( 'merchant_name' );
            $this->secret_key     = $this->get_option( 'secret_key' );
            $this->widget_url     = $this->get_option( 'widget_url' );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            
            // Webhook Hook
            add_action( 'woocommerce_api_wc_swapnopay_gateway', array( $this, 'check_webhook_response' ) );
        }

        // Configuration Form Fields
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'woocommerce-swapnopay' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable SwapnoPay Checkout', 'woocommerce-swapnopay' ),
                    'default' => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce-swapnopay' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-swapnopay' ),
                    'default'     => __( 'bKash/Nagad/Rocket (SwapnoPay)', 'woocommerce-swapnopay' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woocommerce-swapnopay' ),
                    'type'        => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-swapnopay' ),
                    'default'     => __( 'Pay securely using mobile banking MFS. Your payment is verified automatically in real-time.', 'woocommerce-swapnopay' ),
                ),
                'supabase_url' => array(
                    'title'       => __( 'Supabase Project URL', 'woocommerce-swapnopay' ),
                    'type'        => 'text',
                    'description' => __( 'Enter your private self-hosted Supabase URL.', 'woocommerce-swapnopay' ),
                ),
                'anon_key' => array(
                    'title'       => __( 'Supabase Anon Key', 'woocommerce-swapnopay' ),
                    'type'        => 'text',
                    'description' => __( 'Enter your Supabase anonymous API key.', 'woocommerce-swapnopay' ),
                ),
                'merchant_name' => array(
                    'title'       => __( 'Merchant Store Name', 'woocommerce-swapnopay' ),
                    'type'        => 'text',
                    'description' => __( 'This name will be displayed at checkout widget header (e.g. DreamMart).', 'woocommerce-swapnopay' ),
                ),
                'secret_key' => array(
                    'title'       => __( 'Webhook Secret Key', 'woocommerce-swapnopay' ),
                    'type'        => 'password',
                    'description' => __( 'Webhook secret key used to sign transactions and verify edge callback requests.', 'woocommerce-swapnopay' ),
                ),
                'widget_url' => array(
                    'title'       => __( 'SwapnoPay Hosted Widget Location', 'woocommerce-swapnopay' ),
                    'type'        => 'text',
                    'description' => __( 'Absolute URL to the widget folder directory containing widget.html (e.g. https://mystore.com/swapnopay/web/).', 'woocommerce-swapnopay' ),
                )
            );
        }

        // Process Checkouts & Redirect to Widget Frame
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            // 1. Prepare POST payload for create-order Edge Function
            const FUNCTION_PATH = '/functions/v1/create-order';
            $api_url = rtrim( $this->supabase_url, '/' ) . FUNCTION_PATH;

            $payload = array(
                'merchantSecret' => $this->secret_key,
                'tran_id'        => (string) $order_id,
                'amount'         => (float) $order->get_total(),
                'cus_phone'      => $order->get_billing_phone(),
                'cus_email'      => $order->get_billing_email(),
                'cus_name'       => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'product_name'   => 'Order #' . $order_id,
                'callback_url'   => WC()->api_request_url( 'WC_Gateway_SwapnoPay' )
            );

            // Call Deno serverless order API
            $response = wp_remote_post( $api_url, array(
                'method'    => 'POST',
                'headers'   => array(
                    'Content-Type'  => 'application/json',
                    'apikey'        => $this->anon_key,
                    'Authorization' => 'Bearer ' . $this->anon_key
                ),
                'body'      => json_encode( $payload ),
                'timeout'   => 15
            ) );

            if ( is_wp_error( $response ) ) {
                wc_add_notice( 'Connection to payment gateway failed. Please try again.', 'error' );
                return;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['error'] ) || ! isset( $body['order_id'] ) ) {
                wc_add_notice( 'Payment gateway rejected order registration: ' . ( isset( $body['error'] ) ? $body['error'] : 'Unknown Error' ), 'error' );
                return;
            }

            // 2. Build redirect parameters to widget.html UI
            $widget_loc = rtrim( $this->widget_url, '/' ) . '/widget.html';
            
            $redirect_query = add_query_arg( array(
                'order_id'          => $body['order_id'],
                'supabase_url'      => $this->supabase_url,
                'supabase_anon_key' => $this->anon_key,
                'amount'            => $order->get_total(),
                'merchant_name'     => $this->merchant_name,
                'merchant_number'   => isset( $body['merchantNumber'] ) ? $body['merchantNumber'] : '',
                'success_url'       => $this->get_return_url( $order )
            ), $widget_loc );

            // Return success and redirect url
            return array(
                'result'   => 'success',
                'redirect' => $redirect_query
            );
        }

        // Webhook Handler: process callbacks from process-sms Deno function
        public function check_webhook_response() {
            $signature = isset( $_SERVER['HTTP_X_SIGNATURE'] ) ? $_SERVER['HTTP_X_SIGNATURE'] : '';
            $raw_payload = file_get_contents( 'php://input' );
            $data = json_decode( $raw_payload, true );

            if ( ! $data || ! $signature ) {
                status_header( 400 );
                echo 'Bad Request';
                exit;
            }

            // Compute expected signature using HMAC-SHA256
            $expected_sig = hash_hmac( 'sha256', $raw_payload, $this->secret_key );

            if ( ! hash_equals( $expected_sig, $signature ) ) {
                status_header( 401 );
                echo 'Unauthorized Signature';
                exit;
            }

            // Signature is valid. Update order status
            $order_id = intval( $data['tran_id'] );
            $status = $data['status'];
            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                status_header( 404 );
                echo 'Order Not Found';
                exit;
            }

            if ( $status === 'PAID' && ! $order->is_paid() ) {
                $order->payment_complete( isset( $data['trx_id'] ) ? $data['trx_id'] : '' );
                $order->add_order_note( sprintf( 'Payment verified instantly via SwapnoPay (TrxID: %s, Sender: %s)', $data['trx_id'], $data['sender_number'] ) );
                
                // Clear active shopper cart
                WC()->cart->empty_cart();
                
                status_header( 200 );
                echo 'Success';
                exit;
            }

            status_header( 200 );
            echo 'Ignored';
            exit;
        }

    }
}

// Add gateway to WooCommerce selection list
add_filter( 'woocommerce_payment_gateways', 'add_swapnopay_gateway' );
function add_swapnopay_gateway( $gateways ) {
    $gateways[] = 'WC_Gateway_SwapnoPay';
    return $gateways;
}

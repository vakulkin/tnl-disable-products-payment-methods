<?php
/*
Plugin Name: Disable Payment Methods For Products
Description: Disables WooCommerce payment methods for specific products in the cart.
Version: 1.0
Author: TheNewLook
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include Carbon Fields.
use Carbon_Fields\Container;
use Carbon_Fields\Field;

class Disable_Payment_Methods_For_Products
{

    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'load_carbon_fields']);
        add_action('carbon_fields_register_fields', [$this, 'register_custom_fields']);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'initialize_cart_check']);
    }

    public function load_carbon_fields()
    {
        require_once (__DIR__ . '/vendor/autoload.php');
        \Carbon_Fields\Carbon_Fields::boot();
    }

    public function register_custom_fields()
    {
        Container::make('theme_options', 'DisProdMethods')
            ->add_fields([
                Field::make('association', 'tnl_disable_payment_products', 'For Selected Products')
                    ->set_types([
                        [
                            'type' => 'post',
                            'post_type' => 'product',
                        ],
                    ]),
                Field::make('multiselect', 'tnl_disable_payment_methods', 'Disable Payment Methods')
                    ->set_options($this->get_payment_methods_options()),
            ]);
    }

    private function get_payment_methods_options()
    {
        if (!class_exists('WooCommerce')) {
            return [];
        }

        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $options = [];
        foreach ($payment_gateways as $gateway) {
            $options[$gateway->id] = $gateway->get_title();
        }
        return $options;
    }

    public function initialize_cart_check()
    {
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_available_payment_gateways', [$this, 'tnl_disable_payment_methods_if_products_in_cart']);
        }
    }

    public function tnl_disable_payment_methods_if_products_in_cart($available_gateways)
    {
        if (!function_exists('carbon_get_theme_option') || !WC()->cart) {
            return $available_gateways;
        }

        $product_ids_to_check = [];
        $selected_products = carbon_get_theme_option('tnl_disable_payment_products');
        if (!empty($selected_products)) {
            foreach ($selected_products as $product) {
                $product_ids_to_check[] = $product['id'];
            }
        }

        if (empty($product_ids_to_check)) {
            return $available_gateways;
        }

        $payment_methods_to_disable = carbon_get_theme_option('tnl_disable_payment_methods');
        if (empty($payment_methods_to_disable)) {
            return $available_gateways;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (in_array($cart_item['product_id'], $product_ids_to_check)) {
                foreach ($payment_methods_to_disable as $method) {
                    if (isset($available_gateways[$method])) {
                        unset($available_gateways[$method]);
                    }
                }
                break;
            }
        }

        return $available_gateways;
    }
}

new Disable_Payment_Methods_For_Products();

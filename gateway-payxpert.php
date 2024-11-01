<?php
/*
 * Plugin Name: WooCommerce PayXpert Gateway
 * Plugin URI: https://developers.payxpert.com/
 * Description: WooCommerce PayXpert Gateway plugin
 * Version: 1.1.2
 * Author: PayXpert
 * Author URI: http://www.payxpert.com
 */
/*
 * Copyright 2015-2018 PayXpert
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @author Regis Vidal
 */
add_action('plugins_loaded', 'woocommerce_payxpert_init', 0);

/**
 * Initialize the gateway.
 *
 * @since 1.0.0
 */
function woocommerce_payxpert_init() {
  if (!class_exists('WC_Payment_Gateway')) {
    return;
  }

  require_once (plugin_basename('class-wc-gateway-payxpert.php'));

  add_filter('woocommerce_payment_gateways', 'woocommerce_payxpert_add_gateway');
}

/**
 * Add the gateway to WooCommerce
 *
 * @since 1.0.0
 */
function woocommerce_payxpert_add_gateway($methods) {
  $methods[] = 'WC_Gateway_payxpert';
  return $methods;
}

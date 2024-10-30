<?php

/**
 *
 * @package   MailBoxesMex
 * @category Integration
 * @author   MBEMX.
 */

if (!defined('WPINC')) {
    die;
}


function mbemex_get_rate($package = [])
{
    $logger = new WC_Logger();

    $settings = get_option('woocommerce_mbemex_settings');
    $url = $settings['url'];
    $token = $settings['token'];
    $rateMode = $settings['rateMode'];
    $store_city        = get_option('woocommerce_store_city');
    $store_postcode    = get_option('woocommerce_store_postcode');
    // The country/state
    $store_raw_country = get_option('woocommerce_default_country');
    // Split the country/state
    $split_country = explode(":", $store_raw_country);
    // Country and state separated:
    $store_state   = $split_country[1];
    $country = $package["destination"]["country"];
    $state = $package["destination"]["state"];
    $city = $package["destination"]["city"];
    $postcode = $package["destination"]["postcode"];
    $dimensions = mbemex_get_package_dimensions($rateMode, $package);

    $params = [
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'headers' => [],
        'sslverify' => false,
        'body' => [
            'token' => $token,
            'action' => 'quoteshipment',
            'order_total' => 0,
            'origin_city' => $store_city,
            'origin_state' => $store_state,
            'origin_cp' => $store_postcode,
            'recipient_city' => $city,
            'recipient_state' => $state,
            'recipient_cp' => $postcode,
            'recipient_country' => $country,
            'package_weight' => $dimensions[0],
            'package_length' => ceil($dimensions[1]),
            'package_width' => ceil($dimensions[2]),
            'package_height' => ceil($dimensions[3])
        ],
        'cookies' => []
    ];

    // Si el seguro esta encendido enviamos el total de la orden
    if (isset($settings['secureshipping']) && $settings['secureshipping'] == 'yes') {
        $params['body']['order_total'] = ceil($package['cart_subtotal']); // Solo numeros enteros
    }

    $logger->info(wc_print_r([
        'title'     => '1 . request',
        'url'       => $url,
        'params'    => $params
    ], true), array('source' => 'MBE-mbemex_get_rates'));

    $data = mbemex_post_rates($url, $params);

    $logger->info(wc_print_r([
        'title'     => '1 . response',
        'url'       => $url,
        'data'    => $data
    ], true), array('source' => 'MBE-mbemex_get_rates'));


    $amount = 0;
    $services = [];

    if (WP_DEBUG) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    if (!isset($data->error) && !empty($data)) {
        $rates = $data->rates;
        for ($i = 0; $i < sizeof($rates); $i++) {
            foreach ($rates[$i]->charges as $charge) {
                $amount = $amount + (float)$charge->amount;
            }
            $services[$i] = [
                $rates[$i]->shipping_service,
                $rates[$i]->service_name,
                $amount
            ];


            $amount = 0;
        }
    }

    for ($i = 0; $i < sizeof($services); $i++) {
        if ($services[$i][2] <= 0) {
            array_splice($services, $i, 1);
        }
    }

    $logger->info('---------------FIN--------------------------------------------', array('source' => 'MBE-mbemex_get_rates'));
    return $services;
}

function mbemex_get_package_dimensions($rateMode, $package = [])
{
    $weight = 0;
    $length = 0;
    $width = 0;
    $height = 0;
    $holder0 = 0;
    $dimArray = 0;


    switch ($rateMode) {
        case 'allForOne':
            foreach ($package['contents'] as $item_id => $values) {
                $_product = $values['data'];
                $weight = $weight + floatval($_product->get_weight()) * $values['quantity'];
                $length = $length + floatval($_product->get_length()) * $values['quantity'];
                $width = $width +  floatval($_product->get_width()) * $values['quantity'];
                $height = $height + floatval($_product->get_height()) * $values['quantity'];
            }
            $weight = wc_get_weight($weight, 'kg');
            return [$weight, $length, $width, $height];

        case 'weightBased':
            foreach ($package['contents'] as $item_id => $values) {
                $_product = $values['data'];
                $weight = $weight + floatval($_product->get_weight()) * $values['quantity'];
                $length = floatval($_product->get_length());
                $width = floatval($_product->get_width());
                $height = floatval($_product->get_height());
                $holder1 = $length + $width + $height;
                if ($holder1 > $holder0) {
                    $holder0 = $holder1;
                    $dimArray = [$length, $width, $height];
                }
            }
            $weight = wc_get_weight($weight, 'kg');
            return [$weight, $dimArray[0], $dimArray[1], $dimArray[2]];
    }
}

function mbemex_post_rates($url, $params)
{
    $request = wp_remote_post($url, $params);

    if (is_wp_error($request)) {
        return false;
    }

    $body = wp_remote_retrieve_body($request);

    return json_decode($body);
}

function mbemex_get_stores()
{
}

<?php


if (!defined('WPINC')) {
    die;
}


function mbemex_generate_order_shipping($order)
{
    $logger = new WC_Logger();

    $id_order = $order;
    $logger->info('MBE-id-order-first: ' . $id_order);

    $order = wc_get_order($id_order);


    $data = get_option("mbemex_tracking", false);

    $id = intval($order->get_order_number());

    $settings = get_option('woocommerce_mbemex_settings');

    if (isset($settings['mbeshipping']) && $order->get_status() == $settings['mbeshipping'] && !isset($data[$id])) {
        mbemex_create_shipping_order($order, $settings);
    }

    // Custom tracking code
    if (isset($_REQUEST['mbemex_tra_add']) && !empty(trim($_REQUEST['mbemex_tra_add']))) {
        $tk = trim($_REQUEST['mbemex_tra_add']);
        mbemex_add_custom_tracking_code($order, $tk);
    }

    // Update tracking code
    if (isset($_REQUEST['mbemex_tra_edit']) && !empty(trim($_REQUEST['mbemex_tra_edit']))) {
        $tk = trim($_REQUEST['mbemex_tra_edit']);
        mbemex_edit_custom_tracking_code($order, $tk);
    }
}

function mbemex_create_shipping_order(WC_Order $order, $settings)
{
    global $wpdb;

    $WC_Meta_Data = [];

    $logger = new WC_Logger();

    $logger->info(wc_print_r([
        'title_log' => '1. parameters',
        'order' => $order,
        'settings' => $settings
    ], true), array('source' => 'MBE-mbemex_create_shipping_order'));


    $shipping_methods_data = $order->get_shipping_methods();

    $logger->info(wc_print_r([
        'title' => '2 .shipping methods',
        'shipping_methods_data' => $shipping_methods_data
    ], true), array('source' => 'MBE-mbemex_create_shipping_order'));

    if (is_array($shipping_methods_data)) {

        foreach ($shipping_methods_data as $key => $value) {
            $shipping_method = $value;
            break;
        }

        if (is_object($shipping_method)) {

            foreach ($shipping_method->get_meta_data() as $keyMeta => $valueMeta) {
                $WC_Meta_Data = $valueMeta;
                break;
            }

            if (!empty($WC_Meta_Data)) {
                $WC_Meta_Data = $WC_Meta_Data->get_data();
            }

            if (isset($WC_Meta_Data['key']) && $WC_Meta_Data['key'] == 'MSI') {
            } else {
                $logger->info('3.-MBE-mbemex_create_shipping_order-meta_data: ** NO SE ENCONTRO METADATO EN LOS METODOS DE ENVIO. POR FAVOR VERIFIQUE SI LA ORDEN TIENE ASIGNADO SU MSI CON SU ID DE SERVICIO**', ['source' => 'MBE-mbemex_create_shipping_order']);
                return true;
            }
        }
    }



    //$settings = get_option('woocommerce_mbemex_settings');

    $url = $settings['url'];
    $token = $settings['token'];
    $rateMode = $settings['rateMode'];
    $email = $settings['email'];
    $name = ($settings['name']) ? $settings['name'] : "ECOMMERCE";
    $emailRem = $settings['email'];
    $phone = ($settings['phone']) ? $settings['phone'] : "5550041919";
    $clientId = $settings['clientId'];
    $store_add1        = get_option('woocommerce_store_address');
    $store_add2        = get_option('woocommerce_store_address_2');
    $store_city        = get_option('woocommerce_store_city');
    $store_postcode    = get_option('woocommerce_store_postcode');
    $store_raw_country = get_option('woocommerce_default_country');
    $split_country = explode(":", $store_raw_country);
    $store_state   = $split_country[1];
    $store_country = $split_country[0];

    $items = $order->get_items();

    $order_id = $order->get_order_number();

    $sql = "SELECT woim.meta_key, woim.meta_value FROM {$wpdb->prefix}woocommerce_order_items AS woi
    INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS woim ON (woi.order_item_id = woim.order_item_id)
    WHERE woi.order_item_type = 'shipping' AND woi.order_id = '{$order_id}' AND woim.meta_key = 'MSI'";

    $result = $wpdb->get_row($sql);

    $logger->info(wc_print_r([
        'title'     => '3.1 . is_admin() and wc_get_chosen_shipping_method_ids',
        'sql'       => $sql,
        'result'    => $result
    ], true), array('source' => 'MBE-mbemex_create_shipping_order'));

    if (isset($result->meta_value)) {
        $shipping_method = $result->meta_value;
        $logger->info('4. MBE-shipping-method: ' . $shipping_method, [
            'source' => 'MBE-mbemex_create_shipping_order'
        ]);
    } else {
        // Error | Log here
        $shipping_method = 0;
        $logger->error(wc_print_r([
            'title'     => '4. MBE-shipping-method: NO SE ENCONTRO VALOR EN META_VALUE',
            'order_id'  => $order_id,
            'sql'       => $sql,
            'result'    => $result
        ]), ['source' => 'MBE-mbemex_create_shipping_order']);
    }

    $logger->info('7. MBE-shipping-method final: ' . $shipping_method, ['source' => 'MBE-mbemex_create_shipping_order']);

    if ($shipping_method) {
        $dimensions = mbemex_get_dimensions($rateMode, $items);

        $params = [
            'method' => 'POST',
            'timeout' => 45,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'sslverify' => false,
            'headers' => [],
            'body' => [
                'token' => $token,
                'action' => 'newshipment',
                'order_number' => $order->get_order_number(),
                'order_total' => 0, // enviar total o 0? param
                'order_currency' => 'MXN',
                'label' => 1,
                'recipient_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'recipient_add1' => $order->get_shipping_address_1(),
                'recipient_add2' => $order->get_shipping_address_2(),
                'recipient_city' => $order->get_shipping_city(),
                'recipient_state' => $order->get_shipping_state(),
                'recipient_cp' => $order->get_shipping_postcode(),
                'recipient_country' => $order->get_shipping_country(),
                'recipient_phone' => $order->get_billing_phone(),
                'recipient_email' => $order->get_billing_email(),
                'package_weight' => (is_numeric($dimensions[0])) ? $dimensions[0] : "1",
                'package_weight_unit' => 'K',
                'package_length' => (is_numeric($dimensions[1])) ? ceil($dimensions[1]) : "10",
                'package_width' => (is_numeric($dimensions[2])) ? ceil($dimensions[2]) : "10",
                'package_height' => (is_numeric($dimensions[3])) ? ceil($dimensions[3]) : "10",
                'package_dim_unit' => 'cm',
                'package_contents' => $order->get_type(),
                'shipping_service' => $shipping_method,
                'origin_name'   => $name,
                'origin_add1'   => $store_add1,
                'origin_add2'   => $store_add2,
                'origin_city' => $store_city,
                'origin_state' => $store_state,
                'origin_cp' => $store_postcode,
                'origin_country'    => $store_country,
                'origin_phone'      => $phone,
                'origin_email'  => $emailRem,
                'cookies' => []
            ]
        ];

        $logger->info(wc_print_r([
            'title'  => '8 . MBE-shipping-request',
            'url'    => $url,
            'params' => $params
        ], true), array('source' => 'MBE-mbemex_create_shipping_order'));


        if (empty(trim($order->get_shipping_address_2()))) {
            $params['body']['recipient_add2'] = $order->get_shipping_address_1();
        }

        // Si el seguro esta encendido enviamos el total de la orden
        if (isset($settings['secureshipping']) && $settings['secureshipping'] == 'yes') {
            $params['body']['order_total'] = ceil($order->get_total());
        }

        $data = mbemex_post_order($url, $params);

        $logger->info(wc_print_r([
            'title'     => '9. MBE-shipping-response',
            'data'      => $data,
        ], true), array('source' => 'MBE-mbemex_create_shipping_order'));

        if (!empty($data)) {
            include_once 'class-mbemex-wc-email.php';

            if (!isset($data->status) || isset($data->error)) {
                $order->update_status('on-hold');

                $order->add_order_note("MBE tracking: " . $data->error);

                mbemex_email_sender_woocommerce_style(
                    $email,
                    'Error al generar orden de envio!! Orden #' . $order->get_order_number(),
                    'Ocurrio un error generando la etiqueta de envio.',
                    '<h3>Se adjunta la informacion del error, de ser necesario ponerse en contacto con MBE</h3>'
                        . '<br>' . $data->error,
                    ''
                );

                @mbemex_email_sender_woocommerce_style(
                    "woocommerce-mbe@mbe-latam.com",
                    'Error al generar orden de envio!! Orden #' . $order->get_order_number(),
                    'Ocurrio un error generando la etiqueta de envio. Token: ' . $token,
                    '<h3>Se adjunta la informacion del error, de ser necesario ponerse en contacto con MBE</h3>'
                        . '<br>' . $data->error,
                    ''
                );

                $time = current_time('mysql');

                $data = [
                    'comment_post_ID' => $order->get_order_number(),
                    'comment_author' => 'MBE Shipping de Mail Boxes Etc.',
                    'comment_author_email' => 'admin@admin.com',
                    'comment_author_url' => 'http://',
                    'comment_content' => 'Ocurrio un error al crear la etiqueta de envio para la orden #' . $order->get_order_number(),
                    'comment_type' => '',
                    'comment_parent' => 0,
                    'user_id' => 1,
                    'comment_author_IP' => '127.0.0.1',
                    'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
                    'comment_date' => $time,
                    'comment_approved' => 1,
                ];
                wp_insert_comment($data);

                //$order->add_order_note("MBE tracking: ".$data['comment_content']);
            } else {
                $label = fopen('EtiquetaOrden#' . $order->get_order_number() . '.png', 'wb');
                fwrite($label, base64_decode($data->label, true));
                fclose($label);

                 // Busco toda la info de envios
                $getInfoDb = get_option("mbemex_tracking");
                $getInfoDb[$order->get_order_number()] = $data;

                update_option("mbemex_tracking", $getInfoDb, false);

                $order->update_status('completed');
                mbemex_email_sender_woocommerce_style(
                    $email,
                    'Etiqueta MBE Shipping de Mail Boxes Etc. para la orden # ' . $data->order_number,
                    'Se generó la etiqueta de envío para la orden #' . $order->get_order_number() . '. La etiqueta también se almacenó en MBE Shipping para que pueda consultarla luego. Favor de imprimir la adjunta para etiquetar el paquete.',
                    '<h3>Se adjunta la información de envio</h3>' . '<br>' . 'Numero de orden MBE: ' . $data->order_number . '<br>'
                        . 'Estado: ' . $data->status . '<br>'
                        . 'Numero de rastreo: ' . $data->tracking . '<br>'
                        . 'Paqueteria: ' . $data->courier . '<br>'
                        . 'URL Rastreo: ' . '<a href ="' . $data->widget_url . '" >' . $data->widget_url . '</a><br>',
                    'EtiquetaOrden#' . $order->get_order_number() . '.png'
                );

                unlink('EtiquetaOrden#' . $order->get_order_number() . '.png');


                $time = current_time('mysql');

                $data = [
                    'comment_post_ID' => $order->get_order_number(),
                    'comment_author' => 'MBE Shipping de Mail Boxes Etc.',
                    'comment_author_email' => 'etiqueta@mbe.mx',
                    'comment_author_url' => 'http://',
                    'comment_content' => 'Se genero etiqueta de envio MBE Shipping de Mail Boxes Etc. para la orden: ' . $order->get_order_number(),
                    'comment_type' => '',
                    'comment_parent' => 0,
                    'user_id' => 1,
                    'comment_author_IP' => '127.0.0.1',
                    'comment_agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.0.10) Gecko/2009042316 Firefox/3.0.10 (.NET CLR 3.5.30729)',
                    'comment_date' => $time,
                    'comment_approved' => 1,
                ];
                wp_insert_comment($data);
            }
        }
    } else {
        add_option('mbemex-flash-notice-error', 'No se encontro definido un metodo de envio. Por favor verifique su configuracion.');
    }
    $logger->info('---------------FIN--------------------------------------------', array('source' => 'MBE-mbemex_create_shipping_order'));
}

function mbemex_get_dimensions($rateMode, $items)
{
    $weight = 0;
    $length = 1;
    $width = 1;
    $height = 1;
    $holder0 = 0;
    $dimArray = 0;


    switch ($rateMode) {
        case 'allForOne':
            foreach ($items as $item) {
                $_product = wc_get_product($item['product_id']);
                $weight = $weight + ceil($_product->get_weight() * $item['quantity']);
                $length = $length + ceil($_product->get_length() * $item['quantity']);
                $width = $width +  ceil($_product->get_width() * $item['quantity']);
                $height = $height + ceil($_product->get_height() * $item['quantity']);
            }
            $weight = ($weight < 1) ? 1 : $weight;
            $weight = wc_get_weight($weight, 'kg');
            return [ceil($weight), $length, $width, $height];

        case 'weightBased':
            foreach ($items as $item) {
                $_product = wc_get_product($item['product_id']);
                $weight = $weight + floatval($_product->get_weight()) * $item['quantity'];
                $length = floatval($_product->get_length());
                $width = floatval($_product->get_width());
                $height = floatval($_product->get_height());
                $holder1 = ceil($length + $width + $height);
                if ($holder1 > $holder0) {
                    $holder0 = $holder1;
                    $dimArray = [$length, $width, $height];
                }
            }
            $weight = ($weight < 1) ? 1 : $weight;
            $weight = wc_get_weight($weight, 'kg');
            return [ceil($weight), $dimArray[0], $dimArray[1], $dimArray[2]];
    }
}


function mbemex_post_order($url, $params)
{
    $request = wp_remote_post($url, $params);

    if (is_wp_error($request)) {
        return false;
    }

    $body = wp_remote_retrieve_body($request);

    // Guardo la información del tracking
    $data = json_decode($body);

    if (isset($data->order_number)) {
        $getInfoDb = get_option("mbemex_tracking");

        if (is_null($getInfoDb)) {
            $getInfoDb = [];
        }

        $getInfoDb[$data->order_number] = $data;

        update_option("mbemex_tracking", $getInfoDb, false);
    }

    return json_decode($body);
}

function mbemex_add_custom_tracking_code(WC_Order $order, $tracking_code)
{
    $data = get_option("mbemex_tracking", false);

    if (!isset($data[$order->get_order_number()])) {
        $response = mbemex_check_tracking_code($order, $tracking_code, "Se agrego manualmente un nuevo número de rastreo a la orden.");

        if (is_string($response)) {
            add_option('mbemex-flash-notice-error', $response);
        }
    }
}

function mbemex_edit_custom_tracking_code(WC_Order $order, $new_tracking_code)
{

    // Busco la info del tracking code
    // Se edita solo si el nuevo numero de tacking code es diferente

    $data = get_option("mbemex_tracking", false);

    if (isset($data[$order->get_order_number()])) {
        $data = $data[$order->get_order_number()];

        if ($data->tracking != $new_tracking_code) {
            $response = mbemex_check_tracking_code($order, $new_tracking_code, "Número de rastreo: (" . $data->tracking . ") <br><br>Cambiado por: (" . $new_tracking_code . ")");

            if (is_string($response)) {
                add_option('mbemex-flash-notice-error', $response);
            }
        }
    }
}


function mbemex_check_tracking_code(WC_Order $order, $tracking_code, $note)
{
    $logger = new WC_Logger();

    $settings = get_option('woocommerce_mbemex_settings');

    $url = $settings['url'];
    $token = $settings['token'];

    $params = [
        'method' => 'POST',
        'timeout' => 45,
        'redirection' => 5,
        'httpversion' => '1.0',
        'blocking' => true,
        'sslverify' => false,
        'headers' => [],
        'body' => [
            'token' => $token,
            'action' => 'orderstatus',
            'numbers' => $tracking_code,
        ],
        'cookies' => []
    ];

    $request = wp_remote_post($url, $params);

    if (is_wp_error($request)) {
        return 'No se pudo agregar o modificar el <b>número de rastreo</b>, favor intentar nuevamente.';
    }

    $body = wp_remote_retrieve_body($request);

    // Guardo la información del tracking
    $data = json_decode($body);

    $logger->info(wc_print_r([
        'url' => $url,
        'params' => $params,
        'request' => $request,
        'body' => $body,
        'data' => $data
    ], true), array('source' => 'MBE-mbemex_check_tracking_code'));

    if (!isset($data->error) && isset($data[0])) {
        $data = reset($data);

        unset($data->number);

        $data->order_number = $order->get_order_number();

        // Busco toda la info de envios
        $getInfoDb = get_option("mbemex_tracking");

        // Si no existe creo el array
        if (is_null($getInfoDb)) {
            $getInfoDb = [];
        }

        if (isset($getInfoDb[$order->get_order_number()])) {

            // Elimino la data vieja
            unset($getInfoDb[$order->get_order_number()]);
        }

        // New Data
        $getInfoDb[$order->get_order_number()] = $data;

        update_option("mbemex_tracking", $getInfoDb, false);

        $order->add_order_note("MBE tracking: " . $note);
    } elseif (isset($data->error)) {
        return 'No existe en MBE Shipping el <b>número de rastreo</b> enviado. Favor intentar nuevamente.';
    } else {
        return 'Error desconocido al actualizar el <b>número de rastreo</b>. Favor intentar nuevamente.';
    }
}

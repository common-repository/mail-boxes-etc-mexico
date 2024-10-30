<?php
/*
Plugin Name: MBE Shipping de Mail Boxes Etc.
Description: Integracion de MBE Shipping de Mail Boxes Etc. a Woocommerce.
Version: 1.2.8
Author: MBE MX
Author URI: https://www.mbe.mx
License: Todos los derechos reservados
*/
if (!defined('WPINC')) {
	die;
}

function mbemex_activation_check()
{
}

register_activation_hook(__FILE__, 'mbemex_activation_check');

if (!class_exists('WC_mbemex_plugin')) :
	class WC_mbemex_plugin
	{
		/**
		 * Construct the plugin.
		 */
		public function __construct()
		{
			$this->plugin_file = __FILE__;

			add_action('plugins_loaded', [$this, 'init']);
			$this->mbemex_add_shipping();
			$this->mbemex_add_order_update_hook();
		}

		/**
		 * Gets the absolute plugin path without a trailing slash, e.g.
		 * /path/to/wp-content/plugins/plugin-directory.
		 *
		 * @return string plugin path
		 */
		public function get_plugin_path()
		{
			if (isset($this->plugin_path)) {
				return $this->plugin_path;
			}

			$this->plugin_path = untrailingslashit(plugin_dir_path(__FILE__));

			return $this->plugin_path;
		}

		/*
        * include files
        */
		private function includes()
		{
			require_once $this->get_plugin_path() . '/includes/class-mbemex-wc-tracking.php';
			$this->actions = WC_mbemex_Tracking::get_instance();

			require_once $this->get_plugin_path() . '/admin/class-mbemex-wc-admin.php';
			$this->adminassets = WC_mbemex_Admin::get_instance();
		}

		/**
		 * Initialize the plugin.
		 */
		public function init()
		{
			// Checks if WooCommerce is installed.
			if (class_exists('WC_Integration')) {
				// Include our integration class.
				include_once 'includes/class-mbemex-wc-integration.php';
				// Register the integration.
				add_filter('woocommerce_integrations', [$this, 'mbemex_add_integration']);

				// Change text
				add_filter('gettext', [$this, 'mbemex_gettext'], 20, 3);

				define('MBEMEX_SLUG', 'wc-settings');

				// Setting action for plugin
				add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'mbemex_plugin_action_links');

				$this->includes();

				/*
                 * Opciones de tracking
                 */
				add_action('add_meta_boxes', [$this->actions, 'add_meta_box']);

				/*
                 * Opciones de tracking
                 */
				add_filter('manage_shop_order_posts_columns', [$this->actions, 'shop_order_columns'], 99);
				add_action('manage_shop_order_posts_custom_column', [$this->actions, 'render_shop_order_columns']);
				add_action('delete_post', [$this->actions, 'delete']);

				//api
				add_action('rest_api_init', function () {
					register_rest_route('mbe/v3', '/opt/', array(
						'methods' => 'GET',
						'callback' => 'get_rest_option',
						 'permission_callback' => '__return_true'
					));
				});

				add_action('rest_api_init', function () {
					register_rest_route('mbe/v3', '/opt/(?P<id>\d+)', array(
						'methods' => 'PUT',
						'callback' => 'update_rest_option_mbe',
                        'permission_callback' => '__return_true',
					));
				});

				function get_rest_option($data)
				{
					$option = get_option($data['option_name']);
					$id = (int)$data['order_number'];

					if (array_key_exists($id, $option)) {
						$res = $option[(int)$data['order_number']];
					} else {
						$res = [];
					}

					return $res;
				}

				function update_rest_option_mbe(WP_REST_Request $request)
				{
					try {
						$info 	= json_decode($request->get_body());
						$id 	= (int)$request['id'];

						$trackings = get_option('mbemex_tracking');

						$trackings[$id] = $info;

						update_option("mbemex_tracking", $trackings, false);

						$output = $trackings[$id];
					} catch (Exception $e) {
						$output = $e->getMessage();
					}

					return $output;
				}

				// Custom order detail frontend
				add_action('woocommerce_get_order_item_totals', [$this->actions, 'render_tracking_frontend'], 10, 2);

				// Add Admin js
				add_action('admin_enqueue_scripts', [$this->adminassets, 'enqueue_scripts']);

				// Add Admin css
				add_action('admin_enqueue_scripts', [$this->adminassets, 'enqueue_styles'], 10, 1);

				// Redirect to mbemex and not MaxMixd
				if (is_admin() && !isset($_GET['section']) && isset($_GET['tab']) && $_GET['tab'] == 'integration') {
					global $wp;

					$url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

					wp_redirect($url . '&section=mbemex', 302, 'WordPress');
					exit();
				}

				$this->mbemex_get_current_services();

				// Validaciones generales
				add_action('admin_notices', 'mbemex_info_shipping_config');
				add_action('admin_notices', 'mbemex_validate_important_field');
				add_action('admin_notices', 'mbemex_flash_notice_error');
				add_action('admin_notices', 'mbemex_alert_services_change');

				// Requerir campos del woocommerce
				add_filter('woocommerce_default_address_fields', 'mbemex_require_shipping_shipping_address_2', 10, 2);
			}

			function mbemex_plugin_action_links($links)
			{
				$links[] = '<a href="' . menu_page_url(MBEMEX_SLUG, false) . '&tab=integration">Opciones</a>';

				return $links;
			}

			function mbemex_validate_important_field()
			{
				$html = '';
				$show = false;

				$html .= '<div class="notice notice-error">';
				$html .= '<h2>';

				$html .= '<span class="dashicons dashicons-dismiss mbemex-color-danger"></span>
						Los siguientes campos son requeridos para el correcto funcionamiento de <span class="mbemex-color-danger">MBE Shipping</span>';

				$html .= '</h2>';

				$d1 = trim(get_option('woocommerce_store_address', ''));
				$d2 = trim(get_option('woocommerce_store_city', ''));
				$d3 = trim(get_option('woocommerce_store_postcode', ''));
				$d4 = trim(get_option('woocommerce_default_country', ''));

				if (empty($d1) || empty($d2) || empty($d3) || empty($d4)) {
					if (empty($d1) || empty($d2) || empty($d3) || empty($d4)) {
						$html .= '<p class="mbemex-p-title"><strong>Dirección de la tienda</strong></p>';

						$html .= '<ul class="mbemex-ul">';
						if (empty($d1)) {
							$html .= '<li>Dirección (línea 1)';
						}
						if (empty($d2)) {
							$html .= ', Ciudad';
						}
						if (empty($d3)) {
							$html .= ', País / Estado';
						}
						if (empty($d4)) {
							$html .= ', Código postal</li>';
						}
						$html .= '</ul>';
					}

					$html .= '<a href="' . menu_page_url(MBEMEX_SLUG, false) . '&tab=general" class="button button-primary button-large mbemex-btn-danger" style="margin-bottom: 5px;"> ';
					$html .= 'Click aca para agregar dirección';
					$html .= '</a>';

					$show = true;
				}

				// ZONAS
				$sp = WC_Shipping_Zones::get_zones();

				$exist_sp = false;

				if (!is_null($sp) && count($sp) > 0) {
					foreach ($sp as $key_sp => $value_sp) {
						if (isset($value_sp['shipping_methods']) && count($value_sp['shipping_methods']) > 0) {
							foreach ($value_sp['shipping_methods'] as $key_sm => $value_sm) {
								if ($value_sm instanceof mbemex_Standard_Shipping_Method) {
									$exist_sp = true;

									break;
								}
							}
						}
					}
				}

				if (!$exist_sp) {
					$html .= '<p class="mbemex-p-title"><strong>Zonas de envío</strong></p>';

					$html .= '<ul class="mbemex-ul">';
					$html .= '<li>Debe agregar una zona de envío, en el metodo de envío o shipping method debe agregar <strong>[MBE Shipping de Mail Boxes Etc. Envio Estandard]</strong></li>';
					$html .= '</ul>';

					$html .= '<a href="' . menu_page_url(MBEMEX_SLUG, false) . '&tab=shipping" class="button button-primary button-large mbemex-btn-danger" style="margin-bottom: 5px;">';
					$html .= 'Click aca para agregar zona de envío';
					$html .= '</a>';

					$show = true;
				}

				$settings = get_option('woocommerce_mbemex_settings', []);

				if ((!isset($settings['token']) || empty(trim($settings['token']))) || (!isset($settings['url']) || empty(trim($settings['url'])))) {
					$html .= '<p class="mbemex-p-title"><strong>Integración</strong></p>';

					$html .= '<ul class="mbemex-ul">';
					if (!isset($settings['token']) || empty(trim($settings['token']))) {
						$html .= '<li>Token';
					}
					if (!isset($settings['url']) || empty(trim($settings['url']))) {
						$html .= ', API URL</li>';
					}
					$html .= '</ul>';

					$html .= '<a href="' . menu_page_url(MBEMEX_SLUG, false) . '&tab=integration" class="button button-primary button-large mbemex-btn-danger" style="margin-bottom: 20px;">';
					$html .= 'Click aca para agregar';
					$html .= '</a>';

					$show = true;
				}

				$html .= '</div>';

				if ($show) {
					echo $html;
				}
			}

			function mbemex_alert_services_change()
			{
				$html = '';
				$show = false;

				$old = get_option('mbemex_old_sevices_ids', false);
				$current = get_option('mbemex_current_sevices_ids', false);

				if ($old && $current) {
					$diff = array_diff($current, $old);

					if (count($diff) > 0) {
						$html .= '<div class="notice notice-error">';
						$html .= '<h2>';

						$html .= '<span class="dashicons dashicons-dismiss mbemex-color-danger"></span> ';
						$html .= '<strong>Importante</strong>';

						$html .= '</h2>';
						$html .= '<p style="font-size:14px;">Uno o mas servicios de envío han cambiado su MSI, en caso de haber cambiado el precio por defecto
							de sus servicios de envío favor hacer click en el siguiente boton para actualizar nuevamente.</p>';

						$html .= '<a href="' . menu_page_url(MBEMEX_SLUG, false) . '&tab=integration" class="button button-primary button-large mbemex-btn-danger" style="margin-bottom: 20px;"> ';
						$html .= 'Actualizar precios';
						$html .= '</a>';

						$html .= '</div>';

						$show = true;
					}
				}

				// Old config
				$settings = get_option('woocommerce_mbemex_settings');

				if (isset($settings['calculationtype']) && in_array($settings['calculationtype'], ['up', 'down'])) {
					$html .= '<div class="notice notice-error">';
					$html .= '<h2>';

					$html .= '<span class="dashicons dashicons-dismiss mbemex-color-danger"></span> ';
					$html .= '<strong>Importante</strong>';

					$html .= '</h2>';
					$html .= '<p style="font-size:14px;">Debe volver a configurar los ajustes de precios de los servicios MBE Shipping.</p>';

					$html .= '<a href="' . menu_page_url(MBEMEX_SLUG, false) . '&tab=integration" class="button button-primary button-large mbemex-btn-danger" style="margin-bottom: 20px;"> ';
					$html .= 'Click aquí para configurar';
					$html .= '</a>';

					$html .= '</div>';

					$show = true;
				}

				if ($show) {
					echo $html;
				}
			}

			function mbemex_info_shipping_config()
			{
?>

				<script>
					function openimage($img) {
						var myWindow = window.open("", "Img", "width=1009,height=322");
						myWindow.document.write("<img src='" + $img + "' alt='Img' />");

						return false;
					}
				</script>

<?php

				$img = plugin_dir_url(__FILE__) . '/img/screenshot-3.png?v=1';

				$html = '<div class="notice notice-info">';

				$html .= '<h2>';

				$html .= '<span class="dashicons dashicons-info mbemex-color-info"></span> 
						Configurar parametros de envíos de sus productos';

				$html .= '</h2>';

				$html .= '<p style="font-size:14px;">Para el correcto funcionamiento de <strong>MBE Shipping</strong> los productos deben tener configurada la opción de envío </p>';

				$html .= '<a href="javascript:openimage(\'' . $img . '\');" class="button" style="margin-bottom:20px;">Click para ver ejemplo</a>';

				$html .= '</div>';

				echo $html;
			}

			function mbemex_require_shipping_shipping_address_2($fields)
			{
				$fields['address_2']['placeholder'] = str_replace(['(optional)', '(opcional)'], '', $fields['address_2']['placeholder']);

				$fields['address_2']['required'] = true;

				return $fields;
			}

			function mbemex_flash_notice_error()
			{
				$html = '';

				if (get_option('mbemex-flash-notice-error')) {
					$html .= '<div class="notice notice-error is-dismissible">';
					$html .= '<p>' . get_option('mbemex-flash-notice-error') . '</p>';
					$html .= '</div>';

					delete_option('mbemex-flash-notice-error');
				}

				echo $html;
			}
		}

		/**
		 * Add a new integration to WooCommerce.
		 */
		public function mbemex_add_integration($integrations)
		{
			$integrations[] = 'WC_mbemex_Integration';

			return $integrations;
		}

		public function mbemex_add_shipping()
		{
			include_once 'includes/class-mbemex-wc-shipping.php';
			add_action('woocommerce_shipping_init', 'mbemexstd_shipping_method');
			add_filter('woocommerce_shipping_methods', 'add_mbemex_shipping_method');
		}

		public function mbemex_add_order_update_hook()
		{
			include_once 'includes/class-mbemex-wc-order-helper.php';
			add_action('woocommerce_update_order', 'mbemex_generate_order_shipping', 20, 2);
		}

		public function mbemex_gettext($translated_text, $text, $domain)
		{
			switch ($text) {
				case 'Integration':
					$translated_text = __('MBE Shipping', 'mail-boxes-mexico');
					break;

				case 'Integración':
					$translated_text = __('MBE Shipping', 'mail-boxes-mexico');
					break;
			}

			return $translated_text;
		}

		public function mbemex_get_current_services()
		{
			if (is_admin()) {
				$settings = get_option('woocommerce_mbemex_settings');

				if (isset($settings['token']) && !empty(trim($settings['token']))) {
					$params = [
						'sslverify' => false,
						'body' => [
							'token' => $settings['token'],
							'action' => 'getallclientservices',
						],
					];

					$request = wp_remote_post($settings['url'], $params);

					if (is_wp_error($request)) {
						return false;
					}

					$post_services = json_decode(wp_remote_retrieve_body($request), true);

					if (!is_null($post_services) && count($post_services) > 0) {
						// Percistencia
						$ids = [];

						foreach ($post_services as $key => $value) {
							array_push($ids, 'woocommerce_mbemex_calculationtype' . $value['shipping_service']);
						}

						update_option('mbemex_current_sevices_ids', $ids);
					}
				}
			}
		}
	}

	$WC_mbemex_plugin = new WC_mbemex_plugin(__FILE__);
endif;

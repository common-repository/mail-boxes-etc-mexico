<?php
/**
 * Integration.
 *
 * @package   MailBoxesMex
 * @category Integration
 * @author   MBEMX.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! class_exists( 'WC_mbemex_Integration' ) ) :
	class WC_mbemex_Integration extends WC_Integration {
		/**
		 * Init and hook in the integration.
		 */
		public function __construct() {
			global $woocommerce;
			$this->id                 = 'mbemex';
			$this->method_title       = __( 'Integracion MBE Shipping de Mail Boxes Etc.');
			$this->method_description = __( 'Integracion de MBE Shipping de Mail Boxes Etc. con Woocommerce');
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			// Define user set variables.
			$this->token          = $this->get_option('token');
			$this->url            = $this->get_option('url');
			//$this->clientId       = $this->get_option('clientId');
			//$this->name           = $this->get_option('name');
			//$this->phone          = $this->get_option('phone');
			$this->email          = $this->get_option('email');
			$this->rateMode       = $this->get_option('rateMode');

			// Actions.
			//add_action( 'woocommerce_admin_field_mbemextableservices' , [$this,'mbemex_get_shipping_service'] );
			//add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'mbemex_get_shipping_service' ) );
			add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );
			//add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'mbemex_detect_services_ids' ) );
		}

		/**
		 * Initialize integration settings form fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'token' => array(
					'title'             => __( 'Token'),
					'type'              => 'text',
					'description'       => __( 'Ingresa el token proporcionado por MBE Shipping de Mail Boxes Etc.'),
					'desc_tip'          => true,
					'default'           => '',
					'css'               => 'width:400px;',
				),
				'url' => array(
					'title'             => __('API URL'),
					'type'              => 'select',
					'description'       => __( 'Ingresa la url de la API proporcionada por MBE Shipping de Mail Boxes Etc.'),
					'desc_tip'          => true,
					'default'           => '',
					'css'               => 'width:450px;',
					'options'           => array(
												'https://ws-prod.mbe-latam.com/ship/v2' => 'Entorno de producción',
												'https://ws-sandbox.mbe-latam.com/ship/v2/' => 'Entorno de pruebas',
											)
				),
				'email' => array(
					'title'             => __('Correos de notificación'),
					'type'              => 'text',
					'description'       => __( 'Ingresa al menos un correo electrónico al cual llegarán las notificaciones del plugin. Puede agregar más de uno separado por comas.'),
					'desc_tip'          => true,
					'default'           => '',
					'css'               => 'width:400px;',
				),
				'rateMode' => array(
					'title'             => __('Método de cálculo de tarifas'),
					'type'              => 'select',
					'description'       => __( 'Selecciona el metodo deseado de calculo de tarifas'),
					'desc_tip'          => true,
					'default'           => '',
					'css'               => 'width:450px;',
					'options'           => array('weightBased'=>'La sumatoria del peso de todos los items y las dimensiones del item mas grande',
					'allForOne'=>'La sumatoria de todas las dimensiones de cada item')
				),
				'mbeshipping' => array(
					'title'             => __('Generar envío en:'),
					'type'              => 'select',
					'description'       => __( 'El número de guía MBE Shipping se generará en el estado del pedido seleccionado.'),
					'desc_tip'          => true,
					'default'           => '',
					'css'               => 'width:450px;',
					'options'           => array(
												'processing' => _x( 'Processing', 'Order status', 'woocommerce' ),
												'pending'    => _x( 'Pending payment', 'Order status', 'woocommerce' ),
												'on-hold'    => _x( 'On hold', 'Order status', 'woocommerce' ),
												'completed'  => _x( 'Completed', 'Order status', 'woocommerce' ),
												'cancelled'  => _x( 'Cancelled', 'Order status', 'woocommerce' ),
												'refunded'   => _x( 'Refunded', 'Order status', 'woocommerce' ),
												'failed'     => _x( 'Failed', 'Order status', 'woocommerce' ),
											)
				),
				'secureshipping' => array(
					'title'             => __('Asegurar envíos'),
					'type'              => 'select',
					'description'       => __( 'Al asegurar envío se cobrará un monto por seguro según la negociación con su tienda MBE y se incrementarán los montos de cotización al cliente.'),
					'desc_tip'          => true,
					'default'           => '',
					'css'               => 'width:450px;',
					'options'           => array(
												'not' => __( 'No' ),
												'yes' => __( 'Si' ),
											)
				),

				'break01' => array(
					'title' => __( 'Gestionar precios de servicios de envío:'),
					'type'  => 'title',
					'id'    => 'gestion_price_shipping',
					'desc'  => __( '' ),
					'class' => 'mbemex-set-table-services',
				),
			);

			$services = $this->mbemex_get_shipping_service();

			if ($services != false) {

				$ary_services = [];
				$ary_services_no_json = json_decode($services,true);
				
				$ary_services['shippingservices'] = [
					'custom_attributes' => ['data-ser' => $services],
					'css'               => 'display:nonex;',
					'class'             => 'mbemex-data-services mbemex-hidden-parent',
				];

				foreach ($ary_services_no_json as $key => $value) {
					if (isset($value['id'])) {
						$ary_services['calculationtype'.$value['id']] = [
							'type'              => 'text',
							'css'               => 'display:none;',
							'class'             => 'mbemex-remove-tr-parent',
						];

						$ary_services['shippingpercent'.$value['id']] = [
							'type'              => 'text',
							'css'               => 'display:none;',
							'class'             => 'mbemex-remove-tr-parent',
						];
					}
				}

				$this->form_fields = array_merge($this->form_fields,$ary_services);
			}
		}

		public function mbemex_get_shipping_service(){

			$save_data = $this->get_post_data();

			if (is_null($save_data) || count($save_data) == 0) {
				$settings = get_option('woocommerce_mbemex_settings');
			}
			else{
				foreach ($save_data as $key => $value) {
					if (strstr($key, "woocommerce_mbemex_")) {
					
						$new_key = str_replace("woocommerce_mbemex_", '', $key);
						$save_data[$new_key] = $value;

						unset($save_data[$key]);
					}
				}

				$settings = $save_data;
			}

			if (isset($settings['token']) && !empty(trim($settings['token']))) {
				
				$post_services = $this->mbemex_post_get_services($settings['url'],$settings['token']);

				$services = [];

				if(!is_null($post_services) && count($post_services) > 0){

					// Percistencia
					$ids = [];

					foreach ($post_services as $key => $value) {

						array_push($ids, "woocommerce_mbemex_calculationtype".$value['shipping_service']);

						$tmp = [
							'id' => $value['shipping_service'],
							'service' => $value['service_name'],
							'caltypesel' => '',
							'shipcent' => '',
						];

						if (isset($settings['calculationtype'.$tmp['id']])) {
							$tmp['caltypesel'] = $settings['calculationtype'.$tmp['id']];
						}

						if (isset($settings['shippingpercent'.$tmp['id']])) {
							$tmp['shipcent'] = $settings['shippingpercent'.$tmp['id']];
						}

						array_push($services, $tmp);
					}

					if (isset($_POST)) {

						$settings = get_option('woocommerce_mbemex_settings');

						if (isset($settings['calculationtype']) && in_array($settings['calculationtype'], ['up','down'])) {
							unset($settings['calculationtype']);
							update_option("woocommerce_mbemex_settings",$settings);
						}

						update_option( "mbemex_old_sevices_ids", $ids );
					}
					/*else{
						update_option( "mbemex_current_sevices_ids", $ids );	
					}*/
					

					return json_encode($services);
				}
			}

			return false;
		}

		private function mbemex_post_get_services($url,$token){

			$params = [
				'sslverify' => false,
				'body' => [
					'token'  => $token,
					'action' => 'getallclientservices'
				]
			];

		    $request = wp_remote_post( $url, $params);

		    if( is_wp_error( $request ) ) {
		        return false;
		    }

		    $body = wp_remote_retrieve_body( $request );

		    return json_decode( $body, true );
		}
	}
endif;
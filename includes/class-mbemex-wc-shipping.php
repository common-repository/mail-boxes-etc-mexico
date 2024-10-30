<?php
/**
*
* @package   MailBoxesMex
* @category Integration
* @author   MBEMX.
*/

if ( ! defined( 'WPINC' ) ){
	die('security by preventing any direct access to your plugin file');
}



function mbemexstd_shipping_method(){
	
	if (!class_exists('mbemex_Standard_Shipping_Method')){

		class mbemex_Standard_Shipping_Method extends WC_Shipping_Method{

			public function __construct($instance_id = 0){

				$this->id = 'mbemexstd';
				$this->instance_id = absint( $instance_id );
				$this->method_title = __('MBE Shipping de Mail Boxes Etc. Envio Estandard');
				$this->method_description = __('Metodo de envio estandard de MBE Shipping de Mail Boxes Etc.');
				
				$this->supports = array(
					'shipping-zones',
					'instance-settings',
				);
				
				$this->instance_form_fields = array(
					'enabled' => array(
						'title' => __( 'Habilitar' ),
						'type' 	=> 'checkbox',
						'label' => __( 'Habilita este metodo de envio' ),
						'default' => 'yes',
					)
				);

				$this->enabled = $this->get_option( 'enabled' );
				$this->title = 'Envio MBE Shipping de Mail Boxes Etc.';

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
			}

			public function calculate_shipping( $package = array() ) {

				if(!function_exists('mbemex_get_rate')){
					include_once 'class-mbemex-wc-rates.php';
				}

				$settings = get_option('woocommerce_mbemex_settings');

				$services = mbemex_get_rate($package);

				usort($services, function($a, $b) {
					return $a[2] - $b[2];
				});

				foreach ($services as $service){

					$newCost = (is_null($service[2])) ? 0 : $service[2];

					// Aumentar o disminuir costo segun settings
					if ( isset($settings['calculationtype'.$service[0]],$settings['shippingpercent'.$service[0]]) ) {
				        if ($settings['calculationtype'.$service[0]] == 'up') {
				        	$newCost += ( ($newCost * $settings['shippingpercent'.$service[0]]) / 100 );
				        }
				        else if($settings['calculationtype'.$service[0]] == 'down'){
				        	$newCost -= ( ($newCost * $settings['shippingpercent'.$service[0]]) / 100 );
				        }
				        else if($settings['calculationtype'.$service[0]] == 'fixed'){
				        	$newCost = $settings['shippingpercent'.$service[0]];
				        }
				    }

					$this->add_rate( array(
						'id'    => $this->id . $this->instance_id . $service[0],
						'label' => $service[1],
						'cost'  => $newCost,
						'meta_data' => ['MSI'=>$service[0]] /// Se alamacena el MBE Shipping ID
						)
					);
				}
			}
		}
	}
}

function add_mbemex_shipping_method($methods){
	$methods['mbemexstd'] = 'mbemex_Standard_Shipping_Method';
	return $methods;
}



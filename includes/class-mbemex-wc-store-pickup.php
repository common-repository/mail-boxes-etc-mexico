<?php

if ( ! defined( 'WPINC' ) ){
    die('security by preventing any direct access to your plugin file');
}

function mbemexpick_shipping_method()
{
    if (!class_exists('mbemex_Pickup_Shipping_Method')) {
        class mbemex_Pickup_Shipping_Method extends WC_Shipping_Method
        {
            public function __construct($instance_id = 0)
            {
                $this->id = 'mbemexpick';
                $this->instance_id 			     = absint( $instance_id );
                $this->method_title = __('Mail Boxes Etc Store Pickup');
                $this->method_description = __('Recibir en establecimiento Mail Boxes Etc');
                $this->supports              = array(
                    'shipping-zones',
                    'instance-settings',
                );
                $this->instance_form_fields = array(
                    'enabled' => array(
                        'title' 		=> __( 'Habilitar' ),
                        'type' 			=> 'checkbox',
                        'label' 		=> __( 'Habilita este metodo de envio' ),
                        'default' 		=> 'yes',
                    ),
                    'title' => array(
                        'title'         => __('Nombre del metodo'),
                        'type'          => 'text',
                        'label'         => __('Agrega un nombre para esta forma de envio'),
                        'default'       => 'Recoger en establecimiento Mail Boxes Etc.',

                    ),
                    'cost' => array(
                        'title'         => __('Costo del metodo de envio'),
                        'type'          => 'text',
                        'label'         => __('Elige un costo fijo para este metodo de envio'),
                        'default'       => 20
                    )
                );
                $this->enabled		    = $this->get_option( 'enabled' );
                $this->title                = $this->get_option( 'title' );
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
            }

            public function calculate_shipping( $package = array() ) {

                $this->add_rate( array(
                    'id'    => $this->id . $this->instance_id,
                    'label' => $this->title,
                    'cost'  => $this->get_option('cost')
                ) );



            }
        }
    }
}



/*function mbemex_add_dropdown()
{
    $services = $GLOBALS['services'];
    $contents = '<form>';
	$contents .= '<select name="Metodo de envio">';

    for ($i = 0; $i < sizeof($services); $i++)
    {
        $contents .= '<option value='.(int)$services[$i][1].'>'.(string)$services[$i][0].'</option>';

    }

    $contents .= '</select>';
    $contents .= '</form>';

    echo $contents;

}*/

function mbemex_add_pickup_method($methods)
{
    $methods['mbemexpick'] = 'mbemex_Pickup_Shipping_Method';
    return $methods;
}


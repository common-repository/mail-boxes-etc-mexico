<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}		
class WC_mbemex_Tracking {

	private $data = false;

	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;
	
	public function __construct() {
		global $wpdb;
	}

	/**
	 * Get the class instance
	 *
	 * @return WC_mbemex_Tracking
	*/
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}


	/**
	 * Define shipment tracking column in admin orders list.
	 *
	 * @since 1.6.1
	 * @author @ECR
	 *
	 * @param array $columns Existing columns
	 *
	 * @return array Altered columns
	 */
	public function shop_order_columns( $columns ) {
		$columns['woocommerce-shipment-tracking'] = __( 'MBE Tracking' );
		return $columns;
	}

	/**
	 * Render shipment tracking in custom column.
	 *
	 * @since 1.6.1
	 * @author @ECR
	 *
	 * @param string $column Current column
	 */
	public function render_shop_order_columns( $column ) {
		global $post;

		if ( 'woocommerce-shipment-tracking' === $column ) {
			echo $this->get_shipment_tracking_column( $post->ID );
		}
	}

	/**
	 * Get content for shipment tracking column.
	 *
	 * @since 1.6.1
	 * @author @ECR
	 *
	 * @param int $order_id Order ID
	 *
	 * @return string Column content to render
	 */
	public function get_shipment_tracking_column( $order_id ) {

		$this->getDataTracking($order_id);

		$html = '-';

		if ($this->data != false) {
			$html = '<p>';
				$html .= '<strong>Número de rastreo: </strong>';
				
				$html .= '<a href="'.$this->data->widget_url.'" target="_blank">';
					$html .= $this->data->tracking;
				$html .= '</a><br>';
				$html .= '<strong>Estado: </strong>'.$this->data->status;
			$html .= '</p>';
		}

		echo $html;
	}


	/**
	 * Add the meta box for shipment info on the order page
	 *
	 * Esta funcion llama el hook para agregar el recuadro
	 * @author @ECR
	 */
	public function add_meta_box() {

		global $post;

		if(isset($post->ID)) $this->getDataTracking($post->ID);

		if ($this->data != false) {
			add_meta_box( 'woocommerce-shipment-tracking', __( 'MBE Tracking' ), array( $this, 'meta_box' ), 'shop_order', 'side', 'high' );
		}
		else{
			add_meta_box( 'woocommerce-shipment-tracking', __( 'MBE Tracking' ), array( $this, 'meta_box_add' ), 'shop_order', 'side', 'high' );
		}
	}

	/**
	 * Show the meta box for shipment info on the order page
	 *
	 * Esta funcion agrega el recuadro del tracking en el detalle
	 * @author @ECR
	 */
	public function meta_box() {
		//global $post;
		//global $wpdb;

		$html = '<div class="mbe-content-tracking">';

		//$html .= '<p><strong>Numero de orden MBE:</strong>'.$this->data->order_number.'</p>';
		$html .= '<p><strong>Estado: </strong>'.$this->data->status.'</p>';
		
		$html .= '<p class="mbemex-content-tracking-no-edit">';
			$html .= '<strong>Número de rastreo: </strong>';
			
			$html .= '<a href="'.$this->data->widget_url.'" target="_blank">';
				$html .= $this->data->tracking;
			$html .= '</a>';

			$html .= '<a href="#" class="mbemex-edit" data-show="mbemex-content-tracking-edit" data-hide="mbemex-content-tracking-no-edit"></a>';
		$html .= '</p>';

		$html .= '<p class="mbemex-content-tracking-edit" style="display:none;">';
			$html .= '<label for="mbemex-tra-edit"><b>Número de rastreo:</b></label>';
			$html .= '<input type="text" name="mbemex_tra_edit" id="mbemex-tra-edit" value="'.$this->data->tracking.'" style="width:100%;">';
		$html .= '</p>';

		$html .= '<p><strong>Paquetería: </strong>'.$this->data->courier.'</p>';

		$html .= '</div>';

		echo $html;

		
		//do_action("ast_tracking_form_end_meta_box");
	}

	/**
	 * Show the meta box for shipment info on the order page
	 *
	 * Esta funcion agrega el recuadro del tracking en el detalle
	 * @author @ECR
	 */
	public function meta_box_add() {
		//global $post;
		//global $wpdb;

		$html = '<div class="mbe-content-tracking">';


		$html .= '<p class="mbemex-content-tracking-edit">';
			$html .= '<label for="mbemex-tra-add"><b>Agregar número de rastreo:</b></label>';
			$html .= '<input type="text" name="mbemex_tra_add" id="mbemex-tra-add" value="" style="width:100%;">';
		$html .= '</p>';

		$html .= '</div>';

		echo $html;

		
		//do_action("ast_tracking_form_end_meta_box");
	}

	/**
	 * 
	 * Return data of tracking
	 * 
	 * @return stdClass || false
	 */
	public function getDataTracking($postID){

		$data = get_option( "mbemex_tracking", false );

		if (isset($data[$postID])){

			//$tacking = $data[$postID];
			$this->data = $data[$postID];
			
			
			/*if(!$this->updateData($tacking)){
				$this->data = $data[$postID];
			}*/

			return true;
		}
		
		$this->data = false;
		return false;			
	}


	/**
	 * 
	 * Update tracking
	 * 
	 *
	 */
	public function updateData($tacking){
		$settings = get_option('woocommerce_mbemex_settings');

		$url = $settings['url'];
		$token = $settings['token'];

		$params = array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'sslverify' => false,
			'headers' => array(),
			'body' => array( 
				'token' => $token,
				'action' => 'orderstatus',
				'numbers'=> $tacking->tracking,
			),
			'cookies' => array()
		);

		$request = wp_remote_post( $url, $params);

	    if( is_wp_error( $request ) ) {
	        return false;
	    }

	    $body = wp_remote_retrieve_body( $request );

	    // Guardo la información del tracking
	    $data = json_decode($body);

	    if (!isset($data->error) && isset($data[0])) {
	        
	        $getInfoDb = get_option("mbemex_tracking");

	        if(is_null($getInfoDb)) $getInfoDb = [];

	        if ($data[0]->status != $tacking->status) {

	        	$tacking->status = $data[0]->status;

	        	$getInfoDb[$tacking->order_number] = $tacking;

	        	update_option("mbemex_tracking",$getInfoDb,false);

	        	$this->data = $tacking;
	        	return true;
	        }
	    }

	    return false;
	}


	/**
	 * 
	 * Show tracking detail in frontend
	 * 
	 */
	public function render_tracking_frontend($details,$order){

		$id = $order->get_order_number();

		$data = get_option( "mbemex_tracking", false );

		if (isset($data[$id])){

			$tacking = $data[$id];
			
			$txtLabel = "Número de rastreo:";

			$htmlValue = '<a href="'.$tacking->widget_url.'" target="_blank">';
			$htmlValue .= $tacking->tracking;
			$htmlValue .= '</a>';

			$newDetails = [];

			foreach ($details as $key => $value) {
				$newDetails[$key] = $value;

				// Inserto el tracking si es key shipping
				if ($key == "shipping") {
					$newDetails["tracking"] = [
						"label" => $txtLabel,
						"value" => $htmlValue
					];
				}
			}

			return $newDetails;
		}
		else{
			return $details;
		}
	}


	/**
	 * 
	 * Delete option
	 * 
	 */
	public function delete($post_id){

		$data = get_option( "mbemex_tracking", false );

		if (isset($data[$post_id])){

			unset($data[$post_id]);

			update_option("mbemex_tracking",$data,false);
		}
	}
}
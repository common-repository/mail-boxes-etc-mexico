<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://mbe.mx
 * @since      1.0.0
 *
 * @package    WC_mbemex
 * @subpackage WC_mbemex/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}		
class WC_mbemex_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var object Class Instance
	 */
	private static $instance;
	private static $ver = 2.0;
	private $version = '1.3.8';
	
	public function __construct() {
		
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
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WC_mbemex_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WC_mbemex_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( 'mail-boxes-etc-mexico', plugin_dir_url( __FILE__ ) . 'css/mbemex-wc-admin.css?ver='.self::$ver, array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WC_mbemex_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WC_mbemex_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( 'mail-boxes-etc-mexico', plugin_dir_url( __FILE__ ) . 'js/mbemex-wc-admin.js?ver='.self::$ver, array( 'jquery' ), $this->version, false );
	}
}
<?php
/*
    Plugin Name: Kpi Integration
    Description: Integration with Kpi Api
    Version: 1.0.3
    Author: Selected
    Author URI: https://www.selected.co.il/
    Text Domain: kpi
*/

/**
 * Supported Plugins:
 * - Contact Form 7 - version > 5.0.1 + Flamingo (db save) - version > 1.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * todo: load translation
 */
/*
function kpi_load_textdomain() {
	load_plugin_textdomain( 'selected-kpi', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
}
add_action( 'init', 'kpi_load_textdomain' );
*/

/**
 * initalize routing
 */
include( plugin_dir_path( __FILE__ ) . '/api.php');
include( plugin_dir_path( __FILE__ ) . '/admin.php');


//global
final class init_Rest_Kpi_WP {

	public function __construct() {
		$this->initApi();
		$this->initAdmin();
	}

	private function initApi() {
		return new Kpi_Api_Route();
	}

	/**
	 * initalize admin settings
	 */
	private function initAdmin(){
		if(is_admin()){
			return new kpiSettingsPage();
		}
	}
}

function selected_kpi_autoLoad() {
	return new init_Rest_Kpi_WP();
}

// Global for backwards compatibility.
$GLOBALS['kpi_integration'] = selected_kpi_autoLoad();
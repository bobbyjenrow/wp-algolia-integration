<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       bobbyjenrow.com
 * @since      1.0.0
 *
 * @package    Ch_Algolia
 * @subpackage Ch_Algolia/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Ch_Algolia
 * @subpackage Ch_Algolia/includes
 * @author     Bobby Jenrow <bobby@cobblehilldigital.com>
 */
class Ch_Algolia_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'ch-algolia',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}

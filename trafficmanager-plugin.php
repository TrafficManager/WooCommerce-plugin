<?php
/*
Plugin Name: TrafficManager WC
Plugin URI: https://www.trafficmanager.com/woocommerce-plugin/
Description: Official integration plugin between WooCommerce and the TrafficManager tracking platform.
Version: 1.1.7
Author: Traffic Manager Limited
Author URI: https://www.trafficmanager.com/
License: GPL3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/**
 * Released under the GPL license version 3
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * **********************************************************************
 */

// don't call the file directly
if ( !defined( 'ABSPATH' ) ) exit;


class TrafficManagerPlugin {

    /**
     * Start up
     */
    public function __construct()
    {
        // WooCommerce plugin must be active
	    if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		    return;
	    }

	    // Register WooCommerce integrations
	    add_filter( 'woocommerce_integrations', array($this, 'register_integration') );

	    // Plugin links
	    add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
    }



	/**
	 * Initializes the TrafficManagerPlugin class
	 *
	 * Checks for an existing TrafficManagerPlugin() instance
	 * and if it doesn't find one, creates it.
	 */
	public static function init() {
		static $instance = false;

		if ( !$instance ) {
			$instance = new TrafficManagerPlugin();
		}

		return $instance;
	}

	/**
	 * Register integration with WooCommerce
	 *
	 * @param array $integrations
	 * @return array
	 */
	function register_integration( $integrations ) {

		include __DIR__ . '/integration.php';

		$integrations[] = 'TrafficManagerWc_Integration';

		return $integrations;
	}

	/**
	 * Plugin action links
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	function plugin_action_links( $links ) {

		//$links[] = '<a href="https://wordpress.org/plugins/woocommerce-conversion-tracking/#installation" target="_blank">' . __( 'Installation', 'trafficmanager-plugin' ) . '</a>';
		$links[] = '<a href="https://www.trafficmanager.com/woocommerce-plugin/" target="_blank">' . __( 'Help', 'trafficmanager-plugin' ) . '</a>';
		$links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=integration' ) . '">' . __( 'Settings', 'trafficmanager-plugin' ) . '</a>';

		return $links;
	}

}

$trfmng = TrafficManagerPlugin::init();

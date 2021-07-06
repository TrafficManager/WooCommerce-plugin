<?php

/**
 * Class TrafficManagerWc_Integration
 *
 * Version: 1.2.1
 * Traffic Manager Limited
 * https://www.trafficmanager.com/woocommerce-plugin/
 */
class TrafficManagerWc_Integration extends WC_Integration {

	/**
	 * Default tracking cookie TTL
	 */
	const DEFAULT_TTL = 259200;
	const DEFAULT_STATUS = 'wc-completed';


	function __construct() {

		$this->id                 = 'trafficmanager-plugin';
		$this->method_title       = __( 'TrafficManager Plugin', 'trafficmanager-plugin' );
		$this->method_description = __( 'Integration with the TrafficManager Tracking Platform', 'trafficmanager-plugin' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Save settings if the we are in the right section
		if ( isset( $_POST['section'] ) && $this->id === $_POST['section'] ) {
			add_action( 'woocommerce_update_options_integration', array( $this, 'process_admin_options' ) );
		}

		add_action( 'wp_footer', array( $this, 'set_cookie' ) );

        // Send the postback when the order is received
        add_action( 'woocommerce_new_order', array(
            $this,
            'action_woocommerce_new_order'
        ), 10, 1 );

        add_action( 'woocommerce_order_status_changed', array(
            $this,
            'action_woocommerce_order_status_changed',
        ), 10, 1 );


		// Check cookie when the order is made
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'add_cookie_to_order' ) );
	}

	/**
	 * Set the tracking cookie if the proper GET parameter is present
	 */
	public function set_cookie() {
		$ttl = isset( $this->settings['cookie_ttl'] ) ? $this->settings['cookie_ttl'] : self::DEFAULT_TTL;
		echo '<script>!function(){var e=new URL(window.location.href).searchParams.get("tmclk");if(e&&e.match(/^[A-Z][A-Z][A-Z]?[0-9a-f]{32}/)){var t=new Date;t.setTime(t.getTime()+1e3*' . $ttl . '),document.cookie="tm_clickid="+e+";path=/;expires="+t.toGMTString()}}();</script>';
	}

	function add_cookie_to_order( $order_id ) {
		if ( isset( $_COOKIE['tm_clickid'] ) && preg_match( '/^[A-Z][A-Z][A-Z]?[0-9a-f]{32}$/', $_COOKIE['tm_clickid'] ) ) {
			$tm_clickid = sanitize_text_field( $_COOKIE['tm_clickid'] );
			update_post_meta( $order_id, 'tm_clickid', $tm_clickid );
		}
	}

	/**
	 * Processes and saves options.
	 * If there is an error thrown, will continue to save and validate fields, but will leave the erroring field out.
	 * @return bool was anything saved?
	 */
	public function process_admin_options() {
		$this->init_settings();

		$post_data = $this->get_post_data();

		foreach ( $this->get_form_fields() as $key => $field ) {
			if ( 'title' !== $this->get_field_type( $field ) ) {
				try {
					$this->settings[ $key ] = $this->get_field_value( $key, $field, $post_data );
				} catch ( Exception $e ) {
					WC_Admin_Settings::add_error( $e->getMessage() );
				}
			}
		}

		if ( $this->settings['api_key'] && $this->settings['user_id'] ) {
			// Get the postback URL from the TrafficManager API
			$args     = [
				'headers' => [
					'X-User-ID' => $this->settings['user_id'],
					'X-Api-Key' => $this->settings['api_key']
				]
			];
			$response = wp_remote_get( 'https://api.trafficmanager.com/v1/getPostbackUrl/', $args );

			if ( is_wp_error( $response ) ) {
				WC_Admin_Settings::add_error( 'Unexpected error occurred. Please try again later or contact the TrafficManager support.' );
			} else {
				$body        = wp_remote_retrieve_body( $response );
				$apiResponse = json_decode( $body, true );
				if ( isset( $apiResponse['status'] ) && $apiResponse['status'] == 200 ) {
					$this->settings['networkName'] = $apiResponse['networkName'];
					$this->settings['username']    = $apiResponse['username'];
					$this->settings['postbackUrl'] = $apiResponse['postbackUrl'];
				} else {
					WC_Admin_Settings::add_error( 'Error occurred: ' . ( $apiResponse['message'] ?? 'unknown error' ) );
				}
			}
		} else {
			$this->settings['networkName'] = '';
			$this->settings['username']    = '';
			$this->settings['postbackUrl'] = '';
		}


		return update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings ) );
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'user_id'    => array(
				'title'       => __( 'Your user ID', 'trafficmanager-plugin' ),
				'type'        => 'text',
				'description' => __( 'Enter your user ID. You can find it in the security page on your platform (Profile menu > Security).', 'trafficmanager-plugin' ),
				'desc_tip'    => true,
				'default'     => ''
			),
			'api_key'    => array(
				'title'       => __( 'Your API key', 'trafficmanager-plugin' ),
				'type'        => 'text',
				'description' => __( 'Enter your Private API Key. You can find it in the security page on your platform (Profile menu > Security).', 'trafficmanager-plugin' ),
				'desc_tip'    => true,
				'default'     => ''
			),
			'cookie_ttl' => array(
				'title'       => __( 'Cookie TTL', 'trafficmanager-plugin' ),
				'type'        => 'select',
				'description' => __( 'The user tracking ID is stored in a cookie for a certain amount of time. After this period, the user will not be considered led by the affiliate anymore.', 'trafficmanager-plugin' ),
				'desc_tip'    => true,
				'default'     => self::DEFAULT_TTL,
				'options'     => array(
					3600    => '1 hour',
					10800   => '3 hours',
					//21600 => '6 hours',
					43200   => '12 hours',
					86400   => '1 day',
					172800  => '2 days',
					259200  => '3 days',
					604800  => '7 days',
					1296000 => '15 days',
					2592000 => '30 days',
				)
			),
            'send_pending_conv'    => array(
                'label'       => __( 'Send pending conversion when the order is received', 'trafficmanager-plugin' ),
                'type'        => 'checkbox',
                'desc_tip'    => true,
            ),

            'order_status' => array(
                'title'       => __( 'Send conversion postback when the order status changes to:', 'trafficmanager-plugin' ),
                'type'        => 'select',
                'default'     => self::DEFAULT_STATUS,
                'options'     => wc_get_order_statuses()
            ),

			'info' => array(
				//'title'             => __( 'TrafficManager network info', 'trafficmanager-plugin' ),
				'type' => 'network_info',
			),
		);
	}

	public function validate_user_id_field( $key, $value ) {
		if ( ! preg_match( '/^[a-z0-9\-]{36}$/i', $value ) ) {
			WC_Admin_Settings::add_error( esc_html__( 'Invalid user ID.', 'trafficmanager-plugin' ) );

			return '';
		}

		return $value;
	}

	public function validate_api_key_field( $key, $value ) {

		if ( isset( $value ) && ! preg_match( '/^[A-Z][A-Z][A-Z]?[0-9a-f]{40}$/', $value ) ) { // App prefix + sha1 key (40 digits long)
			WC_Admin_Settings::add_error( esc_html__( 'Invalid API key.', 'trafficmanager-plugin' ) );

			return '';
		}

		return $value;
	}

	/**
	 * Shows the TrafficManager network info in the settings page, if available
	 * @return string
	 */
	public function generate_network_info_html() {
		ob_start();

		if ( ! isset( $this->settings['networkName'] ) || ! $this->settings['networkName'] ) {
			echo '<div class="notice notice-warning"><p>' . __( 'The TrafficManager plugin is not active. Insert your user ID and API key to enable the plugin.', 'trafficmanager-plugin' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success"><p>' . __( 'The TrafficManager plugin is active. Completed sales are sent to the ' . $this->settings['networkName'] . ' tracking platform.', 'trafficmanager-plugin' ) . '</p></div>';
			?>
            <tr valign="top">
                <th scope="row" class="titledesc"><?= __( 'Network name', 'trafficmanager-plugin' ) ?></th>
                <td class="forminp"><strong><?= $this->settings['networkName'] ?></strong></td>
            </tr>
            <tr valign="top">
                <th scope="row" class="titledesc"
                    style="padding-top:0"><?= __( 'Username', 'trafficmanager-plugin' ) ?></th>
                <td class="forminp" style="padding-top:0"><strong><?= $this->settings['username'] ?></strong></td>
                </td>
            </tr>
			<?php
		}

		return ob_get_clean();
	}


    /**
     * Sends the S2S postback
     *
     * @param $orderId
     */
    public function action_woocommerce_order_status_changed($orderId) {
        $order = new WC_Order( $orderId );
        $this->sendPostback($orderId, $order->get_status());
    }

    /**
     * Sends the S2S postback
     *
     * @param $orderId
     */
    public function action_woocommerce_new_order( $orderId ) {
        $this->sendPostback($orderId, 'new_order');
    }


    private function sendPostback($orderId, $status) {
        if (!isset( $this->settings['postbackUrl'] ) ) {
            return;
        }

        if ($status == 'new_order' && isset($this->settings['send_pending_conv']) && $this->settings['send_pending_conv'] == 'yes') {
            $this->postback($orderId, $status);
        }

        if (isset($this->settings['order_status'])) {
            if ('wc-' . $status == $this->settings['order_status']){
                $this->postback($orderId, $status);
            }
        }
    }

    private function postback($orderId, $status) {
        try {
            if ($status == 'new') {
                $this->add_cookie_to_order($orderId);
            }
            $order = new WC_Order( $orderId );

            if ( ! $order->get_meta( 'tm_clickid' ) ) {
                // This order has no clickid, don't send the postback
                error_log( "No clickId");
                return;
            }

            // Build the url
            $url = $this->settings['postbackUrl'];
            $url = str_replace( '{clickid}', $order->get_meta( 'tm_clickid' ), $url );
            $url = str_replace( '{transaction_id}', $orderId, $url );
            $url = str_replace( '{amount}', $order->get_subtotal(), $url );

            if (isset($this->settings['send_pending_conv']) && $this->settings['send_pending_conv'] == 'yes' && $status != 'new') {
                $url .= '&approve=1';
            }

            // Send the postback
            $response = wp_remote_get( $url );
            if ( is_wp_error( $response ) ) {
                error_log( 'TrafficManager postback failed: ' . $url );
            } elseif ( 'OK' !== $response ) {
                error_log( 'TrafficManager postback not valid: ' . $url );
            }

        } catch ( Exception $ex ) {
            error_log( $ex->getMessage() );
        }
    }
}

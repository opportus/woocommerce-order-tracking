<?php

/**
 * Plugin Name: WooCommerce Order Tracking
 * Plugin URI: https://github.com/opportus/woocommerce-tracking-number/
 * Author: Clément Cazaud
 * Author URI: https://github.com/opportus/
 * Licence: MIT Licence
 * Licence URI: https://opensource.org/licenses/MIT
 * Description: A simple and flexible order tracking solution for WooCommerce.
 * Version: 0.1
 * Requires at least: 4.4
 * Tested up to 4.6
 * Text Domain: woocommerce-order-tracking
 *
 * @version 0.1
 * @author  Clément Cazaud <opportus@gmail.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly...
}

/**
 * @class WCOT
 */
class WCOT {
	
	/**
	 * @var object $instance
	 */
	private static $instance;

	/**
	 * Initializes singleton instance.
	 *
	 * @return object self::$instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
	
	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-order-tracking' ), '0.1' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'woocommerce-order-tracking' ), '0.1' );
	}


	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_text_domain();

		add_action( 'init', array( $this, 'init_hooks' ), 0 );
	}

	/**
	 * Initialization hooks.
	 */
	public function init_hooks() {
		if ( is_admin() ) {
			// Setting page hooks...
			add_action( 'admin_menu',                   array( $this, 'init_settings_page' ) );
			add_action( 'admin_init',                   array( $this, 'init_settings_sections' ) );
			add_action( 'admin_init',                   array( $this, 'init_settings_fields' ) );
			// Order page hooks...
			add_action( 'admin_notices',                array( $this, 'admin_notice' ) );
			add_action( 'add_meta_boxes',               array( $this, 'add_meta_box' ) );
			add_action( 'save_post',                    array( $this, 'save_meta_box' ) );
			add_filter( 'is_protected_meta',            array( $this, 'protect_meta' ), 10, 2 );
		} else {
			// Frontend hooks...
			add_action( 'woocommerce_view_order',       array( $this, 'information_display' ), 5,  1 );
			add_action( 'woocommerce_email_order_meta', array( $this, 'information_display' ), 10, 1 );
		}
	}

	/**
	 * Initializes text domain.
	 */
	private function init_text_domain() {
		load_plugin_textdomain( 'woocommerce-order-tracking', false, 'woocommerce-order-tracking/languages' );
	}

	/**
	 * Returns settings fields array.
	 *
	 * @return array $settings_fields
	 */
	public function get_settings_fields() {
		$settings_fields = array(
			'shipper_url_0'  => array(
				'name'        => __( 'Shipper\'s URL', 'woocommerce-order-tracking' ),
				'section'     => 'wcot_settings_shippers',
				'description' => __( 'Add the new shipper\'s tracking service URL. Eg for FEDEX: http://www.fedex.com/Tracking?action=track&tracknumbers=', 'woocommerce-order-tracking' ),
			),
			'shipper_name_0' => array(
				'name'        => __( 'Shipper\'s Name', 'woocommerce-order-tracking' ),
				'section'     => 'wcot_settings_shippers',
				'description' => __( 'Enter the name of the new shipper. Note that it will be displayed as such to your customer.', 'woocommerce-order-tracking' ),
			),
		);

		$options = get_option( 'wcot_settings' );

		// Let's add dynamically new pair of settings fields when the previous are filled...
		if ( isset( $options['shippers'] ) ) {
			// Grep `shipper_name` fields in options.
			$shippers = preg_grep( '#^shipper_name#', array_keys( $options['shippers'] ) );
			$shippers_number = 0;

			foreach( $shippers as $shipper ) {
				// If `shipper_name` value is not null...
				if ( ! empty( $options['shippers'][ $shipper ] ) ) {
					// Count one more shipper.
					$shippers_number ++;
				}
			}

			// If at least 1 shipper name has been set, let's add new `shipper_name` and `shipper_url` setting fields pair...
			if ( $shippers_number ) {
				// We count the original shipper's setting fields pair which has the ID `_0`.
				// The `$field_number` variable will also define the new shipper's setting fields pair ID...
				$field_number = 1;

				while ( $field_number <= $shippers_number ) {
					// Create new shipper's setting fields pair with their new ID `$field_number`...
					$new_fields = array(
						'shipper_url_' . $field_number  => $settings_fields['shipper_url_0'],
						'shipper_name_' . $field_number => $settings_fields['shipper_name_0'],
					);

					// Add them to the existing settings fields.
					$settings_fields += $new_fields;
					$field_number ++;
				}
			}
		}

		return apply_filters( 'wcot_settings_fields', $settings_fields, $options );
	}

	/**
	 * Returns settings sections array.
	 *
	 * @return array $settings_sections
	 */
	public function get_settings_sections() {
		$settings_sections = array(
			'shippers' => array(
				'name'        => __( 'Set your shippers', 'woocommerce-order-tracking' ),
				'description' => __( 'In this section, you can add new shippers and/or edit the shippers previously created.', 'woocommerce-order-tracking' ),
			)
		);

		return apply_filters( 'wcot_settings_sections', $settings_sections );
	}

	/**
	 * Initializes settings page.
	 *
	 * Hooked into `admin_menu` action hook.
	 */
	public function init_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'WooCommerce Order Tracking', 'woocommerce-order-tracking' ),
			__( 'Order Tracking', 'woocommerce-order-tracking' ),
			'manage_options',
			'wcot_settings_page',
			array( $this, 'settings_page_template' )
		);
	}

	/**
	 * Initializes settings sections.
	 *
	 * Hooked into `admin_init` action hook.
	 */
	public function init_settings_sections() {
		$settings_sections = $this->get_settings_sections();

		foreach ( $settings_sections as $section => $setting ) {
			add_settings_section(
				'wcot_settings' . '_' . $section,
				$setting['name'],
				array( $this, 'settings_sections_template' ),
				'wcot_settings_page'
			);
		}
	}

	/**
	 * Initializes settings fields.
	 *
	 * Hooked into `admin_init` action hook.
	 */
	public function init_settings_fields() {
		register_setting(
			'wcot_settings_group',
			'wcot_settings',
			'wc_clean'
		);

		$options         = get_option( 'wcot_settings' );
		$settings_fields = array_reverse( $this->get_settings_fields() );

		foreach ( $settings_fields as $field => $setting ) {
			// Remove section prefix `wcot_settings_` from the setting section...
			$section = substr( $setting['section'], 14 );
			// Get the option value for displaying it in field template.
			$value   = isset( $options[ $section ][ $field ] ) ? $options[ $section ][ $field ] : null;

			add_settings_field(
				$slug = $setting['section'] . '_' . $field,
				$setting['name'],
				array( $this, 'settings_fields_template' ),
				'wcot_settings_page',
				$setting['section'],
				// Send this array as argument to the settings_fields_template method.
				array(
					'section'   => $section,
					'field'     => $field,
					'slug'      => $slug,
					'value'     => $value,
					'label_for' => $slug,
					'description' => $setting['description'],
				)
			);
		}
	}

	/**
	 * Settings page template.
	 */
	public function settings_page_template() {
		echo '<h1>' . esc_html__( 'Order Tracking Options', 'woocommerce-order-tracking' ) . '</h1>';
		echo '<form method="POST" action="options.php">';

		submit_button();
		do_settings_sections( 'wcot_settings_page' );
		submit_button();
		settings_fields( 'wcot_settings_group' );

		echo '</form>';
	}

	/**
	 * Settings sections template.
	 *
	 * @param array $args From `add_settings_section()`
	 */
	public function settings_sections_template( $args ) { 
		// Remove section prefix 'wcot_settings_'...
		$section = substr( $args['id'], 14 );
		$settings_sections = $this->get_settings_sections();

		echo '<p>' . $settings_sections[ $section ]['description'] . '</p>';
	}

	/**
	 * Settings fields template.
	 *
	 * @param array $args From `add_settings_field()`
	 */
	public function settings_fields_template( $args ) {
		$description = $args['description'];
		$section     = $args['section'];
		$field       = $args['field'];
		$slug        = $args['slug'];
		$value       = $args['value'];
		$name        = 'wcot_settings[' . $section . '][' . $field . ']';
		$style       = ! empty( $value ) ? ' style="background-color: #f2f2f2"' : '';

		echo '<input type="text" id="' . esc_attr( $slug ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text"' . $style . ' />';

		if ( ! empty( $description ) && empty( $value ) ) {
			echo '<p class="description">' . $description . '</p>';
		}
	}

	/**
	 * Structures and returns a nicely structured array containing shippers settings.
	 *
	 * Grep serialized `shippers` settings from wp_options table and return them in a nicely structured array.
	 *
	 * The option we're grepping are structured this way:
	 * 
	 * 'wcot_settings' = array(
	 *   'shippers' => array(
	 *     'shipper_name_0' => 'Example Shipper',
	 *     'shipper_url_0'  => 'http://www.example.com/tracking-service?url=',
	 *     'shipper_name_1' => null,
	 *     'shipper_url_1'  => null,
	 *   )
	 * )
	 * NOTE: The pair of option fields are added dynamically when a new shipper is found.
	 * The `_0` or `_1` part is the incremental ID of the pair of fields.
	 *
	 * For friendly later uses, we're attempting to return the settings this way:
	 *
	 * 'example_shipper' = array(
	 *   'name' => 'Example Shipper',
	 *   'url'  => 'http://www.example.com/tracking-service?url='
	 * )
	 *
	 * @return array $shippers
	 */
	public function get_shippers() {
		$options = get_option( 'wcot_settings' );
		$shippers_options =  isset( $options['shippers'] ) ? $options['shippers'] : null;
		$shippers_tmp = array(); // Temporary array which will get filled by shippers classified by their ID.
		$shippers = array(); // Futur shippers array which will have their name (if available) as key. Else by thier ID.
		$number = 0;

		if ( is_null( $shippers_options ) ) {
			return $shippers;
		}

		foreach ( $shippers_options as $key => $value ) {
			list( , $option, $id ) = explode( '_', $key );

			$id = (int) $id;
			$shippers_tmp[ $id ][ $option ] = isset( $value ) ? $value : null;
		}

		foreach ( $shippers_tmp as $key => $value ) {
			if ( ! empty( $value['name'] ) ) {
				$name = str_replace( ' ', '_', strtolower( $value['name'] ) );	

				if ( isset( $shippers[ $name ] ) ) {
					$name = $name . '_' . (string) $number++;
				}

				$shippers[ $name ] = $value;
			}
		}

		return apply_filters( 'wcot_shippers', $shippers, $options );
	}

	/**
	 * Adds tracking meta box.
	 *
	 * Hooked into `add_meta_boxes` action hook.
	 */
	public function add_meta_box() {
		add_meta_box(
			'wcot',
			__( 'Order Tracking', 'woocommerce-order-tracking' ),
			array( $this, 'meta_box' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Meta box template. 
	 */
	public function meta_box() {
		$shippers = $this->get_shippers();

		if ( empty( $shippers ) ) {
			echo '<p>' . sprintf( __( 'You first need to add new shippers on the %s.', 'woocommerce-order-tracking' ), '<a href="' . esc_url( admin_url( 'admin.php?page=wcot_settings_page' ) ) . '" target="_blank">' . __( 'settings page', 'woocommerce-order-tracking' ) . '</a>' ) . '</p>';

			return;
		}

		global $post;

		$wcot_shipper = get_post_meta( $post->ID, 'wcot_shipper', true );
		$wcot_number  = get_post_meta( $post->ID, 'wcot_number', true );

		echo '<p class="description">' . esc_html__( 'Select the shipper, enter your tracking number and save...', 'woocommerce-order-tracking' ) . '</p>';
		echo '<p><label for="wcot_shipper">' . esc_html__( 'Shipper', 'woocommerce-order-tracking' ) . '</label /><br />';
		echo '<select id="wcot_shipper" name="wcot_shipper">';
		echo '<option value="">' . esc_html__( 'Select the shipper', 'woocommerce-order-tracking' ) . '</option>';

		foreach ( $shippers as $key => $value ) {
			if ( ! is_int( $key ) && ! empty( $key ) ) {
				$selected = ( $wcot_shipper === $key ) ? 'selected ' : '';

				echo '<option ' . $selected . 'value="' . esc_attr( $key ) . '">' . esc_html( $value['name'] ) . '</option>';
			}
		}

		echo '</select></p>';
		echo '<p><label for="wcot_number">' . esc_html__( 'Tracking number', 'woocommerce-order-tracking' ) . '</label>';
		echo '<input type="text" id="wcot_number" name="wcot_number" value="' . esc_attr( $wcot_number ) . '" /></p>';
		
		if ( ! empty( $wcot_number ) && ! empty( $wcot_shipper ) && array_key_exists( $wcot_shipper, $shippers ) ) {
			echo '<a href="' . esc_url( $shippers[ $wcot_shipper ]['url'] . $wcot_number ) . '" rel="nofollow" target="_blank">' . esc_html__( 'Track it', 'woocommerce-order-tracking' ) . '</a>';
		}
	}

	/**
	 * Saves order tracking meta.
	 *
	 * Hooked into `save_post` action hook.
	 *
	 * @param int $post_ID
	 */
	public function save_meta_box( $post_ID ) {
		if ( empty( $_POST['wcot_shipper'] ) || empty( $_POST['wcot_number'] ) || ! current_user_can( 'edit_post', $post_ID ) ) {
			return;
		}

		$wcot_shipper = sanitize_text_field( $_POST['wcot_shipper'] );
		$wcot_number  = sanitize_text_field( $_POST['wcot_number'] );

		if ( preg_match( '/^[\p{L}0-9\s\-_]{2,50}$/u', $wcot_shipper ) && preg_match( '/^[\p{L}0-9\s\-_]{2,50}$/u', $wcot_number ) ) {
			update_post_meta( $post_ID, 'wcot_shipper', $wcot_shipper );
			update_post_meta( $post_ID, 'wcot_number', $wcot_number );
		}

		else set_transient( 'wcot_error', true, 60 );
	}

	/**
	 * Admin notice template
	 *
	 * Hooked into 'admin_notices' action.
	 */
	public function admin_notice() {
		if ( get_transient( 'wcot_error' ) ) {
			echo '<div class="updated error notice is-dismissible">';
			echo '<p>' . esc_html__( 'Invalid / Missing tracking number or tracking shipper', 'woocommerce-order-tracking' ) . '.</p>';
			echo '</div>';

			delete_transient( 'wcot_error' );
		}
	}

	/**
	 * Hides `wcot_*` custom fields.
	 *
	 * Hooked into `is_protected_meta` filter hook.
	 *
	 * @param  bool $protected
	 * @param  int  $meta_key
	 * @return bool
	 */
	public function protect_meta( $protected, $meta_key ) {
		if ( 'wcot_shipper' === $meta_key || 'wcot_number' === $meta_key ) {
			return true;
		}

		return $protected;
	}
	
	/**
	 * Displays shipping information.
	 *
	 * Hooked into `woocommerce_view_order` action hook.
	 * Hooked into `woocommerce_email_before_order_table` action hook.
	 *
	 * @param object $order
	 */
	public function information_display( $order ) {
		if ( is_object( $order ) ) {
			$order_id = $order->id;
		} elseif ( is_int( $order ) ) {
			$order_id = $order;
		}
		
		$shippers     = $this->get_shippers();
		$wcot_shipper = get_post_meta( $order_id, 'wcot_shipper', true );
		$wcot_number  = get_post_meta( $order_id, 'wcot_number', true );

		if ( ! empty( $wcot_shipper ) && ! empty( $wcot_number ) && array_key_exists( $wcot_shipper, $shippers ) ) {
			$html  = sprintf( esc_html__( 'Shipped by %s', 'woocommerce-order-tracking' ), esc_html( $shippers[ $wcot_shipper ]['name'] ) ) . '<br />';
			$html .= sprintf( esc_html__( 'Tracking number: %s', 'woocommerce-order-tracking' ), $tracking = esc_html( $wcot_number ) ) . '<br />';
			$html .= '<a href="' . esc_url( $shippers[ $wcot_shipper ]['url'] . $wcot_number ) . '" rel="nofollow" target="_blank">' . esc_html__( 'Track my order', 'woocommerce-order-tracking' )  . '</a>';

			echo apply_filters( 'wcot_information_display', $html, $order, $wcot_shipper, $wcot_number );
		}
	}
}

/**
 * Main function.
 *
 * Avoids the use of a global..
 *
 * @return object Plugin Instance
 */
function wcot_order_tracking() {
	return WCOT::get_instance();
}

wcot_order_tracking();

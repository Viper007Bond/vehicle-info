<?php /*

**************************************************************************

Plugin Name:  Vehicle Info
Plugin URI:   
Description:  
Version:      
Author:       Alex Mills (Viper007Bond)
Author URI:   http://www.viper007bond.com/

Text Domain:  vehicle-info
Domain Path:  /localization/

**************************************************************************

Copyright (C) 2012 Alex Mills (Viper007Bond)
Contact: http://www.viper007bond.com/contact/

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License version 2 or greater,
as published by the Free Software Foundation.

You may NOT assume that you can use any other version of the GPL.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

The license for this software can likely be found here:
http://www.gnu.org/licenses/gpl-2.0.html

**************************************************************************

TODO:

* Cache wrappers for non-caching functions?

**************************************************************************/

add_action( 'admin_init', 'vehicleinfo_initialize_cmb_meta_boxes', 9999 );
function vehicleinfo_initialize_cmb_meta_boxes() {
	if ( ! class_exists( 'cmb_Meta_Box' ) ) {
		require_once( __DIR__ . '/metabox/init.php' );
	}
}

class Vehicle_Info {

	/** Constants *************************************************************/

	/**
	 * @var string Plugin version number.
	 */
	const VERSION            = '1.0';

	/**
	 * @var string The slug used for the WordPress Settings API.
	 */
	const SETTINGS_SLUG      = 'vehicleinfo';

	/**
	 * @var string The slug used for the WordPress option.
	 */
	const OPTION_NAME        = 'vehicleinfo';

	/**
	 * @var string The fillup custom post type slug.
	 */
	const CPT_FILLUP         = 'vehicleinfo_fillup';

	/**
	 * @var string The service custom post type slug.
	 */
	const CPT_SERVICE        = 'vehicleinfo_service';

	/**
	 * @var string The vehicle custom taxonomy slug.
	 */
	const TAX_VEHICLE        = 'vehicleinfo_vehicle';

	/**
	 * @var string The location custom taxonomy slug.
	 */
	const TAX_LOCATION       = 'vehicleinfo_location';

	/**
	 * @var string The fillup type custom taxonomy slug.
	 */
	const TAX_FILLUP_TYPE    = 'vehicleinfo_fillup_type';

	/**
	 * @var string The fuel type custom taxonomy slug.
	 */
	const TAX_FUEL_TYPE      = 'vehicleinfo_fuel_type';

	/**
	 * @var string The fuel brand custom taxonomy slug.
	 */
	const TAX_FUEL_BRAND     = 'vehicleinfo_fuel_brand';

	/**
	 * @var string The service type custom taxonomy slug.
	 */
	const TAX_SERVICE_TYPE   = 'vehicleinfo_service_type';

	/**
	 * @var string The payment type custom taxonomy slug.
	 */
	const TAX_PAYMENT_TYPE   = 'vehicleinfo_payment_type';

	/**
	 * @var string The odomoter reading meta data slug.
	 */
	const META_ODOMETER      = 'vehicleinfo_odometer';

	/**
	 * @var string The fuel unit price meta data slug.
	 */
	const META_FUELUNITPRICE = 'vehicleinfo_fuelunitprice';

	/**
	 * @var string The fuel units meta data slug.
	 */
	const META_FUELUNITS     = 'vehicleinfo_fuelunits';

	/**
	 * @var string The cost meta data slug.
	 */
	const META_COST          = 'vehicleinfo_cost';

	/** Variables *************************************************************/

	/**
	 * @var string Post meta key for link URL.
	 */
	private $settings          = array();

	/**
	 * @var string Post meta key for link URL.
	 */
	private $labels            = array();

	/**
	 * @var string Post meta key for link URL.
	 */
	private $chart_incrementor = 0;

	/**
	 * @var string Post meta key for link URL.
	 */
	private $charts            = array();

	/**
	 * @var string Post meta key for link URL.
	 */
	private $import_attachment = null;

	/**
	 * @var string Post meta key for link URL.
	 */
	private $import_data       = null;

	/** Singleton *************************************************************/

	/**
	 * @var Vehicle_Info Stores the instance of this class.
	 */
	private static $instance;

	/**
	 * Main Vehicle_Info Instance
	 *
	 * Insures that only one instance of Vehicle_Info exists in memory at any one time.
	 *
	 * @since Vehicle_Info 1.0
	 * @staticvar array $instance
	 * @uses Vehicle_Info::setup_globals() Setup the globals needed
	 * @uses Vehicle_Info::includes() Include the required files
	 * @uses Vehicle_Info::setup_actions() Setup the hooks and actions
	 * @see vehicle_info()
	 * @return The one true Vehicle_Info
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Vehicle_Info;
			self::$instance->setup();
		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent Vehicle_Info from being loaded more than once.
	 *
	 * @since Vehicle_Info 1.0
	 * @see Vehicle_Info::instance()
	 * @see Vehicle_Info();
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent Vehicle_Info from being cloned
	 */
	public function __clone() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/**
	 * A dummy magic method to prevent Vehicle_Info from being unserialized
	 */
	public function __wakeup() { wp_die( __( 'Cheatin’ uh?' ) ); }

	/** Private Methods *******************************************************/

	/**
	 * Set up all of the hooks
	 *
	 * @access private
	 * @uses add_action() To add various actions
	 * @uses add_filter() To add various filters
	 * @uses add_shortcode() To register various shortcodes
	 */
	private function setup() {
		add_action( 'init', array( &$this, 'action_init' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'register_scripts' ) );

		//add_action( 'admin_init', array( &$this, 'create_default_terms' ) );
		add_action( 'admin_init', array( &$this, 'register_settings' ) );
		add_action( 'admin_menu', array( &$this, 'register_settings_page' ) );

		add_action( 'load-settings_page_vehicleinfo', array( &$this, 'maybe_process_upload_or_import' ) );

		add_filter( 'manage_' . self::CPT_FILLUP . '_posts_columns', array( &$this, 'fillup_columns_add' ) );
		add_filter( 'manage_edit-' . self::CPT_FILLUP . '_sortable_columns', array( &$this, 'fillup_columns_sortable' ) );
		add_action( 'manage_' . self::CPT_FILLUP . '_posts_custom_column', array( &$this, 'fillup_columns_values' ), 10, 2 );

		add_filter( 'cmb_meta_boxes', array( &$this, 'register_cmb_meta_boxes' ) );
		add_action( 'add_meta_boxes_' . self::CPT_FILLUP, array( &$this, 'queue_fillup_screen_stuff' ) );
		add_action( 'add_meta_boxes', array( &$this, 'remove_default_taxonomy_meta_boxes' ) );

		add_filter( 'cmb_render_vehicleinfo_taxonomy_select_default',   array( &$this, 'cmb_render_taxonomy_select_default' ), 10, 2 );
		add_filter( 'cmb_render_vehicleinfo_text_money',                array( &$this, 'cmb_render_vehicleinfo_text_money' ), 10, 2 );

		add_filter( 'cmb_validate_select',                              array( &$this, 'cmb_validate' ), 10, 3 );
		add_filter( 'cmb_validate_text_small',                          array( &$this, 'cmb_validate' ), 10, 3 );
		add_filter( 'cmb_validate_vehicleinfo_taxonomy_select_default', array( &$this, 'cmb_validate' ), 10, 3 );
		add_filter( 'cmb_validate_vehicleinfo_text_money',              array( &$this, 'cmb_validate' ), 10, 3 );

		add_action( 'save_post', array( &$this, 'set_post_title_on_save' ), 10, 2 ); // wp_insert_post_data could be used instead but it's a PITA


		add_shortcode( 'vehicleinfo_total_value', array( &$this, 'shortcode_total_value' ) );
		add_shortcode( 'vehicleinfo_average', array( &$this, 'shortcode_average' ) );

		add_shortcode( 'vehicleinfo_list_fillups', array( &$this, 'shortcode_list_fillups' ) );

		add_shortcode( 'vehicleinfo_chart_distance', array( &$this, 'shortcode_chart_distance' ) );
		add_shortcode( 'vehicleinfo_chart_mileage', array( &$this, 'shortcode_chart_mileage' ) );
		add_shortcode( 'vehicleinfo_chart_fuel_prices', array( &$this, 'shortcode_chart_fuel_prices' ) );
	}

	/** Public Hook Callback Methods ******************************************/

	public function action_init() {
		// TODO: Labels for everything
		register_post_type( self::CPT_FILLUP, array(
			'labels' => array(
				'name'          => 'Fuel Fill Ups',
				'singular_name' => 'Fuel Fill Up',
				'add_new'       => 'Add New Fuel Fill Up',
				'edit_item'     => 'Edit Fuel Fill Up',
				'new_item'      => 'New Fuel Fill Up',
				'all_items'     => 'All Fuel Fill Ups',
				'view_item'     => 'View Fuel Fill Up',
				'search_items'  => 'Search Fuel Fill Ups',
				'menu_name'     => 'Vehicle Fill Ups',
			),
			'public'              => true,
			'supports'            => array( 'editor', 'custom-fields' ),
			'exclude_from_search' => true,
		) );

		register_post_type( self::CPT_SERVICE, array(
			'label' => 'Vehicle Services',
			'public' => true,
			'supports' => array( 'title', 'editor', 'custom-fields' ),
		) );


		register_taxonomy( self::TAX_VEHICLE, array( self::CPT_FILLUP, self::CPT_SERVICE ), array(
			'label' => 'Vehicles',
			'hierarchical' => true,
		) );

		register_taxonomy( self::TAX_LOCATION, array( self::CPT_FILLUP, self::CPT_SERVICE ), array(
			'hierarchical' => true,
			'labels' => array(
				'name' => 'Locations',
			),
		) );

		register_taxonomy( self::TAX_FILLUP_TYPE, self::CPT_FILLUP, array(
			'label' => 'Fillup Types',
			'public' => false, // We only want the pre-defined types
			'rewrite' => false,
			'hierarchical' => true,
		) );

		register_taxonomy( self::TAX_FUEL_TYPE, self::CPT_FILLUP, array(
			'label' => 'Fuel Types',
			'hierarchical' => true,
		) );

		register_taxonomy( self::TAX_FUEL_BRAND, self::CPT_FILLUP, array(
			'label' => 'Fuel Brands',
			'hierarchical' => true,
		) );

		register_taxonomy( self::TAX_SERVICE_TYPE, self::CPT_SERVICE, array(
			'label' => 'Services',
			'hierarchical' => true,
		) );

		register_taxonomy( self::TAX_PAYMENT_TYPE, array( self::CPT_FILLUP, self::CPT_SERVICE ), array(
			'label' => 'Payment Types',
			'hierarchical' => true,
		) );

		// Make sure the fillup types exist
		$fillup_types = array(
			'full'    => __( 'Full',    'vehicle-info' ),
			'partial' => __( 'Partial', 'vehicle-info' ),
			'reset'   => __( 'Reset',   'vehicle-info' ),
		);
		foreach ( $fillup_types as $slug => $name ) {
			if ( ! get_term_by( 'slug', $slug, self::TAX_FILLUP_TYPE ) ) {
				wp_insert_term( $name, self::TAX_FILLUP_TYPE, array(
					'slug' => $slug,
				) );
			}
		}

		$this->settings = wp_parse_args( (array) get_option( self::OPTION_NAME ), array(
			'currency_symbol'           => _x( '$', 'your currency symbol', 'vehicle-info' ),
			/* translators: This must be either exactly "before" or "after", untranslated and nothing else! */
			'currency_symbol_placement' => _x( 'before', 'symbol placement location for this locale', 'vehicle-info' ),
			/* translators: This must be either exactly "imperial" or "metric", untranslated and nothing else! */
			'unit_system'               => _x( 'imperial', 'measurement system for this locale, either imperial or metric', 'vehicle-info' ),
			'color_background'          => '',
			'color_border'              => '',
			'border_width'              => '',
			'axis_color'                => '',
			'grid_line_color'           => '',
			'line_colors'               => '',
			'font_size'                 => '',
			'font_name'                 => '',
		) );

		switch ( $this->settings['unit_system'] ) {
			case 'metric':
				$this->labels = array(
					'volume_singular' => __( 'litre',      'vehicle-info' ),
					'volume_plural'   => __( 'litres',     'vehicle-info' ),
					'mileage'         => __( 'L/100km',    'vehicle-info' ),
					'distance_plural' => __( 'kilometers', 'vehicle-info' ),
				);
				break;

			case 'imperial':
			default:
				$this->labels = array(
					'volume_singular' => __( 'gallon',  'vehicle-info' ),
					'volume_plural'   => __( 'gallons', 'vehicle-info' ),
					/* translators: MPG is the abbreviation for miles per gallon */
					'mileage'         => __( 'MPG',     'vehicle-info' ),
					'distance_plural' => __( 'miles',   'vehicle-info' ),
				);
				break;
		}
	}

	public function register_scripts() {
		wp_register_script( 'google-jsapi', 'https://www.google.com/jsapi', array(), false, true );
	}

	public function create_default_terms() {
		$default_terms = array(
			self::TAX_FUEL_TYPE => array(
				__( 'Regular',       'vehicle-info' ),
				__( 'Plus',          'vehicle-info' ),
				__( 'Premium',       'vehicle-info' ),
				__( 'Diesel',        'vehicle-info' ),
			),
			self::TAX_FUEL_BRAND => array(
				/*
				__( 'Shell',         'vehicle-info' ),
				__( 'Chevron',       'vehicle-info' ),
				__( 'Arco',          'vehicle-info' ),
				*/
			),
			self::TAX_PAYMENT_TYPE => array(
				__( 'Cash',          'vehicle-info' ),
				__( 'Debit',         'vehicle-info' ),
				__( 'VISA',          'vehicle-info' ),
				__( 'MasterCard',    'vehicle-info' ),
				__( 'Discover',      'vehicle-info' ),
			),
			self::TAX_SERVICE_TYPE => array(
				__( 'Oil Change',    'vehicle-info' ),
				__( 'Tune Up',       'vehicle-info' ),
				__( 'Tire Rotation', 'vehicle-info' ),
				__( 'New Tires',     'vehicle-info' ),
			),
		);

		foreach ( $default_terms as $taxonomy => $terms ) {
			foreach ( $terms as $term ) {
				wp_insert_term( $term, $taxonomy );
			}
		}
	}

	public function remove_default_taxonomy_meta_boxes() {
		remove_meta_box( self::TAX_VEHICLE . 'div',      self::CPT_FILLUP,  'side' );
		remove_meta_box( self::TAX_VEHICLE . 'div',      self::CPT_SERVICE, 'side' );
		remove_meta_box( self::TAX_FUEL_TYPE . 'div',    self::CPT_FILLUP,  'side' );
		//remove_meta_box( self::TAX_FUEL_BRAND . 'div',   self::CPT_FILLUP,  'side' );
		remove_meta_box( self::TAX_PAYMENT_TYPE . 'div', self::CPT_FILLUP,  'side' );
		remove_meta_box( self::TAX_PAYMENT_TYPE . 'div', self::CPT_SERVICE, 'side' );
		remove_meta_box( self::TAX_SERVICE_TYPE . 'div', self::CPT_SERVICE, 'side' );
	}

	public function fillup_columns_add( $columns ) {
		// Moving it to the end
		$date = $columns['date'];
		unset( $columns['date'] );

		$columns[self::TAX_VEHICLE]        = __( 'Vehicle', 'vehicle-info' );
		$columns[self::TAX_FILLUP_TYPE]    = __( 'Fillup Type', 'vehicle-info' );
		$columns[self::META_ODOMETER]      = __( 'Odometer', 'vehicle-info' );
		$columns[self::META_FUELUNITPRICE] = __( 'Unit Price', 'vehicle-info' );
		$columns[self::META_FUELUNITS]     = ucwords( strtolower( $this->labels['volume_plural'] ) );
		$columns[self::META_COST]          = __( 'Cost', 'vehicle-info' );
		$columns[self::TAX_LOCATION]       = __( 'Location', 'vehicle-info' );
		$columns[self::TAX_FUEL_TYPE]      = __( 'Fuel Type', 'vehicle-info' );
		$columns[self::TAX_FUEL_BRAND]     = __( 'Fuel Brand', 'vehicle-info' );
		$columns[self::TAX_PAYMENT_TYPE]   = __( 'Payment Type', 'vehicle-info' );
		$columns['vehicleinfo_mileage']    = __( 'Mileage', 'vehicle-info' );

		$columns['date'] = $date;

		return $columns;
	}

	public function fillup_columns_sortable( $sortable ) {
		$sortable[self::TAX_VEHICLE]       = self::TAX_VEHICLE;
		//$sortable[self::TAX_FILLUP_TYPE]   = self::TAX_FILLUP_TYPE;
		$sortable[self::TAX_LOCATION]      = self::TAX_LOCATION;
		//$sortable[self::TAX_FUEL_TYPE]     = self::TAX_FUEL_TYPE;
		$sortable[self::TAX_FUEL_BRAND]    = self::TAX_FUEL_BRAND;
		$sortable[self::TAX_PAYMENT_TYPE]  = self::TAX_PAYMENT_TYPE;

		return $sortable;
	}

	public function fillup_columns_values( $column_name, $post_ID ) {
		global $posts;

		//var_export( $posts ); exit();

		switch ( $column_name ) {
			case self::TAX_VEHICLE:
			case self::TAX_FILLUP_TYPE:
			case self::TAX_LOCATION:
			case self::TAX_FUEL_TYPE:
			case self::TAX_FUEL_BRAND:
			case self::TAX_PAYMENT_TYPE:
				if ( $term = $this->get_first_assigned_term( $post_ID, $column_name ) )
					echo $term->name;

				break;

			case self::META_ODOMETER:
				echo number_format_i18n( get_post_meta( $post_ID, $column_name, true ), 1 );
				break;

			case self::META_FUELUNITS:
				echo number_format_i18n( get_post_meta( $post_ID, $column_name, true ), 2 );
				break;

			case self::META_FUELUNITPRICE:
			case self::META_COST:
				echo $this->add_currency_symbol( get_post_meta( $post_ID, $column_name, true ) );
				break;

			case 'vehicleinfo_mileage':
				$fillups = array_values( array_reverse( $posts ) );
				foreach ( $fillups as $key => $fillup ) {
					if ( $fillup->ID !== $post_ID )
						continue;

					echo $this->get_mileage( $key, $fillups );

					break;
				}
				break;
		}
	}

	public function queue_fillup_screen_stuff() {
		add_action( 'admin_head', array( &$this, 'fillup_meta_box_javascript' ) );
	}

	public function fillup_meta_box_javascript() { ?>

		<script type="text/javascript">
			jQuery(document).ready(function($){
				$( "#vehicleinfo_fuelunitprice, #vehicleinfo_fuelunits" ).change( function() {
					if ( self::META_FUELUNITPRICE == $( this ).prop( "id" ) ) {
						var unitprice = $( this ).val();
						var units     = $( "#vehicleinfo_fuelunits" ).val();
					} else {
						var unitprice = $( "#vehicleinfo_fuelunitprice" ).val();
						var units     = $( this ).val();
					}

					// Only fill in the cost if both units and unit price is filled in
					if ( 0 == unitprice.length || 0 == units.length )
						return;

					var cost = parseFloat( unitprice ) * parseFloat( units );

					$( "#vehicleinfo_cost" ).val( cost.toFixed( 2 ) );
				});
			});
		</script>

<?php
	}

	public function register_cmb_meta_boxes( $meta_boxes ) {
		$meta_boxes[] = array(
			'id'         => 'cmb_vehicleinfo',
			'title'      => 'Vehicle Information',
			'pages'      => array( self::CPT_FILLUP, self::CPT_SERVICE ),
			'context'    => 'normal',
			'priority'   => 'high',
			'show_names' => true,
			'fields'     => array(
				array(
					'name'                 => 'Odometer Reading',
					'desc'                 => 'No thousands separator!',
					'id'                   => self::META_ODOMETER,
					'type'                 => 'text_small',
					'vehicleinfo_validate' => 'number_float_nothousands', // Custom
				),
				array(
					'name'                 => 'Vehicle',
					'id'                   => self::TAX_VEHICLE,
					'type'                 => 'vehicleinfo_taxonomy_select_default',
					'taxonomy'             => self::TAX_VEHICLE,
				),
			),
		);

		// TODO: No defaults for anything but new posts

		$meta_boxes[] = array(
			'id'         => 'cmb_vehicleinfo_fillup',
			'title'      => 'Fill Up Details',
			'pages'      => array( self::CPT_FILLUP ),
			'context'    => 'normal',
			'priority'   => 'high',
			'show_names' => true,
			'fields'     => array(
				array(
					'name'                 => 'Fill Up Type',
					'desc'                 => 'Did you fill the tank up or only partially? Select &quot;Reset&quot; if you forgot to enter a fill up and need to start over.',
					'id'                   => self::TAX_FILLUP_TYPE,
					'type'                 => 'vehicleinfo_taxonomy_select_default',
					'taxonomy'             => self::TAX_FILLUP_TYPE,
					'std'                  => ( $full = get_term_by( 'slug', 'full', self::TAX_FILLUP_TYPE ) ) ? $full->term_id : null,
				),
				array(
					'name'                 => 'Fuel Type',
					'id'                   => self::TAX_FUEL_TYPE,
					'type'                 => 'vehicleinfo_taxonomy_select_default',
					'taxonomy'             => self::TAX_FUEL_TYPE,
					//'std'                  => ( $regular = get_term_by( 'slug', 'regular', self::TAX_FUEL_TYPE ) ) ? $regular->term_id : null,
				),
				array(
					'name'                 => 'Payment Type',
					'desc'                 => sprintf( 'Add new types from the <a href="%s">edit payment types page</a>', esc_url( admin_url( 'edit-tags.php?taxonomy=vehicleinfo_payment_type&post_type=' . self::CPT_FILLUP ) ) ),
					'id'                   => self::TAX_PAYMENT_TYPE,
					'type'                 => 'vehicleinfo_taxonomy_select_default',
					'taxonomy'             => self::TAX_PAYMENT_TYPE,
				),
				array(
					'desc'                 => "You only need to enter data into 2 of the 3 following fields. The third value can be calculated. Don't enter any thousands separators!",
					'id'                   => 'title_fillup_details',
					'type'                 => 'title',
				),
				array(
					'name'                 => 'Cost per Fuel Unit',
					'desc'                 => esc_html( sprintf( __( 'As in %1$s per %2$s', 'fuel unit price example', 'vehicle-info' ), $this->add_currency_symbol( '4.50' ), $this->labels['volume_singular'] ) ),

					'id'                   => self::META_FUELUNITPRICE,
					'type'                 => 'vehicleinfo_text_money',
				),
				array(
					'name'                 => 'Units of Fuel',
					'desc'                 => sprintf( __( 'How many %s did you buy?', 'vehicle-info' ), $this->labels['volume_plural'] ),
					'id'                   => self::META_FUELUNITS,
					'type'                 => 'text_small',
					'vehicleinfo_validate' => 'number_float_nothousands', // Custom
				),
				array(
					'name'                 => 'Total Cost',
					'id'                   => self::META_COST,
					'type'                 => 'vehicleinfo_text_money',
				),
			),
		);

		return $meta_boxes;
	}

	public function array_to_cmb_options( $array ) {
		$options = array();

		foreach ( $array as $value => $name )
			$options[] = array( 'name' => $name, 'value' => $value );

		return $options;
	}

	public function cmb_render_taxonomy_select_default( $field, $meta ) {
		global $post;

		$used_terms = wp_get_object_terms( $post->ID, $field['taxonomy'] );

		if ( ! empty( $used_terms ) && is_array( $used_terms ) )
			$selected = $used_terms[0]->term_id;
		else
			$selected = $field['std'];

		wp_dropdown_categories( array(
			'show_option_none' => __( '&mdash; Select &mdash;' ),
			'taxonomy' => $field['taxonomy'],
			'hierarchical' => 1,
			'orderby' => 'name',
			'hide_empty' => 0,
			'name' => $field['id'],
			'selected' => (int) $selected,
		) );

		if ( ! empty( $field['desc'] ) )
			echo '<p class="cmb_metabox_description">' . $field['desc'] . '</p>';
	}

	public function cmb_render_vehicleinfo_text_money( $field, $meta ) {
		$value = ( ! empty( $meta ) ) ? $meta : $field['std'];

		echo trim( $this->add_currency_symbol( ' <input class="cmb_text_money" type="text" name="' . esc_attr( $field['id'] ) . '" id="' . esc_attr( $field['id'] ) . '" value="' . esc_attr( $value ) . '" /> ' ) ) . '<span class="cmb_metabox_description">' . $field['desc'] . '</span>';
	}

	public function cmb_validate( $value, $post_ID, $field ) {
		if ( empty( $value ) )
			return $value;

		// Don't validate fields not created by this plugin
		if ( 'vehicleinfo_' != substr( $field['id'], 0, strlen( 'vehicleinfo_' ) ) )
			return $value;

		// Was a specific validation method specified?
		if ( ! empty( $field['vehicleinfo_validate'] ) ) {
			switch ( $field['vehicleinfo_validate'] ) {
				case 'number_float_nothousands':
					$value = $this->float_nothousands( $value );
					break;

				default:
					$value = strip_tags( $value );
			}
		}

		// If not, validate based on field type
		else {
			switch ( $field['type'] ) {
				case 'select':
					if ( ! in_array( $value, wp_list_pluck( $field['options'], 'value' ) ) )
						$value = '';
					break;

				case 'vehicleinfo_taxonomy_select_default':
					$value = (int) $value;

					if ( $value > 1 && get_term_by( 'id', $value, $field['taxonomy'] ) )
						wp_set_object_terms( $post_ID, $value, $field['taxonomy'] );

					// Don't set any post meta
					$value = '';
					break;

				case 'vehicleinfo_text_money':
					$value = $this->float_nothousands( $value );
					break;

				default:
					$value = strip_tags( $value );
			}
		}

		// If this is the total cost and both units and unit price is filled out,
		// then discard the user entered cost and calculate it again so it's always correct.
		if ( self::META_COST == $field['id'] && ! empty( $_POST[self::META_FUELUNITPRICE] ) && ! empty( $_POST[self::META_FUELUNITS] ) ) {
			$value = $this->float_nothousands( $_POST[self::META_FUELUNITPRICE] ) * $this->float_nothousands( $_POST[self::META_FUELUNITS] );
			$value = sprintf( '%01.2f', $value );
		}

		return $value;
	}

	public function set_post_title_on_save( $post_ID, $post = false ) {
		if ( ! $post = get_post( $post_ID ) )
			return;

		if ( self::CPT_FILLUP != $post->post_type )
			return;

		$update_post = array();
		$update_post['ID'] = $post->ID;

		if ( empty( $post->post_date_gmt ) || '0000-00-00 00:00:00' == $post->post_date_gmt )
			$update_post['post_title'] = 'Fuel Fill Up';
		else
			$update_post['post_title'] = 'Fuel Fill Up On ' . date( get_option( 'date_format' ), mysql2date( 'U', $post->post_date_gmt ) );

		$update_post['post_name'] = sanitize_title( $update_post['post_title'] );

		// Prevent infinite loops
		remove_action( current_filter(), array( &$this, __FUNCTION__ ) );
		wp_update_post( $update_post );
		add_action( current_filter(), array( &$this, __FUNCTION__ ) );
	}

	public function register_settings() {
		register_setting( self::OPTION_NAME, self::OPTION_NAME, array( &$this, 'settings_validate' ) );
	}

	public function register_settings_page() {
		$hook_suffix = add_options_page( 'Vehicle Info', 'Vehicle Info', 'manage_options', self::SETTINGS_SLUG, array( &$this, 'settings_page' ) );

		add_action( "admin_print_styles-$hook_suffix", array( &$this, 'enqueue_settings_page_scripts_and_styles' ) );
	}

	public function enqueue_settings_page_scripts_and_styles() {
		wp_enqueue_style(  'vehicle-info-settings-page', plugins_url( 'settings-page.css', __FILE__ ), false, self::VERSION );
		wp_enqueue_script( 'vehicle-info-settings-page', plugins_url( 'settings-page.js',  __FILE__ ), array( 'farbtastic' ), self::VERSION );
		wp_enqueue_style( 'farbtastic' );
	}

	public function settings_page() {

		echo '<div class="wrap">';

		screen_icon(); // TODO: Better icon


		// Show step 2 of the import process if we're mid -import
		if ( $this->import_attachment ) {

			echo '<h2>' . __( 'Vehicle Info: Import From Gas Cubby', 'vehicle-info' ) . '</h2>';

			// Vehicles
			if ( ! $vehicles = $this->get_unique_values( $this->import_data, 'Vehicle' ) ) {
				echo '<p>' . esc_html__( "There doesn't appear to be any valid entries in this import. Are you sure it's correct?", 'vehicle-info' ) . '</p>';
				echo '<p><a href="' . esc_url( remove_query_arg( 'importing' ) ) . '">' . __( '&laquo; Back' ) . '</a></p>';
				echo '</div>';
				return;
			}

			add_settings_section( 'vehicleinfo_import_map_vehicles', __( 'Vehicles', 'vehicle-info' ), array( &$this, 'settings_section_import_vehicles' ), 'vehicleinfo_import_map' );
			foreach ( $vehicles as $key => $vehicle ) {
				add_settings_field(
					"vehicleinfo_import_map_vehicle_{$key}", $vehicle, array( &$this, 'settings_field_import_map' ), 'vehicleinfo_import_map', 'vehicleinfo_import_map_vehicles', array(
						'taxonomy' => self::TAX_VEHICLE,
						'new_text' => __( '&mdash; Create As New Vehicle &mdash;', 'vehicle-info' ),
						'id'       => $key,
						'value'    => $vehicle,
					)
				);
			}

			// Fuel Types
			if ( $fuel_types = $this->get_unique_values( $this->import_data, 'Octane' ) ) {
				add_settings_section( 'vehicleinfo_import_map_fuel_types', __( 'Fuel Types', 'vehicle-info' ), array( &$this, 'settings_section_import_fuel_types' ), 'vehicleinfo_import_map' );
				foreach ( $fuel_types as $key => $fuel_type ) {
					add_settings_field(
						"vehicleinfo_import_map_fuel_type_{$key}", $fuel_type, array( &$this, 'settings_field_import_map' ), 'vehicleinfo_import_map', 'vehicleinfo_import_map_fuel_types', array(
							'taxonomy' => self::TAX_FUEL_TYPE,
							'new_text' => __( '&mdash; Create As New Fuel Type &mdash;', 'vehicle-info' ),
							'id'       => $key,
							'value'    => $fuel_type,
						)
					);
				}
			}

			// Fuel Brands
			if ( $fuel_brands = $this->get_unique_values( $this->import_data, 'Gas Brand' ) ) {
				add_settings_section( 'vehicleinfo_import_map_fuel_brands', __( 'Fuel Brands', 'vehicle-info' ), array( &$this, 'settings_section_import_fuel_brands' ), 'vehicleinfo_import_map' );
				foreach ( $fuel_brands as $key => $fuel_brand ) {
					add_settings_field(
						"vehicleinfo_import_map_fuel_brand_{$key}", $fuel_brand, array( &$this, 'settings_field_import_map' ), 'vehicleinfo_import_map', 'vehicleinfo_import_map_fuel_brands', array(
							'taxonomy' => self::TAX_FUEL_BRAND,
							'new_text' => __( '&mdash; Create As New Fuel Brand &mdash;', 'vehicle-info' ),
							'id'       => $key,
							'value'    => $fuel_brand,
						)
					);
				}
			}

			// Locations
			if ( $locations = $this->get_unique_values( $this->import_data, 'Location' ) ) {
				add_settings_section( 'vehicleinfo_import_map_locations', __( 'Locations', 'vehicle-info' ), array( &$this, 'settings_section_import_locations' ), 'vehicleinfo_import_map' );
				foreach ( $locations as $key => $location ) {
					add_settings_field(
						"vehicleinfo_import_map_location_{$key}", $location, array( &$this, 'settings_field_import_map' ), 'vehicleinfo_import_map', 'vehicleinfo_import_map_locations', array(
							'taxonomy' => self::TAX_LOCATION,
							'new_text' => __( '&mdash; Create As New Location &mdash;', 'vehicle-info' ),
							'id'       => $key,
							'value'    => $location,
						)
					);
				}
			}

			// Payment Types
			if ( $payment_types = $this->get_unique_values( $this->import_data, 'Payment Type' ) ) {
				add_settings_section( 'vehicleinfo_import_map_payment_types', __( 'Payment Types', 'vehicle-info' ), array( &$this, 'settings_section_import_payment_types' ), 'vehicleinfo_import_map' );
				foreach ( $payment_types as $key => $payment_type ) {
					add_settings_field(
						"vehicleinfo_import_map_payment_type_{$key}", $payment_type, array( &$this, 'settings_field_import_map' ), 'vehicleinfo_import_map', 'vehicleinfo_import_map_payment_types', array(
							'taxonomy' => self::TAX_PAYMENT_TYPE,
							'new_text' => __( '&mdash; Create As New Payment Type &mdash;', 'vehicle-info' ),
							'id'       => $key,
							'value'    => $payment_type,
						)
					);
				}
			}

			echo '<p>' . __( 'Nearly done! You just need to associate vehicles and fuel types from Gas Cubby with vehicles and fuel types in Vehicle Info.', 'vehicle-info' ) . '</p>';

			echo '<form action="' . esc_url( remove_query_arg( '_wpnonce' ) ) . '" method="post">';
			echo '<input type="hidden" name="vehicleinfo_import_attachment_id" value="' . esc_attr( $this->import_attachment['id'] ) . '" />';

			wp_nonce_field( 'vehicle-info-import-' . $this->import_attachment['id'] );

			do_settings_sections( 'vehicleinfo_import_map' );

			submit_button( __( 'Finish Import', 'vehicle-info' )) ;

			echo '<p>' . __( 'Be patient! This can take a few moments, especially if importing a large number of entires!', 'vehicle-info' ) . '</p>';

			echo '</form>';
		}

		// Otherwise display the settings page
		else {

			add_settings_section( 'vehicleinfo_labels', esc_html__( 'Currency & Units', 'vehicle-info' ), array( &$this, 'settings_section_units' ), 'vehicleinfo' );
			add_settings_field( 'vehicleinfo_unit_system', esc_html__( 'Unit System', 'vehicle-info' ), array( &$this, 'settings_field_select' ), 'vehicleinfo', 'vehicleinfo_labels', array(
				'name'        => 'unit_system',
				'description' => esc_html__( 'Gallons and miles or liters and kilometers?', 'vehicle-info' ),
				'options'     => array(
					'imperial' => esc_html__( 'Imperial', 'vehicle-info' ),
					'metric'   => esc_html__( 'Metric', 'vehicle-info' ),
				),
			) );
			add_settings_field( 'vehicleinfo_currency_symbol', esc_html__( 'Currency Symbol', 'vehicle-info' ), array( &$this, 'settings_field_text_input' ), 'vehicleinfo', 'vehicleinfo_labels', array(
				'name'        => 'currency_symbol',
				'description' => esc_html__( "What's the symbol for you currency? Examples include $, €, and £.", 'vehicle-info' ),
				'size'        => 'small',
			) );
			add_settings_field( 'vehicleinfo_currency_symbol_placement', esc_html__( 'Currency Symbol Placement', 'vehicle-info' ), array( &$this, 'settings_field_select' ), 'vehicleinfo', 'vehicleinfo_labels', array(
				'name'        => 'currency_symbol_placement',
				'description' => esc_html__( 'Should the currency symbol go before or after the number?', 'vehicle-info' ),
				'options'     => array(
					'before' => esc_html__( 'Before', 'vehicle-info' ),
					'after'  => esc_html__( 'After', 'vehicle-info' ),
				),
			) );

			add_settings_section( 'vehicleinfo_chart_customization',    esc_html__( 'Chart Customization',      'vehicle-info' ), array( &$this, 'settings_section_chart_customization' ), 'vehicleinfo' );
			add_settings_field( 'vehicleinfo_overall_background_color', esc_html__( 'Overall Background Color', 'vehicle-info' ), array( &$this, 'settings_field_text_input_color' ), 'vehicleinfo', 'vehicleinfo_chart_customization', array(
				'name'        => 'color_background',
				'description' => sprintf( esc_html__( 'Enter %s for a transparent background.', 'vehicle-info' ), '<code>none</code>' ),
			) );
			add_settings_field( 'vehicleinfo_overall_border_color',     esc_html__( 'Overall Border Color',     'vehicle-info' ), array( &$this, 'settings_field_text_input_color' ), 'vehicleinfo', 'vehicleinfo_chart_customization', array( 'name' => 'color_border' ) );
			add_settings_field( 'vehicleinfo_overall_border_width',     esc_html__( 'Overall Border Width',     'vehicle-info' ), array( &$this, 'settings_field_text_input'       ), 'vehicleinfo', 'vehicleinfo_chart_customization', array(
				'name'        => 'border_width',
				'description' => esc_html__( 'pixels', 'vehicle-info' ),
				'size'        => 'small',
			) );
			add_settings_field( 'vehicleinfo_axis_color',               esc_html__( 'Axis Color',               'vehicle-info' ), array( &$this, 'settings_field_text_input_color' ), 'vehicleinfo', 'vehicleinfo_chart_customization', array( 'name' => 'axis_color' ) );
			add_settings_field( 'vehicleinfo_grid_line_color',          esc_html__( 'Grid Line Color',          'vehicle-info' ), array( &$this, 'settings_field_text_input_color' ), 'vehicleinfo', 'vehicleinfo_chart_customization', array( 'name' => 'grid_line_color' ) );
			add_settings_field( 'vehicleinfo_line_colors',              esc_html__( 'Default Line Colors',      'vehicle-info' ), array( &$this, 'settings_field_text_input'       ), 'vehicleinfo', 'vehicleinfo_chart_customization', array(
				'name'        => 'line_colors',
				'description' => esc_html__( "A comma separated list of colors to use for the data lines. They'll be used in order.", 'vehicle-info' ),
				'size'        => 'regular',
			) );
			add_settings_field( 'vehicleinfo_font_size',                esc_html__( 'Font Size',                'vehicle-info' ), array( &$this, 'settings_field_text_input'       ), 'vehicleinfo', 'vehicleinfo_chart_customization', array(
				'name'        => 'font_size',
				'description' => esc_html__( 'pixels', 'vehicle-info' ),
				'size'        => 'small',
			) );
			add_settings_field( 'vehicleinfo_font_name',                esc_html__( 'Font Name',                 'vehicle-info' ), array( &$this, 'settings_field_text_input'       ), 'vehicleinfo', 'vehicleinfo_chart_customization', array(
				'name'        => 'font_name',
				'description' => esc_html__( 'What font should be used in the charts? By default Arial is used.', 'vehicle-info' ),
				'size'        => 'regular',
			) );


			add_settings_section( 'vehicleinfo_import', 'Import From Gas Cubby', array( &$this, 'settings_section_import' ), 'vehicleinfo_import' );

			?>

		<h2><?php _e( 'Vehicle Info', 'vehicle-info' ); ?></h2>

		<form method="post" action="options.php">

			<?php settings_fields( 'vehicleinfo' ); // matches value from register_setting() ?>

			<?php do_settings_sections( 'vehicleinfo' ); // matches values from add_settings_section/field() ?>

			<?php submit_button(); ?>

		</form>

		<?php do_settings_sections( 'vehicleinfo_import' ); ?>

		<?php
			// Come back here for step 2 (handled above)
			wp_import_upload_form( admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG ) );
		?>
<?php

			var_dump( $this->settings );

		} // endif $_GET['importing']

		echo '</div>'; // wrap
	}

	public function settings_section_units() {
		echo '<p>' . __( 'Select what currency label and units you would like to use. This is purely for display purposes and can be changed at any time &mdash; all of the data is all stored without any units attached.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_chart_customization() {
		echo '<p>' . __( 'Customize the colors and other aspects of the graphs to match your site. Leave blank to use the default value.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_import() {
		echo '<p>' . sprintf( __( 'If you use the <a href="%s">Gas Cubby</a> iPhone app to track fuel fill ups and vehicle services, you can import that data here.', 'vehicle-info' ), 'http://appcubby.com/gas-cubby/' ) . '</p>';
		echo '<p>' . __( 'Start by pressing the bottom-left arrow until &quot;All Vehicles&quot; is showing. Once it is, tap the search icon in the upper left of Gas Cubby and then select the &quot;Export&quot; button that shows up in the upper right. Send the e-mail to yourself. While the content of the e-mail will contain your exported data, there will also be an attached <code>.csv</code> file. Upload that CSV file here.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_import_vehicles() {
		echo '<p>' . __( 'Assign Gas Cubby vehicle names to either existing vehicles or create a new vehicle.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_import_fuel_types() {
		echo '<p>' . __( 'Assign Gas Cubby fuel types (octane rating) to either existing fuel types or create a new fuel type.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_import_fuel_brands() {
		echo '<p>' . __( 'Assign Gas Cubby fuel brands to either existing fuel brands or create a new fuel brand.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_import_locations() {
		echo '<p>' . __( 'Assign Gas Cubby locations to either existing locations or create a new location.', 'vehicle-info' ) . '</p>';
	}

	public function settings_section_import_payment_types() {
		echo '<p>' . __( 'Assign Gas Cubby payment types to either existing payment types or create a new payment type.', 'vehicle-info' ) . '</p>';
	}

	public function settings_field_import_map( $args ) {
		wp_dropdown_categories( array(
			'taxonomy'         => $args['taxonomy'],
			'orderby'          => 'name',
			'hide_empty'       => false,
			'name'             => $args['taxonomy'] . '_import_mapping[' . (int) $args['id'] . ']',
			'id'               => $args['taxonomy'] . '_import_mapping_' . (int) $args['id'],
			'show_option_none' => $args['new_text'],
			'selected'         => $this->guess_import_map( $args ),
		) );
	}

	public function settings_field_text_input( $args ) {
		if ( empty( $args['name'] ) )
			return false;

		if ( empty( $args['size'] ) )
			$args['size'] = 'regular';

		$value = ( isset( $this->settings[ $args['name'] ] ) ) ? $this->settings[ $args['name'] ] : '';

		echo '<input type="text" name="vehicleinfo[' . esc_attr( $args['name'] ) . ']" value="' . esc_attr( $value ) . '" class="' . esc_attr( $args['size'] . '-text' ) . '" />';

		if ( ! empty( $args['description'] ) )
			echo ' <span class="description">' . $args['description'] . '</span>';
	}

	public function settings_field_text_input_color( $args ) {
		if ( empty( $args['name'] ) )
			return false;

		$value = ( isset( $this->settings[ $args['name'] ] ) ) ? $this->settings[ $args['name'] ] : '';

?>

		<div class="vehicle-info-color-selector">
			<input class="vehicle-info-color-selector-input" type="text" name="vehicleinfo[<?php echo esc_attr( $args['name'] ); ?>]" value="<?php echo esc_attr( $value ); ?>"/>
			<span  class="vehicle-info-color-selector-preview hide-if-no-js" style="background-color:<?php echo esc_attr( $value ); ?>"></span>
			<input class="vehicle-info-color-selector-button button hide-if-no-js" type="button" value="<?php esc_attr_e( 'Select a Color', 'vehicle-info' ); ?>" />
			<div   class="vehicle-info-color-selector-picker"></div>
<?php

		if ( ! empty( $args['description'] ) )
			echo ' <span class="description">' . $args['description'] . '</span>';

		echo "\t\t</div>\n";
	}

	public function settings_field_select( $args ) {
		if ( empty( $args['name'] ) || ! is_array( $args['options'] ) )
			return false;

		$selected = ( isset( $this->settings[ $args['name'] ] ) ) ? $this->settings[ $args['name'] ] : '';

		echo '<select name="vehicleinfo[' . esc_attr( $args['name'] ) . ']">';

		foreach ( (array) $args['options'] as $value => $label )
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $selected, false ) . '>' . $label . '</option>';

		echo '</select>';

		if ( ! empty( $args['description'] ) )
			echo ' <span class="description">' . $args['description'] . '</span>';
	}

	public function guess_import_map( $args ) {
		switch ( $args['taxonomy'] ) {
			case self::TAX_FUEL_TYPE:
				switch ( $args['value'] ) {
					case '87':
						$slug = 'regular';
						break;
					case '89':
						$slug = 'plus';
						break;
					case '90+':
					case '91':
						$slug = 'premium';
						break;
					case 'Diesel':
						$slug = 'diesel';
						break;
					default:
						return 0;
				}

				if ( ! $term = get_term_by( 'slug', $slug, $args['taxonomy'] ) )
					return 0;

				return (int) $term->term_id;

			default:
				// Yeah, this is badass. I know. :D
				$terms = get_terms( $args['taxonomy'], array(
					'search'     => esc_html( $args['value'] ), // Term names are HTML escaped
					'hide_empty' => false,
					'orderby'    => 'count',
					'order'      => 'DESC',
					'number'     => 1,
					'fields'     => 'ids',
				) );

				if ( empty( $terms ) || is_wp_error( $terms ) )
					return 0;

				return (int) $terms[0];
		}

		return 0;
	}


	public function settings_validate( $settings ) {
		// TODO: VALIDATE
		return $settings;
	}

	public function maybe_process_upload_or_import() {

		// Submission of the standardized upload form
		if ( ! empty( $_POST['action'] ) && 'save' == $_POST['action'] ) {

			if ( ! check_admin_referer( 'import-upload' ) )
				return;

			$import = wp_import_handle_upload();

			// Display the error if there was one
			if ( isset( $import['error'] ) ) {
				add_settings_error( 'vehicleinfo', 'vehicleinfo_import_error', $import['error'] );
				return;
			}

			$this->import_attachment = $import;

			if ( ! is_file( $this->import_attachment['file'] ) ) {
				add_settings_error( 'vehicleinfo', 'vehicleinfo_import_error', __( "Something went wrong. The import file you just uploaded can't be found in your upload folder.", 'vehicle-info' ) );
				return;
			}

			if ( ! $import_data = file_get_contents( $this->import_attachment['file'] ) ) {
				add_settings_error( 'vehicleinfo', 'vehicleinfo_import_error', __( "Unable to parse import file. Are you sure it's a valid GasCubby export file?", 'vehicle-info' ) );
				return;
			}

			$this->import_data = $this->import_parse( $import_data );

			if ( empty( $this->import_data ) ) {
				add_settings_error( 'vehicleinfo', 'vehicleinfo_import_error', __( "Unable to parse import file. Are you sure it's a valid GasCubby export file?", 'vehicle-info' ) );
				return;
			}

			// All good. The settings page will handle mapping.
		}

		// Submission of the mapping form
		elseif ( ! empty( $_POST['vehicleinfo_import_attachment_id'] ) ) {

			if ( ! check_admin_referer( 'vehicle-info-import-' . $_POST['vehicleinfo_import_attachment_id'] ) )
				return;

			if ( $results = $this->do_import( $_POST['vehicleinfo_import_attachment_id'] ) ) {
				if ( $results['skipped'] || $results['error'] ) {
					$message = sprintf( __( 'Successfully imported %1$s entries, skipped %2$s existing entries, and encounted %3$s errors.', 'vehicle-info' ), $results['imported'], $results['skipped'], $results['error'] );
				} else {
					$message = sprintf( _n( 'Successfully imported 1 entry.', 'Successfully imported %s entries.', $results['imported'], 'vehicle-info' ), $results['imported'] );
				}
				add_settings_error( 'vehicleinfo_import', 'vehicleinfo_import_completed', $message, 'updated' );
			} else {
				add_settings_error( 'vehicleinfo_import', 'vehicleinfo_import_failed', __( 'Something went wrong with the import. Please try again.', 'vehicle-info' ) );
			}
		}
	}

	public function import_parse( $string ) {
		$string = str_replace( "\r\n", "\n", $string ); // Stupid Windows
		$rows = explode( "\n", $string );
		$rows = array_filter( $rows );
		$rows = array_map( 'trim', $rows );
		$rows = array_map( 'str_getcsv', $rows );

		// The first row is the column labels/headers
		$labels = array_shift( $rows );

		// Replace the numeric keys with the labels to make an associative array
		foreach ( $rows as $row ) {
			$entries[] = array_combine( $labels, $row );
		}

		// Remove invalid entries
		foreach ( $entries as $key => $entry ) {
			if ( empty( $entry['Type'] ) || empty( $entry['Date'] ) || empty( $entry['Vehicle'] ) ) {
				unset( $entries[$key] );
			}
		}

		return $entries;
	}

	public function get_unique_values( $list, $field ) {
		// This function can get asked to get missing keys, so check they exist
		foreach ( $list as $key => $list_item ) {
			if ( ( is_object( $list_item ) && ! method_exists( $list_item->field ) ) || ( is_array( $list_item ) && ! isset( $list_item[$field] ) ) ) {
				unset( $list[$key] );
			}
		}

		if ( empty( $list ) )
			return false;

		return array_filter( array_unique( wp_list_pluck( $list, $field ) ) );
	}

	public function do_import( $attachment_id ) {

		$file = get_attached_file( $attachment_id );

		if ( ! $file  || ! is_file( $file ) )
			return false;

		$import_data = (array) $this->import_parse( file_get_contents( $file ) );

		$results = array(
			'imported' => 0,
			'skipped'  => 0,
			'error'    => 0,
		);

		// Process each entry
		foreach ( $import_data as $entry ) {

			switch ( $entry['Type'] ) {
				case 'Gas':
					$post_type = self::CPT_FILLUP;
					break;

				case 'Service':
					$post_type = self::CPT_SERVICE;
					break;

				// Unknown type, skip it
				default:
					$results['error']++;
					continue 2;
			}

			$post_date = $this->get_datetime( $entry );
			$post_timestamp = strtotime( $post_date ); // Yeah, I know (back and forth)

			wp_die( 'fix duplicate detection' );

			// Skip already imported entries
			// TODO: Multiple items per.... hmm
			$existing_post = new WP_Query( array(
				'post_type'      => $post_type,
				'posts_per_page' => 1,

				'year'           => date( 'Y', $post_timestamp ),
				'monthnum'       => date( 'm', $post_timestamp ),
				'day'            => date( 'd', $post_timestamp ),
				'hour'           => date( 'H', $post_timestamp ),
				'minute'         => date( 'i', $post_timestamp ),
			) );
			if ( $existing_post->have_posts() ) {
				$results['skipped']++;
				continue;
			}



			$post_data = array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
				'post_type'   => $post_type,
			);

			if ( ! empty( $entry['Notes'] ) )
				$post_data['post_content'] = $entry['Notes'];

			if ( $vehicle_term_id = $this->import_get_mapping_result( $import_data, $entry, 'Vehicle', self::TAX_VEHICLE ) )
				$post_data['tax_input'][self::TAX_VEHICLE] = array( $vehicle_term_id );

			if ( $location_term_id = $this->import_get_mapping_result( $import_data, $entry, 'Location', self::TAX_LOCATION ) )
				$post_data['tax_input'][self::TAX_LOCATION] = array( $location_term_id );


			if ( 'Gas' == $entry['Type'] ) {

				$post_data['post_title'] = 'Fuel Fill Up';

				if ( $fuel_type_term_id = $this->import_get_mapping_result( $import_data, $entry, 'Octane', self::TAX_FUEL_TYPE ) )
					$post_data['tax_input'][self::TAX_FUEL_TYPE] = array( $fuel_type_term_id );

				if ( $fuel_brand_term_id = $this->import_get_mapping_result( $import_data, $entry, 'Gas Brand', self::TAX_FUEL_BRAND ) )
					$post_data['tax_input'][self::TAX_FUEL_BRAND] = array( $fuel_brand_term_id );

				if ( $payment_type_term_id = $this->import_get_mapping_result( $import_data, $entry, 'Payment Type', self::TAX_PAYMENT_TYPE ) )
					$post_data['tax_input'][self::TAX_PAYMENT_TYPE] = array( $payment_type_term_id );

				// Only pre-defined terms for this taxonomy
				if ( ! empty( $entry['Filled Up'] ) ) {
					$slug = false;

					switch ( $entry['Filled Up'] ) {
						case 'Full':
							$slug = 'full';
							break;

						case 'Partial':
							$slug = 'partial';
							break;

						case 'Reset':
							$slug = 'reset';
							break;
					}

					if ( $slug ) {
						if ( $term = get_term_by( 'slug', $slug, self::TAX_FILLUP_TYPE ) ) {
							$post_data['tax_input'][self::TAX_FILLUP_TYPE] = array( $term->term_id );
						}
					}
				}


				$post_ID = wp_insert_post( $post_data );


				if ( ! empty( $entry['Cost/Gallon'] ) )
					update_post_meta( $post_ID, self::META_FUELUNITPRICE, $this->float_nothousands( $entry['Cost/Gallon'] ) );
				elseif ( ! empty( $entry['Cost/Liter'] ) )
					update_post_meta( $post_ID, self::META_FUELUNITPRICE, $this->float_nothousands( $entry['Cost/Liter'] ) );

				if ( ! empty( $entry['Gallons'] ) )
					update_post_meta( $post_ID, self::META_FUELUNITS, $this->float_nothousands( $entry['Gallons'] ) );
				elseif ( ! empty( $entry['Liters'] ) )
					update_post_meta( $post_ID, self::META_FUELUNITS, $this->float_nothousands( $entry['Liters'] ) );
			}

			elseif ( 'Service' == $entry['Type'] ) {

				$post_data['post_title'] = 'Service';

				$post_ID = wp_insert_post( $post_data );
			}

			if ( ! empty( $entry['Odometer'] ) )
				update_post_meta( $post_ID, self::META_ODOMETER, $this->human_to_float( $entry['Odometer'] ) );

			if ( ! empty( $entry['Total Cost'] ) )
				update_post_meta( $post_ID, self::META_COST, $this->float_nothousands( $entry['Total Cost'] ) );

			$results['imported']++;
		}

		// Remove the temporary import file
		wp_import_cleanup( $attachment_id );

		return $results;
	}

	public function import_get_mapping_result( $import_data, $entry, $field, $taxonomy ) {
		$term_id = false;

		if ( ! empty( $entry[$field] ) && is_array( $_POST[$taxonomy . '_import_mapping'] ) ) {
			$values = $this->get_unique_values( $import_data, $field );
			$value_key = array_search( $entry[$field], $values, true );

			if ( isset( $_POST[$taxonomy . '_import_mapping'][$value_key] ) ) {
				$item_id = $_POST[$taxonomy . '_import_mapping'][$value_key];

				// -1 means create a new term
				if ( '-1' == $item_id ) {
					$term = wp_insert_term( $entry[$field], $taxonomy );

					// If it was created, use it
					if ( is_array( $term ) && ! is_wp_error( $term ) ) {
						$term_id = $term['term_id'];
					} else {
						// The term must already exist. Let's see if we can find it.
						if ( $term = get_term_by( 'name', addslashes( $entry[$field] ), $taxonomy ) ) {
							$term_id = $term->term_id;
						}
					}
				} else {
					$term_id = (int) $item_id;
				}
			}
		}

		return $term_id;
	}

	public function get_datetime( $data ) {
		if ( empty( $data['Date'] ) )
			return false;

		$datetime = $data['Date'];

		if ( ! empty( $data['Time'] ) )
			$datetime .= ' ' . $data['Time'];
		else 
			$datetime .= ' 12:00'; // Middle of the day isn't affected by DST

		return date( 'Y-m-d H:i:s', strtotime( $datetime, current_time( 'timestamp' ) ) );
	}

	public function float_nothousands( $number ) {
		$number = str_replace( '$', '', $number );
		$number = str_replace( ',', '.', $number );

		return (float) $number;
	}

	public function human_to_float( $number ) {
		$number = str_replace( '$', '', $number );

		// By "info at marc-gutt dot de" via http://www.php.net/manual/en/function.floatval.php#85346
		$number = floatval( preg_replace( '#^([-]*[0-9\.,\' ]+?)((\.|,){1}([0-9-]{1,2}))*$#e', "str_replace(array('.', ',', \"'\", ' '), '', '\\1') . '.\\4'", $number ) );

		return $number;
	}

	public function add_currency_symbol( $string ) {
		return ( 'after' == $this->settings['currency_symbol_placement'] ) ? $string . $this->settings['currency_symbol'] : $this->settings['currency_symbol'] . $string;
	}

	public function return_error( $error ) {
		if ( is_wp_error( $error ) )
			$error = $error->get_error_message();

		// TODO: Remove red? There for debugging but maybe it's helpful
		return '<em class="vehicle-info-error" style="color:red">' . $error . '</em>';
	}

	public function get_post_meta_values( $meta_key, $type = 'total', $vehicle = null, $entry_type = 'any' ) {
		if ( 'maxmin' == $type )
			return $this->get_post_meta_max_minus_min( $meta_key, $vehicle );

		$query_args = array(
			'posts_per_page' => -1,
			'meta_query'     => array(
				array(
					'key'     => $meta_key,
					'value'   => 0,
					'type'    => 'numeric',
					'compare' => '>'
				),
			),
		);

		switch ( $entry_type ) {
			case 'fillup':
				$query_args['post_type'] = self::CPT_FILLUP;
				break;

			case 'service':
				$query_args['post_type'] = self::CPT_SERVICE;
				break;

			default:
				$query_args['post_type'] = array( self::CPT_FILLUP, self::CPT_SERVICE );
		}

		if ( ! empty( $vehicle ) ) {
			if ( ! $tax_query = $this->get_vehicle_tax_query( $vehicle ) )
				return new WP_Error( 'vinfo_invalid_vehicle', sprintf( __( 'No vehicle with the slug %s could be found.', 'vehicle-info' ), '<code>' . esc_html( $vehicle ) . '</code>' ) );

			$query_args['tax_query'] = $tax_query;
		}

		$entries = new WP_Query( $query_args );

		if ( ! $entries->have_posts() )
			return new WP_Error( 'vinfo_not_enough_entries', __( 'Not enough entries were found to fulfill your request.', 'vehicle-info' ) );

		$values = array();
		foreach ( $entries->posts as $entry )
			$values[] = (float) get_post_meta( $entry->ID, $meta_key, true );

		$sum = array_sum( $values );

		switch ( $type ) {
			case 'total':
			case 'sum':
				return $sum;

			case 'average':
				return $sum / count( $values );
		}

		// Unknown type returns nothing
	}

	public function get_post_meta_max_minus_min( $meta_key, $vehicle = null ) {
		$query_args = array(
			'post_type'      => array( self::CPT_FILLUP, self::CPT_SERVICE ),
			'meta_key'       => $meta_key, // For ordering
			'orderby'        => 'meta_value_num',
			'order'          => 'DESC',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'     => $meta_key,
					'value'   => 0,
					'type'    => 'numeric',
					'compare' => '>'
				),
			),
		);

		if ( ! empty( $vehicle ) ) {
			if ( ! $tax_query = $this->get_vehicle_tax_query( $vehicle ) ) {
				return new WP_Error( 'vinfo_invalid_vehicle', sprintf( __( 'No vehicle with the slug %s could be found.', 'vehicle-info' ), '<code>' . esc_html( $vehicle ) . '</code>' ) );
			}

			$query_args['tax_query'] = $tax_query;
		}

		$maximum = new WP_Query( $query_args );

		if ( ! $maximum->have_posts() )
			return new WP_Error( 'vinfo_not_enough_entries', __( 'Not enough entries were found to fulfill your request.', 'vehicle-info' ) );

		$query_args['order']   = 'ASC';
		$query_args['exclude'] = array( $maximum->posts[0]->ID );

		$minimum = new WP_Query( $query_args );

		if ( ! $minimum->have_posts() )
			return new WP_Error( 'vinfo_not_enough_entries', __( 'Not enough entries were found to fulfill your request.', 'vehicle-info' ) );

		$maximum_value = (float) get_post_meta( $maximum->posts[0]->ID, $meta_key, true );
		$minimum_value = (float) get_post_meta( $minimum->posts[0]->ID, $meta_key, true );

		return $maximum_value - $minimum_value;
	}

	public function get_value( $what, $vehicle = null, $decimals = null ) {
		switch ( $what ) {
			case 'miles':
			case 'mile':
			case 'kilometers':
			case 'kilometer':
			case '100km':
			case 'odometer':
				// TODO: Allow total distance put on all vehicles (will require calculating each separately)
				if ( empty( $vehicle ) )
					return new WP_Error( 'vinfo_missing_vehicle', sprintf( __( 'Missing %s parameter. You need to pass the slug of a vehicle entry.', 'vehicle-info' ), '<code>vehicle</code>' ) );

				$meta_key = self::META_ODOMETER;
				$type = 'maxmin';

				if ( is_null( $decimals ) )
					$decimals = 1;

				break;

			case 'cost':
			case 'dollars';
			case 'dollar':
			case 'euros':
			case 'euro':
				$meta_key = self::META_COST;
				$type = 'total';

				if ( is_null( $decimals ) )
					$decimals = 2;

				break;

			case 'gallons':
			case 'gallon':
			case 'liters':
			case 'liter':
				$meta_key = self::META_FUELUNITS;
				$type = 'total';

				if ( is_null( $decimals ) )
					$decimals = 2;

				break;

			default:
				return new WP_Error( 'vinfo_invalid_parameter', sprintf( __( '%s is an invalid parameter.', 'vehicle-info' ), "<code>{$what}</code>" ) );
		}

		$value = $this->get_post_meta_values( $meta_key, $type, $vehicle );

		if ( is_wp_error( $value ) )
			return $value;

		// TODO: Make less lame?
		if ( '100km' == $what )
			$value = $value / 100;

		return array(
			'number'   => $value,
			'human'    => number_format_i18n( $value, $decimals ),
			'decimals' => $decimals,
		);
	}

	public function get_vehicle_tax_query( $vehicle ) {
		if ( ! is_object( $vehicle ) ) {
			if ( ! $vehicle = get_term_by( 'slug', $vehicle, self::TAX_VEHICLE ) ) {
				return false;
			}
		}

		if ( empty( $vehicle->term_id ) )
			return false;

		$tax_query = array(
			array(
				'taxonomy' => self::TAX_VEHICLE,
				'field'    => 'id',
				'terms'    => array( $vehicle->term_id ),
			),
		);

		return $tax_query;
	}

	public function get_terms_for_posts( $posts, $taxonomy ) {
		$terms = array();

		foreach ( (array) $posts as $post ) {
			if ( ! $post_terms = get_the_terms( $post->ID, $taxonomy ) )
				continue;

			foreach ( $post_terms as $post_term )
				$terms[$post_term->term_id] = $post_term;
		}

		usort( $terms, function( $a, $b ) {
			return strcasecmp( $a->name, $b->name );
		} );

		return $terms;
	}

	public function get_mileage( $key, $fillups ) {

		#
		#
		# This function is still being tested. It's complicated to calculate!
		#
		#

		// TODO: Full, partial, full should work

		// Something is wrong here, check the Mustang graph. 40 MPG?!

		$key++;

		//var_dump( $key );
		//var_dump( $fillups );

		/*
		foreach ( $fillups as $fillup )
			var_dump( $this->get_first_assigned_term( $fillup, self::TAX_VEHICLE )->name );
		var_dump( '--------' );
		/**/

		//var_dump( $fillups[$key] );

		// Sanity check
		if ( empty( $fillups[$key] ) )
			return false;//'bad key';

		if ( ! $this_distance = get_post_meta( $fillups[$key]->ID, self::META_ODOMETER, true ) )
			return false;//'unknown odometer reading for this';

		if ( ! $this_fillup_type = $this->get_first_assigned_term( $fillups[$key], self::TAX_FILLUP_TYPE ) )
			return false;//'unknown fillup type';

		if ( 'full' != $this_fillup_type->slug )
			return false;//'bad fillup type ' . $this_fillup_type->slug;

		if ( ! $this_vehicle = $this->get_first_assigned_term( $fillups[$key], self::TAX_VEHICLE ) )
			return false;//'unknown vehicle';

		//var_dump( $this_vehicle );

		if ( ! $units = get_post_meta( $fillups[$key]->ID, self::META_FUELUNITS, true ) )
			return false;//'no units for this fillup';

		//var_dump( "we added $units this current fillup" );

		$previous_key = $key;

		while ( true ) {
			$previous_key--;

			if ( empty( $fillups[$previous_key] ) )
				return false;//'no previous key';

			$previous_fillup = $fillups[$previous_key];

			//var_dump( 'checking ' . $previous_fillup->ID );

			if ( ! $previous_distance = get_post_meta( $previous_fillup->ID, self::META_ODOMETER, true ) )
				return false;//'unknown odometer reading for this';

			$previous_vehicle = $this->get_first_assigned_term( $previous_fillup, self::TAX_VEHICLE );
			if ( $previous_vehicle->term_id != $this_vehicle->term_id ) {
				//var_dump( 'skipped ' . $previous_vehicle->name );
				continue;
			}

			if ( ! $previous_fillup_type = $this->get_first_assigned_term( $previous_fillup, self::TAX_FILLUP_TYPE ) ) {
				//var_dump( 'failed to get fillup type for ' . $previous_fillup->ID );
				continue;
			}

			switch ( $previous_fillup_type->slug ) {
				// This is what we were looking for
				case 'full':
					break 2;

				// Record the units added in the previous partial fillup and keep going
				case 'partial':
					$units = $units + get_post_meta( $previous_fillup->ID, self::META_FUELUNITS, true );
					//var_dump( 'it was a partial, skipping but adding this many units: ' . get_post_meta( $previous_fillup->ID, self::META_FUELUNITS, true ) );
					continue 2;

				// We ran across a reset, abort everything
				case 'reset':
					return false;//'previous was a reset';

				// Unknown type, skip this
				default:
					continue;
			}

			exit( 'logic error' );
		}

		//var_dump( $previous_fillup );

		//var_dump( "went from {$previous_distance} to {$this_distance} via {$units} units" );

		$distance = $this_distance - $previous_distance;

		if ( 'metric' == $this->settings['unit_system'] ) {
			return $units / ( $distance / 100 );
		} else {
			return $distance / $units;
		}
	}

	public function get_first_assigned_term( $post, $taxonomy ) {
		if ( ! $post = get_post( $post ) )
			return false;

		$terms = get_the_terms( $post->ID, $taxonomy );

		if ( ! $terms || is_wp_error( $terms ) )
			return false;

		return array_shift( $terms );
	}

	public function shortcode_list_fillups( $atts ) {
		$atts = shortcode_atts( array(
			'vehicle' => null,
			'number'  => -1,
		), $atts );

		if ( empty( $atts['vehicle'] ) )
			return $this->return_error( sprintf( __( 'Missing %s parameter. You need to pass the slug of a vehicle entry.', 'vehicle-info' ), '<code>vehicle</code>' ) );

		if ( ! $tax_query = $this->get_vehicle_tax_query( $atts['vehicle'] ) )
			return $this->return_error( sprintf( __( 'No vehicle with the slug %s could be found.', 'vehicle-info' ), '<code>' . esc_html( $vehicle ) . '</code>' ) );

		$fillups = new WP_Query( array(
			'post_type' => self::CPT_FILLUP,
			'posts_per_page' => $atts['number'],
			'tax_query' => $tax_query,
		) );

		if ( ! $fillups->have_posts() )
			return $this->return_error( __( 'No valid fillups found.', 'vehicle-info' ) );

		$return = '<ul>';

		while( $fillups->have_posts() ) {
			$fillups->the_post();

			$return .= '<li><a href="'. esc_url( get_permalink() ) . '">' . get_the_date() . '</a></li>';
		}

		$return .= '</ul>';

		wp_reset_postdata();

		return $return;
	}

	// TODO: Total value across all vehicles
	public function shortcode_total_value( $atts ) {
		// Make sure keys are set to avoid notices
		$atts = shortcode_atts( array(
			'what'     => null,
			'vehicle'  => null,
			'decimals' => null,
		), $atts );

		if ( empty( $atts['what'] ) )
			return $this->return_error( sprintf( __( 'Missing %s parameter specifying what thing you want to sum up.', 'vehicle-info' ), '<code>what</code>' ) );

		$value = $this->get_value( $atts['what'], $atts['vehicle'], $atts['decimals'] );

		if ( is_wp_error( $value ) )
			return $this->return_error( $value );

		return $value['human'];
	}

	// TODO: Average values across all vehicles
	public function shortcode_average( $atts ) {
		if ( empty( $atts['vehicle'] ) )
			return $this->return_error( sprintf( __( 'Missing %s parameter. You need to pass the slug of a vehicle entry.', 'vehicle-info' ), '<code>vehicle</code>' ) );

		if ( empty( $atts['what'] ) )
			return $this->return_error( sprintf( __( 'Missing %s parameter specifying what thing you want to sum up.', 'vehicle-info' ), '<code>what</code>' ) );

		if ( empty( $atts['per_what'] ) )
			return $this->return_error( sprintf( __( 'Missing %s parameter specifying what thing you want to sum up and divide by.', 'vehicle-info' ), '<code>per_what</code>' ) );

		$what = $this->get_value( $atts['what'], $atts['vehicle'] );

		if ( is_wp_error( $what ) )
			return $this->return_error( $what );

		$per_what = $this->get_value( $atts['per_what'], $atts['vehicle'] );

		if ( is_wp_error( $per_what ) )
			return $this->return_error( $per_what );

		if ( 0 == $per_what['number'] )
			return $this->return_error( __( 'Division by zero', 'vehicle-info' ) );

		if ( ! isset( $atts['decimals'] ) )
			$atts['decimals'] = $what['decimals'];

		return number_format_i18n( $what['number'] / $per_what['number'], $atts['decimals'] );
	}

	public function shortcode_chart_distance( $atts ) {
		$query_args = array(
			'post_type'      => array( self::CPT_FILLUP, self::CPT_SERVICE ),
			'posts_per_page' => -1,
			'meta_query'     => array(
				// Exclude zero mileage entries
				array(
					'key'     => self::META_ODOMETER,
					'value'   => 0,
					'type'    => 'numeric',
					'compare' => '>'
				),
			),
			'orderby' => 'post_date',
			'order'   => 'ASC',
		);

		if ( ! empty( $atts['vehicle'] ) ) {
			if ( ! $vehicle = get_term_by( 'slug', $atts['vehicle'], self::TAX_VEHICLE ) ) {
				return $this->return_error( sprintf( __( 'No vehicle with the slug %s could be found.', 'vehicle-info' ), '<code>' . esc_html( $atts['vehicle'] ) . '</code>' ) );
			}

			$query_args['tax_query'] = $this->get_vehicle_tax_query( $atts['vehicle'] );
		}

		$entries = new WP_Query( $query_args );

		if ( ! $entries->have_posts() )
			return $this->return_error( __( 'No valid entries found.', 'vehicle-info' ) );

		if ( ! empty( $atts['vehicle'] ) ) {
			$vehicles[] = $vehicle;
		} else {
			$vehicles = $this->get_terms_for_posts( $entries->posts, self::TAX_VEHICLE );
		}

		$columns = array(
			array(
				'id'    => 'date',
				'label' => 'Date',
				'type'  => 'date',
			),
		);
		foreach ( $vehicles as $vehicle ) {
			$columns[] = array(
				'id'    => 'vehicle_' . $vehicle->slug,
				'label' => $vehicle->name,
				'type'  => 'number',
			);
		}

		$rows = array();
		foreach ( $entries->posts as $key => $entry ) {
			if ( ! $entry_vehicle = $this->get_first_assigned_term( $entry, self::TAX_VEHICLE ) )
				continue;

			$data = array(
				array(
					'v' => (int) mysql2date( 'U', $entry->post_date ),
					'f' => mysql2date( get_option( 'date_format' ), $entry->post_date ),
				),
			);

			foreach ( $vehicles as $vehicle ) {
				if ( $vehicle->term_id === $entry_vehicle->term_id && $odometer = (float) get_post_meta( $entry->ID, self::META_ODOMETER, true ) ) {
					$data[] = array(
						'v' => $odometer,
						'f' => number_format_i18n( $odometer, 1 ) . ' ' . $this->labels['distance_plural'],
					);
				} else {
					$data[] = array(
						'v' => null,
					);
				}
			}

			$rows[] = $data;
		}

		if ( ! isset( $atts['title'] ) )
			$atts['title'] = __( 'Odometer Reading', 'vehicle-info' );

		return $this->create_chart( $atts, $columns, $rows );
	}

	public function shortcode_chart_mileage( $atts ) {
		$query_args = array(
			'post_type'      => self::CPT_FILLUP,
			'posts_per_page' => -1,
			'meta_query'     => array(
				// Exclude zero mileage entries
				array(
					'key'     => self::META_ODOMETER,
					'value'   => 0,
					'type'    => 'numeric',
					'compare' => '>'
				),
			),
			'orderby' => 'post_date',
			'order'   => 'ASC',
		);

		if ( ! empty( $atts['vehicle'] ) ) {
			if ( ! $vehicle = get_term_by( 'slug', $atts['vehicle'], self::TAX_VEHICLE ) ) {
				return $this->return_error( sprintf( __( 'No vehicle with the slug %s could be found.', 'vehicle-info' ), '<code>' . esc_html( $atts['vehicle'] ) . '</code>' ) );
			}

			$query_args['tax_query'] = $this->get_vehicle_tax_query( $atts['vehicle'] );
		}

		$fillups = new WP_Query( $query_args );

		if ( ! $fillups->have_posts() )
			return $this->return_error( __( 'No valid fillups found.', 'vehicle-info' ) );

		if ( ! empty( $atts['vehicle'] ) ) {
			$vehicles[] = $vehicle;
		} else {
			$vehicles = $this->get_terms_for_posts( $fillups->posts, self::TAX_VEHICLE );
		}

		$columns = array(
			array(
				'id'    => 'date',
				'label' => 'Date',
				'type'  => 'date',
			),
		);
		foreach ( $vehicles as $vehicle ) {
			$columns[] = array(
				'id'    => 'vehicle_' . $vehicle->slug,
				'label' => $vehicle->name,
				'type'  => 'number',
			);
		}

		$rows = array();
		foreach ( $fillups->posts as $key => $fillup ) {
			if ( ! $fillup_vehicle = $this->get_first_assigned_term( $fillup, self::TAX_VEHICLE ) )
				continue;

			$data = array(
				array(
					'v' => (int) mysql2date( 'U', $fillup->post_date ),
					'f' => mysql2date( get_option( 'date_format' ), $fillup->post_date ),
				),
			);

			foreach ( $vehicles as $vehicle ) {
				if ( $vehicle->term_id === $fillup_vehicle->term_id && $mileage = $this->get_mileage( $key, $fillups->posts ) ) {
					$data[] = array(
						'v' => $mileage,
						'f' => sprintf( __( '%1$s %2$s', 'mileage ([units] [label])', 'vehicle-info' ), number_format_i18n( $mileage, 1 ), $this->labels['mileage'] ),
					);
				} else {
					$data[] = array(
						'v' => null,
					);
				}
			}

			$rows[] = $data;
		}

		if ( ! isset( $atts['title'] ) )
			$atts['title'] = 'Mileage';

		return $this->create_chart( $atts, $columns, $rows );
	}

	public function shortcode_chart_fuel_prices( $atts ) {
		$args = array(
			'orderby' => 'id', // Need a better solution than this
		);

		if ( ! empty( $atts['type'] ) )
			$args['slug'] = $atts['type'];

		$fuel_types = get_terms( self::TAX_FUEL_TYPE, $args );

		if ( ! $fuel_types || is_wp_error( $fuel_types ) )
			return $this->return_error( __( 'No fuel price results were found.', 'vehicle-info' ) );

		$query_args = array(
			'post_type' => self::CPT_FILLUP,
			'posts_per_page' => -1,
			'meta_query' => array(
				// Exclude zero price entries
				array(
					'key'     => self::META_FUELUNITPRICE,
					'value'   => 0,
					'type'    => 'numeric',
					'compare' => '>'
				),
			),
			'orderby' => 'post_date',
			'order'   => 'ASC',
		);

		if ( ! empty( $atts['type'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => self::TAX_FUEL_TYPE,
					'field'    => 'slug',
					'terms'    => array( $atts['type'] ),
				),
			);
		}

		$fillups = new WP_Query( $query_args );

		if ( ! $fillups->have_posts() )
			return $this->return_error( __( 'No valid fillups found.', 'vehicle-info' ) );

		$columns = array(
			array(
				'id'    => 'date',
				'label' => 'Date',
				'type'  => 'date',
			),
		);
		foreach ( $fuel_types as $fuel_type ) {
			$columns[] = array(
				'id'    => 'fuelunitprice_' . $fuel_type->slug,
				'label' => $fuel_type->name,
				'type'  => 'number',
			);
		}

		$rows = array();
		foreach ( $fillups->posts as $fillup ) {
			if ( ! $fillup_fuel_type = $this->get_first_assigned_term( $fillup, self::TAX_FUEL_TYPE ) )
				continue;

			$data = array(
				array(
					'v' => (int) mysql2date( 'U', $fillup->post_date ),
					'f' => mysql2date( get_option( 'date_format' ), $fillup->post_date ),
				),
			);

			foreach ( $fuel_types as $fuel_type ) {
				if ( $fuel_type->term_id === $fillup_fuel_type->term_id ) {
					$unit_price = get_post_meta( $fillup->ID, self::META_FUELUNITPRICE, true );
					$data[] = array(
						'v' => (float) $unit_price,
						'f' => sprintf( __( '%1$s per %2$s', 'fuel unit price', 'vehicle-info' ), $this->add_currency_symbol( $unit_price ), $this->labels['volume_singular'] ),
					);
				} else {
					$data[] = array(
						'v' => null,
					);
				}
			}

			$rows[] = $data;
		}

		if ( ! isset( $atts['title'] ) )
			$atts['title'] = 'Fuel Prices';

		if ( ! isset( $atts['vaxisminalue'] ) )
			$atts['vaxisminalue'] = 0;

		return $this->create_chart( $atts, $columns, $rows );
	}

	public function create_chart( $atts, $columns, $rows ) {
		global $content_width;

		// Fixes ampersands in values
		$atts = array_map( 'html_entity_decode', $atts );

		$raw_atts = $atts;

		$atts = shortcode_atts( array(
			'width'            => $content_width,
			'height'           => null,
			'align'            => 'none',
			'title'            => '',
			'colors'           => null,
			'vaxisminalue'     => null, // Meant for internal use but can be used

			// Super advanced users only
			'options'          => null,
			'haxis'            => null,
			'vaxis'            => null,
		), $atts );

		$atts['width']  = absint( $atts['width'] );
		$atts['height'] = absint( $atts['height'] );

		if ( $atts['width'] < 1 )
			$atts['width'] = 400;

		if ( $atts['height'] < 1 )
			$atts['height'] = 300;

		wp_enqueue_script( 'google-jsapi' );
		add_action( 'wp_print_footer_scripts', array( &$this, 'google_jsapi_draw_charts' ) );

		$this->chart_incrementor++;

		$chart = array(
			'id'    => 'vehicleinfo_chart' . $this->chart_incrementor,
			'data'  => array(
				'cols' => $columns,
				'rows' => array(),
			),
			'options' => wp_parse_args( $atts['options'], array(
				'title'            => $atts['title'],
				'interpolateNulls' => 1,
			) ),
		);


		# Set configuration items from the plugin settings

		if ( ! empty( $this->settings['color_background'] ) )
			$chart['options']['backgroundColor']['fill'] = $this->settings['color_background'];

		if ( ! empty( $this->settings['color_border'] ) )
			$chart['options']['backgroundColor']['stroke'] = $this->settings['color_border'];

		if ( ! empty( $this->settings['border_width'] ) )
			$chart['options']['backgroundColor']['strokeWidth'] = $this->settings['border_width'];

		if ( ! empty( $this->settings['axis_color'] ) ) {
			$chart['options']['hAxis']['baselineColor'] = $this->settings['axis_color'];
			$chart['options']['vAxis']['baselineColor'] = $this->settings['axis_color'];
		}

		if ( ! empty( $this->settings['grid_line_color'] ) ) {
			$chart['options']['hAxis']['gridlines']['color'] = $this->settings['grid_line_color'];
			$chart['options']['vAxis']['gridlines']['color'] = $this->settings['grid_line_color'];
		}

		if ( ! empty( $this->settings['line_colors'] ) )
			$chart['options']['colors'] = (array) explode( ',', $this->settings['line_colors'] );

		if ( ! empty( $this->settings['font_size'] ) )
			$chart['options']['fontSize'] = $this->settings['font_size'];

		if ( ! empty( $this->settings['font_name'] ) )
			$chart['options']['fontName'] = $this->settings['font_name'];


		# Set configuration items from the shortcode attributes

		if ( $atts['colors'] )
			$chart['options']['colors'] = (array) explode( ',', $atts['colors'] );

		if ( $atts['vaxisminalue'] )
			$chart['options']['vAxis']['minValue'] = $atts['vaxisminalue'];

		if ( $atts['haxis'] ) {
			if ( ! isset( $chart['options']['hAxis'] ) )
				$chart['options']['hAxis'] = array();

			$chart['options']['hAxis'] = wp_parse_args( $atts['haxis'], $chart['options']['hAxis'] );
		}

		if ( $atts['vaxis'] ) {
			if ( ! isset( $chart['options']['vAxis'] ) )
				$chart['options']['vAxis'] = array();

			$chart['options']['vAxis'] = wp_parse_args( $atts['vaxis'], $chart['options']['vAxis'] );
		}


		// Fill rows
		foreach ( $rows as $row )
			$chart['data']['rows'][] = array( 'c' => $row );

		// One last chance to change any chart options via other plugins
		$chart['options'] = apply_filters( 'vehicle_info_chart_options', $chart['options'], $chart, $atts, $raw_atts );

		$this->charts[] = $chart;

		$style = 'width:' . intval( $atts['width'] ) . 'px;height:' . intval( $atts['height'] ) . 'px;';

		switch ( $atts['align'] ) {
			case 'left':
				$style .= 'float:left;';
				break;
			case 'right':
				$style .= 'float:right;';
				break;
			case 'center':
				$style .= 'margin:0 auto;';
				break;
		}

		return '<div id="' . esc_attr( $chart['id'] ) . '" style="' . esc_attr( $style ) . '"></div>';
	}

	public function google_jsapi_draw_charts() {

		// This function is dynamically queued, but still let's be safe
		if ( empty( $this->charts ) )
			return false;

	?>

<script type="text/javascript">
	google.load('visualization', '1.0', {'packages':['corechart']});
	google.setOnLoadCallback(VehicleInfo_DrawCharts);

	function VehicleInfo_DrawCharts() {

		// All chart data
		var charts = <?php echo json_encode( $this->charts ); ?>;

		// Draw each chart
		for ( var inc1 = 0; inc1 < charts.length; inc1++ ) {

			// Convert Unix timestamps to Date() objects
			for ( var inc2 = 0; inc2 < charts[inc1].data.rows.length; inc2++ ) {
				charts[inc1].data.rows[inc2].c[0].v = new Date( charts[inc1].data.rows[inc2].c[0].v * 1000 );
			}

			var data = new google.visualization.DataTable( charts[inc1].data );

			var chart = new google.visualization.LineChart( document.getElementById( charts[inc1].id ) );
			chart.draw( data, charts[inc1].options );
		}
	}
</script>

<?php
	}
}


/**
 * The main function responsible for returning the one true Vehicle_Info instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $Vehicle_Info = Vehicle_Info(); ?>
 *
 * @return The one true Vehicle_Info Instance
 */
function Vehicle_Info() {
	return Vehicle_Info::instance();
}

add_action( 'plugins_loaded', 'Vehicle_Info' );

?>
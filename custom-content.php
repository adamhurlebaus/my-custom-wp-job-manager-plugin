<?php
/**
 * Plugin Name: WP Job Manager - My Custom Content
 * Description: A modification of Astoundify's first Pre-Defined Regions Plugin.  Loads my custom content to WP Job Manager
 * Author:      Adam Hurlebaus
 * Version:     1.0.2
 * Text Domain: mcjm
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class My_Custom_Job_Manager_Content {

	/**
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Make sure only one instance is only running.
	 */
	public static function instance() {
		if ( ! isset ( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Start things up.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private function setup_globals() {
		$this->file         = __FILE__;

		$this->basename     = apply_filters( 'mcjm_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_dir   = apply_filters( 'mcjm_plugin_dir_path',  plugin_dir_path( $this->file ) );
		$this->plugin_url   = apply_filters( 'mcjm_plugin_dir_url',   plugin_dir_url ( $this->file ) );

		$this->lang_dir     = apply_filters( 'mcjm_lang_dir',     trailingslashit( $this->plugin_dir . 'languages' ) );

		$this->domain       = 'mcjm';
	}

	/**
	 * Setup the default hooks and actions for editing cutsom taxonomies and custom fields
	 *
	 * @since 1.0
	 *
	 * @return void
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'register_post_taxonomy' ) );
		add_filter( 'submit_job_form_fields', array( $this, 'form_fields' ) );
		add_action( 'job_manager_update_job_data', array( $this, 'update_job_data' ), 10, 2 );
		add_filter( 'submit_job_form_fields_get_job_data', array( $this, 'form_fields_get_job_data' ), 10, 2 );
		add_filter( 'job_manager_job_listing_data_fields', array( $this, 'job_listing_data_fields' ) );
		add_action( 'single_job_listing_meta_end', array( $this, 'display_custom_property_type' ) );
		add_action( 'submit_job_form_job_fields_end', array( $this, 'add_property_fileds_to_submit' ) );

		$this->load_textdomain();
	}

	/**
	 * Create the `job_listing_property_type` taxonomy.
	 *
	 * @since 1.0
	 */
	public function register_post_taxonomy() {
		$admin_capability = 'manage_job_listings';

		$singular  = __( 'Property Type', 'mcjm' );
		$plural    = __( 'Property Types', 'mcjm' );

		if ( current_theme_supports( 'job-manager-templates' ) ) {
			$rewrite     = array(
				'slug'         => _x( 'property-type', 'Property type slug - resave permalinks after changing this', 'mcjm' ),
				'with_front'   => false,
				'hierarchical' => false
			);
		} else {
			$rewrite = false;
		}

		register_taxonomy( 'job_listing_property_type',
	        array( 'job_listing' ),
	        array(
	            'hierarchical' 			=> true,
	            'update_count_callback' => '_update_post_term_count',
	            'label' 				=> $plural,
	            'labels' => array(
                    'name' 				=> $plural,
                    'singular_name' 	=> $singular,
                    'search_items' 		=> sprintf( __( 'Search %s', 'mcjm' ), $plural ),
                    'all_items' 		=> sprintf( __( 'All %s', 'mcjm' ), $plural ),
                    'parent_item' 		=> sprintf( __( 'Parent %s', 'mcjm' ), $singular ),
                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'mcjm' ), $singular ),
                    'edit_item' 		=> sprintf( __( 'Edit %s', 'mcjm' ), $singular ),
                    'update_item' 		=> sprintf( __( 'Update %s', 'mcjm' ), $singular ),
                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'mcjm' ), $singular ),
                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'mcjm' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'query_var' 			=> true,
	            'has_archive'           => true,
	            'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),
	            'rewrite' 				=> $rewrite,
	        )
	    );
	}

	/**
	 * Add custom fields to the submission form.
	 *
	 * @since 1.0
	 */
	function form_fields( $fields ) {
		$fields[ 'property' ][ 'property_type' ] = array(
			'label'       => __( 'Property Type', 'job_manager' ),
			'type'        => 'select',
			'options'     => mcjm_get_properties_simple(),
			'required'    => true,
			'priority'    => '1'
		);
		$fields[ 'property' ][ 'property_name' ] = array(
			'label'       => __( 'Name of Property', 'wp-job-manager' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'Enter the name of the building or complex', 'wp-job-manager' ),
			'priority'    => 2
		);
		$fields[ 'property' ][ 'property_size' ] = array(
			'label'       => __( 'Size of Property', 'wp-job-manager' ),
			'type'        => 'text',
			'required'    => false,
			'placeholder' => __( 'Enter number of units on property', 'wp-job-manager' ),
			'priority'    => 3
		);

		return $fields;
	}

	/**
	 * Get the current value for the property type. We can't rely
	 * on basic meta value getting, instead we need to find the term.
	 *
	 * @since 1.0
	 */
	function form_fields_get_job_data( $fields, $job ) {
		$fields[ 'property' ][ 'property_type' ][ 'value' ] = current( wp_get_object_terms( $job->ID, 'job_listing_property_type', array( 'fields' => 'slugs' ) ) );

		return $fields;
	}

	/**
	 * When the form is submitted, update the data.
	 *
	 * @since 1.0
	 */
	function update_job_data( $job_id, $values ) {
		update_post_meta( $job_id, '_property_name', $values['property']['property_name'] );
		update_post_meta( $job_id, '_property_size', $values['property']['property_size'] );
		$property = isset ( $values[ 'property' ][ 'property_type' ] ) ? $values[ 'property' ][ 'property_type' ] : null;

		if ( ! $property )
			return;

		$term   = get_term_by( 'slug', $property, 'job_listing_property_type' );

		wp_set_post_terms( $job_id, array( $term->term_id ), 'job_listing_property_type', false );
		
	}


	/**
	 * On a singular job page, display the property type.
	 *
	 * @since 1.0
	 */
	function display_custom_property_type() {

		global $post;

		if ( ! is_singular( 'job_listing' ) )
			exit;
		
		$terms = wp_get_post_terms( $post->ID, 'job_listing_property_type' );
		
		if ( is_wp_error( $terms ) || empty( $terms ) )
			exit;

		$property = $terms[0];
		$propertyname  = $property->name;

		if ( $property )
			echo '<li>' . __( 'Property Type:' ) . ' ' . $propertyname . '</li>';
	}

	function add_property_fileds_to_submit( $fields ) {

		$property_fields = WP_Job_Manager_Form_Submit_Job::$fields['property'];

		foreach ( $property_fields as $key => $field ) : ?>
			<fieldset class="fieldset-<?php esc_attr_e( $key ); ?>">
				<label for="<?php esc_attr_e( $key ); ?>"><?php echo $field['label'] . apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ); ?></label>
				<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
					<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', array( 'key' => $key, 'field' => $field ) ); ?>
				</div>
			</fieldset>
		<?php endforeach;
	}

	/**
	 * Loads the plugin language files
	 *
	 * @since 1.0
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/' . $this->domain . '/' . $mofile;

		// Look in global /wp-content/languages/mcjm folder
		if ( file_exists( $mofile_global ) ) {
			return load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/mcjm/languages/ folder
		} elseif ( file_exists( $mofile_local ) ) {
			return load_textdomain( $this->domain, $mofile_local );
		}

		return false;
	}
}

/**
 * Start things up.
 *
 * Use this function instead of a global.
 *
 * $mcjm = mcjm();
 *
 * @since 1.0
 */
function mcjm() {
	return My_Custom_Job_Manager_Content::instance();
}

mcjm();

/**
 * Get properties (terms) helper.
 *
 * @since 1.0
 */
function mcjm_get_properties() {
	$properties = get_terms( 'job_listing_property_type', apply_filters( 'mcjm_get_property_args', array( 'hide_empty' => 0 ) ) );

	return $properties;
}

/**
 * Create a key => value pair of term ID and term name.
 *
 * @since 1.0
 */
function mcjm_get_properties_simple() {
	$properties = mcjm_get_properties();
	$simple    = array();

	foreach ( $properties as $property ) {
		$simple[ $property->slug ] = $property->name;
	}

	return apply_filters( 'mcjm_get_properties_simple', $simple );
}

/**
 * Custom widgets
 *
 * @since 1.1
 */
function mcjm_widgets_init() {
	if ( ! class_exists( 'Custom_Property_Widget' ) )
		return;

	$mcjm = mcjm();

	include_once( $mcjm->plugin_dir . '/widgets.php' );

	register_widget( 'My_Custom_Job_Manager_Content_Widget' );
}
add_action( 'after_setup_theme', 'mcjm_widgets_init', 11 );

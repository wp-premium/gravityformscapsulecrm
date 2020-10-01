<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Contains the primary functionality of the Capsule CRM add-on.
 *
 * @package gravityformscapsulecrm
 */

GFForms::include_feed_addon_framework();

/**
 * The main class for the Capsule CRM add-on.
 *
 * @package GFCapsuleCRM
 *
 * @uses GFFeedAddOn
 */
class GFCapsuleCRM extends GFFeedAddOn {

	/**
	 * Defines the add-on version.
	 *
	 * @var string
	 */
	protected $_version = GF_CAPSULECRM_VERSION;

	/**
	 * Defines minimum gravity forms version required.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '1.9.14.26';

	/**
	 * The add-on slug.
	 *
	 * @var string
	 */
	protected $_slug = 'gravityformscapsulecrm';

	/**
	 * The path to the main plugin file.
	 *
	 * Relative to the WordPress plugins folder.
	 *
	 * @var string
	 */
	protected $_path = 'gravityformscapsulecrm/capsulecrm.php';

	/**
	 * The full path to this file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * The add-on URL.
	 *
	 * @var string
	 */
	protected $_url = 'http://www.gravityforms.com';

	/**
	 * The title of the add-on.
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms Capsule CRM Add-On';

	/**
	 * The add-on short title.
	 *
	 * @var string
	 */
	protected $_short_title = 'Capsule CRM';

	/**
	 * If this add-on should allow auto-updates.
	 *
	 * @var string
	 */
	protected $_enable_rg_autoupgrade = true;

	/**
	 * Stores the API class.
	 *
	 * @var GF_CapsuleCRM_API
	 */
	protected $api = null;

	/**
	 * Stores the instance of this class.
	 *
	 * @var GFCapsuleCRM|null
	 */
	private static $_instance = null;

	/* Members plugin integration */

	/**
	 * Capabilites required for this add-on.
	 *
	 * @var array
	 */
	protected $_capabilities = array( 'gravityforms_capsulecrm', 'gravityforms_capsulecrm_uninstall' );

	/**
	 * Permissions required to access theadd-on on the Gravity Forms settings page.
	 *
	 * @var string
	 */
	protected $_capabilities_settings_page = 'gravityforms_capsulecrm';

	/**
	 * Permissions required to access the add-on on the form settings page.
	 *
	 * @var string
	 */
	protected $_capabilities_form_settings = 'gravityforms_capsulecrm';

	/**
	 * Permissions required to uninstall this add-on.
	 *
	 * @var string
	 */
	protected $_capabilities_uninstall = 'gravityforms_capsulecrm_uninstall';

	/**
	 * Get instance of this class.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return GFCapsuleCRM
	 */
	public static function get_instance() {

		if ( self::$_instance == null ) {
			self::$_instance = new self;
		}

		return self::$_instance;

	}

	/**
	 * Register needed plugin hooks and PayPal delayed payment support.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFCommon::add_dismissible_message()
	 * @uses GFFeedAddOn::add_delayed_payment_support()
	 */
	public function init() {

		parent::init();

		if ( get_option( 'gform_capsulecrm_oauth_upgrade_needed' ) ) {

			// Prepare message.
			$message = sprintf(
				esc_html__( "Recent upgrades to Capsule CRM's API require re-authenticating your account. %sClick here to update your authentication settings.%s %sNo Capsule CRM feeds will be processed until reauthenticated.%s", 'gravityformscapsulecrm' ),
				'<a href="' . admin_url( 'admin.php?page=gf_settings&subview=gravityformscapsulecrm' ) . '">',
				'</a>',
				'<strong>',
				'</strong>'
			);

			GFCommon::add_dismissible_message( $message, 'capsulecrm_oauth_upgrade', 'error', false, true );

		}

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Create Capsule CRM object only when payment is received.', 'gravityformscapsulecrm' ),
			)
		);

	}

	/**
	 * Register needed styles.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_base_url()
	 *
	 * @return array $styles
	 */
	public function styles() {
		$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

		$styles = array(
			array(
				'handle'  => 'gform_capsulecrm_form_settings_css',
				'src'     => $this->get_base_url() . "/css/form_settings{$min}.css",
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );

	}





	// # PLUGIN SETTINGS -----------------------------------------------------------------------------------------------

	/**
	 * Setup plugin settings fields.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses GFCapsuleCRM::initialize_api()
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {

		// Prepare description.
		$description = sprintf(
			'<p>%s</p>',
			sprintf(
				esc_html__( 'Capsule CRM is a contact management tool that makes it easy to track cases, opportunities, people and tasks. Use Gravity Forms to collect customer information and automatically add it to your Capsule CRM account. If you don\'t have a Capsule CRM account, you can %1$s sign up for one here.%2$s', 'gravityformscapsulecrm' ),
				'<a href="http://www.capsulecrm.com/" target="_blank">',
				'</a>'
			)
		);

		// Add authentication instructions to description.
		if ( ! $this->initialize_api() ) {

			$description .= sprintf(
				'<p>%s</p>',
				esc_html__( 'Gravity Forms Capsule CRM Add-On requires personal access token, which can be found on the API Authentication Token page in the My Preferences page.', 'gravityformscapsulecrm' )

			);

		}

		return array(
			array(
				'title'       => '',
				'description' => $description,
				'fields'      => array(
					array(
						'name'              => 'authToken',
						'label'             => esc_html__( 'Personal Access Token', 'gravityformscapsulecrm' ),
						'type'              => 'text',
						'class'             => 'large',
						'feedback_callback' => array( $this, 'initialize_api' ),
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => esc_html__( 'Capsule CRM settings have been updated.', 'gravityformscapsulecrm' ),
						),
					),
				),
			),
		);

	}

	/**
	 * Updates plugin settings with the provided settings.
	 * (Forked to trigger milestone migration.)
	 *
	 * @since  1.2
	 * @access public
	 *
	 * @param array $settings Plugin settings to be saved.
	 *
	 * @uses GFCapsuleCRM::upgrade_milestones()
	 */
	public function update_plugin_settings( $settings ) {

		// Update plugin settings.
		parent::update_plugin_settings( $settings );

		// If upgrade flag is not set, exit.
		if ( ! get_option( 'gform_capsulecrm_oauth_upgrade_needed' ) ) {
			return;
		}

		// If API is initialized, upgrade.
		if ( $this->initialize_api() ) {
			$this->upgrade_milestones();
		}

	}





	// # FEED SETTINGS -------------------------------------------------------------------------------------------------

	/**
	 * Setup fields for feed settings.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @uses GFCapsuleCRM::custom_fields_for_feed_mapping()
	 * @uses GFCapsuleCRM::get_categories_as_choices()
	 * @uses GFCapsuleCRM::get_milestones_as_choices()
	 * @uses GFCapsuleCRM::get_task_assignments_as_choices()
	 * @uses GFCapsuleCRM::get_users_as_choices()
	 * @uses GFCapsuleCRM::standard_fields_for_feed_mapping()
	 * @uses GFFeedAddOn::get_default_feed_name()
	 *
	 * @return array
	 */
	public function feed_settings_fields() {

		return array(
			array(
				'title'  => '',
				'fields' => array(
					array(
						'name'          => 'feed_name',
						'label'         => esc_html__( 'Feed Name', 'gravityformscapsulecrm' ),
						'type'          => 'text',
						'required'      => true,
						'class'         => 'medium',
						'default_value' => $this->get_default_feed_name(),
						'tooltip'       => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Name', 'gravityformscapsulecrm' ),
							esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformscapsulecrm' )
						),
					),
					array(
						'name'     => 'action',
						'label'    => esc_html__( 'Action', 'gravityformscapsulecrm' ),
						'type'     => 'checkbox',
						'required' => true,
						'onclick'  => "jQuery(this).parents('form').submit();",
						'choices'  => array(
							array(
								'name'  => 'create_person',
								'label' => esc_html__( 'Create New Person', 'gravityformscapsulecrm' ),
							),
							array(
								'name'  => 'create_task',
								'label' => esc_html__( 'Create New Task', 'gravityformscapsulecrm' ),
							),
						),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Person Details', 'gravityformscapsulecrm' ),
				'dependency' => array( 'field' => 'create_person', 'values' => array( '1' ) ),
				'fields'     => array(
					array(
						'name'      => 'person_standard_fields',
						'label'     => esc_html__( 'Map Fields', 'gravityformscapsulecrm' ),
						'type'      => 'field_map',
						'field_map' => $this->standard_fields_for_feed_mapping(),
						'tooltip'   => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Map Fields', 'gravityformscapsulecrm' ),
							esc_html__( 'Select which Gravity Form fields pair with their respective Capsule CRM fields.', 'gravityformscapsulecrm' )
						),
					),
					array(
						'name'           => 'person_custom_fields',
						'label'          => '',
						'type'           => 'dynamic_field_map',
						'field_map'      => $this->custom_fields_for_feed_mapping(),
						'disable_custom' => true,
					),
					array(
						'name'  => 'person_about',
						'label' => esc_html__( 'About', 'gravityformscapsulecrm' ),
						'type'  => 'textarea',
						'class' => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					),
					array(
						'name'     => 'update_person',
						'label'    => esc_html__( 'Update Person', 'gravityformscapsulecrm' ),
						'type'     => 'checkbox_and_select',
						'tooltip'  => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Update Person', 'gravityformscapsulecrm' ),
							esc_html__( 'If enabled and an existing person is found, their contact details will either be replaced or appended. Job title and organization will be replaced whether replace or append is chosen.', 'gravityformscapsulecrm' )
						),
						'checkbox' => array(
							'name'  => 'update_person_enable',
							'label' => esc_html__( 'Update Person if already exists', 'gravityformscapsulecrm' ),
						),
						'select'   => array(
							'name'    => 'update_person_action',
							'choices' => array(
								array(
									'label' => esc_html__( 'and replace existing data', 'gravityformscapsulecrm' ),
									'value' => 'replace',
								),
								array(
									'label' => esc_html__( 'and append new data', 'gravityformscapsulecrm' ),
									'value' => 'append',
								),
							),
						),
					),
					array(
						'name'    => 'assign_to',
						'label'   => esc_html__( 'Assign To', 'gravityformscapsulecrm' ),
						'type'    => 'checkbox',
						'onclick' => "jQuery(this).parents('form').submit();",
						'choices' => array(
							array(
								'name'  => 'create_case',
								'label' => esc_html__( 'Assign Person to a New Case', 'gravityformscapsulecrm' ),
							),
							array(
								'name'  => 'create_opportunity',
								'label' => esc_html__( 'Assign Person to a New Opportunity', 'gravityformscapsulecrm' ),
							),
						),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Case Details', 'gravityformscapsulecrm' ),
				'dependency' => array( 'field' => 'create_case', 'values' => array( '1' ) ),
				'fields'     => array(
					array(
						'name'     => 'case_name',
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label'    => esc_html__( 'Name', 'gravityformscapsulecrm' ),
					),
					array(
						'name'  => 'case_description',
						'type'  => 'text',
						'class' => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label' => esc_html__( 'Description', 'gravityformscapsulecrm' ),
					),
					array(
						'name'    => 'case_status',
						'type'    => 'select',
						'label'   => esc_html__( 'Status', 'gravityformscapsulecrm' ),
						'choices' => array(
							array(
								'label' => esc_html__( 'Open', 'gravityformscapsulecrm' ),
								'value' => 'OPEN',
							),
							array(
								'label' => esc_html__( 'Closed', 'gravityformscapsulecrm' ),
								'value' => 'CLOSED',
							),
						),
					),
					array(
						'name'    => 'case_owner',
						'type'    => 'select',
						'label'   => esc_html__( 'Owner', 'gravityformscapsulecrm' ),
						'choices' => $this->get_users_as_choices(),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Opportunity Details', 'gravityformscapsulecrm' ),
				'dependency' => array( 'field' => 'create_opportunity', 'values' => array( '1' ) ),
				'fields'     => array(
					array(
						'name'     => 'opportunity_name',
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label'    => esc_html__( 'Name', 'gravityformscapsulecrm' ),
					),
					array(
						'name'  => 'opportunity_description',
						'type'  => 'text',
						'class' => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label' => esc_html__( 'Description', 'gravityformscapsulecrm' ),
					),
					array(
						'name'     => 'opportunity_milestone',
						'type'     => 'select',
						'required' => true,
						'label'    => esc_html__( 'Milestone', 'gravityformscapsulecrm' ),
						'choices'  => $this->get_milestones_as_choices(),
					),
					array(
						'name'    => 'opportunity_owner',
						'type'    => 'select',
						'label'   => esc_html__( 'Owner', 'gravityformscapsulecrm' ),
						'choices' => $this->get_users_as_choices(),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Task Details', 'gravityformscapsulecrm' ),
				'dependency' => array( 'field' => 'create_task', 'values' => ( '1' ) ),
				'fields'     => array(
					array(
						'name'     => 'task_description',
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label'    => esc_html__( 'Description', 'gravityformscapsulecrm' ),
					),
					array(
						'name'  => 'task_detail',
						'type'  => 'text',
						'class' => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
						'label' => esc_html__( 'Detail', 'gravityformscapsulecrm' ),
					),
					array(
						'name'                => 'task_days_until_due',
						'type'                => 'text',
						'required'            => true,
						'class'               => 'small',
						'label'               => esc_html__( 'Days Until Due', 'gravityformscapsulecrm' ),
						'validation_callback' => array( $this, 'validate_task_days_until_due' ),
					),
					array(
						'name'    => 'task_status',
						'type'    => 'select',
						'label'   => esc_html__( 'Status', 'gravityformscapsulecrm' ),
						'choices' => array(
							array(
								'label' => esc_html__( 'Open', 'gravityformscapsulecrm' ),
								'value' => 'OPEN',
							),
							array(
								'label' => esc_html__( 'Completed', 'gravityformscapsulecrm' ),
								'value' => 'COMPLETED',
							),
						),
					),
					array(
						'name'    => 'task_category',
						'type'    => 'select',
						'label'   => esc_html__( 'Category', 'gravityformscapsulecrm' ),
						'choices' => $this->get_categories_as_choices(),
					),
					array(
						'name'    => 'task_owner',
						'type'    => 'select',
						'label'   => esc_html__( 'Owner', 'gravityformscapsulecrm' ),
						'choices' => $this->get_users_as_choices(),
					),
					array(
						'name'    => 'assign_task',
						'label'   => esc_html__( 'Assign Task', 'gravityformscapsulecrm' ),
						'type'    => 'select',
						'choices' => $this->get_task_assignments_as_choices(),
					),
				),
			),
			array(
				'title'      => esc_html__( 'Feed Conditional Logic', 'gravityformscapsulecrm' ),
				'dependency' => array( $this, 'show_conditional_logic_field' ),
				'fields'     => array(
					array(
						'name'           => 'feed_condition',
						'type'           => 'feed_condition',
						'label'          => esc_html__( 'Conditional Logic', 'gravityformscapsulecrm' ),
						'checkbox_label' => esc_html__( 'Enable', 'gravityformscapsulecrm' ),
						'instructions'   => esc_html__( 'Export to Capsule CRM if', 'gravityformscapsulecrm' ),
						'tooltip'        => sprintf(
							'<h6>%s</h6>%s',
							esc_html__( 'Conditional Logic', 'gravityformscapsulecrm' ),
							esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Capsule CRM when the condition is met. When disabled, all form submissions will be posted.', 'gravityformscapsulecrm' )
						),
					),
				),
			),
		);

	}

	/**
	 * Set custom dependency for conditional logic.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFAddOn::get_setting()
	 *
	 * @return bool
	 */
	public function show_conditional_logic_field() {

		return $this->get_setting( 'create_person' ) || $this->get_setting( 'create_task' );

	}

	/**
	 * Check if "Task Days Until Due" setting is numeric.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array  $field         The field being validated.
	 * @param string $field_setting The setting to validate.
	 *
	 * @uses   GFAddOn::set_field_error()
	 */
	public function validate_task_days_until_due( $field, $field_setting ) {

		if ( ! is_numeric( $field_setting ) ) {
			$this->set_field_error( $field, esc_html__( 'This field must be numeric.', 'gravityforms' ) );
		}

	}

	/**
	 * Get Capsule CRM users for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFCapsuleCRM::initialize_api()
	 * @uses   GF_CapsuleCRM_API::get_milestones()
	 *
	 * @return array
	 */
	public function get_users_as_choices() {

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$users = $this->api->get_users();

		if ( is_wp_error( $users ) ) {
			$this->log_error( __METHOD__ . '(): Unable to retrieve Capsule CRM users; ' . $users->get_error_message() );

			return array();
		}

		if ( empty( $users ) || ! is_array( $users ) ) {
			return array();
		}

		$choices = array(
			array(
				'label' => esc_html__( 'Choose a Capsule CRM User', 'gravityformscapsulecrm' ),
				'value' => '',
			),
		);

		// Loop through milestones.
		foreach ( $users as $user ) {

			// Add milestone as choice.
			$choices[] = array(
				'label' => esc_html( $user['name'] ),
				'value' => esc_html( $user['username'] ),
			);

		}

		return $choices;

	}

	/**
	 * Get Capsule CRM opportunity milestones for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFCapsuleCRM::initialize_api()
	 * @uses   GF_CapsuleCRM_API::get_milestones()
	 *
	 * @return array
	 */
	public function get_milestones_as_choices() {

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$milestones = $this->api->get_milestones();

		if ( is_wp_error( $milestones ) ) {
			$this->log_error( __METHOD__ . '(): Unable to retrieve Capsule CRM milestones; ' . $milestones->get_error_message() );

			return array();
		}

		if ( empty( $milestones ) || ! is_array( $milestones ) ) {
			return array();
		}

		// Initialize choices array.
		$choices = array(
			array(
				'label' => esc_html__( 'Choose a Milestone', 'gravityformscapsulecrm' ),
				'value' => '',
			),
		);

		// Loop through milestones.
		foreach ( $milestones as $milestone ) {

			// Add milestone as choice.
			$choices[] = array(
				'label' => esc_html( $milestone['name'] ),
				'value' => esc_html( $milestone['name'] ),
			);

		}

		return $choices;

	}

	/**
	 * Get Capsule CRM task categories for feed settings field.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFCapsuleCRM::initialize_api()
	 * @uses   GF_CapsuleCRM_API::get_categories()
	 *
	 * @return array
	 */
	public function get_categories_as_choices() {

		// If API is not initialized, return.
		if ( ! $this->initialize_api() ) {
			return array();
		}

		$categories = $this->api->get_categories();

		if ( is_wp_error( $categories ) ) {
			$this->log_error( __METHOD__ . '(): Unable to retrieve Capsule CRM categories; ' . $categories->get_error_message() );

			return array();
		}

		if ( empty( $categories ) || ! is_array( $categories ) ) {
			return array();
		}

		// Initialize choices array.
		$choices = array(
			array(
				'label' => esc_html__( 'Choose a Category', 'gravityformscapsulecrm' ),
				'value' => '',
			),
		);

		// Loop through categories.
		foreach ( $categories as $category ) {

			// Add category as choice.
			$choices[] = array(
				'label' => esc_html( $category['name'] ),
				'value' => esc_html( $category['name'] ),
			);

		}

		return $choices;

	}

	/**
	 * Get task assignment options for feed settings fields.
	 *
	 * @since 1.0
	 * @access public
	 *
	 * @uses GFAddOn::get_setting()
	 *
	 * @return array
	 */
	public function get_task_assignments_as_choices() {

		// Initialize choices array.
		$choices = array(
			array(
				'value' => 'none',
				'label' => esc_html__( 'Do Not Assign Task', 'gravityformscapsulecrm' ),
			),
		);

		// Add case field as choice.
		if ( $this->get_setting( 'create_case' ) ) {
			$choices[] = array(
				'value' => 'case',
				'label' => esc_html__( 'Assign Task to Created Case', 'gravityformscapsulecrm' ),
			);
		}

		// Add person field as choice.
		if ( $this->get_setting( 'create_person' ) ) {
			$choices[] = array(
				'value' => 'person',
				'label' => esc_html__( 'Assign Task to Created Person', 'gravityformscapsulecrm' ),
			);
		}

		// Add opportunity field as choice.
		if ( $this->get_setting( 'create_opportunity' ) ) {
			$choices[] = array(
				'value' => 'opportunity',
				'label' => esc_html__( 'Assign Task to Created Opportunity', 'gravityformscapsulecrm' ),
			);
		}

		return $choices;

	}

	/**
	 * Prepare standard fields for feed field mapping.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function standard_fields_for_feed_mapping() {

		return array(
			array(
				'name'       => 'first_name',
				'label'      => esc_html__( 'First Name', 'gravityformscapsulecrm' ),
				'required'   => true,
				'field_type' => array( 'name', 'text', 'hidden' ),
			),
			array(
				'name'       => 'last_name',
				'label'      => esc_html__( 'Last Name', 'gravityformscapsulecrm' ),
				'required'   => true,
				'field_type' => array( 'name', 'text', 'hidden' ),
			),
			array(
				'name'       => 'email_address',
				'label'      => esc_html__( 'Email Address', 'gravityformscapsulecrm' ),
				'required'   => true,
				'field_type' => array( 'email', 'hidden' ),
			),
		);

	}

	/**
	 * Prepare contact and custom fields for feed field mapping.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function custom_fields_for_feed_mapping() {

		return array(
			array(
				'label' => esc_html__( 'Choose a Field', 'gravityformscapsulecrm' ),
			),
			array(
				'value' => 'title',
				'label' => esc_html__( 'Job Title', 'gravityformscapsulecrm' ),
			),
			array(
				'value' => 'organization',
				'label' => esc_html__( 'Organization', 'gravityformscapsulecrm' ),
			),
			array(
				'label'   => esc_html__( 'Email Address', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Work', 'gravityformscapsulecrm' ),
						'value' => 'email_work',
					),
					array(
						'label' => esc_html__( 'Home', 'gravityformscapsulecrm' ),
						'value' => 'email_home',
					),
				),
			),
			array(
				'label'   => esc_html__( 'Phone Number', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Work', 'gravityformscapsulecrm' ),
						'value' => 'phone_work',
					),
					array(
						'label' => esc_html__( 'Mobile', 'gravityformscapsulecrm' ),
						'value' => 'phone_mobile',
					),
					array(
						'label' => esc_html__( 'Fax', 'gravityformscapsulecrm' ),
						'value' => 'phone_fax',
					),
					array(
						'label' => esc_html__( 'Home', 'gravityformscapsulecrm' ),
						'value' => 'phone_home',
					),
					array(
						'label' => esc_html__( 'Direct', 'gravityformscapsulecrm' ),
						'value' => 'phone_direct',
					),
				),
			),
			array(
				'label'   => esc_html__( 'Address', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Office', 'gravityformscapsulecrm' ),
						'value' => 'address_office',
					),
					array(
						'label' => esc_html__( 'Home', 'gravityformscapsulecrm' ),
						'value' => 'address_home',
					),
					array(
						'label' => esc_html__( 'Postal', 'gravityformscapsulecrm' ),
						'value' => 'address_postal',
					),
				),
			),
			array(
				'label'   => esc_html__( 'Website', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'URL', 'gravityformscapsulecrm' ),
						'value' => 'website_url',
					),
					array(
						'label' => esc_html__( 'Skype', 'gravityformscapsulecrm' ),
						'value' => 'website_skype',
					),
					array(
						'label' => esc_html__( 'Twitter', 'gravityformscapsulecrm' ),
						'value' => 'website_twitter',
					),
					array(
						'label' => esc_html__( 'Facebook', 'gravityformscapsulecrm' ),
						'value' => 'website_facebook',
					),
					array(
						'label' => esc_html__( 'LinkedIn', 'gravityformscapsulecrm' ),
						'value' => 'website_linked_in',
					),
					array(
						'label' => esc_html__( 'Xing', 'gravityformscapsulecrm' ),
						'value' => 'website_xing',
					),
					array(
						'label' => esc_html__( 'Feed', 'gravityformscapsulecrm' ),
						'value' => 'website_feed',
					),
					array(
						'label' => esc_html__( 'Google Plus', 'gravityformscapsulecrm' ),
						'value' => 'website_google_plus',
					),
					array(
						'label' => esc_html__( 'Flickr', 'gravityformscapsulecrm' ),
						'value' => 'website_flickr',
					),
					array(
						'label' => esc_html__( 'GitHub', 'gravityformscapsulecrm' ),
						'value' => 'website_github',
					),
					array(
						'label' => esc_html__( 'YouTube', 'gravityformscapsulecrm' ),
						'value' => 'website_youtube',
					),
				),
			),
		);

	}





	// # FEED LIST -----------------------------------------------------------------------------------------------------

	/**
	 * Setup columns for feed list table.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @return array
	 */
	public function feed_list_columns() {

		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformscapsulecrm' ),
			'action'    => esc_html__( 'Action', 'gravityformscapsulecrm' ),
		);

	}

	/**
	 * Get action for feed list column.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed The feed object.
	 *
	 * @return string
	 */
	public function get_column_value_action( $feed ) {

		$create_person = rgars( $feed, 'meta/create_person' );
		$create_task   = rgars( $feed, 'meta/create_task' );

		if ( $create_person && $create_task ) {

			return esc_html__( 'Create Person & Task', 'gravityformscapsulecrm' );

		} else if ( $create_person ) {

			return esc_html__( 'Create Person', 'gravityformscapsulecrm' );

		} else if ( $create_task ) {

			return esc_html__( 'Create Task', 'gravityformscapsulecrm' );

		}

		return esc_html__( 'No Action', 'gravityformscapsulecrm' );

	}

	/**
	 * Set feed creation control.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFCapsuleCRM::initialize_api()
	 *
	 * @return bool
	 */
	public function can_create_feed() {

		return $this->initialize_api();

	}

	/**
	 * Enable feed duplication.
	 *
	 * @since  1.1
	 * @access public
	 *
	 * @param int|array $id The ID of the feed to be duplicated or the feed object when duplicating a form.
	 *
	 * @return bool
	 */
	public function can_duplicate_feed( $id ) {

		return true;

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Process feed.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The Feed object.
	 * @param array $entry The Entry object.
	 * @param array $form  The Form object.
	 *
	 * @uses   GFAddOn::get_field_value()
	 * @uses   GFCapsuleCRM::create_case()
	 * @uses   GFCapsuleCRM::create_opportunity()
	 * @uses   GFCapsuleCRM::create_person()
	 * @uses   GFCapsuleCRM::create_task()
	 * @uses   GFCapsuleCRM::init()
	 * @uses   GFCapsuleCRM::update_person()
	 * @uses   GF_CapsuleCRM_API::search_parties()
	 * @uses   GFFeedAddOn::add_feed_error()
	 */
	public function process_feed( $feed, $entry, $form ) {

		// If API is not initialized, exit.
		if ( ! $this->initialize_api() ) {
			$this->add_feed_error( esc_html__( 'Feed was not processed because API was not initialized.', 'gravityformscapsulecrm' ), $feed, $entry, $form );

			return;
		}

		// Create party.
		if ( rgars( $feed, 'meta/create_person' ) ) {

			$parties = null;

			// Update existing party.
			if ( rgars( $feed, 'meta/update_person_enable' ) ) {

				// Get email address.
				$email_address = rgars( $feed, 'meta/person_standard_fields_email_address' );
				$email_address = $this->get_field_value( $form, $entry, $email_address );

				// Search for existing party.
				$parties = $this->api->search_parties( urlencode( $email_address ) );

				if ( is_wp_error( $parties ) ) {
					$this->add_feed_error( 'Unable to search for existing party, creating new party; ' . $parties->get_error_message(), $feed, $entry, $form );
					$parties = null;
				}

			}

			if ( ! empty( $parties ) ) {
				$this->log_debug( __METHOD__ . '(): Found parties: ' . print_r( $parties, true ) );
				$party = $this->update_person( $parties[0], $feed, $entry, $form );
			} else {
				$party = $this->create_person( $feed, $entry, $form );
			}

			// Create case.
			if ( rgars( $feed, 'meta/create_case' ) == '1' && $party ) {
				$this->create_case( $party, $feed, $entry, $form );
			}

			// Create opportunity.
			if ( rgars( $feed, 'meta/create_opportunity' ) == '1' && $party ) {
				$this->create_opportunity( $party, $feed, $entry, $form );
			}

		}

		// Create task.
		if ( rgars( $feed, 'meta/create_task' ) ) {
			$this->create_task( $feed, $entry, $form );
		}

	}

	/**
	 * Create a new case.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $party Capsule CRM party object.
	 * @param array $feed  The feed object.
	 * @param array $entry The entry object.
	 * @param array $form  The form object.
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GF_CapsuleCRM_API::create_case()
	 * @uses   GFCommon::replace_variables()
	 * @uses   GFFeedAddOn::add_feed_error()
	 *
	 * @return null|array
	 */
	public function create_case( $party, $feed, $entry, $form ) {

		// Log that we are creating a case.
		$this->log_debug( __METHOD__ . '(): Creating case.' );

		// Initialize case object.
		$case = array(
			'name'        => GFCommon::replace_variables( $feed['meta']['case_name'], $form, $entry, false, false, false, 'text' ),
			'description' => GFCommon::replace_variables( $feed['meta']['case_description'], $form, $entry, false, false, false, 'text' ),
			'status'      => $feed['meta']['case_status'],
			'party'       => array( 'id' => $party['id'] ),
		);

		// Add case owner.
		if ( rgars( $feed, 'meta/case_owner' ) ) {
			$case['owner'] = array( 'username' => $feed['meta']['case_owner'] );
		}

		/**
		 * Modify the Capsule CRM case.
		 *
		 * @since 1.1.4
		 *
		 * @param array $case  Capsule CRM case.
		 * @param array $form  The form object.
		 * @param array $entry The entry object.
		 * @param array $feed  The feed object.
		 */
		$case = gf_apply_filters( array(
			'gform_capsulecrm_case',
			$form['id'],
			$feed['id'],
		), $case, $form, $entry, $feed );

		// If the name is empty, exit.
		if ( rgblank( $case['name'] ) ) {
			$this->add_feed_error( esc_html__( 'Case could not be created because case name was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
		}

		// Log case being created.
		$this->log_debug( __METHOD__ . '(): Creating case: ' . print_r( $case, true ) );

		// Create case.
		$case = $this->api->create_case( $case );

		if ( is_wp_error( $case ) ) {
			$this->add_feed_error( sprintf(
				esc_html__( 'Case could not be created. %s', 'gravityformscapsulecrm' ),
				$case->get_error_message()
			), $feed, $entry, $form );

			// Log additional errors.
			if ( $error_data = $case->get_error_data() ) {
				$this->log_error( __METHOD__ . '(): Additional error messages: ' . print_r( $error_data, true ) );
			}

			return null;
		}

		$case = $case['kase'];

		// Log that case was created.
		$this->log_debug( __METHOD__ . '(): Case #' . $case['id'] . ' created.' );

		// Assign case ID to entry object.
		gform_update_meta( $entry['id'], 'capsulecrm_case_id', $case['id'] );

		return $case;

	}

	/**
	 * Create a new opportunity.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $party Capsule CRM party object.
	 * @param array $feed  The Feed object.
	 * @param array $entry The Entry object.
	 * @param array $form  The Form object.
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GF_CapsuleCRM_API::create_opportunity()
	 * @uses   GFCommon::replace_variables()
	 * @uses   GFFeedAddOn::add_feed_error()
	 *
	 * @return null|array
	 */
	public function create_opportunity( $party, $feed, $entry, $form ) {

		// Log that we are creating an opportunity.
		$this->log_debug( __METHOD__ . '(): Creating opportunity.' );

		// Initialize opportunity object.
		$opportunity = array(
			'name'        => GFCommon::replace_variables( $feed['meta']['opportunity_name'], $form, $entry, false, false, false, 'text' ),
			'description' => GFCommon::replace_variables( $feed['meta']['opportunity_description'], $form, $entry, false, false, false, 'text' ),
			'party'       => array( 'id' => $party['id'] ),
		);

		// Add opportunity milestone.
		if ( rgars( $feed, 'meta/opportunity_milestone' ) ) {
			$opportunity['milestone'] = array( 'name' => $feed['meta']['opportunity_milestone'] );
		}

		// Add opportunity owner.
		if ( rgars( $feed, 'meta/opportunity_owner' ) ) {
			$opportunity['owner'] = array( 'username' => $feed['meta']['opportunity_owner'] );
		}

		/**
		 * Modify the Capsule CRM opportunity.
		 *
		 * @since 1.1.4
		 *
		 * @param array $opportunity Capsule CRM opportunity.
		 * @param array $form        The form object.
		 * @param array $entry       The entry object.
		 * @param array $feed        The feed object.
		 */
		$opportunity = gf_apply_filters( array( 'gform_capsulecrm_opportunity', $form['id'], $feed['id'] ), $opportunity, $form, $entry, $feed );

		// If the name is empty, exit.
		if ( rgblank( $opportunity['name'] ) ) {
			$this->add_feed_error( esc_html__( 'Opportunity could not be created because opportunity name was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
		}

		// Log opportunity being created.
		$this->log_debug( __METHOD__ . '(): Creating opportunity: ' . print_r( $opportunity, true ) );

		// Create opportunity.
		$opportunity = $this->api->create_opportunity( $opportunity );

		if ( is_wp_error( $opportunity ) ) {
			$this->add_feed_error( sprintf(
				esc_html__( 'Opportunity could not be created. %s', 'gravityformscapsulecrm' ),
				$opportunity->get_error_message()
			), $feed, $entry, $form );

			return null;
		}

		$opportunity = $opportunity['opportunity'];

		// Log that opportunity was created.
		$this->log_debug( __METHOD__ . '(): Opportunity #' . $opportunity['id'] . ' created.' );

		// Assign opportunity ID to entry object.
		gform_update_meta( $entry['id'], 'capsulecrm_opportunity_id', $opportunity['id'] );

		return $opportunity;

	}

	/**
	 * Create a new person.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The Feed object.
	 * @param array $entry The Entry object.
	 * @param array $form  The Form object.
	 *
	 * @return null|array
	 */
	public function create_person( $feed, $entry, $form ) {

		// Log that we are creating a person.
		$this->log_debug( __METHOD__ . '(): Creating person.' );

		// Get field mappings.
		$standard_fields = $this->get_field_map_fields( $feed, 'person_standard_fields' );
		$custom_fields   = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		// Initialize person object.
		$person = array(
			'type'           => 'person',
			'firstName'      => $this->get_field_value( $form, $entry, $standard_fields['first_name'] ),
			'lastName'       => $this->get_field_value( $form, $entry, $standard_fields['last_name'] ),
			'about'          => GFCommon::replace_variables( $feed['meta']['person_about'], $form, $entry, false, false, false, 'text' ),
			'jobTitle'       => rgar( $custom_fields, 'title' ) ? $this->get_field_value( $form, $entry, $custom_fields['title'] ) : '',
			'emailAddresses' => array(
				array(
					'address' => $this->get_field_value( $form, $entry, $standard_fields['email_address'] ),
				),
			),
		);

		// Add organization.
		if ( rgar( $custom_fields, 'organization' ) ) {

			// Get organization name.
			$organization_name = $this->get_field_value( $form, $entry, $custom_fields['organization'] );

			if ( ! rgblank( $organization_name ) ) {
				$person['organisation']['name'] = $organization_name;
			}

		}

		// Add contact data.
		$person = $this->add_person_address_data( $person, $feed, $entry, $form );
		$person = $this->add_person_email_data( $person, $feed, $entry, $form );
		$person = $this->add_person_phone_data( $person, $feed, $entry, $form );
		$person = $this->add_person_website_data( $person, $feed, $entry, $form );

		/**
		 * Modify the Capsule CRM person.
		 *
		 * @since 1.1.4
		 *
		 * @param array $person Capsule CRM person.
		 * @param array $form   The form object.
		 * @param array $entry  The entry object.
		 * @param array $feed   The feed object.
		 */
		$person = gf_apply_filters( array(
			'gform_capsulecrm_person',
			$form['id'],
			$feed['id'],
		), $person, $form, $entry, $feed );

		// If the name is empty, exit.
		if ( rgblank( $person['firstName'] ) || rgblank( $person['lastName'] ) ) {
			$this->add_feed_error( esc_html__( 'Person could not be created as first and/or last name were not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
		}

		// If the email address is invalid, exit.
		if ( GFCommon::is_invalid_or_empty_email( $person['emailAddresses'][0]['address'] ) ) {
			$this->add_feed_error( esc_html__( 'Person could not be created as email address was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
		}

		// Log person object being created.
		$this->log_debug( __METHOD__ . '(): Creating person: ' . print_r( $person, true ) );

		// Create party.
		$person = $this->api->create_party( $person );

		if ( is_wp_error( $person ) ) {
			$this->add_feed_error( sprintf(
				esc_html__( 'Person could not be created. %s', 'gravityformscapsulecrm' ),
				$person->get_error_message()
			), $feed, $entry, $form );

			// Log additional errors.
			if ( $error_data = $person->get_error_data() ) {
				$this->log_error( __METHOD__ . '(): Additional error messages: ' . print_r( $error_data, true ) );
			}

			return null;
		}

		$person = $person['party'];

		// Log that person was created.
		$this->log_debug( __METHOD__ . '(): Person #' . $person['id'] . ' created.' );

		// Assign person ID to entry object.
		gform_update_meta( $entry['id'], 'capsulecrm_person_id', $person['id'] );

		return $person;

	}

	/**
	 * Create a new task.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $feed  The Feed object.
	 * @param array $entry The Entry object.
	 * @param array $form  The Form object.
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GF_CapsuleCRM_API::create_task()
	 * @uses   GFCommon::replace_variables()
	 * @uses   GFFeedAddOn::add_feed_error()
	 *
	 * @return null|array
	 */
	public function create_task( $feed, $entry, $form ) {

		// Log that we are creating a task.
		$this->log_debug( __METHOD__ . '(): Creating task.' );

		// Initialize task object.
		$task = array(
			'description' => GFCommon::replace_variables( $feed['meta']['task_description'], $form, $entry, false, false, false, 'text' ),
			'detail'      => GFCommon::replace_variables( $feed['meta']['task_detail'], $form, $entry, false, false, false, 'text' ),
			'dueOn'       => date( 'Y-m-d', strtotime( '+' . $feed['meta']['task_days_until_due'] . ' days' ) ),
			'dueTime'     => date( 'H:i:s', strtotime( '+' . $feed['meta']['task_days_until_due'] . ' days' ) ),
			'status'      => $feed['meta']['task_status'],
		);

		// Add task category.
		if ( rgars( $feed, 'meta/task_category' ) ) {
			$task['category'] = array( 'name' => $feed['meta']['task_category'] );
		}

		// Add task owner.
		if ( rgars( $feed, 'meta/task_owner' ) ) {
			$task['owner'] = array( 'username' => $feed['meta']['task_owner'] );
		}

		// Add case.
		if ( 'case' == rgars( $feed, 'meta/assign_task' ) ) {
			$task['kase']['id'] = gform_get_meta( $entry['id'], 'capsulecrm_case_id' );
		}

		// Add opportunity.
		if ( 'opportunity' == rgars( $feed, 'meta/assign_task' ) ) {
			$task['opportunity']['id'] = gform_get_meta( $entry['id'], 'capsulecrm_opportunity_id' );
		}

		// Add party.
		if ( 'person' == rgars( $feed, 'meta/assign_task' ) ) {
			$task['party']['id'] = gform_get_meta( $entry['id'], 'capsulecrm_person_id' );
		}

		/**
		 * Modify the Capsule CRM task.
		 *
		 * @since 1.1.4
		 *
		 * @param array $task  Capsule CRM task.
		 * @param array $form  The form object.
		 * @param array $entry The entry object.
		 * @param array $feed  The feed object.
		 */
		$task = gf_apply_filters( array(
			'gform_capsulecrm_task',
			$form['id'],
			$feed['id'],
		), $task, $form, $entry, $feed );

		// If the description is empty, exit.
		if ( rgblank( $task['description'] ) ) {
			$this->add_feed_error( esc_html__( 'Task could not be created as the task description was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
		}

		// Log task being created.
		$this->log_debug( __METHOD__ . '(): Creating task: ' . print_r( $task, true ) );

		// Create task.
		$task = $this->api->create_task( $task );

		if ( is_wp_error( $task ) ) {
			$this->add_feed_error( sprintf(
				esc_html__( 'Task could not be created. %s', 'gravityformscapsulecrm' ),
				$task->get_error_message()
			), $feed, $entry, $form );

			return null;
		}

		$task = $task['task'];

		// Log that task was created.
		$this->log_debug( __METHOD__ . '(): Task #' . $task['id'] . ' created.' );

		// Assign task ID to entry object.
		gform_update_meta( $entry['id'], 'capsulecrm_task_id', $task['id'] );

		return $task;

	}

	/**
	 * Update existing person.
	 *
	 * @access public
	 *
	 * @param array $person The person to update.
	 * @param array $feed   The feed object.
	 * @param array $entry  The entry object.
	 * @param array $form   Thew form object.
	 *
	 * @return array $person
	 */
	public function update_person( $person, $feed, $entry, $form ) {

		// Log that we are updating a person.
		$this->log_debug( __METHOD__ . '(): Updating person #' . $person['id'] . '.' );

		// Store a reference of the original person.
		$original_person = $person;

		// Get field mappings.
		$standard_fields = $this->get_field_map_fields( $feed, 'person_standard_fields' );
		$custom_fields   = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		// Add standard data.
		$person['firstName'] = $this->get_field_value( $form, $entry, $standard_fields['first_name'] );
		$person['lastName']  = $this->get_field_value( $form, $entry, $standard_fields['last_name'] );
		$person['jobTitle']  = rgar( $custom_fields, 'title' ) ? $this->get_field_value( $form, $entry, $custom_fields['title'] ) : '';

		// Add organization.
		if ( rgar( $custom_fields, 'organization' ) ) {

			// Get organization name.
			$organization_name = $this->get_field_value( $form, $entry, $custom_fields['organization'] );

			if ( ! rgblank( $organization_name ) ) {
				$person['organisation'] = array( 'name' => $organization_name );
			} else {
				$person['organisation'] = null;
			}

		} else {

			$person['organisation'] = null;

		}

		// Replace or append contact data.
		if ( $feed['meta']['update_person_action'] == 'replace' ) {

			// Get data keys.
			$data_keys = array( 'emailAddresses', 'addresses', 'phoneNumbers', 'websites' );

			// Remove current contact information.
			foreach ( $data_keys as $data_key ) {

				// Skip data type if empty.
				if ( empty( $person[ $data_key ] ) ) {
					continue;
				}

				// Loop through items.
				foreach ( $person[ $data_key ] as $i => $data ) {
					$person[ $data_key ][ $i ]['_delete'] = true;
				}

			}

			// Add about and email address.
			$person['about']            = GFCommon::replace_variables( $feed['meta']['person_about'], $form, $entry, false, false, false, 'text' );
			$person['emailAddresses'][] = array( 'address' => $this->get_field_value( $form, $entry, $standard_fields['email_address'] ) );

			// Add contact data.
			$person = $this->add_person_address_data( $person, $feed, $entry, $form );
			$person = $this->add_person_email_data( $person, $feed, $entry, $form );
			$person = $this->add_person_phone_data( $person, $feed, $entry, $form );
			$person = $this->add_person_website_data( $person, $feed, $entry, $form );

		} else if ( $feed['meta']['update_person_action'] == 'append' ) {

			// Add about.
			$person['about'] .= PHP_EOL . PHP_EOL . GFCommon::replace_variables( $feed['meta']['person_about'], $form, $entry, false, false, false, 'text' );

			// Add contact data.
			$person = $this->add_person_address_data( $person, $feed, $entry, $form, true );
			$person = $this->add_person_email_data( $person, $feed, $entry, $form, true );
			$person = $this->add_person_phone_data( $person, $feed, $entry, $form, true );
			$person = $this->add_person_website_data( $person, $feed, $entry, $form, true );

		}

		/**
		 * Modify the Capsule CRM person.
		 *
		 * @since 1.1.4
		 *
		 * @param array $person Capsule CRM person.
		 * @param array $form   The form object.
		 * @param array $entry  The entry object.
		 * @param array $feed   The feed object.
		 */
		$person = gf_apply_filters( array( 'gform_capsulecrm_person', $form['id'], $feed['id'] ), $person, $form, $entry, $feed );

		// If the name is empty, exit.
		if ( rgblank( $person['firstName'] ) || rgblank( $person['lastName'] ) ) {
			$this->add_feed_error( esc_html__( 'Person could not be updated as first and/or last name were not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
		}

		// Log person object being updated.
		$this->log_debug( __METHOD__ . '(): Updating person: ' . print_r( $person, true ) );

		// Update party.
		$person = $this->api->update_party( $person['id'], $person );

		if ( is_wp_error( $person ) ) {
			$this->add_feed_error( sprintf(
				esc_html__( 'Person #%1$s could not be updated. %2$s', 'gravityformscapsulecrm' ),
				$person['id'], $person->get_error_message()
			), $feed, $entry, $form );

			// Log additional errors.
			if ( $error_data = $person->get_error_data() ) {
				$this->log_error( __METHOD__ . '(): Additional error messages: ' . print_r( $error_data, true ) );
			}

			return $original_person;
		}

		$person = $person['party'];

		// Log that person was updated.
		$this->log_debug( __METHOD__ . '(): Person #' . $person['id'] . ' updated.' );

		// Assign person ID to entry object.
		gform_update_meta( $entry['id'], 'capsulecrm_person_id', $person['id'] );

		return $person;

	}





	// # FEED PROCESSING -----------------------------------------------------------------------------------------------

	/**
	 * Upgrade routines.
	 *
	 * @since  1.2
	 * @access public
	 *
	 * @param string $previous_version Previously installed version number.
	 */
	public function upgrade( $previous_version ) {

		// Set OAuth upgrade flag.
		if ( version_compare( $previous_version, '1.2', '<' ) ) {
			update_option( 'gform_capsulecrm_oauth_upgrade_needed', true );
		}

	}

	/**
	 * Upgrade milestones to use name instead of ID in feed setting.
	 *
	 * @since  1.2
	 * @access public
	 *
	 * @uses   GFAddOn::log_debug()
	 * @uses   GFAddOn::log_error()
	 * @uses   GFCapsuleCRM::initialize_api()
	 * @uses   GF_CapsuleCRM_API::get_milestones()
	 * @uses   GFCommon::remove_dismissible_message()
	 * @uses   GFFeedAddOn::get_feeds()
	 * @uses   GFFeedAddOn::update_feed_meta()
	 */
	public function upgrade_milestones() {

		// If API could not be initialized, return.
		if ( ! $this->initialize_api() ) {
			$this->log_error( __METHOD__ . '(): Unable to migrate milestones because API could not be initialized.' );
			return;
		}

		// Get milestones.
		$milestones = $this->api->get_milestones();

		if ( is_wp_error( $milestones ) ) {
			// Log that we could not retrieve milestones.
			$this->log_error( __METHOD__ . '(): Unable to retrieve milestones; ' . $milestones->get_error_message() );

			return;
		}

		// Get feeds.
		$feeds = $this->get_feeds();

		// Loop through feeds and modify milestone value.
		foreach ( $feeds as $feed ) {

			// Get old milestone ID.
			$milestone_id = rgars( $feed, 'meta/opportunity_milestone' );

			// If no milestone ID is set, skip feed.
			if ( ! $milestone_id ) {
				continue;
			}

			// Loop through milestones.
			foreach ( $milestones as $milestone ) {

				// If ID does not match, skip.
				if ( $milestone['id'] != $milestone_id ) {
					continue;
				}

				// Update feed milestone ID.
				$feed['meta']['opportunity_milestone'] = $milestone['name'];

			}

			// Save feed.
			$this->update_feed_meta( $feed['id'], $feed['meta'] );

		}

		// Delete upgrade flag.
		delete_option( 'gform_capsulecrm_oauth_upgrade_needed' );

		// Delete message.
		GFCommon::remove_dismissible_message( 'capsulecrm_oauth_upgrade' );

	}





	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Add address contact data to a Capsule CRM object.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $object             Capsule CRM object.
	 * @param array $feed               The feed object.
	 * @param array $entry              The entry object.
	 * @param array $form               The form object.
	 * @param bool  $check_for_existing If existing data should be checked. Defaults to false.
	 *
	 * @uses   GFAddOn::get_dynamic_field_map_fields()
	 * @uses   GFCapsuleCRM::exists_in_array()
	 * @uses   GFFormsModel::get_field()
	 * @uses   GFFormsModel::get_input_type()
	 *
	 * @return array
	 */
	public function add_person_address_data( $object, $feed, $entry, $form, $check_for_existing = false ) {

		// Get custom fields.
		$custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		// Loop through custom fields.
		foreach ( $custom_fields as $field_key => $field ) {

			// If this is not an address mapped field, skip.
			if ( strpos( $field_key, 'address_' ) !== 0 ) {
				continue;
			}

			// Get address field object.
			$address_field = GFFormsModel::get_field( $form, $field );

			// If selected field is not an Address field, skip.
			if ( 'address' !== GFFormsModel::get_input_type( $address_field ) ) {
				continue;
			}

			// Prepare address type.
			$type = ucfirst( str_replace( 'address_', '', $field_key ) );

			// Get address field ID.
			$field_id = $address_field->id;

			// If any of the required inputs are empty, skip.
			if ( ! rgar( $entry, $field_id . '.1' ) || ! rgar( $entry, $field_id . '.3' ) || ! rgar( $entry, $field_id . '.4' ) || ! rgar( $entry, $field_id . '.5' ) ) {
				$this->log_error( __METHOD__ . '(): Not adding address to person because an address input was left empty.' );
				continue;
			}

			// Prepare address.
			$address = array(
				'type'    => $type,
				'street'  => trim( $entry[ $field_id . '.1' ] . ' ' . $entry[ $field_id . '.2' ] ),
				'city'    => $entry[ $field_id . '.3' ],
				'state'   => $entry[ $field_id . '.4' ],
				'zip'     => $entry[ $field_id . '.5' ],
				'country' => $entry[ $field_id . '.6' ],
			);

			// If address is already assigned to object, skip.
			if ( $check_for_existing && rgar( $object, 'addresses' ) && $this->exists_in_array( $object['addresses'], 'street', $address['street'] ) ) {
				continue;
			}

			// Add address to object.
			$object['addresses'][] = $address;

		}

		return $object;

	}

	/**
	 * Add email address contact data to a Capsule CRM object.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $object             Capsule CRM object.
	 * @param array $feed               The feed object.
	 * @param array $entry              The entry object.
	 * @param array $form               The form object.
	 * @param bool  $check_for_existing If existing data should be checked. Defaults to false.
	 *
	 * @uses   GFAddOn::get_dynamic_field_map_fields()
	 * @uses   GFAddOn::get_field_value()
	 * @uses   GFCapsuleCRM::exists_in_array()
	 * @uses   GFCommon::is_valid_email()
	 *
	 * @return array
	 */
	public function add_person_email_data( $object, $feed, $entry, $form, $check_for_existing = false ) {

		// Get custom fields.
		$custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		// Loop through custom fields.
		foreach ( $custom_fields as $field_key => $field ) {

			// If this is not an email address field, skip.
			if ( strpos( $field_key, 'email_' ) !== 0 ) {
				continue;
			}

			// Get the email address.
			$email_address = $this->get_field_value( $form, $entry, $field );

			// If email address is invalid, skip.
			if ( ! GFCommon::is_valid_email( $email_address ) ) {
				continue;
			}

			// If email address is already assigned to object, skip.
			if ( $check_for_existing && rgar( $object, 'emailAddresses' ) && $this->exists_in_array( $object['emailAddresses'], 'address', $email_address ) ) {
				continue;
			}

			// Prepare email address type.
			$type = ucfirst( str_replace( 'email_', '', $field_key ) );

			// Add email address to object.
			$object['emailAddresses'][] = array(
				'type'         => $type,
				'emailAddress' => $email_address,
			);

		}

		return $object;

	}

	/**
	 * Add phone number contact data to a Capsule CRM object.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $object             Capsule CRM object.
	 * @param array $feed               The feed object.
	 * @param array $entry              The entry object.
	 * @param array $form               The form object.
	 * @param bool  $check_for_existing If existing data should be checked. Defaults to false.
	 *
	 * @uses   GFAddOn::get_dynamic_field_map_fields()
	 * @uses   GFAddOn::get_field_value()
	 * @uses   GFCapsuleCRM::exists_in_array()
	 *
	 * @return array
	 */
	public function add_person_phone_data( $object, $feed, $entry, $form, $check_for_existing = false ) {

		// Get custom fields.
		$custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		// Loop through custom fields.
		foreach ( $custom_fields as $field_key => $field ) {

			// If this is not a phone number field, skip.
			if ( strpos( $field_key, 'phone_' ) !== 0 ) {
				continue;
			}

			// Get the phone number.
			$phone_number = $this->get_field_value( $form, $entry, $field );

			// If phone number is empty, skip.
			if ( rgblank( $phone_number ) ) {
				continue;
			}

			// If phone number is already assigned to object, skip.
			if ( $check_for_existing && rgar( $object, 'phoneNumbers' ) && $this->exists_in_array( $object['phoneNumbers'], 'number', $phone_number ) ) {
				continue;
			}

			// Prepare phone number type.
			$type = ucfirst( str_replace( 'phone_', '', $field_key ) );

			// Add phone number to object.
			$object['phoneNumbers'][] = array(
				'type'   => $type,
				'number' => $phone_number,
			);

		}

		return $object;

	}

	/**
	 * Add website contact data to a Capsule CRM object.
	 *
	 * @access public
	 *
	 * @param array $object             Capsule CRM object.
	 * @param array $feed               The feed object.
	 * @param array $entry              The entry object.
	 * @param array $form               The form object.
	 * @param bool  $check_for_existing If existing data should be checked. Defaults to false.
	 *
	 * @uses   GFAddOn::get_dynamic_field_map_fields()
	 * @uses   GFAddOn::get_field_value()
	 * @uses   GFCapsuleCRM::exists_in_array()
	 *
	 * @return array
	 */
	public function add_person_website_data( $object, $feed, $entry, $form, $check_for_existing = false ) {

		// Get custom fields.
		$custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		// Loop through custom fields.
		foreach ( $custom_fields as $field_key => $field ) {

			// If this is not an website address field, skip.
			if ( strpos( $field_key, 'website_' ) !== 0 ) {
				continue;
			}

			// Get the website address.
			$website_address = $this->get_field_value( $form, $entry, $field );

			// If website address is empty, skip.
			if ( rgblank( $website_address ) ) {
				continue;
			}

			// If website address is already assigned to object, skip.
			if ( $check_for_existing && rgar( $object, 'websites' ) && $this->exists_in_array( $object['websites'], 'address', $website_address ) ) {
				continue;
			}

			// Prepare website type.
			$service = strtoupper( str_replace( 'website_', '', $field_key ) );

			// Add website to object.
			$object['websites'][] = array(
				'service' => $service,
				'address' => $website_address,
			);

		}

		return $object;

	}

	/**
	 * Check if value exists in multidimensional array.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array  $array The array to check.
	 * @param string $key   The key to check in.
	 * @param string $value The value to check for.
	 *
	 * @return bool
	 */
	public function exists_in_array( $array, $key, $value ) {

		foreach ( $array as $item ) {

			if ( ! isset( $item[ $key ] ) ) {
				continue;
			}

			if ( $item[ $key ] == $value ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Initialized Capsule CRM API if credentials are valid.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GFAddOn::get_plugin_settings()
	 * @uses   GF_CapsuleCRM_API::get_users()
	 *
	 * @return bool
	 */
	public function initialize_api() {

		// If API is already initialized, return.
		if ( ! is_null( $this->api ) ) {
			return true;
		}

		// Load the Capsule CRM API library. */
		if ( ! class_exists( 'GF_CapsuleCRM_API' ) ) {
			require_once 'includes/class-gf-capsulecrm-api.php';
		}

		// Get the plugin settings.
		$settings = $this->get_plugin_settings();

		// If authorization token is not provided, return.
		if ( ! rgar( $settings, 'authToken' ) ) {
			return null;
		}

		// Log that we are validating API credentials.
		$this->log_debug( __METHOD__ . '(): Validating API info.' );

		// Initialize new Capsule CRM API object.
		$capsule = new GF_CapsuleCRM_API( $settings['authToken'] );

		// Attempt to get account users.
		$users = $capsule->get_users();

		if ( is_wp_error( $users ) ) {
			$this->log_error( __METHOD__ . '(): API credentials are invalid; ' . $users->get_error_message() );

			return false;
		}

		// Log that test passed.
		$this->log_debug( __METHOD__ . '(): API credentials are valid.' );

		// Assign Capsule CRM object to the class.
		$this->api = $capsule;

		return true;

	}

}

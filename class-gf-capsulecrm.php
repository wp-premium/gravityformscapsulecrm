<?php
	
GFForms::include_feed_addon_framework();

class GFCapsuleCRM extends GFFeedAddOn {
	
	protected $_version = GF_CAPSULECRM_VERSION;
	protected $_min_gravityforms_version = '1.9.12';
	protected $_slug = 'gravityformscapsulecrm';
	protected $_path = 'gravityformscapsulecrm/capsulecrm.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms Capsule CRM Add-On';
	protected $_short_title = 'Capsule CRM';
	protected $_enable_rg_autoupgrade = true;
	protected $api = null;
	private static $_instance = null;

	/* Members plugin integration */
	protected $_capabilities = array( 'gravityforms_capsulecrm', 'gravityforms_capsulecrm_uninstall' );

	/* Permissions */
	protected $_capabilities_settings_page = 'gravityforms_capsulecrm';
	protected $_capabilities_form_settings = 'gravityforms_capsulecrm';
	protected $_capabilities_uninstall = 'gravityforms_capsulecrm_uninstall';
	
	/**
	 * Get instance of this class.
	 * 
	 * @access public
	 * @static
	 * @return $_instance
	 */
	public static function get_instance() {
		
		if ( self::$_instance == null ) {
			self::$_instance = new self;
		}

		return self::$_instance;
		
	}

	/**
	 * Register needed styles.
	 * 
	 * @access public
	 * @return array $styles
	 */
	public function styles() {
		
		$styles = array(
			array(
				'handle'  => 'gform_capsulecrm_form_settings_css',
				'src'     => $this->get_base_url() . '/css/form_settings.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				)
			)
		);
		
		return array_merge( parent::styles(), $styles );
		
	}

	/**
	 * Setup plugin settings fields.
	 * 
	 * @access public
	 * @return array
	 */
	public function plugin_settings_fields() {
						
		return array(
			array(
				'title'       => '',
				'description' => $this->plugin_settings_description(),
				'fields'      => array(
					array(
						'name'              => 'account_url',
						'label'             => esc_html__( 'Account URL', 'gravityformscapsulecrm' ),
						'type'              => 'text',
						'class'             => 'small',
						'after_input'       => '.capsulecrm.com',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'name'              => 'api_token',
						'label'             => esc_html__( 'API Token', 'gravityformscapsulecrm' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'initialize_api' )
					),
					array(
						'type'              => 'save',
						'messages'          => array(
							'success' => esc_html__( 'Capsule CRM settings have been updated.', 'gravityformscapsulecrm' )
						),
					),
				),
			),
		);
		
	}

	/**
	 * Prepare plugin settings description.
	 * 
	 * @access public
	 * @return string
	 */
	public function plugin_settings_description() {
		
		$description  = '<p>';
		$description .= sprintf(
			esc_html__( 'Capsule CRM is a contact management tool makes it easy to track cases, opportunities, people and tasks. Use Gravity Forms to collect customer information and automatically add them to your Capsule CRM account. If you don\'t have a Capsule CRM account, you can %1$s sign up for one here.%2$s', 'gravityformscapsulecrm' ),
			'<a href="http://www.capsulecrm.com/" target="_blank">', '</a>'
		);
		$description .= '</p>';
		
		if ( ! $this->initialize_api() ) {
			
			$description .= '<p>';
			$description .= esc_html__( 'Gravity Forms Capsule CRM Add-On requires your account URL and API Token, which can be found on the API Authentication Token page in the My Preferences page.', 'gravityformscapsulecrm' );
			$description .= '</p>';
			
		}
				
		return $description;
		
	}
	
	/**
	 * Setup fields for feed settings.
	 * 
	 * @access public
	 * @return array
	 */
	public function feed_settings_fields() {
		
		/* Build base fields array. */
		$base_fields = array(
			'title'  => '',
			'fields' => array(
				array(
					'name'           => 'feed_name',
					'label'          => esc_html__( 'Feed Name', 'gravityformscapsulecrm' ),
					'type'           => 'text',
					'required'       => true,
					'default_value'  => $this->get_default_feed_name(),
					'tooltip'        => '<h6>'. esc_html__( 'Name', 'gravityformscapsulecrm' ) .'</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gravityformscapsulecrm' )
				),
				array(
					'name'           => 'action',
					'label'          => esc_html__( 'Action', 'gravityformscapsulecrm' ),
					'type'           => 'checkbox',
					'required'       => true,
					'onclick'        => "jQuery(this).parents('form').submit();",
					'choices'        => array(
						array(
							'name'          => 'create_person',
							'label'         => esc_html__( 'Create New Person', 'gravityformscapsulecrm' ),
						),
						array(
							'name'          => 'create_task',
							'label'         => esc_html__( 'Create New Task', 'gravityformscapsulecrm' ),
						),
					)
				)
			)
		);
		
		/* Build person fields array. */
		$person_fields = array(
			'title'  => esc_html__( 'Person Details', 'gravityformscapsulecrm' ),
			'dependency' => array( 'field' => 'create_person', 'values' => ( '1' ) ),
			'fields' => array(
				array(
					'name'           => 'person_standard_fields',
					'label'          => esc_html__( 'Map Fields', 'gravityformscapsulecrm' ),
					'type'           => 'field_map',
					'field_map'      => $this->standard_fields_for_feed_mapping(),
					'tooltip'        => '<h6>'. esc_html__( 'Map Fields', 'gravityformscapsulecrm' ) .'</h6>' . esc_html__( 'Select which Gravity Form fields pair with their respective Capsule CRM fields.', 'gravityformscapsulecrm' )
				),
				array(
					'name'           => 'person_custom_fields',
					'label'          => '',
					'type'           => 'dynamic_field_map',
					'field_map'      => $this->custom_fields_for_feed_mapping(),
					'disable_custom' => true,
				),
				array(
					'name'           => 'person_about',
					'label'          => esc_html__( 'About', 'gravityformscapsulecrm' ),
					'type'           => 'textarea',
					'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
				),		
				array(
					'name'           => 'update_person',
					'label'          => esc_html__( 'Update Person', 'gravityformscapsulecrm' ),
					'type'           => 'checkbox_and_select',
					'tooltip'        => '<h6>'. esc_html__( 'Update Person', 'gravityformscapsulecrm' ) .'</h6>' . esc_html__( 'If enabled and an existing person is found, their contact details will either be replaced or appended. Job title and organization will be replaced whether replace or append is chosen.', 'gravityformscapsulecrm' ),
					'checkbox'       => array(
						'name'          => 'update_person_enable',
						'label'         => esc_html__( 'Update Person if already exists', 'gravityformscapsulecrm' ),
					),
					'select'         => array(
						'name'          => 'update_person_action',
						'choices'       => array(
							array(
								'label'         => esc_html__( 'and replace existing data', 'gravityformscapsulecrm' ),
								'value'         => 'replace'
							),
							array(
								'label'         => esc_html__( 'and append new data', 'gravityformscapsulecrm' ),
								'value'         => 'append'
							)
						)	
					),
				),
				array(
					'name'           => 'assign_to',
					'label'          => esc_html__( 'Assign To', 'gravityformscapsulecrm' ),
					'type'           => 'checkbox',
					'onclick'        => "jQuery(this).parents('form').submit();",
					'choices'        => array(
						array(
							'name'          => 'create_case',
							'label'         => esc_html__( 'Assign Person to a New Case', 'gravityformscapsulecrm' ),
						),
						array(
							'name'          => 'create_opportunity',
							'label'         => esc_html__( 'Assign Person to a New Opportunity', 'gravityformscapsulecrm' ),
						),
					)
				)
			)
		);

		/* Build case fields array. */
		$case_fields = array(
			'title'      => esc_html__( 'Case Details', 'gravityformscapsulecrm' ),
			'dependency' => array( 'field' => 'create_case', 'values' => ( '1' ) ),
			'fields'     => array(
				array(
					'name'           => 'case_name',
					'type'           => 'text',
					'required'       => true,
					'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'          => esc_html__( 'Name', 'gravityformscapsulecrm' ),
				),
				array(
					'name'           => 'case_description',
					'type'           => 'text',
					'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'          => esc_html__( 'Description', 'gravityformscapsulecrm' ),
				),
				array(
					'name'           => 'case_status',
					'type'           => 'select',
					'label'          => esc_html__( 'Status', 'gravityformscapsulecrm' ),
					'choices'        => array(
						array(
							'label' => esc_html__( 'Open', 'gravityformscapsulecrm' ),
							'value' => 'OPEN',
						),	
						array(
							'label' => esc_html__( 'Closed', 'gravityformscapsulecrm' ),
							'value' => 'CLOSED',
						)	
					),
				),
				array(
					'name'           => 'case_owner',
					'type'           => 'select',
					'label'          => esc_html__( 'Owner', 'gravityformscapsulecrm' ),
					'choices'        => $this->get_owners_for_feed_setting(),
				),
			)
		);

		/* Build opportunity fields array. */
		$opportunity_fields = array(
			'title'      => esc_html__( 'Opportunity Details', 'gravityformscapsulecrm' ),
			'dependency' => array( 'field' => 'create_opportunity', 'values' => ( '1' ) ),
			'fields'     => array(
				array(
					'name'           => 'opportunity_name',
					'type'           => 'text',
					'required'       => true,
					'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'          => esc_html__( 'Name', 'gravityformscapsulecrm' ),
				),
				array(
					'name'           => 'opportunity_description',
					'type'           => 'text',
					'class'          => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'          => esc_html__( 'Description', 'gravityformscapsulecrm' ),
				),
				array(
					'name'           => 'opportunity_milestone',
					'type'           => 'select',
					'required'       => true,
					'label'          => esc_html__( 'Milestone', 'gravityformscapsulecrm' ),
					'choices'        => $this->get_opportunity_milestones_for_feed_setting(),
				),
				array(
					'name'           => 'opportunity_owner',
					'type'           => 'select',
					'label'          => esc_html__( 'Owner', 'gravityformscapsulecrm' ),
					'choices'        => $this->get_owners_for_feed_setting(),
				),
			)
		);

		/* Build task fields array. */
		$task_fields = array(
			'title'      => esc_html__( 'Task Details', 'gravityformscapsulecrm' ),
			'dependency' => array( 'field' => 'create_task', 'values' => ( '1' ) ),
			'fields'     => array(
				array(
					'name'                => 'task_description',
					'type'                => 'text',
					'required'            => true,
					'class'               => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'               => esc_html__( 'Description', 'gravityformscapsulecrm' ),
				),
				array(
					'name'                => 'task_detail',
					'type'                => 'text',
					'class'               => 'medium merge-tag-support mt-position-right mt-hide_all_fields',
					'label'               => esc_html__( 'Detail', 'gravityformscapsulecrm' ),
				),
				array(
					'name'                => 'task_days_until_due',
					'type'                => 'text',
					'required'            => true,
					'class'               => 'small',
					'label'               => esc_html__( 'Days Until Due', 'gravityformscapsulecrm' ),
					'validation_callback' => array( $this, 'validate_task_days_until_due' )
				),
				array(
					'name'                => 'task_status',
					'type'                => 'select',
					'label'               => esc_html__( 'Status', 'gravityformscapsulecrm' ),
					'choices'             => array(
						array(
							'label' => esc_html__( 'Open', 'gravityformscapsulecrm' ),
							'value' => 'OPEN',
						),	
						array(
							'label' => esc_html__( 'Completed', 'gravityformscapsulecrm' ),
							'value' => 'COMPLETED',
						)	
					),
				),
				array(
					'name'                => 'task_category',
					'type'                => 'select',
					'label'               => esc_html__( 'Category', 'gravityformscapsulecrm' ),
					'choices'             => $this->get_task_categories_for_feed_setting(),
				),
				array(
					'name'                => 'task_owner',
					'type'                => 'select',
					'label'               => esc_html__( 'Owner', 'gravityformscapsulecrm' ),
					'choices'             => $this->get_owners_for_feed_setting(),
				),
				array(
					'name'                => 'assign_task',
					'label'               => esc_html__( 'Assign Task', 'gravityformscapsulecrm' ),
					'type'                => 'select',
					'choices'             => $this->get_task_assignment_for_feed_setting()
				)
			)
		);

		/* Build conditional logic fields array. */
		$conditional_fields = array(
			'title'      => esc_html__( 'Feed Conditional Logic', 'gravityformscapsulecrm' ),
			'dependency' => array( $this, 'show_conditional_logic_field' ),
			'fields'     => array(
				array(
					'name'           => 'feed_condition',
					'type'           => 'feed_condition',
					'label'          => esc_html__( 'Conditional Logic', 'gravityformscapsulecrm' ),
					'checkbox_label' => esc_html__( 'Enable', 'gravityformscapsulecrm' ),
					'instructions'   => esc_html__( 'Export to Capsule CRM if', 'gravityformscapsulecrm' ),
					'tooltip'        => '<h6>' . esc_html__( 'Conditional Logic', 'gravityformscapsulecrm' ) . '</h6>' . esc_html__( 'When conditional logic is enabled, form submissions will only be exported to Capsule CRM when the condition is met. When disabled, all form submissions will be posted.', 'gravityformscapsulecrm' )
				),
				
			)
		);
		
		return array( $base_fields, $person_fields, $case_fields, $opportunity_fields, $task_fields, $conditional_fields );
		
	}
	
	/**
	 * Set custom dependency for conditional logic.
	 * 
	 * @access public
	 * @return bool
	 */
	public function show_conditional_logic_field() {
		
		/* Get current feed. */
		$feed = $this->get_current_feed();
		
		/* Show if an action is chosen */
		if ( rgpost( '_gaddon_setting_create_person' ) == '1' || $feed['meta']['create_person'] == '1' || rgpost( '_gaddon_setting_create_task' ) == '1' || $feed['meta']['create_task'] == '1' ) {
			
			return true;
						
		}
		
		return false;
		
	}
	
	/**
	 * Check if "Task Days Until Due" setting is numeric.
	 * 
	 * @access public
	 * @param array $field
	 * @param string $field_setting
	 */
	public function validate_task_days_until_due( $field, $field_setting ) {
		
		if ( ! is_numeric( $field_setting ) )
			$this->set_field_error( $field, esc_html__( 'This field must be numeric.', 'gravityforms' ) );
		
	}
	
	/**
	 * Get Capsule CRM users for feed settings field.
	 * 
	 * @access public
	 * @return array $owners
	 */
	public function get_owners_for_feed_setting() {
		
		/* Setup initial owners array. */
		$owners = array(
			array(
				'label' => esc_html__( 'Choose a Capsule CRM User', 'gravityformscapsulecrm' ),
				'value' => ''
			)	
		);
		
		/* If API is not initialized, return owners array. */
		if ( ! $this->initialize_api() )
			return $owners;
		
		try {
			
			$users = $this->api->get_users();
				
			foreach ( $users as $user ) {
					
				$owners[] = array(
					'label' => $user['name'],
					'value' => $user['username']	
				);
				
			}
			
		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Could not get Capsule CRM users; '. $e->getMessage() );	
			
		}
		
		/* Return owners array. */
		return $owners;
		
	}

	/**
	 * Get Capsule CRM opportunity milestones for feed settings field.
	 * 
	 * @access public
	 * @return array $milestones
	 */
	public function get_opportunity_milestones_for_feed_setting() {
		
		/* Setup initial milestones array. */
		$milestones = array(
			array(
				'label' => esc_html__( 'Choose a Milestone', 'gravityformscapsulecrm' ),
				'value' => ''
			)	
		);
		
		/* If API is not initialized, return milestones array. */
		if ( ! $this->initialize_api() )
			return $milestones;
		
		try {
			
			$_milestones = $this->api->get_opportunity_milestones();

			if ( ! empty( $_milestones ) ) {
				
				foreach ( $_milestones as $milestone ) {
					
					$milestones[] = array(
						'label' => $milestone['name'],
						'value' => $milestone['id']
					);
					
				}
				
			}
			
		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Could not get Capsule CRM opportunity milestones; '. $e->getMessage() );	
			
		}
		
		/* Return categories array. */
		return $milestones;
		
	}

	/**
	 * Get Capsule CRM task categories for feed settings field.
	 * 
	 * @access public
	 * @return array $categories
	 */
	public function get_task_categories_for_feed_setting() {
		
		/* Setup initial categories array. */
		$categories = array(
			array(
				'label' => esc_html__( 'Choose a Category', 'gravityformscapsulecrm' ),
				'value' => ''
			)	
		);
		
		/* If API is not initialized, return categories array. */
		if ( ! $this->initialize_api() )
			return $categories;
		
		try {
			
			$_categories = $this->api->get_task_categories();

			if ( ! empty( $_categories ) ) {
				
				foreach ( $_categories as $category ) {
					
					$categories[] = array(
						'label' => $category,
						'value' => $category
					);
					
				}
				
			}
			
		} catch ( Exception $e ) {
			
			$this->log_error( __METHOD__ . '(): Could not get Capsule CRM task categories; '. $e->getMessage() );	
			
		}
		
		/* Return categories array. */
		return $categories;
		
	}

	/**
	 * Get task assignment options for feed settings fields.
	 * 
	 * @access public
	 * @return array $assignments
	 */
	public function get_task_assignment_for_feed_setting() {
		
		/* Setup assignments array. */
		$assignments = array(
			array(
				'value' => 'none',
				'label' => esc_html__( 'Do Not Assign Task', 'gravityformscapsulecrm' ),
			)
		);
		
		/* Get current feed. */
		$feed = $this->get_current_feed();
		
		/* Add case field */
		if ( rgpost( '_gaddon_setting_create_case' ) == '1' || ( isset( $feed['meta']['create_case'] ) && $feed['meta']['create_case'] == '1' ) ) {
			
			$assignments[] = array(
				'value' => 'case',
				'label' => esc_html__( 'Assign Task to Created Case', 'gravityformscapsulecrm' ),
			);
			
		}

		/* Add contact field */
		if ( rgpost( '_gaddon_setting_create_person' ) == '1' || $feed['meta']['create_person'] == '1' ) {
			
			$assignments[] = array(
				'value' => 'person',
				'label' => esc_html__( 'Assign Task to Created Person', 'gravityformscapsulecrm' ),
			);
			
		}

		/* Add opportunity field */
		if ( rgpost( '_gaddon_setting_create_opportunity' ) == '1' || ( isset( $feed['meta']['create_opportunity'] ) && $feed['meta']['create_opportunity'] == '1' ) ) {
			
			$assignments[] = array(
				'value' => 'opportunity',
				'label' => esc_html__( 'Assign Task to Created Opportunity', 'gravityformscapsulecrm' ),
			);
			
		}
		
		/* Return assignments array. */
		return $assignments;
		
	}

	/**
	 * Prepare standard fields for feed field mapping.
	 * 
	 * @access public
	 * @return array
	 */
	public function standard_fields_for_feed_mapping() {
		
		return array(
			array(	
				'name'          => 'first_name',
				'label'         => esc_html__( 'First Name', 'gravityformscapsulecrm' ),
				'required'      => true,
				'field_type'    => array( 'name' ),
				'default_value' => $this->get_first_field_by_type( 'name', 3 ),
			),
			array(	
				'name'          => 'last_name',
				'label'         => esc_html__( 'Last Name', 'gravityformscapsulecrm' ),
				'required'      => true,
				'field_type'    => array( 'name' ),
				'default_value' => $this->get_first_field_by_type( 'name', 6 ),
			),
			array(	
				'name'          => 'email_address',
				'label'         => esc_html__( 'Email Address', 'gravityformscapsulecrm' ),
				'required'      => true,
				'field_type'    => array( 'email' ),
				'default_value' => $this->get_first_field_by_type( 'email' ),
			),
		);
		
	}

	/**
	 * Prepare contact and custom fields for feed field mapping.
	 * 
	 * @access public
	 * @return array
	 */
	public function custom_fields_for_feed_mapping() {
		
		return array(
			array(
				'label'   => esc_html__( 'Choose a Field', 'gravityformscapsulecrm' ),	
			),
			array(	
				'value'    => 'title',
				'label'    => esc_html__( 'Job Title', 'gravityformscapsulecrm' ),
			),
			array(	
				'value'    => 'organization',
				'label'    => esc_html__( 'Organization', 'gravityformscapsulecrm' ),
			),
			array(	
				'label'   => esc_html__( 'Email Address', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Work', 'gravityformscapsulecrm' ),
						'value' => 'email_work'	
					),
					array(
						'label' => esc_html__( 'Home', 'gravityformscapsulecrm' ),
						'value' => 'email_home'	
					),
				)
			),
			array(	
				'label'   => esc_html__( 'Phone Number', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Work', 'gravityformscapsulecrm' ),
						'value' => 'phone_work'	
					),
					array(
						'label' => esc_html__( 'Mobile', 'gravityformscapsulecrm' ),
						'value' => 'phone_mobile'	
					),
					array(
						'label' => esc_html__( 'Fax', 'gravityformscapsulecrm' ),
						'value' => 'phone_fax'	
					),
					array(
						'label' => esc_html__( 'Home', 'gravityformscapsulecrm' ),
						'value' => 'phone_home'	
					),
					array(
						'label' => esc_html__( 'Direct', 'gravityformscapsulecrm' ),
						'value' => 'phone_direct'	
					),
				)
			),
			array(	
				'label'   => esc_html__( 'Address', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'Office', 'gravityformscapsulecrm' ),
						'value' => 'address_office'	
					),
					array(
						'label' => esc_html__( 'Home', 'gravityformscapsulecrm' ),
						'value' => 'address_home'	
					),
					array(
						'label' => esc_html__( 'Postal', 'gravityformscapsulecrm' ),
						'value' => 'address_postal'	
					),
				)
			),
			array(	
				'label'   => esc_html__( 'Website', 'gravityformscapsulecrm' ),
				'choices' => array(
					array(
						'label' => esc_html__( 'URL', 'gravityformscapsulecrm' ),
						'value' => 'website_url'	
					),
					array(
						'label' => esc_html__( 'Skype', 'gravityformscapsulecrm' ),
						'value' => 'website_skype'	
					),
					array(
						'label' => esc_html__( 'Twitter', 'gravityformscapsulecrm' ),
						'value' => 'website_twitter'	
					),
					array(
						'label' => esc_html__( 'Facebook', 'gravityformscapsulecrm' ),
						'value' => 'website_facebook'	
					),
					array(
						'label' => esc_html__( 'LinkedIn', 'gravityformscapsulecrm' ),
						'value' => 'website_linked_in'	
					),
					array(
						'label' => esc_html__( 'Xing', 'gravityformscapsulecrm' ),
						'value' => 'website_xing'	
					),
					array(
						'label' => esc_html__( 'Feed', 'gravityformscapsulecrm' ),
						'value' => 'website_feed'	
					),
					array(
						'label' => esc_html__( 'Google Plus', 'gravityformscapsulecrm' ),
						'value' => 'website_google_plus'	
					),
					array(
						'label' => esc_html__( 'Flickr', 'gravityformscapsulecrm' ),
						'value' => 'website_flickr'	
					),
					array(
						'label' => esc_html__( 'GitHub', 'gravityformscapsulecrm' ),
						'value' => 'website_github'	
					),
					array(
						'label' => esc_html__( 'YouTube', 'gravityformscapsulecrm' ),
						'value' => 'website_youtube'	
					),
				)
			),
		);
		
	}

	/**
	 * Setup columns for feed list table.
	 * 
	 * @access public
	 * @return array
	 */
	public function feed_list_columns() {
		
		return array(
			'feed_name' => esc_html__( 'Name', 'gravityformscapsulecrm' ),
			'action'    => esc_html__( 'Action', 'gravityformscapsulecrm' )
		);
		
	}

	/**
	 * Get action for feed list column.
	 * 
	 * @access public
	 * @param array $feed
	 * @return string
	 */
	public function get_column_value_action( $feed ) {
		
		$create_person = ( $feed['meta']['create_person'] == '1' );
		$create_task   = ( $feed['meta']['create_task'] == '1' );
		
		if ( $create_person && $create_task ) {
			
			return esc_html__( 'Create Person & Task', 'gravityformscapsulecrm' );
			
		} else if ( $create_person ) {
			
			return esc_html__( 'Create Person', 'gravityformscapsulecrm' );
			
		} else if ( $create_task ) {
			
			return esc_html__( 'Create Task', 'gravityformscapsulecrm' );
			
		}
		
	}

	/**
	 * Set feed creation control.
	 * 
	 * @access public
	 * @return bool
	 */
	public function can_create_feed() {
		
		return $this->initialize_api();
		
	}

	/**
	 * Process feed.
	 * 
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 */
	public function process_feed( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Processing feed.' );
		
		/* If API instance is not initialized, exit. */
		if ( ! $this->initialize_api() ) {
			
			$this->add_feed_error( esc_html__( 'Feed was not processed because API was not initialized.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return;
			
		}
		
		/* Create person? */
		if ( $feed['meta']['create_person'] == '1') {
			
			if ( $feed['meta']['update_person_enable'] == '1' ) {
				
				$existing_people = $this->api->find_people_by_email( $this->get_field_value( $form, $entry, $feed['meta']['person_standard_fields_email_address'] ) );
			
				if ( empty( $existing_people ) )
					$person = $this->create_person( $feed, $entry, $form );
				else
					$person = $this->update_person( $existing_people[0], $feed, $entry, $form );
			
			} else {
				
				$person = $this->create_person( $feed, $entry, $form );
			
			}
			
			/* Create case? */
			if ( $feed['meta']['create_case'] == '1' && is_array( $person ) )
				$case = $this->create_case( $person, $feed, $entry, $form );

			/* Create opportunity? */
			if ( $feed['meta']['create_opportunity'] == '1' && is_array( $person ) )
				$opportunity = $this->create_opportunity( $person, $feed, $entry, $form );
			
		}
		
		/* Create task? */
		if ( $feed['meta']['create_task'] == '1') {
			
			$task = $this->create_task( $feed, $entry, $form );
			
		}

	}

	/**
	 * Create a new case.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return null|array $case
	 */
	public function create_case( $person, $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Creating case.' );

		/* Prepare case object. */
		$case = array(
			'name'        => GFCommon::replace_variables( $feed['meta']['case_name'], $form, $entry ),
			'description' => GFCommon::replace_variables( $feed['meta']['case_description'], $form, $entry ),
			'status'      => $feed['meta']['case_status'],
			'owner'       => $feed['meta']['case_owner']
		);
		
		/* If the name is empty, exit. */
		if ( rgblank( $case['name'] ) ) {
			
			$this->add_feed_error( esc_html__( 'Case could not be created because case name was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
			
		}

		/* Create task. */
		try {
			
			$case['id'] = $this->api->create_case( $person['id'], $case );
			
			gform_update_meta( $entry['id'], 'capsulecrm_case_id', $case['id'] );

			$this->log_debug( __METHOD__ . '(): Case #' . $case['id'] . ' created.' );
			
		} catch ( Exception $e ) {
			
			$this->add_feed_error( sprintf(
				esc_html__( 'Case could not be created. %s', 'gravityformscapsulecrm' ),
				$e->getMessage()
			), $feed, $entry, $form );
			
			return null;
			
		}
		
		return $case;
		
	}

	/**
	 * Create a new opportunity.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return null|array $opportunity
	 */
	public function create_opportunity( $person, $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Creating opportunity.' );

		/* Prepare opportunity object. */
		$opportunity = array(
			'name'        => GFCommon::replace_variables( $feed['meta']['opportunity_name'], $form, $entry ),
			'description' => GFCommon::replace_variables( $feed['meta']['opportunity_description'], $form, $entry ),
			'milestoneId' => $feed['meta']['opportunity_milestone'],
			'owner'       => $feed['meta']['opportunity_owner']
		);
		
		/* If the name is empty, exit. */
		if ( rgblank( $opportunity['name'] ) ) {
			
			$this->add_feed_error( esc_html__( 'Opportunity could not be created because opportunity name was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			
			return null;
			
		}

		/* Create task. */
		try {
			
			$opportunity['id'] = $this->api->create_opportunity( $person['id'], $opportunity );
			
			gform_update_meta( $entry['id'], 'capsulecrm_opportunity_id', $opportunity['id'] );

			$this->log_debug( __METHOD__ . '(): Opportunity #' . $opportunity['id'] . ' created.' );
			
		} catch ( Exception $e ) {
			
			$this->add_feed_error( sprintf(
				esc_html__( 'Opportunity could not be created. %s', 'gravityformscapsulecrm' ),
				$e->getMessage()
			), $feed, $entry, $form );

			return null;
			
		}
		
		return $opportunity;
		
	}

	/**
	 * Create a new person.
	 * 
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return null|array $person
	 */
	public function create_person( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Creating person.' );

		/* Setup mapped fields array. */
		$person_standard_fields = $this->get_field_map_fields( $feed, 'person_standard_fields' );
		$person_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		/* Prepare task object */
		$person = array(
			'firstName'        => $this->get_field_value( $form, $entry, $person_standard_fields['first_name'] ),
			'lastName'         => $this->get_field_value( $form, $entry, $person_standard_fields['last_name'] ),
			'jobTitle'         => isset( $person_custom_fields['title'] ) ? $this->get_field_value( $form, $entry, $person_custom_fields['title'] ) : '',
			'organisationName' => isset( $person_custom_fields['organization'] ) ? $this->get_field_value( $form, $entry, $person_custom_fields['organization'] ) : '',
			'about'            => GFCommon::replace_variables( $feed['meta']['person_about'], $form, $entry, false, false, false, 'text' ),
			'contacts'         => array(
				'address'  => array(),
				'email'    => array(
					array(
						'emailAddress' => $this->get_field_value( $form, $entry, $person_standard_fields['email_address'] )
					)
				),
				'phone'    => array(),
				'website'  => array()
			)
		);

		/* If the name is empty, exit. */
		if ( rgblank( $person['firstName'] ) || rgblank( $person['lastName'] ) ) {
			
			$this->add_feed_error( esc_html__( 'Person could not be created as first and/or last name were not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			
			return null;
			
		}

		/* If the email address is empty, exit. */
		if ( rgblank( $person['contacts']['email'][0]['emailAddress'] ) ) {
			
			$this->add_feed_error( esc_html__( 'Person could not be created as email address was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			
			return null;
			
		}

		/* Add any mapped addresses. */
		$person = $this->add_person_address_data( $person, $feed, $entry, $form );

		/* Add any mapped email addresses. */
		$person = $this->add_person_email_data( $person, $feed, $entry, $form );

		/* Add any mapped phone numbers. */
		$person = $this->add_person_phone_data( $person, $feed, $entry, $form );

		/* Add any mapped websites. */
		$person = $this->add_person_website_data( $person, $feed, $entry, $form );

		/* Create person. */
		$this->log_debug( __METHOD__ . '(): Creating person: ' . print_r( $person, true ) );
		
		try {
			
			$person['id'] = $this->api->create_person( $person );
			
			gform_update_meta( $entry['id'], 'capsulecrm_person_id', $person['id'] );

			$this->log_debug( __METHOD__ . '(): Person #' . $person['id'] . ' created.' );
			
		} catch ( Exception $e ) {
			
			$this->add_feed_error( sprintf(
				esc_html__( 'Person could not be created. %s', 'gravityformscapsulecrm' ),
				$e->getMessage()
			), $feed, $entry, $form );
			
			return null;
			
		}
				
		return $person;
		
	}

	/**
	 * Create a new task.
	 * 
	 * @access public
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return null|array $task
	 */
	public function create_task( $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Creating task.' );

		/* Prepare task object */
		$task = array(
			'description' => GFCommon::replace_variables( $feed['meta']['task_description'], $form, $entry ),
			'detail'      => GFCommon::replace_variables( $feed['meta']['task_detail'], $form, $entry ),
			'dueDateTime' => date( 'c', strtotime( '+' . $feed['meta']['task_days_until_due'] . ' days' ) ),
			'category'    => $feed['meta']['task_category'],
			'status'      => $feed['meta']['task_status'],
			'owner'       => $feed['meta']['task_owner']
		);
		
		/* If the description is empty, exit. */
		if ( rgblank( $task['description'] ) ) {
			
			$this->add_feed_error( esc_html__( 'Task could not be created as the task description was not provided.', 'gravityformscapsulecrm' ), $feed, $entry, $form );
			return null;
			
		}
		
		/* Prepare task assignment. */
		$assign_to = $assign_object_id = null;
		
		if ( $feed['meta']['assign_task'] !== 'none' ) {
			
			$object_type = $feed['meta']['assign_task'];
			$assign_object_id = gform_get_meta( $entry['id'], 'capsulecrm_' . $object_type . '_id' );
			
			if ( $assign_object_id ) {
				
				if ( $object_type == 'case' )
					$assign_to = 'kase';
				else if ( $object_type == 'person' )
					$assign_to = 'party';
				else
					$assign_to = $object_type;
				
			}
			
		}
		
		/* Create task. */
		$this->log_debug( __METHOD__ . '(): Creating task: ' . print_r( $task, true ) );
		try {
			
			$task['id'] = $this->api->create_task( $task, $assign_to, $assign_object_id );

			gform_update_meta( $entry['id'], 'capsulecrm_task_id', $task['id'] );

			$this->log_debug( __METHOD__ . '(): Task #' . $task['id'] . ' created.' );
			
		} catch ( Exception $e ) {
			
			$this->add_feed_error( sprintf(
				esc_html__( 'Task could not be created. %s', 'gravityformscapsulecrm' ),
				$e->getMessage()
			), $feed, $entry, $form );

			return null;
			
		}
		
		return $task;
		
	}

	/**
	 * Update existing person.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @return array $person
	 */
	public function update_person( $person, $feed, $entry, $form ) {
		
		$this->log_debug( __METHOD__ . '(): Updating person #' . $person['id'] . '.' );

		/* Save original person object in case. */
		$original_person = $person;

		/* Setup mapped fields array. */
		$person_standard_fields = $this->get_field_map_fields( $feed, 'person_standard_fields' );
		$person_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		/* Move addresses to arrays if they are not already arrays. */
		if ( isset( $person['contacts']['address'] ) ) {
			
			$addresses = array_values( $person['contacts']['address'] );
			
			if ( ! is_array( $addresses[0] ) ) {
				
				$person['contacts']['address'] = array( $person['contacts']['address'] );
				
			}

		} else if ( ! isset( $person['contacts']['address'] ) ) {
			
			$person['contacts']['address'] = array();
			
		}

		/* Move email addresses to arrays if they are not already arrays. */
		if ( isset( $person['contacts']['email'] ) ) {
			
			$email_addresses = array_values( $person['contacts']['email'] );
			
			if ( ! is_array( $email_addresses[0] ) ) {
				
				$person['contacts']['email'] = array( $person['contacts']['email'] );
				
			}
			
		} else if ( ! isset( $person['contacts']['email'] ) ) {
			
			$person['contacts']['email'] = array();
			
		}

		/* Move phone numbers to arrays if they are not already arrays. */
		if ( isset( $person['contacts']['phone'] ) ) {
			
			$phone_numbers = array_values( $person['contacts']['phone'] );
			
			if ( ! is_array( $phone_numbers[0] ) ) {
			
				$person['contacts']['phone'] = array( $person['contacts']['phone'] );
				
			}

		} else if ( ! isset( $person['contacts']['phone'] ) ) {
			
			$person['contacts']['phone'] = array();
			
		}

		/* Move websites to arrays if they are not already arrays. */
		if ( isset( $person['contacts']['website'] ) ) {
			
			$websites = array_values( $person['contacts']['website'] );

			if ( ! is_array( $websites[0] ) ) {
			
				$person['contacts']['website'] = array( $person['contacts']['website'] );
				
			}
			
		} else if ( ! isset( $person['contacts']['website'] ) ) {
			
			$person['contacts']['website'] = array();
			
		}
		
		/* Add standard data. */
		$person['firstName']        = $this->get_field_value( $form, $entry, $person_standard_fields['first_name'] );
		$person['lastName']         = $this->get_field_value( $form, $entry, $person_standard_fields['last_name'] );
		$person['jobTitle']         = isset( $person_custom_fields['title'] ) ? $this->get_field_value( $form, $entry, $person_custom_fields['title'] ) : '';
		$person['organisationName'] = isset( $person_custom_fields['organization'] ) ? $this->get_field_value( $form, $entry, $person_custom_fields['organization'] ) : '';
		
		/* Remove organization ID. */
		unset( $person['organisationId'] );

		/* Either replace or append new contact information. */
		if ( $feed['meta']['update_person_action'] == 'replace' ) {
			
			/* Remove current contact information. */
			foreach ( $person['contacts'] as &$contact_type ) {
				
				if ( ! empty( $contact_type ) ) {
					
					foreach ( $contact_type as &$contact_data ) {
						
						$contact_data['@delete'] = true;
						
					}
					
				}
				
			}
			
			$person['about']               = GFCommon::replace_variables( $feed['meta']['person_about'], $form, $entry, false, false, false, 'text' );
			$person['contacts']['email'][] = array( 'emailAddress' => $this->get_field_value( $form, $entry, $person_standard_fields['email_address'] ) );
			
			/* Add any mapped addresses. */
			$person = $this->add_person_address_data( $person, $feed, $entry, $form );
	
			/* Add any mapped email addresses. */
			$person = $this->add_person_email_data( $person, $feed, $entry, $form );
	
			/* Add any mapped phone numbers. */
			$person = $this->add_person_phone_data( $person, $feed, $entry, $form );
	
			/* Add any mapped websites. */
			$person = $this->add_person_website_data( $person, $feed, $entry, $form );

		} else if ( $feed['meta']['update_person_action'] == 'append' ) {
			
			$about = GFCommon::replace_variables( $feed['meta']['person_about'], $form, $entry, false, false, false, 'text' );
			
			/* Add standard data. */
			if ( ! isset( $person['jobTitle'] ) ) {
				$person['jobTitle'] = null;
			}

			if ( ! isset( $person['organisationName'] ) ) {
				$person['organisationName'] = null;
			}
			
			$person['about']             = isset( $person['about'] ) ? $person['about'] . ' ' . $about : $about;
			
			/* Add any mapped addresses. */
			$person = $this->add_person_address_data( $person, $feed, $entry, $form, true);
	
			/* Add any mapped email addresses. */
			$person = $this->add_person_email_data( $person, $feed, $entry, $form, true );
	
			/* Add any mapped phone numbers. */
			$person = $this->add_person_phone_data( $person, $feed, $entry, $form, true );
	
			/* Add any mapped websites. */
			$person = $this->add_person_website_data( $person, $feed, $entry, $form, true );

		}
		
		/* Update person. */
		$this->log_debug( __METHOD__ . '(): Updating person: ' . print_r( $person, true ) );
		try {
			
			$this->api->update_person( $person['id'], $person );
			
			gform_update_meta( $entry['id'], 'capsulecrm_person_id', $person['id'] );

			$this->log_debug( __METHOD__ . '(): Person #' . $person['id'] . ' updated.' );
			
		} catch ( Exception $e ) {
			
			$this->add_feed_error( sprintf(
				esc_html__( 'Person #%s could not be updated. %s', 'gravityformscapsulecrm' ),
				$person['id'], $e->getMessage()
			), $feed, $entry, $form );
			
			return $original_person;
			
		}
				
		return $person;
		
	}
	
	/**
	 * Add address contact data to person.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @param bool $check_for_existing (default: false)
	 * @return array $person
	 */
	public function add_person_address_data( $person, $feed, $entry, $form, $check_for_existing = false ) {
		
		$person_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		/* Add any mapped addresses. */
		foreach ( $person_custom_fields as $field_key => $field ) {
			
			/* If this is not an address mapped field, move on. */
			if ( strpos( $field_key, 'address_' ) !== 0 )
				continue;
			
			$address_field = GFFormsModel::get_field( $form, $field );
			
			/* If the selected field is not an address field, move on. */
			if ( GFFormsModel::get_input_type( $address_field ) !== 'address' )
				continue;
				
			/* Prepare the type field. */
			$type = ucfirst( str_replace( 'address_', '', $field_key ) );

			/* Get the address field ID. */
			$address_field_id = $address_field->id;

			/* If any of the fields are empty, move on. */
			if ( rgblank( $entry[$address_field_id . '.1'] ) || rgblank( $entry[$address_field_id . '.3'] ) || rgblank( $entry[$address_field_id . '.4'] ) || rgblank( $entry[$address_field_id . '.5'] ) )
				continue;

			/* Check if this address is already in the address data. */
			if ( $check_for_existing && ! empty( $person['contacts']['address'] ) && $this->exists_in_array( $person['contacts']['address'], 'street', $entry[$address_field_id . '.1'] .' '. $entry[$address_field_id . '.2'] ) )
				continue;

			/* Add the address to the contact. */
			$person['contacts']['address'][] = array(
				'type'    => $type,
				'street'  => $entry[$address_field_id . '.1'] .' '. $entry[$address_field_id . '.2'],
				'city'    => $entry[$address_field_id . '.3'],
				'state'   => $entry[$address_field_id . '.4'],
				'zip'     => $entry[$address_field_id . '.5'],
				'country' => $entry[$address_field_id . '.6']
			);
			
		}

		return $person;
		
	}
	
	/**
	 * Add email address contact data to person.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @param bool $check_for_existing (default: false)
	 * @return array $person
	 */
	public function add_person_email_data( $person, $feed, $entry, $form, $check_for_existing = false ) {
		
		$person_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		/* Add any mapped email fields. */
		foreach ( $person_custom_fields as $field_key => $field ) {
			
			/* Get the email address. */
			$email_address = $this->get_field_value( $form, $entry, $field );

			/* If this is not an email address field or the email address is blank, move on. */
			if ( strpos( $field_key, 'email_' ) !== 0 || rgblank( $email_address ) )
				continue;
				
			/* Check if this email address is already in the email address data. */
			if ( $check_for_existing && ! empty( $person['contacts']['email'] ) && $this->exists_in_array( $person['contacts']['email'], 'emailAddress', $email_address ) )
				continue;
			
			/* Prepare the type field. */
			$type = ucfirst( str_replace( 'email_', '', $field_key ) );
			
			/* Add the email address to the contact. */
			$person['contacts']['email'][] = array(
				'type'         => $type,
				'emailAddress' => $email_address	
			);
			
		}

		return $person;

	}

	/**
	 * Add phone number contact data to person.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @param bool $check_for_existing (default: false)
	 * @return array $person
	 */
	public function add_person_phone_data( $person, $feed, $entry, $form, $check_for_existing = false ) {
		
		$person_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		/* Add any mapped phone numbers. */
		foreach ( $person_custom_fields as $field_key => $field ) {
			
			/* Get the phone number. */
			$phone_number = $this->get_field_value( $form, $entry, $field );

			/* If this is not an phone number field or the phone number is blank, move on. */
			if ( strpos( $field_key, 'phone_' ) !== 0 || rgblank( $phone_number ) )
				continue;
				
			/* Check if this phone number is already in the phone number data. */
			if ( $check_for_existing && ! empty( $person['contacts']['phone'] ) && $this->exists_in_array( $person['contacts']['phone'], 'phoneNumber', $phone_number ) )
				continue;
			
			/* Prepare the type field. */
			$type = ucfirst( str_replace( 'phone_', '', $field_key ) );
			
			/* Add the phone nubmer to the contact. */
			$person['contacts']['phone'][] = array(
				'type'        => $type,
				'phoneNumber' => $phone_number	
			);
			
		}

		return $person;
		
	}

	/**
	 * Add website contact data to person.
	 * 
	 * @access public
	 * @param array $person
	 * @param array $feed
	 * @param array $entry
	 * @param array $form
	 * @param bool $check_for_existing (default: false)
	 * @return array $person
	 */
	public function add_person_website_data( $person, $feed, $entry, $form, $check_for_existing = false ) {
		
		$person_custom_fields = $this->get_dynamic_field_map_fields( $feed, 'person_custom_fields' );

		/* Add any mapped websites. */
		foreach ( $person_custom_fields as $field_key => $field ) {
			
			/* Get the website address. */
			$website_address = $this->get_field_value( $form, $entry, $field );

			/* If this is not an website address field or the website address is blank, move on. */
			if ( strpos( $field_key, 'website_' ) !== 0 || rgblank( $website_address ) )
				continue;
			
			/* Check if this website is already in the website data. */
			if ( $check_for_existing && ! empty( $person['contacts']['website'] ) && $this->exists_in_array( $person['contacts']['website'], 'webAddress', $website_address ) )
				continue;

			/* Prepare the service field. */
			$service = strtoupper( str_replace( 'website_', '', $field_key ) );
			
			/* Add the website to the contact. */
			$person['contacts']['website'][] = array(
				'webService' => $service,
				'webAddress' => $website_address	
			);
			
		}

		return $person;
		
	}
	
	/**
	 * Check if value exists in multidimensional array.
	 * 
	 * @access public
	 * @param array $array
	 * @param string $key
	 * @param string $value
	 * @return bool
	 */
	public function exists_in_array( $array, $key, $value ) {
		
		foreach ( $array as $item ) {
			
			if ( ! isset( $item[$key] ) ) {
				continue;
			}
				
			if ( $item[$key] == $value ) {
				return true;
			}
			
		}
		
		return false;
		
	}

	/**
	 * Initialized Capsule CRM API if credentials are valid.
	 * 
	 * @access public
	 * @return bool
	 */
	public function initialize_api() {

		if ( ! is_null( $this->api ) )
			return true;
		
		/* Load the Capsule CRM API library. */
		require_once 'includes/class-capsulecrm.php';

		/* Get the plugin settings */
		$settings = $this->get_plugin_settings();
		
		/* If any of the account information fields are empty, return null. */
		if ( rgblank( $settings['account_url'] ) || rgblank( $settings['api_token'] ) )
			return null;
			
		$this->log_debug( __METHOD__ . "(): Validating API info." );
		
		$capsule = new CapsuleCRM( $settings['account_url'], $settings['api_token'] );
		
		try {
			
			/* Run API test. */
			$capsule->get_users();
			
			/* Log that test passed. */
			$this->log_debug( __METHOD__ . '(): API credentials are valid.' );
			
			/* Assign Capsule CRM object to the class. */
			$this->api = $capsule;
			
			return true;
			
		} catch ( Exception $e ) {
			
			/* Log that test failed. */
			$this->log_error( __METHOD__ . '(): API credentials are invalid; '. $e->getMessage() );			

			return false;
			
		}
		
	}

}

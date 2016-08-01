<?php
	
require_once 'class-capsulecrm-exception.php';

class CapsuleCRM {
		
	protected $curl;
		
	function __construct( $account_url, $api_token, $verify_ssl = true ) {
			
		$this->account_url = $account_url;
		$this->api_token   = $api_token;
		$this->verify_ssl  = $verify_ssl;
		
	}

	/**
	 * Make API request.
	 * 
	 * @access public
	 * @param string $action
	 * @param array $options (default: array())
	 * @param string $method (default: 'GET')
	 * @param int $expected_code (default: 200)
	 * @return array or int
	 */
	function make_request( $action, $options = array(), $method = 'GET', $expected_code = 200 ) {
					
		$request_options = ( $method == 'GET' ) ? '?' . http_build_query( $options ) : null;
		
		/* Build request URL. */
		$request_url = 'https://' . $this->account_url . '.capsulecrm.com/api/' . $action . $request_options;
		
		/* Setup request arguments. */
		$args = array(
			'headers'   => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $this->api_token . ':x' ),
				'Content-Type'  => 'application/json'
			),
			'method'    => $method,
			'sslverify' => $this->verify_ssl	
		);
		
		/* Add request options to body of POST and PUT requests. */
		if ( $method == 'POST' || $method == 'PUT' ) {
			$args['body'] = $options;
		}

		/* Execute request. */
		$result         = wp_remote_request( $request_url, $args );
		$decoded_result = json_decode( $result['body'], true );
			
		/* If WP_Error, throw exception */
		if ( is_wp_error( $result ) ) {
			throw new Exception( 'Request failed. '. $result->get_error_messages() );
		}
		
		/* If API credentials failed, throw exception. */
		if ( strpos( rgars( $result, 'response/content-type' ), 'application/json' ) == FALSE && $result['response']['code'] !== $expected_code ) {
			throw new Exception( 'API credentials invalid.' );
		}
		
		/* If return HTTP code does not match expected code, throw exception. */
		if ( $result['response']['code'] !== $expected_code ) {
			throw new CapsuleCRM_Exception( $decoded_result['message'], $result['response']['code'], null, rgar( $decoded_result, 'errors' ) );				}
			
		/* If the decoded result isn't empty, return it. */
		if ( ! empty( $decoded_result ) ) {
			return $decoded_result;
		}
		
		/* If the body is empty, retrieve the ID from the location header. */
		if ( rgars( $result, 'headers/location' ) ) {
			$new_id = explode( '/', $result['headers']['location'] );
			return end( $new_id );
		}
		
		return;
		
	}
			
	/**
	 * Create new case.
	 * 
	 * @access public
	 * @param int $party_id
	 * @param array $case
	 * @return int $case_id
	 */
	function create_case( $party_id, $case ) {
		
		/* Prepare case object for creation. */
		$case = json_encode( array( 'kase' => $case ) );
		
		/* Crease case. */
		return $this->make_request( 'party/' . $party_id . '/kase', $case, 'POST', 201 );
		
	}

	/**
	 * Create new opportunity.
	 * 
	 * @access public
	 * @param int $party_id
	 * @param array $opportunity
	 * @return int $opportunity_id
	 */
	function create_opportunity( $party_id, $opportunity ) {
		
		/* Prepare case object for creation. */
		$opportunity = json_encode( array( 'opportunity' => $opportunity ) );
		
		/* Crease case. */
		return $this->make_request( 'party/' . $party_id . '/opportunity', $opportunity, 'POST', 201 );
		
	}
	
	/**
	 * Create new person.
	 * 
	 * @access public
	 * @param array $person
	 * @return string $person_id
	 */
	function create_person( $person ) {
		
		/* Prepare person object for creation. */
		$person = json_encode( array( 'person' => $person ) );
		
		/* Create person. */
		return $this->make_request( 'person', $person, 'POST', 201 );
		
	}
	
	/**
	 * Create new task.
	 * 
	 * @access public
	 * @param array $task
	 * @param string $assign_to (default: null)
	 * @param int $assign_object_id (default: null)
	 * @return string $task_id
	 */
	function create_task( $task, $assign_to = null, $assign_object_id = null ) {
		
		/* Prepare task object for creation. */
		$task = json_encode( array( 'task' => $task ) );
		
		/* Create task. */
		$task_url = ( is_null( $assign_to ) || is_null( $assign_object_id ) ) ? 'task' : $assign_to . '/' . $assign_object_id . '/task';
		
		return $this->make_request( $task_url, $task, 'POST', 201 );
		
	}
	
	/**
	 * Find people by email address.
	 * 
	 * @access public
	 * @param mixed $email_address
	 * @return array $people
	 */
	function find_people_by_email( $email_address ) {
		
		$people = $this->make_request( 'party', array( 'email' => $email_address ) );

		if ( $people['parties']['@size'] == 0 ) {
			
			return array();
			
		} else if ( $people['parties']['@size'] == 1 ) {
			
			return array( $people['parties']['person'] );
			
		} else if ( $people['parties']['@size'] > 1 ) {
			
			return $people['parties']['person'];
			
		}			
		
	}

	/**
	 * Get a person.
	 * 
	 * @access public
	 * @param int $person_id
	 * @return array $person
	 */
	function get_person( $person_id ) {
		
		return $this->make_request( 'party/' . $person_id );
		
	}

	/**
	 * Get opportunity milestones.
	 * 
	 * @access public
	 * @return array $opportunity_milestones
	 */
	function get_opportunity_milestones() {
		
		$opportunity_milestones = $this->make_request( 'opportunity/milestones' );
		
		return ( $opportunity_milestones['milestones']['@size'] == 0 ) ? array() : $opportunity_milestones['milestones']['milestone'];
		
	}

	/**
	 * Get task categories.
	 * 
	 * @access public
	 * @return array $task_categories
	 */
	function get_task_categories() {
		
		$task_categories = $this->make_request( 'task/categories' );
		
		return $task_categories['taskCategories']['taskCategory'];
		
	}

	/**
	 * Get users.
	 * 
	 * @access public
	 * @return array $users
	 */
	function get_users() {
		
		$users = $this->make_request( 'users' );
		
		if ( $users['users']['@size'] == 0 ) {
			
			return array();
			
		} else if ( $users['users']['@size'] == 1 ) {
			
			return array( $users['users']['user'] );
			
		} else if ( $users['users']['@size'] > 1 ) {
			
			return $users['users']['user'];
			
		}			
		
	}
	
	/**
	 * Update person.
	 * 
	 * @access public
	 * @param int $person_id
	 * @param array $person
	 * @return void
	 */
	function update_person( $person_id, $person ) {
		
		/* Prepare person object for creation. */
		$person = json_encode( array( 'person' => $person ) );

		return $this->make_request( 'person/' . $person_id, $person, 'PUT' );
		
	}
	
}

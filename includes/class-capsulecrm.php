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
			
			/* Initialize cURL session. */
			$this->curl = curl_init();
			
			/* Setup cURL options. */
			curl_setopt( $this->curl, CURLOPT_URL, $request_url );
			curl_setopt( $this->curl, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $this->curl, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl );
			curl_setopt( $this->curl, CURLOPT_USERPWD, $this->api_token . ':x' );
			curl_setopt( $this->curl, CURLOPT_HTTPHEADER, array( 'Accept: application/json' , 'Content-Type: application/json' ) );
			curl_setopt( $this->curl, CURLOPT_HEADER, true );

			/* If this is a POST request, pass the request options via cURL option. */
			if ( $method == 'POST' ) {
				
				curl_setopt( $this->curl, CURLOPT_POST, true );
				curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $options );
				
			}

			/* If this is a PUT request, pass the request options via cURL option. */
			if ( $method == 'PUT' ) {
				
				curl_setopt( $this->curl, CURLOPT_CUSTOMREQUEST, 'PUT' );
				curl_setopt( $this->curl, CURLOPT_POSTFIELDS, $options );
				
			}
			
			/* Execute request. */
			$response = curl_exec( $this->curl );
			
			/* If cURL error, die. */
			if ( $response === false )
				throw new Exception( 'Request failed. ' . curl_error( $this->curl ) );

			/* Decode response. */
			list( $headers, $body ) = explode( "\r\n\r\n", $response, 2 );
			$body = json_decode( $body, true );
			
			/* If API credentials failed, throw exception. */
			if ( strpos( curl_getinfo( $this->curl, CURLINFO_CONTENT_TYPE ), 'json' ) == false && curl_getinfo( $this->curl, CURLINFO_HTTP_CODE ) !== $expected_code )
				throw new Exception( 'API credentials invalid.' );
		
			if ( curl_getinfo( $this->curl, CURLINFO_HTTP_CODE ) !== $expected_code )
				throw new CapsuleCRM_Exception( $body['message'], curl_getinfo( $this->curl, CURLINFO_HTTP_CODE ), null, ( isset( $body['errors'] ) ? $body['errors'] : null ) );
			
			if ( ! is_null( $body ) )
				return $body;
				
			/* If the body is empty, retrieve the ID from the location header. */
			preg_match_all( '/^Location:(.*)$/mi', $headers, $location );
			$new_url = trim( $location[1][0] );
			$new_url = explode( '/', $new_url );
			return end( $new_url );
			
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

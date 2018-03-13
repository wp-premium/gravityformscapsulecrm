<?php

require_once 'class-gf-capsulecrm-exception.php';

class GF_CapsuleCRM_API {

	/**
	 * Capsule CRM Authentication Token.
	 *
	 * @var string
	 */
	public $auth_token = '';

	/**
	 * Initialize API object.
	 *
	 * @param string $auth_token Authorization token.
	 */
	public function __construct( $auth_token ) {

		$this->auth_token = $auth_token;

	}





	// # CASE METHODS --------------------------------------------------------------------------------------------------

	/**
	 * Create new case.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $case Case object.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function create_case( $case ) {

		return $this->make_request( 'kases', array( 'kase' => $case ), 'POST', 201 );

	}





	// # OPPORTUNITY METHODS -------------------------------------------------------------------------------------------

	/**
	 * Create new opportunity.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $opportunity Opportunity object.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function create_opportunity( $opportunity ) {

		return $this->make_request( 'opportunities', array( 'opportunity' => $opportunity ), 'POST', 201 );

	}





	// # PARTY METHODS -------------------------------------------------------------------------------------------------

	/**
	 * Create a new party.
	 *
	 * @since  1.2
	 * @access public
	 *
	 * @param array $party Party object.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function create_party( $party ) {

		return $this->make_request( 'parties', array( 'party' => $party ), 'POST', 201 );

	}

	/**
	 * Get a specific party.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param int $party_id Party ID.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function get_party( $party_id ) {

		return $this->make_request( 'parties/' . $party_id );

	}

	/**
	 * Search parties.
	 *
	 * @since  1.2
	 * @access public
	 *
	 * @param string $query Search query.
	 * @param string $type  Party type.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function search_parties( $query, $type = '' ) {

		// Get parties.
		$parties = $this->make_request( 'parties/search', array( 'q' => $query, 'perPage' => 100 ) );

		// If no parties were found, return.
		if ( ! rgar( $parties, 'parties' ) ) {
			return array();
		}

		// If no parties type was defined, return results.
		if ( ! $type ) {
			return rgar( $parties, 'parties' );
		}

		return array_filter( $parties['parties'], function( $party ) use ( $type ) {
			return $type == $party['type'];
		} );

	}

	/**
	 * Update party.
	 *
	 * @since  1.2
	 * @access public
	 *
	 * @param int   $party_id Party ID.
	 * @param array $party    Party object.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function update_party( $party_id, $party ) {

		return $this->make_request( 'parties/' . $party_id, array( 'party' => $party ), 'PUT' );

	}





	// # TASK METHODS --------------------------------------------------------------------------------------------------

	/**
	 * Create new task.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @param array $task Task object.
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function create_task( $task ) {

		return $this->make_request( 'tasks', array( 'task' => $task ), 'POST', 201 );

	}





	// # USER METHODS --------------------------------------------------------------------------------------------------

	/**
	 * Get users.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function get_users() {

		// Get users.
		$users = $this->make_request( 'users' );

		return rgar( $users, 'users' );

	}





	// # SETTINGS METHODS ----------------------------------------------------------------------------------------------

	/**
	 * List all task categories.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function get_categories() {

		$categories = $this->make_request( 'categories' );

		return rgar( $categories, 'categories' );

	}

	/**
	 * Get opportunity milestones.
	 *
	 * @since  1.0
	 * @access public
	 *
	 * @uses   GF_CapsuleCRM_API::make_request()
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	public function get_milestones() {

		$milestones = $this->make_request( 'milestones' );

		return rgar( $milestones, 'milestones' );

	}






	// # HELPER METHODS ------------------------------------------------------------------------------------------------

	/**
	 * Make API request.
	 *
	 * @since  1.0
	 * @access private
	 *
	 * @param string $action
	 * @param array  $options       (default: array())
	 * @param string $method        (default: 'GET')
	 * @param int    $expected_code (default: 200)
	 *
	 * @return array
	 * @throws GF_CapsuleCRM_Exception
	 */
	private function make_request( $action, $options = array(), $method = 'GET', $expected_code = 200 ) {

		// Build request URL.
		$request_url = 'https://api.capsulecrm.com/api/v2/' . $action;
		$request_url = 'GET' == $method ? add_query_arg( $options, $request_url ) : $request_url;

		// Prepare request arguments.
		$args = array(
			'headers'   => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $this->auth_token,
				'Content-Type'  => 'application/json',
			),
			'method'    => $method,
			'sslverify' => false,
		);

		// Add request options to body of POST and PUT requests.
		if ( in_array( $method, array( 'PUT', 'POST' ) ) ) {
			$args['body'] = json_encode( $options );
		}

		// Execute request.
		$result        = wp_remote_request( $request_url, $args );
		$response_code = wp_remote_retrieve_response_code( $result );

		// If WP_Error, throw exception.
		if ( is_wp_error( $result ) ) {
			throw new GF_CapsuleCRM_Exception( $result->get_error_message(), $result->get_error_code() );
		}

		// If API credentials failed, throw exception.
		if ( 401 == $response_code ) {
			throw new GF_CapsuleCRM_Exception( 'API credentials invalid.', 401 );
		}

		// Decode response.
		$response = json_decode( wp_remote_retrieve_body( $result ), true );

		// If returned HTTP code does not match expected code, throw exception.
		if ( $expected_code !== $response_code ) {
			throw new GF_CapsuleCRM_Exception( $response['message'], $response_code, null, rgar( $response, 'errors' ) );
		}

		return $response;

	}

}

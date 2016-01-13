<?php
	
	class CapsuleCRM_Exception extends Exception {
		
		protected $errors;
		
		public function __construct( $message = null, $code = 0, Exception $previous = null, $errors = array() ) {
			
			parent::__construct( $message, $code, $previous );
			
			$this->errors = $errors;
			
		}
		
		public function getErrors() {
			
			return $this->errors;
			
		}
		
	}
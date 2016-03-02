<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Recess_assign extends CI_Driver{

	// GLOBALLY VARIABLES
	protected static $assigns = [];

	public function get( $index, $xss_clean = NULL ) {
		return $this->_parent->array_search( self::$assigns, $index, $xss_clean );
	}

	public function put( $index, $value, $replace = TRUE ) {

		if( is_array( $index ) ) {

			foreach( $index as $k => $v ) {
				$this->put( $k, $v, $replace );
			}

			return;
		}

		if( isset( self::$assigns[$index] ) && ($replace !== TRUE) ) {
			return;
		}

		self::$assigns[$index] = $value;
	}

}

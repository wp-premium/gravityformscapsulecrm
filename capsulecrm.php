<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
Plugin Name: Gravity Forms Capsule CRM Add-On
Plugin URI: https://www.gravityforms.com
Description: Integrates Gravity Forms with Capsule CRM, allowing form submissions to be automatically sent to your Capsule CRM account.
Version: 1.3
Author: rocketgenius
Author URI: https://www.rocketgenius.com
License: GPL-2.0+
Text Domain: gravityformscapsulecrm
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2018 Rocketgenius, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_CAPSULECRM_VERSION', '1.3' );

add_action( 'gform_loaded', array( 'GF_CapsuleCRM_Bootstrap', 'load' ), 5 );

/**
 * Bootstraps the Capsule CRM add-on.
 *
 * @package GF_CapsuleCRM_Bootstrap
 */
class GF_CapsuleCRM_Bootstrap {

	/**
	 * Loads the main class file and registers the add-on.
	 *
	 * @access public
	 *
	 * @uses GFAddOn::register()
	 *
	 * @return void
	 */
	public static function load() {
		require_once( 'class-gf-capsulecrm.php' );
		GFAddOn::register( 'GFCapsuleCRM' );
	}

}

/**
 * Gets an instance of the GFCapsuleCRM class.
 *
 * @return GFCapsuleCRM
 */
function gf_capsulecrm() {
	return GFCapsuleCRM::get_instance();
}

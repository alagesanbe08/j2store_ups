<?php
/**
 * --------------------------------------------------------------------------------
 * Shipping Plugin - UPS
 * --------------------------------------------------------------------------------
 * @package     Joomla 2.5 -  3.x
 * @subpackage  J2 Store
 * @author      Alagesan, J2Store <support@j2store.org>
 * @copyright   Copyright (c) 2016 J2Store . All rights reserved.
 * @license     GNU/GPL license: http://www.gnu.org/licenses/gpl-2.0.html
 * @link        http://j2store.org
 * --------------------------------------------------------------------------------
 *
 * */
// no direct access
defined('_JEXEC') or die('Restricted access');

class plgJ2StoreShipping_UpsInstallerScript {

	function preflight( $type, $parent ) {
		
		
		if(!JComponentHelper::isEnabled('com_j2store')) {
			Jerror::raiseWarning(null, 'J2Store not found. Please install J2Store before installing this plugin');
			return false;
		}
		
		require_once (JPATH_ADMINISTRATOR.'/components/com_j2store/version.php');
		// abort if the current J2Store release is older
		if( version_compare( J2STORE_VERSION, '3.2.12', 'lt' ) ) {
			Jerror::raiseWarning(null, 'You are using an old version of J2Store. Please upgrade to the latest version');
			return false;
		}

	}

}

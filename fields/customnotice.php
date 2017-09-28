<?php
/**
 * --------------------------------------------------------------------------------
 * Shipping Plugin - UPS shipping
 * --------------------------------------------------------------------------------
 * @package     Joomla 2.5 -  3.x
 * @subpackage  J2 Store
 * @author      Alagesan, J2Store <support@j2store.org>
 * @copyright   Copyright (c) 2015 J2Store . All rights reserved.
 * @license     GNU/GPL license: http://www.gnu.org/licenses/gpl-2.0.html
 * @link        http://j2store.org
 * --------------------------------------------------------------------------------
 *
 * */
// No direct access to this file
defined('_JEXEC') or die;
/* class JFormFieldFieldtypes extends JFormField */
class JFormFieldCustomNotice extends JFormField
{
	protected $type = 'customnotice';

	public function getInput() {

		$html = '';
		$html .= '<div class="alert alert-block alert-info"><strong>'.$this->getTitle().'</strong></div>';
		return  $html;
	}

	public function getLabel() {
		return '';
	}

}
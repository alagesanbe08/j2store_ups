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
defined('_JEXEC') or die('Restricted access');

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');


class JFormFieldBoxlist extends JFormFieldList {

	protected $type = 'boxlist';	
	public function getInput() {
		
		
		
		// Including fallback code for HTML5 non supported browsers.
		JHtml::_('jquery.framework');
		JHtml::_('script', 'system/html5fallback.js', false, true);
		$db = JFactory::getDbo();
		$query = $db->getQuery(true);
		$query->select('*')->from('#__extensions')
			->where('type="plugin" AND folder="j2store" AND element="shipping_ups"');
		$db->setQuery($query);
		$some_data = $db->loadObject();
		$params = json_decode($some_data->params);
		
		$data ='<table class="table table-striped table-bordered shipping_boxes">
					<thead>
						<tr>													
							<th>Outer Length</th>
							<th>Outer Width</th>
							<th>Outer Height</th>
							<th>Inner Length</th>
							<th>Inner Width</th>
							<th>Inner Height</th>
							<th>Empty Box Weight</th>
							<th>Box Max Weight</th>
						</tr>
					</thead>
					<tbody >';
		$i = 0;
		if(isset($params->box_list) && !empty($params->box_list)){
			
			foreach ($params->box_list as $key=>$box){
				$data .='<tr id="'.$this->id.'_'.$key.'" >';
				//$checked   = isset($box->boxes_id)&&!empty($box->boxes_id) ? ' checked' : '';
				//$box_id = isset($box->boxes_id) ? $box->boxes_id : '';
				//$data .= '<td><input type="checkbox" onclick="checkboxclick(this)" name="' . $this->name . '['.$key.'][boxes_id]" id="' . $this->id . '_'.$key.'_boxes_id" value="'.$box_id.'"'. $checked .' /></td>';			
				$data .= '<td><input type="text" name="'.$this->name.'['.$key.'][outer_length]" value="'.$box->outer_length.'" id="' . $this->id . '_'.$key.'_outer_length"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][outer_width]" value="'.$box->outer_width.'" id="' . $this->id . '_'.$key.'_outer_width"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][outer_height]" value="'.$box->outer_height.'" id="' . $this->id . '_'.$key.'_outer_height"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][inner_length]" value="'.$box->inner_length.'" id="' . $this->id . '_'.$key.'_inner_length"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][inner_width]" value="'.$box->inner_width.'" id="' . $this->id . '_'.$key.'_inner_width"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][inner_height]" value="'.$box->inner_height.'" id="' . $this->id . '_'.$key.'_inner_height"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][box_weight]" value="'.$box->box_weight.'" id="' . $this->id . '_'.$key.'_box_weight"/></td>';
				$data .= '<td><input type="text" size="5" name="'.$this->name.'['.$key.'][max_weight]" value="'.$box->max_weight.'" id="' . $this->id . '_'.$key.'_max_weight"/></td>';
				$data .= '<td><input type="button" value="Remove" class="btn btn-danger" onclick="removeBox('.$this->id.'_'.$key.')"/></td>';
				$data .="</tr>";
				$i = $i+1;
			}
		}	
		//TODO: change add new	
		$data .= '</tbody>
				<tfoot>
				<tr>
					<td colspan="10"><input type="button" onclick="addnew()" class="btn btn-primary" value="Add New"></td>
				</tr>
				</foot>
				</table>
				<input type="hidden" id="add_new_row_count" value="'.$i.'" />';
		
		return $data;
	}
	
}
?>
<style>
.shipping_boxes input{
width: 60px;
}
</style>
<script type="text/javascript">
if(typeof(j2store) == 'undefined') {
	var j2store = {};
}
if(typeof(j2store.jQuery) == 'undefined') {
	j2store.jQuery = jQuery.noConflict();
}
function removeBox(id){
	(function($) {
		$(id).remove();
	})(j2store.jQuery);	
}
function checkboxclick(chkbox){
	(function($) {
		if($(chkbox).prop("checked") == true){
			
			$(chkbox).attr('checked',true)
			$(chkbox).attr('value','1');
		}else{
			$(chkbox).attr('checked',false)
			$(chkbox).attr('value','0');
		}		
    })(j2store.jQuery);	
}
//<td><input type="checkbox" value="" id="jform_params_box_list_'+key+'_boxes_id" name="jform[params][box_list]['+key+'][boxes_id]" onclick="checkboxclick(this)"></td>\
function addnew(){
	(function($) {
		var row_count = jQuery('#add_new_row_count').val();
		var key = row_count;
		var $tbody = jQuery('.shipping_boxes').find('tbody');
		var size = $tbody.find('tr').size();		
		var k = 0;
		key = key+1;				
		var code = '<tr id="jform_params_box_list_'+key+'" >\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_outer_length" value="" name="jform[params][box_list]['+key+'][outer_length]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_outer_width" value="" name="jform[params][box_list]['+key+'][outer_width]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_outer_height" value="" name="jform[params][box_list]['+key+'][outer_height]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_inner_length" value="" name="jform[params][box_list]['+key+'][inner_length]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_inner_width" value="" name="jform[params][box_list]['+key+'][inner_width]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_inner_height" value="" name="jform[params][box_list]['+key+'][inner_height]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_box_weight" value="" name="jform[params][box_list]['+key+'][box_weight]"></td>\
			<td><input type="text" size="5" id="jform_params_box_list_'+key+'_max_weight" value="" name="jform[params][box_list]['+key+'][max_weight]"></td>\
			<td><input type="button" onclick="removeBox(jform_params_box_list_'+key+')" class="btn btn-danger" value="Remove"></td>\
			</tr>';				
		$tbody.append( code );

		jQuery('#add_new_row_count').val(key++);
		
	 })(j2store.jQuery);
}
</script>
<?php
/**
 * Created by JetBrains PhpStorm.
 * User: powellc
 * Date: 7/6/12
 * Time: 5:18 PM
 * To change this template use File | Settings | File Templates.
 */
class FormSubmitInput extends FormElement {
	public function  __construct($atts = null) {
		parent::__construct($atts);

		// Some defaults
		$this->_attributes['class'] = 'formelement formsubmitinput';
		$this->_validattributes     = array('accesskey', 'dir', 'disabled', 'id', 'lang', 'name', 'size', 'tabindex', 'width', 'height', 'value', 'style');
	}
}
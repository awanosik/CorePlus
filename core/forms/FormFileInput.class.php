<?php
/**
 * Created by JetBrains PhpStorm.
 * User: powellc
 * Date: 7/6/12
 * Time: 5:12 PM
 * To change this template use File | Settings | File Templates.
 */

class FormFileInput extends FormElement {
	private static $_AutoID = 0;

	public function __construct($atts = null) {
		// Some defaults
		$this->_attributes      = array(
			'class'             => 'formelement formfileinput',
			'previewdimensions' => '200x100',
			'browsable'         => false,
			'basedir'           => '',
			'allowlink'         => true,
		);
		$this->_validattributes = array();
		$this->requiresupload   = true;

		parent::__construct($atts);
	}

	public function render() {
		if (!$this->get('id')) {
			// This system requires a valid id.
			++self::$_AutoID;
			$this->set('id', 'formfileinput-' . self::$_AutoID);
		}

		if (!$this->get('basedir')) {
			throw new Exception('FormFileInput cannot be rendered without a basedir attribute!');
		}

		return parent::render();
	}

	/**
	 * Get the respective File object for this element.
	 * Use the Core system to ensure compatibility with CDNs.
	 *
	 * @return File_Backend
	 */
	public function getFile() {
		if ($this->get('value')) {
			$f = Core::File($this->get('basedir') . '/' . $this->get('value'));
		}
		else {
			$f = Core::File();
		}
		return $f;
	}

	public function setValue($value) {
		if ($this->get('required') && !$value) {
			$this->_error = $this->get('label') . ' is required.';
			return false;
		}

		// _link_ allows users to paste in a URL for a given file.  This is then copied locally as normal.
		// In order to detect this, I need to look for the presence of a protocol indicator and this element needs
		// to have allowlink set.
		if($this->get('allowlink') && strpos($value, '_link_://') === 0){
			$n = $this->get('name');
			$value = substr($value, 9);

			// Source
			$f = new File_remote_backend($value);

			if(!$f->exists()){
				$this->_error = 'Remote file does not seem to exist';
				return false;
			}

			// Destination
			$nf = Core::File($this->get('basedir') . '/' . $f->getBaseFilename());

			// do NOT copy the contents over until the accept check has been ran!

			// Now that I have a file object, (in the temp filesystem still), I should validate the filetype
			// to see if the developer wanted a strict "accept" type to be requested.
			// If present, I'll have something to run through and see if the file matches.
			// I need the destination now because I need to full filename if an extension is requested in the accept.
			if($this->get('accept')){
				$acceptcheck = \Core\check_file_mimetype($this->get('accept'), $f->getMimetype(), $nf->getExtension());

				// Now that all the mimetypes have run through, I can see if one matched.
				if($acceptcheck != ''){
					$this->_error = $acceptcheck;
					return false;
				}
			}

			// Now all the checks should be completed and I can safely copy the file away from the temporary filesystem.
			$f->copyTo($nf);

			$value = $nf->getBaseFilename();
		}
		elseif ($value == '_upload_') {
			$n = $this->get('name');

			// Because PHP will have different sources depending if the name has [] in it...
			if (strpos($n, '[') !== false) {
				$p1 = substr($n, 0, strpos($n, '['));
				$p2 = substr($n, strpos($n, '[') + 1, -1);

				if (!isset($_FILES[$p1])) {
					$this->_error = 'No file uploaded for ' . $this->get('label');
					return false;
				}

				$in = array(
					'name'     => $_FILES[$p1]['name'][$p2],
					'type'     => $_FILES[$p1]['type'][$p2],
					'tmp_name' => $_FILES[$p1]['tmp_name'][$p2],
					'error'    => $_FILES[$p1]['error'][$p2],
					'size'     => $_FILES[$p1]['size'][$p2],
				);
			}
			else {
				$in =& $_FILES[$n];
			}


			if (!isset($in)) {
				$this->_error = 'No file uploaded for ' . $this->get('label');
				return false;
			}
			else {
				$error = \Core\translate_upload_error($in['error']);
				if($error != ''){
					$this->_error = $error;
					return false;
				}

				// Source
				$f = new File_local_backend($in['tmp_name']);
				// Destination
				$nf = Core::File($this->get('basedir') . '/' . $in['name']);

				// do NOT copy the contents over until the accept check has been ran!

				// Now that I have a file object, (in the temp filesystem still), I should validate the filetype
				// to see if the developer wanted a strict "accept" type to be requested.
				// If present, I'll have something to run through and see if the file matches.
				// I need the destination now because I need to full filename if an extension is requested in the accept.
				if($this->get('accept')){
					$acceptcheck = \Core\check_file_mimetype($this->get('accept'), $f->getMimetype(), $nf->getExtension());

					// Now that all the mimetypes have run through, I can see if one matched.
					if($acceptcheck != ''){
						$this->_error = $acceptcheck;
						return false;
					}
				}

				// Now all the checks should be completed and I can safely copy the file away from the temporary filesystem.
				$f->copyTo($nf);

				$value = $nf->getBaseFilename();
			}
		}

		$this->_attributes['value'] = $value;
		return true;
	}
}
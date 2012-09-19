<?php
/**
 * Gallery listing page, the main interface for all gallery frontend and most backend functions.
 *
 * @package Gallery
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2012  Charlie Powell
 * @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, version 3.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see http://www.gnu.org/licenses/agpl-3.0.txt.
 */

class GalleryController extends Controller_2_1 {
	/**
	 * Listing page that displays all gallery albums
	 */
	public function index(){
		$view = $this->getView();

		$albums = GalleryAlbumModel::Find(null, null, null);
		$manager = \Core\user()->checkAccess('p:gallery_manage');

		$view->title = 'Gallery Listings';
		$view->assignVariable('albums', $albums);

		if($manager){
			$view->addControl('Add Album', '/gallery/create', 'add');
			$view->addControl('Edit Album Listing Page', '/gallery/updatelisting', 'edit');
		}

	}

	/**
	 * Controller method to allow the page metadata to be edited.
	 *
	 * @return int
	 */
	public function updatelisting(){
		$view = $this->getView();
		$manager = \Core\user()->checkAccess('p:gallery_manage');

		if(!$manager){
			return View::ERROR_ACCESSDENIED;
		}

		$page = new PageModel('/gallery');
		$form = Form::BuildFromModel($page);
		$form->set('callsmethod', 'GalleryFormHandler::SaveListing');
		$form->addElement('pageinsertables', array('name' => 'insertables', 'baseurl' => '/gallery'));
		$form->addElement('submit', array('value' => 'Update Listing'));

		$view->assign('form', $form);
	}

	/**
	 * View a gallery album or an individual image.
	 *
	 * @return int
	 */
	public function view(){
		$req  = $this->getPageRequest();
		$page = $this->getPageModel();
		$view = $this->getView();

		if(!$this->setAccess($page->get('access'))){
			return View::ERROR_ACCESSDENIED;
		}

		$album = new GalleryAlbumModel($req->getParameter(0));

		if(!$album->exists()) return View::ERROR_NOTFOUND;

		$manager = \Core\user()->checkAccess('p:gallery_manage');
		$editor  = (\Core\user()->checkAccess($album->get('editpermissions')) || $manager);
		$uploader = (\Core\user()->checkAccess($album->get('uploadpermissions')) || $editor);

		// image view, (there are two parameters)
		if($req->getParameter(1)){
			$image = new GalleryImageModel($req->getParameter(1));
			// I still need all the other images to know where this image lies in the stack!
			$images = $album->getLink('GalleryImage', 'weight');

			if(!$image->exists()){
				return View::ERROR_NOTFOUND;
			}

			if($image->get('albumid') != $album->get('id')){
				return View::ERROR_NOTFOUND;
			}

			$id = $image->get('id');
			$link = $image->getRewriteURL();
			$exif = $image->getExif();
			var_dump($exif);
			$next = null;
			$prev = null;
			$lastnum = sizeof($images) - 1;
			// Determine the next/prev array.
			foreach($images as $k => $img){
				if($img->get('id') == $id){
					// Found it! is it first or last?
					if($k == 0 && $k == $lastnum){
						// both are blank.... well how 'bout that :/
					}
					elseif($k == 0){
						// It's the first image.
						$next = $images[$k + 1];
					}
					elseif($k == $lastnum){
						// It's the last image.
						$prev = $images[$k - 1];
					}
					else{
						// It's somewhere in between :)
						$next = $images[$k + 1];
						$prev = $images[$k - 1];
					}
				}
			}

			$view->mode = View::MODE_PAGEORAJAX;
			$view->templatename = '/pages/gallery/view-image.tpl';
			$view->assign('image', $image);
			$view->assign('album', $album);
			$view->assign('lightbox_available', Core::IsComponentAvailable('jquery-lightbox'));
			$view->assign('editor', $editor);
			$view->assign('manager', $manager);
			$view->assign('uploader', $uploader);
			$view->assign('prev', $prev);
			$view->assign('next', $next);
			$view->assign('exif', $exif);
			$view->updated = $image->get('updated');
			$view->canonicalurl = Core::ResolveLink($link);
			$view->meta['keywords'] = $image->get('keywords');
			$view->meta['description'] = $image->get('description');
			$view->meta['og:image'] = $image->getFile()->getPreviewURL('200x200');
			$view->addBreadcrumb($album->get('title'), $album->get('baseurl'));
			$view->title = ($image->get('title') ? $image->get('title') : 'Image Details');
			if($editor){
				$view->addControl(
					array(
						'title' => 'Edit Image',
					    'link' => '#',
						'class' => 'update-link',
						'icon' => 'edit',
						'image' => $image->get('id'),
					)
				);
				$view->addControl(
					array(
						'title' => 'Rotate CCW',
						'link' => '#',
						'class' => 'rotate-link',
						'icon' => 'undo',
						'image' => $image->get('id'),
						'rotate' => 'ccw',
					)
				);
				$view->addControl(
					array(
						'title' => 'Rotate CW',
						'link' => '#',
						'class' => 'rotate-link',
						'icon' => 'repeat',
						'image' => $image->get('id'),
						'rotate' => 'cw',
					)
				);
				$view->addControl(
					array(
						'title' => 'Remove Image',
						'link' => 'gallery/images/delete/' . $album->get('id') . '?image=' . $image->get('id'),
						'confirm' => 'Confirm deleting image?',
						'icon' => 'remove',
					)
				);
			}
		}
		// album view, (only one parameter)
		else{
			$url = $album->get('rewriteurl');
			$images = $album->getLink('GalleryImage', 'weight');
			$lastupdated = $album->get('updated');

			if($uploader){
				$uploadform = new Form();
				$uploadform->set('action', Core::ResolveLink('/gallery/images_update/' . $album->get('id')));
				$uploadform->addElement(
					'multifile',
					array(
						'basedir' => 'public/galleryalbum',
						'title' => 'Bulk Upload Images',
						'name' => 'images',
						'accept' => 'image/*'
					)
				);
				$uploadform->addElement('submit', array('value' => 'Bulk Upload'));
			}
			else{
				$uploadform = false;
			}

			// I need to attach a friendly URL for each image.
			// This gets a little tricky since each image doesn't have a unique title necessarily.
			foreach($images as $i){
				// This will be the core part; the ID.
				// This is what actually provides a useful lookup for the image.
				$link = $i->get('id');
				if($i->get('title')) $link .= '-' . \Core\str_to_url($i->get('title'));

				// Prepend the album URL.
				$link = $url . '/' . $link;
				$i->set('link', $link);

				// I would like to know when the last change overall was, not just for the gallery.
				$lastupdated = max($lastupdated, $i->get('updated'));
			}

			$view->templatename = '/pages/gallery/view.tpl';
			$view->assign('album', $album);
			$view->assign('images', $images);
			$view->assign('editor', $editor);
			$view->assign('manager', $manager);
			$view->assign('uploader', $uploader);
			$view->assign('uploadform', $uploadform);
			$view->assign('userid', \Core\user()->get('id'));
			$view->updated = $lastupdated;

			// @todo Implement a move link here.

			// If there are images in this gallery, grab the first one to show as a preview!
			if(count($images)){
				$view->meta['og:image'] = $images[0]->getFile()->getPreviewURL('200x200');
			}

			if($editor)  $view->addControl('Edit Gallery Album', '/gallery/edit/' . $album->get('id'), 'edit');
		}
/*
		if(\Core\user()->checkAccess('g:admin')){
			$view->addControl('Add Page', '/Content/Create', 'add');
			$view->addControl('Edit Page', '/Content/Edit/' . $m->get('id'), 'edit');
			$view->addControl('Delete Page', '/Content/Delete/' . $m->get('id'), 'delete');
			$view->addControl('All Content Pages', '/Content', 'directory');
		}
*/
	}

	/**
	 * Create a new gallery album
	 *
	 * This is an administrative-only function, ie: p:gallery_manage.
	 *
	 * @return int
	 */
	public function create(){
		$view = $this->getView();

		if(!$this->setAccess('p:gallery_manage')){
			return View::ERROR_ACCESSDENIED;
		}

		$m = new GalleryAlbumModel();

		$form = Form::BuildFromModel($m);
		$form->set('callsmethod', 'GalleryFormHandler::SaveAlbum');

		$form->addElement('pagemeta', array('name' => 'page'));

		$form->addElement('pageinsertables', array('name' => 'insertables', 'baseurl' => '/gallery/view/new'));

		// Tack on a submit button
		$form->addElement('submit', array('value' => 'Create'));


		$view->templatename = '/pages/gallery/update.tpl';
		$view->title = 'New Gallery Album';
		$view->assignVariable('model', $m);
		$view->assignVariable('form', $form);

		//$view->addControl('All Content Pages', '/Content', 'directory');
	}

	/**
	 * Edit an existing gallery album
	 *
	 * This should be either an administrative, (p:gallery_manage) or editpermission.
	 *
	 * @return int
	 */
	public function edit(){
		$view = $this->getView();
		$req = $this->getPageRequest();

		$album = new GalleryAlbumModel($req->getParameter(0));

		if(!$album->exists()) return View::ERROR_NOTFOUND;

		$editor  = (\Core\user()->checkAccess($album->get('editpermissions')) || \Core\user()->checkAccess('p:gallery_manage'));
		$manager = \Core\user()->checkAccess('p:gallery_manage');

		if(!($editor || $manager)){
			return View::ERROR_ACCESSDENIED;
		}

		$form = Form::BuildFromModel($album);
		$form->set('callsmethod', 'GalleryFormHandler::SaveAlbum');

		$form->addElement('pagemeta', array('name' => 'page', 'model' => $album->getLink('Page')));

		$form->addElement('pageinsertables', array('name' => 'insertables', 'baseurl' => '/gallery/view/' . $album->get('id')));

		// Tack on a submit button
		$form->addElement('submit', array('value' => 'Update'));

		// Editors have certain permissions here, namely limited.
		if($editor && !$manager){
			$form->removeElement('model[nickname]');
			$form->removeElement('model[editpermissions]');
			$form->removeElement('page[rewriteurl]');
			$form->removeElement('page[parenturl]');
		}

		$view->templatename = '/pages/gallery/update.tpl';
		$view->title = 'Edit Gallery Album';
		$view->assignVariable('model', $album);
		$view->assignVariable('form', $form);

		//$view->addControl('All Content Pages', '/Content', 'directory');
	}


	/**
	 * Handles the upload of new and existing images.  Not meant to be called directly, but is used by the images page.
	 */
	public function images_update(){
		$view    = $this->getView();
		$request = $this->getPageRequest();
		$albumid = $request->getParameter(0);
		$album   = new GalleryAlbumModel($albumid);
		$type    = $album->get('store_type');
		$image   = new GalleryImageModel($request->getParameter('image'));

		$manager = \Core\user()->checkAccess('p:gallery_manage');
		$editor  = (\Core\user()->checkAccess($album->get('editpermissions')) || $manager);
		$uploader  = (\Core\user()->checkAccess($album->get('uploadpermissions')) || $editor);

		if(!$uploader){
			return View::ERROR_ACCESSDENIED;
		}

		// Uploaders only can only edit their own image!
		if(!$editor && $image->get('uploaderid') != \Core\user()->get('id')){
			return View::ERROR_ACCESSDENIED;
		}


		if($request->isPost()){
			// This is meant to be loaded in an iframe and rendered from there.
			$view->mode = View::MODE_NOOUTPUT;

			if(!$albumid){
				echo '<div id="error">No album requested</div>';
				return View::ERROR_BADREQUEST;
			}

			if(!$album->exists()){
				echo '<div id="error">Invalid album requested</div>';
				return View::ERROR_NOTFOUND;
			}

			if($image->exists() && $image->get('albumid') != $album->get('id')){
				echo '<div id="error">Invalid album requested</div>';
				return View::ERROR_BADREQUEST;
			}

			// Multiple images were uploaded, (probably via one of the multi uploaders).
			// In this case, minimal data is available, but enough to save the image.
			if(isset($_POST['images']) && is_array($_POST['images'])){
				$savecount = 0;
				foreach($_POST['images'] as $img){
					// instead of just blindly saving this image...
					$image = GalleryImageModel::Find(array('albumid' => $album->get('id'), 'file' => $img));
					if($image) continue; // NO fun....

					// Generate a moderately meaningful title
					$title = substr($img, 0, strrpos($img, '.'));
					$title = preg_replace('/[^a-zA-Z0-9 ]/', ' ', $title);
					$title = trim(preg_replace('/[ ]+/', ' ', $title));
					$title = ucwords($title);

					$image = new GalleryImageModel();
					$image->setFromArray(
						array(
							'albumid' => $album->get('id'),
							'file' => $img,
							'weight' => sizeof($album->getLink('GalleryImage')) + 1,
							'title' => $title,
						)
					);
					$image->save();
					$savecount++;
				}
				if($savecount == 0){
					$action = 'No images uploaded';
					$actiontype = 'info';
				}
				else{
					$action = 'Added ' . $savecount . ' Images!';
					$actiontype = 'success';
				}
				$imageid = '';
			}
			// Traditional image update, just one image, so it has all the information.
			else{
				// These are the standard updateable fields.
				$image->setFromArray(
					array(
						'title' => $_POST['model']['title'],
						'keywords' => $_POST['model']['keywords'],
						'description' => $_POST['model']['description'],
					)
				);

				// The fields that need to be set on new images
				if(!$image->exists()){
					$image->setFromArray(
						array(
							'albumid' => $album->get('id'),
							'weight' => sizeof($album->getLink('GalleryImage')) + 1,
						)
					);
				}

				// Make sure it uploaded successfully.
				// I'm using the form system because it already has support for file errors builtin.
				// Also, this is only required for new images.  Existing ones can skip this if _upload_ is not chosen.
				if(!$image->exists() || ($image->exists() && $_POST['model']['file'] == '_upload_')){
					$el = new FormFileInput(array('name' => 'model[file]', 'basedir' => $type . '/galleryalbum', 'accept' => 'image/*'));
					$el->setValue('_upload_');
					if($el->hasError()){
						echo '<div id="error">' . $el->getError() . '</div>';
						return;
					}

					$f = $el->getFile();
					$image->set('file', $f->getBaseFilename());
				}

				// I need to know what to say...
				$action = (($image->exists()) ? 'Updated' : 'Added') . ' Image!';
				$actiontype = 'success';
				$imageid = $image->get('id');

				$image->save();
			}

			if($request->isAjax()){
				// This will be rendered with jquery, so it'll be data-esque.
				echo '<div id="success">' . $action . '</div>' . '<div id="imageid">' . $imageid . '</div>';
				Core::SetMessage($action, $actiontype);
				return;
			}
			else{
				Core::SetMessage($action, $actiontype);
				Core::Redirect($album->get('rewriteurl'));
			}
		}
		else{

			$view->mode = View::MODE_AJAX;

			if(!$albumid){
				return View::ERROR_BADREQUEST;
			}

			if(!$album->exists()){
				return View::ERROR_NOTFOUND;
			}

			if($image->exists() && $image->get('albumid') != $album->get('id')){
				return View::ERROR_BADREQUEST;
			}

			// Give me the upload new form.
			$form = Form::BuildFromModel($image);

			$view->assign('image', $image);
			$view->assign('album', $album);
			$view->assign('form', $form);
			$view->assign('savetext', ($image->exists() ? 'Update' : 'Upload'));

		}
	}

	public function images_delete(){
		$view = $this->getView();
		$request = $this->getPageRequest();

		if(!$request->isPost()){
			return View::ERROR_BADREQUEST;
		}

		$albumid = $request->getParameter(0);
		$album   = new GalleryAlbumModel($albumid);
		$image   = new GalleryImageModel($request->getParameter('image'));

		if(!$albumid){
			return View::ERROR_BADREQUEST;
		}

		if(!$album->exists()){
			return View::ERROR_NOTFOUND;
		}

		if(!$image->exists()){
			return View::ERROR_NOTFOUND;
		}

		if($image->get('albumid') != $album->get('id')){
			return View::ERROR_BADREQUEST;
		}

		$image->delete();

		Core::SetMessage('Removed image successfully', 'success');
		Core::Redirect($album->get('rewriteurl'));
	}

	public function images_rotate(){
		$view = $this->getView();
		$request = $this->getPageRequest();

		if(!$request->isJSON()){
			return View::ERROR_BADREQUEST;
		}

		$albumid = $request->getParameter(0);
		$album   = new GalleryAlbumModel($albumid);
		$image   = new GalleryImageModel($request->getParameter('image'));
		$rotate  = $request->getParameter('rotate');
		$view->contenttype = View::CTYPE_JSON;

		if(!$albumid){
			return View::ERROR_BADREQUEST;
		}

		if(!$album->exists()){
			return View::ERROR_NOTFOUND;
		}

		if(!$image->exists()){
			return View::ERROR_NOTFOUND;
		}

		if($image->get('albumid') != $album->get('id')){
			return View::ERROR_BADREQUEST;
		}

		// According to GD:
		//       0
		//  90       270
		//      180

		// The new rotation is based on the previous and the direction.
		$current = $image->get('rotation');
		$new = null;
		if($rotate == 'cw'){
			if($current == 0)      $new = 270;
			elseif($current > 270) $new = 270;
			elseif($current > 180) $new = 180;
			elseif($current > 90)  $new = 90;
			else                   $new = 0;
		}
		else{
			if($current == 0)      $new = 90;
			elseif($current < 90)  $new = 90;
			elseif($current < 180) $new = 180;
			elseif($current < 270) $new = 270;
			else                   $new = 0;
		}

		$image->set('rotation', $new);
		$image->save();

		$view->jsondata = array('status' => '1', 'message' => 'Rotated image');
	}
}

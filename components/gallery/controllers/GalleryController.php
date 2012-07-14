<?php
/**
 * Created by JetBrains PhpStorm.
 * User: powellc
 * Date: 7/9/12
 * Time: 4:46 PM
 * To change this template use File | Settings | File Templates.
 *
 * @todo implement better access permissions for everything, such as...
 *
 * ## global
 * Create Gallery Albums
 * Update Gallery Albums
 * Delete Gallery Albums
 *
 * ## per-album
 * Manage Images
 * Manage Gallery
 *
 */
class GalleryController extends Controller_2_1 {
	/**
	 * Listing page that displays all gallery albums
	 */
	public function index(){
		$view = $this->getView();

		$albums = GalleryAlbumModel::Find(null, null, null);

		$view->title = 'Gallery Listings';
		$view->assignVariable('albums', $albums);

		if(Core::User()->checkAccess('g:admin')){
			$view->addControl('Add Album', '/gallery/create', 'add');
		}

	}

	public function view(){
		$req  = $this->getPageRequest();
		$page = $this->getPageModel();
		$view = $this->getView();

		if(!$this->setAccess($page->get('access'))){
			return View::ERROR_ACCESSDENIED;
		}

		$album = new GalleryAlbumModel($req->getParameter(0));

		if(!$album->exists()) return View::ERROR_NOTFOUND;

		// image view, (there are two parameters)
		if($req->getParameter(1)){
			$image = new GalleryImageModel($req->getParameter(1));

			if(!$image->exists()){
				return View::ERROR_NOTFOUND;
			}

			if($image->get('albumid') != $album->get('id')){
				return View::ERROR_NOTFOUND;
			}

			$link = $image->get('id');
			if($image->get('title')) $link .= '-' . \Core\str_to_url($image->get('title'));

			$view->templatename = '/pages/gallery/view-image.tpl';
			$view->assign('image', $image);
			$view->assign('album', $album);
			$view->assign('lightbox_available', Core::IsComponentAvailable('jquery-lightbox'));
			$view->updated = $image->get('updated');
			$view->canonicalurl = Core::ResolveLink($album->get('rewriteurl') . '/' . $link);
			$view->meta['keywords'] = $image->get('keywords');
			$view->meta['description'] = $image->get('description');
			$view->meta['og:image'] = $image->getFile()->getPreviewURL('200x200');
			$view->addBreadcrumb($album->get('title'), $album->get('rewriteurl'));
			$view->title = ($image->get('title') ? $image->get('title') : 'Image Details');
		}
		// album view, (only one parameter)
		else{
			$url = $album->get('rewriteurl');
			$images = $album->getLink('GalleryImage', 'weight');
			$lastupdated = $album->get('updated');

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
			$view->assign('can_manage_images', Core::User()->checkAccess('g:admin'));
			$view->updated = $lastupdated;

			// If there are images in this gallery, grab the first one to show as a preview!
			if(count($images)){
				$view->meta['og:image'] = $images[0]->getFile()->getPreviewURL('200x200');
			}

			$view->addControl('Edit Gallery Album', '/gallery/edit/' . $album->get('id'), 'edit');
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
	 * @return int
	 */
	public function create(){
		$view = $this->getView();

		// @todo Implement more fine-grain access restrictions
		if(!$this->setAccess('g:admin')){
			return View::ERROR_ACCESSDENIED;
		}

		$m = new GalleryAlbumModel();

		$form = Form::BuildFromModel($m);
		$form->set('callsmethod', 'GalleryController::_SaveHandler');

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
	 * @return int
	 */
	public function edit(){
		$view = $this->getView();
		$req = $this->getPageRequest();

		// @todo Implement more fine-grain access restrictions
		if(!$this->setAccess('g:admin')){
			return View::ERROR_ACCESSDENIED;
		}

		$album = new GalleryAlbumModel($req->getParameter(0));

		if(!$album->exists()) return View::ERROR_NOTFOUND;

		$form = Form::BuildFromModel($album);
		$form->set('callsmethod', 'GalleryController::_SaveHandler');

		$form->addElement('pagemeta', array('name' => 'page', 'model' => $album->getLink('Page')));

		$form->addElement('pageinsertables', array('name' => 'insertables', 'baseurl' => '/gallery/view/' . $album->get('id')));

		// Tack on a submit button
		$form->addElement('submit', array('value' => 'Update'));


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
			$action = ($image->exists()) ? 'Updated' : 'Added';

			$image->save();

			// This will be rendered with jquery, so it'll be data-esque.
			echo '<div id="success">' . $action . ' Image!</div>' .
				'<div id="imageid">' . $image->get('id') . '</div>';
			Core::SetMessage($action . ' image successfully', 'success');
			return;
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






	/// Static methods, ie: form handlers

	public static function _SaveHandler(Form $form){

		$model = $form->getModel();
		// Ensure that everything is marked as updated...
		$model->set('updated', Time::GetCurrent());
		//var_dump($model); die();
		$model->save();

		$page = $form->getElementByName('page')->getModel();
		$page->set('fuzzy', 1);
		$page->set('baseurl', '/gallery/view/' . $model->get('id'));
		$page->set('updated', Time::GetCurrent());
		$page->save();

		$insertables = $form->getElementByName('insertables');
		$insertables->set('baseurl', '/gallery/view/' . $model->get('id'));
		$insertables->save();

		// w00t
		return $page->getResolvedURL();
	}
}

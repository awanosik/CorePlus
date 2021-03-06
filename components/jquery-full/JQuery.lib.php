<?php
/**
 * jQuery library file, just includes the various jquery javascript assets.
 * 
 * @package JQuery
 * @since 0.1
 * @author Charlie Powell <charlie@eval.bz>
 * @copyright Copyright (C) 2009-2012  Charlie Powell
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

abstract class JQuery {
	
	public static function IncludeJQuery(){
		$base = 'jquery-1.8.3';

		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function IncludeJQueryUI(){
		self::IncludeJQuery();
		CurrentPage::AddScript ('js/jquery/jquery-ui-1.8.22.min.js');
		CurrentPage::AddStylesheet('css/jquery-ui-1.8.22.css');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_nestedSortable(){
		$base = 'jquery.ui.nestedSortable';
		// I need jquery ui first.
		self::IncludeJQueryUI();
		
		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_tmpl(){
		$base = 'tmpl';
		// I need jquery ui first.
		self::IncludeJQueryUI();

		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_readonly(){
		$base = 'jquery.ui.readonly';
		// I need jquery ui first.
		self::IncludeJQueryUI();
		
		CurrentPage::AddStylesheet('css/jquery.readonly.css');
		
		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
	
	public static function Include_json(){
		$base = 'jquery.json-2.2';
		
		// I need jquery first.
		self::IncludeJQuery();
		
		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');
		
		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_cookie(){
		$base = 'jquery.cookie';

		// I need jquery first.
		self::IncludeJQuery();

		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_masonry(){
		// I need jquery first.
		self::IncludeJQuery();
		CurrentPage::AddScript('js/jquery/jquery.masonry.min.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_form(){
		// I need jquery first.
		self::IncludeJQuery();
		CurrentPage::AddScript('js/jquery/jquery.form.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_timepicker(){
		// I need jquery ui first.
		self::IncludeJQueryUI();
		CurrentPage::AddScript('js/jquery/jqueryui.timepicker.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}

	public static function Include_waypoints(){
		// I need jquery first.
		self::IncludeJQuery();

		$base = 'waypoints';

		if(ConfigHandler::Get('/core/javascript/minified')) CurrentPage::AddScript ('js/jquery/' . $base . '.min.js');
		else CurrentPage::AddScript ('js/jquery/' . $base . '.js');

		// IMPORTANT!  Tells the script that the include succeeded!
		return true;
	}
}

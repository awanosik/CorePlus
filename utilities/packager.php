#!/usr/bin/env php
<?php
/**
 * The purpose of this file is to archive up the core, components, and bundles.
 * and to set all the appropriate information.
 *
 * @package Core Plus\CLI Utilities
 * @since 1.9
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


if(!isset($_SERVER['SHELL'])){
	die("Please run this script from the command line.");
}

// This is required to establish the root path of the system, (since it's always one directory up from "here"
$path = realpath(
	dirname($_SERVER['PWD'] . '/' . $_SERVER['SCRIPT_FILENAME']) . '/..'
	) . '/';
define('ROOT_PDIR', $path);
define('ROOT_WDIR', '/');

// Include the core bootstrap, this will get the system functional.
require_once(ROOT_PDIR . 'core/bootstrap.php');


/**
 * Just a simple usage tutorial.
 */
function print_help(){
	echo "This utility will compile the component.xml metafile for any component," . NL;
	echo "and optionally allow you to create a deployable package of the component.";
	echo NL . NL;
	echo "Usage: simply run it without any arguments and follow the prompts." . NL;
}


// Allow for inline arguments.
if($argc > 1){
	$arguments = $argv;
	// Drop the first, that is the filename.
	array_shift($arguments);

	// I'm using a for here instead of a foreach so I can increment $i artifically if an argument is two part,
	// ie: --option value_for_option --option2 value_for_option2
	for($i = 0; $i < sizeof($arguments); $i++){
		switch($arguments[$i]){
			case '-h':
			case '--help':
			case '-?':
				print_help();
				exit;
				break;
			default:
				echo "ERROR: unknown argument [" . $arguments[$i] . "]" . NL;
				print_help();
				exit;
		}
	}
}



// I need a valid editor.
CLI::RequireEditor();

// Some cache variables.
$_cversions = null;


/**
 * Create Unique Arrays using an md5 hash
 *
 * @param array $array
 * @return array
 */
function arrayUnique($array, $preserveKeys = false){
    // Unique Array for return
    $arrayRewrite = array();
    // Array with the md5 hashes
    $arrayHashes = array();
    foreach($array as $key => $item) {
        // Serialize the current element and create a md5 hash
        $hash = md5(serialize($item));
        // If the md5 didn't come up yet, add the element to
        // to arrayRewrite, otherwise drop it
        if (!isset($arrayHashes[$hash])) {
            // Save the current element hash
            $arrayHashes[$hash] = $hash;
            // Add element to the unique Array
            if ($preserveKeys) {
                $arrayRewrite[$key] = $item;
            } else {
                $arrayRewrite[] = $item;
            }
        }
    }
    return $arrayRewrite;
}


/**
 * Simple function to get any license from a file context.
 *
 * @todo This may move to the CLI system if found useful enough...
 * @param string $file
 * @return array
 */
function get_file_licenses($file){
	$ret = array();

	$fh = fopen($file, 'r');
	// ** sigh... counldn't open the file... oh well, skip to the next.
	if(!$fh) return $ret;
	// This will make filetype be the extension of the file... useful for expanding to JS, HTML and CSS files.
	$filetype = strtolower(substr($file, strrpos($file, '.') + 1));

	$counter = 0;
	$inphpdoc = false;

	while(!feof($fh)){
		$counter++;
		$line = trim(fgets($fh, 1024));
		switch($filetype){
			case 'php':
				// Skip line 1... should be <?php
				if($counter == 1) continue;
				// start of a phpDoc comment.
				if($line == '/**'){
					$inphpdoc = true;
					continue;
				}
				// end of a phpDoc comment.  This indicates the end of the reading of the file...
				// Valid license tags must be in the FIRST phpDoc of the page, immediately after the <?php.
				if($file == '*/'){
					break(2);
				}
				// At line 5 and no phpDoc yet?!?  wtf?
				if($counter == 5 && !$inphpdoc){
					break(2);
				}
				// Recognize PHPDoc syntax... basically just [space]*[space]@license...
				if($inphpdoc && stripos($line, '@license') !== false){
					$lic = preg_replace('/\*[ ]*@license[ ]*/i', '', $line);
					if(substr_count($lic, ' ') == 0 && strpos($lic, '://') !== false){
						// lic is similar to @license http://www.gnu.org/licenses/agpl-3.0.txt
						// Take the entire string as both URL and title.
						$ret[] = array('title' => $lic, 'url' => $lic);
					}
					elseif(strpos($lic, '://') === false){
						// lic is similar to @license GNU Affero General Public License v3
						// There's no url at all... just take the entire string as a title, blank URL.
						$ret[] = array('title' => $lic, 'url' => null);
					}
					elseif(strpos($lic, '<') !== false && strpos($lic, '>') !== false){
						// lic is similar to @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
						// String has both.
						$title = preg_replace('/[ ]*<[^>]*>/', '', $lic);
						$url = preg_replace('/.*<([^>]*)>.*/', '$1', $lic);
						$ret[] = array('title' => $title, 'url' => $url);
					}
				}
				break; // type: 'php'
		}
	}
	fclose($fh);
	return $ret;
}


/**
 * Slightly more advanced function to parse for specific information from file headers.
 *
 * @todo Support additional filetypes other than just PHP.
 *
 * Will return an array containing any author, license
 *
 * @todo This may move to the CLI system if found useful enough...
 * @param string $file
 * @return array
 */
function parse_for_documentation($file){
	$ret = array(
		'authors' => array(),
		'licenses' => array()
	);

	$fh = fopen($file, 'r');
	// ** sigh... counldn't open the file... oh well, skip to the next.
	if(!$fh) return $ret;

	// This will make filetype be the extension of the file... useful for expanding to JS, HTML and CSS files.
	if(strpos(basename($file), '.') !== false){
		$filetype = strtolower(substr($file, strrpos($file, '.') + 1));
	}
	else{
		$filetype = null;
	}


	// This is the counter for non valid doc lines.
	$counter = 0;
	$inphpdoc = false;
	$incomment = false;

	while(!feof($fh) && $counter <= 10){
		// I want to limit the number of lines read so this doesn't continue on reading the entire file.

		// Remove any extra whitespace.
		$line = trim(fgets($fh, 1024));
		switch($filetype){
			case 'php':
				// This only support multi-line phpdocs.
				// start of a phpDoc comment.
				if($line == '/**'){
					$inphpdoc = true;
					break;
				}
				// end of a phpDoc comment.  This indicates the end of the reading of the file...
				if($line == '*/'){
					$inphpdoc = false;
					break;
				}
				// Not in phpdoc... ok
				if(!$inphpdoc){
					$counter++;
					break;
				}

				// Recognize PHPDoc syntax... basically just [space]*[space]@license...
				if($inphpdoc){
					// Is this an @license line?
					if(stripos($line, '@license') !== false){
						$lic = preg_replace('/\*[ ]*@license[ ]*/i', '', $line);
						if(substr_count($lic, ' ') == 0 && strpos($lic, '://') !== false){
							// lic is similar to @license http://www.gnu.org/licenses/agpl-3.0.txt
							// Take the entire string as both URL and title.
							$ret['licenses'][] = array('title' => $lic, 'url' => $lic);
						}
						elseif(strpos($lic, '://') === false){
							// lic is similar to @license GNU Affero General Public License v3
							// There's no url at all... just take the entire string as a title, blank URL.
							$ret['licenses'][] = array('title' => $lic, 'url' => null);
						}
						elseif(strpos($lic, '<') !== false && strpos($lic, '>') !== false){
							// lic is similar to @license GNU Affero General Public License v3 <http://www.gnu.org/licenses/agpl-3.0.txt>
							// String has both.
							$title = preg_replace('/[ ]*<[^>]*>/', '', $lic);
							$url = preg_replace('/.*<([^>]*)>.*/', '$1', $lic);
							$ret['licenses'][] = array('title' => $title, 'url' => $url);
						}
					}
					// Is this an @author line?
					if(stripos($line, '@author') !== false){
						$aut = preg_replace('/\*[ ]*@author[ ]*/i', '', $line);
						$autdata = array();
						if(strpos($aut, '<') !== false && strpos($aut, '>') !== false && preg_match('/<[^>]*(@| at )[^>]*>/i', $aut)){
							// Resembles: @author user foo <email@domain.com>
							// or         @author user foo <email at domain dot com>
							preg_match('/(.*) <([^>]*)>/', $aut, $matches);
							$autdata = array('name' => $matches[1], 'email' => $matches[2]);
						}
						elseif(strpos($aut, '(') !== false && strpos($aut, ')') !== false && preg_match('/\([^\)]*(@| at )[^\)]*\)/i', $aut)){
							// Resembles: @author user foo (email@domain.com)
							// of         @author user foo (email at domain dot com)
							preg_match('/(.*) \(([^\)]*)\)/', $aut, $matches);
							$autdata = array('name' => $matches[1], 'email' => $matches[2]);
						}
						else{
							// Eh, must be something else...
							$autdata = array('name' => $aut, 'email' => null);
						}

						// Sometimes the @author line may consist of:
						// @author credit to someone <someone@somewhere.com>
						$autdata['name'] = preg_replace('/^credit[s]* to/i', '', $autdata['name']);
						$autdata['name'] = preg_replace('/^contribution[s]* from/i', '', $autdata['name']);
						$autdata['name'] = trim($autdata['name']);
						$ret['authors'][] = $autdata;
					}
				}
				break; // type: 'php'
			case 'js':
				// This only support multi-line phpdocs.
				// start of a multiline comment.
				if($line == '/*' || $line == '/*!' || $line == '/**'){
					$incomment = true;
					break;
				}
				// end of a phpDoc comment.  This indicates the end of the reading of the file...
				if($line == '*/'){
					$incomment = false;
					break;
				}
				// Not in phpdoc... ok
				if(!$incomment){
					$counter++;
					break;
				}

				// Recognize "* Author: Person Blah" syntax... basically just [space]*[space]license...
				if($incomment){
					// Is this line Author: ?
					if(stripos($line, 'author:') !== false){
						$aut = preg_replace('/\*[ ]*author:[ ]*/i', '', $line);
						$autdata = array();
						if(strpos($aut, '<') !== false && strpos($aut, '>') !== false && preg_match('/<[^>]*(@| at )[^>]*>/i', $aut)){
							// Resembles: @author user foo <email@domain.com>
							// or         @author user foo <email at domain dot com>
							preg_match('/(.*) <([^>]*)>/', $aut, $matches);
							$autdata = array('name' => $matches[1], 'email' => $matches[2]);
						}
						elseif(strpos($aut, '(') !== false && strpos($aut, ')') !== false && preg_match('/\([^\)]*(@| at )[^\)]*\)/i', $aut)){
							// Resembles: @author user foo (email@domain.com)
							// of         @author user foo (email at domain dot com)
							preg_match('/(.*) \(([^\)]*)\)/', $aut, $matches);
							$autdata = array('name' => $matches[1], 'email' => $matches[2]);
						}
						else{
							// Eh, must be something else...
							$autdata = array('name' => $aut, 'email' => null);
						}

						// Sometimes the @author line may consist of:
						// @author credit to someone <someone@somewhere.com>
						$autdata['name'] = preg_replace('/^credit[s]* to/i', '', $autdata['name']);
						$autdata['name'] = preg_replace('/^contribution[s]* from/i', '', $autdata['name']);
						$autdata['name'] = trim($autdata['name']);
						$ret['authors'][] = $autdata;
					}
				}
				break; // type: 'js'
			default:
				break(2);
		}
	}
	fclose($fh);

	// I don't want 5 million duplicates... so remove all the duplicate results.
	// I need to use arrayUnique because the arrays are multi-dimensional.
	$ret['licenses'] = arrayUnique($ret['licenses']);
	$ret['authors'] = arrayUnique($ret['authors']);
	return $ret;
}

/**
 * Simple function to intelligently "up" the version number.
 * Supports Ubuntu-style versioning for non-original maintainers (~extraversionnum)
 *
 * Will try to utilize the versioning names, ie: dev to alpha to beta, etc.
 *
 * @param string $version
 * @param boolean $original
 * @return string
 */
function _increment_version($version, $original){
	if($original){

		// It's an official package, increment the regular number and drop anything after the ~...
		if(strpos($version, '~') !== false){
			$version = substr($version, 0, strpos($version, '~'));
		}

		// if there's a -dev, -b, -rc[0-9], -beta, -a, -alpha, etc... just step up to the next one.
		// dev < alpha = a < beta = b < RC = rc < # <  pl = p
		if(preg_match('/\-(dev|a|alpha|b|beta|rc[0-9]|p|pl)$/i', $version, $match)){
			// Step the development stage up instead of the version number.
			$basev = substr($version, 0, -1-strlen($match[1]));
			switch(strtolower($match[1])){
				case 'dev':
					return $basev . '-alpha';
				case 'a':
				case 'alpha':
					return $basev . '-beta';
				case 'b':
				case 'beta':
					return $basev . '-rc1';
				case 'p':
				case 'pl':
					return $basev;
			}
			// still here, might be 'rc#'.
			if(preg_match('/rc([0-9]*)/i', $match[1], $rcnum)){
				return $basev . '-rc' . ($rcnum[1] + 1);
			}

			// still no?  I give up...
			$version = $basev;
		}

		// Increment the version number by 0.0.1.
		@list($vmaj, $vmin, $vrev) = explode('.', $version);
		// They need to at least be 0....
		if(is_null($vmaj)) $vmaj = 1;
		if(is_null($vmin)) $vmin = 0;
		if(is_null($vrev)) $vrev = 0;

		$vrev++;
		$version = "$vmaj.$vmin.$vrev";
	}
	else{
		// This is a release, but not the original packager.
		// Therefore, all versions should be after the ~ to signify this.
		if(strpos($version, '~') === false){
			$version .= '~1';
		}
		else{
			preg_match('/([^~]*)~([^0-9]*)([0-9]*)/', $version, $matches);
			$version = $matches[1];
			$vname = $matches[2];
			$vnum = $matches[3];
			$vnum++;
			$version .= '~' . $vname . $vnum;
		}
	}

	return $version;
}

/**
 * Try to intelligently merge duplicate authors, matching a variety of input names.
 *
 * @param array <<string>> $authors
 * @return array
 */
function get_unique_authors($authors){
	// This clusterfuck of a section will basicaly match up the name to its email,
	// use the email as a unique key for name grouping,
	// then try to figure out the canonical name of the author.
	$ea = array();
	foreach($authors as $a){
		// Remove any whitespace.
		$a['email'] = trim($a['email']);
		$a['name'] = trim($a['name']);

		// Group the names under the emails attached.
		if(!isset($ea[$a['email']])) $ea[$a['email']] = array($a['name']);
		else $ea[$a['email']][] = $a['name'];
	}
	// I now have a cross reference list of emails to names.

	// Handle the unset emails first.
	if(isset($ea[''])){
		array_unique($ea['']);
		foreach($ea[''] as $nk => $n){
			// Look up this name in the list of names that have emails to them.
			foreach($ea as $k => $v){
				if($k == '') continue;
				if(in_array($n, $v)){
					// This name is also under an email address... opt to use the email address one instead.
					unset($ea[''][$nk]);
					continue 2;
				}
			}
		}

		// If there are no more unset names, no need to keep this array laying about.
		if(!sizeof($ea[''])) unset($ea['']);
	}


	$authors = array();
	// Now handle every email.
	foreach($ea as $e => $na){
		$na = array_unique($na);
		if($e == ''){
			foreach($na as $name) $authors[] = array('name' => $name);
			continue;
		}


		// Match differences such as Tomas V.V.Cox and Tomas V. V. Cox
		$simsearch = array();
		foreach($na as $k => $name){
			$key = preg_replace('/[^a-z]/i', '', $name);
			if(in_array($key, $simsearch)) unset($na[$k]);
			else $simsearch[] = $key;
		}


		// There may be a pattern in the names, ie: Charlie Powell == cpowell == powellc == charlie.powell
		$aliases = array();
		// Try to get the first and last name.
		$ln = $fn = $funame = '';
		foreach($na as $name){
			if(preg_match('/([a-z]*)[ ]+([a-z]*)/i', $name, $matches)){
				$funame = $matches[1] . ' ' . $matches[2];
				$fn = strtolower($matches[1]);
				$ln = strtolower($matches[2]);
				break;
			}
		}
		if($ln && $fn){
			foreach($na as $name){
				switch(strtolower($name)){
					case $fn . ' ' . $ln:
					case $ln . $fn{0}:
					case $fn . $ln{0}:
					case $fn . '.' . $ln:
						// It matches the pattern, it'll just use the fullname.
						continue 2;
						break;
					default:
						$authors[] = array('email' => $e, 'name' => $name);
				}
			}
			$authors[] = array('email' => $e, 'name' => $funame);
		}
		else{
			foreach($na as $name){
				$authors[] = array('email' => $e, 'name' => $name);
			}
		}
	}

	return $authors;
}

function get_unique_licenses($licenses){
	// This behaves much similar to the unique_authors system above, but much simplier.
	$lics = array();
	foreach($licenses as $k => $v){
		$v['title'] = trim($v['title']);
		$v['url'] = trim($v['url']);

		if(!isset($lics[$v['title']])){
			$lics[$v['title']] = array($v['url']);
		}
		elseif(!in_array($v['url'], $lics[$v['title']])){
			$lics[$v['title']][] = $v['url'];
		}
	}
	// $lics should be unique-ified now.
	$licenses = array();
	foreach($lics as $l => $urls){
		foreach($urls as $url) $licenses[] = array('title' => $l, 'url' => $url);
	}

	return $licenses;
}



function process_component($component, $forcerelease = false){
	global $packagername, $packageremail;

	// Get that component, should be available via the component handler.
	$cfile = ComponentFactory::ResolveNameToFile($component);
	if(!$cfile){
		throw new Exception('Unable to locate component.xml file for component ' . $component);
	}

	// Resolve it to the full path
	$fullcfile = ROOT_PDIR . $cfile;

	// Get the XMLLoader object for this file.  This will allow me to have more fine-tune control over the file.
	$xml = new XMLLoader();
	$xml->setRootName('component');
	if(!$xml->loadFromFile($fullcfile)){
		throw new Exception('Unable to load XML file ' . $cfile);
	}

	// Get the current version, this will be used to autocomplete for the next version.
	$version = $xml->getRootDOM()->getAttribute("version");

	// Not a 2.1 component version?... well it needs to be!
	// This actually needs to work with a variety of versions.
	$componentapiversion = $xml->getDOM()->doctype->systemId;
	switch($componentapiversion){
		case 'http://corepl.us/api/2_1/component.dtd':
		case 'http://corepl.us/api/2_4/component.dtd':
			// Now I can load the component itself, now that I know that the metafile is a 2.1 compatible version.
			$comp = new Component_2_1($fullcfile);
			$comp->load();
			break;
		default:
			throw new Exception('Unsupported component version, please ensure that your doctype systemid is correct.');
	}


	// Get the licenses currently set.  (maybe there's one that's not in the code)
	$licenses = array();
	if(CLI::PromptUser('Retrieve current list of licenses and merge in from code?', 'boolean', true)){
		foreach($xml->getElements('//component/licenses/license') as $el){
			$url = @$el->getAttribute('url');
			$licenses[] = array(
				'title' => $el->nodeValue,
				'url' => $url
			);
		}
	}


	// Get the authors currently set. (maybe there's one that's not in the code)
	$authors = array();
	if(CLI::PromptUser('Retrieve current list of authors and merge in from code?', 'boolean', true)){
		foreach($xml->getElements('//component/authors/author') as $el){
			$authors[] = array(
				'name' => $el->getAttribute('name'),
				'email' => @$el->getAttribute('email'),
			);
		}
	}

	$it = new CAEDirectoryIterator();
	// Ignore the component metaxml, this will get added automatically via the installer.
	$it->addIgnore($cfile);

	// The core has a "few" extra ignores to it...
	if($component == 'core'){
		$it->addIgnores('components/', 'config/configuration.xml', 'dropins/', 'exports/', 'nbproject/', 'themes/', 'utilities/', '.htaccess', 'gnupg');
		if(ConfigHandler::Get('/core/filestore/assetdir')) $it->addIgnore(ConfigHandler::Get('/core/filestore/assetdir'));
		if(ConfigHandler::Get('/core/filestore/publicdir')) $it->addIgnore(ConfigHandler::Get('/core/filestore/publicdir'));
		if(strpos(TMP_DIR_WEB, ROOT_PDIR) === 0) $it->addIgnore(TMP_DIR_WEB);
		if(strpos(TMP_DIR_CLI, ROOT_PDIR) === 0) $it->addIgnore(TMP_DIR_CLI);
	}

	// @todo Should I support ignored files in the component.xml file?
	// advantage, developers can have tools in their directories that are not meant to be packaged.
	// disadvantage, currently no component other than core requires this.....
	/*$list = $this->getElements('/ignorefiles/file');
	foreach($list as $el){
		$it->addIgnores($this->getBaseDir() . $el->getAttribute('filename'));
	}*/

	if($component == 'core'){
		$it->setPath(ROOT_PDIR);
	}
	else{
		$it->setPath(dirname($cfile));
	}

	echo "Loading root path...";
	$it->scan();
	echo "OK" . NL;

	$viewdir    = $comp->getViewSearchDir();
	$assetdir   = $comp->getAssetDir();
	$basestrlen = strlen($comp->getBaseDir());
	$assetfiles = array();
	$viewfiles  = array();
	$otherfiles = array();


	echo "Scanning files for documentation and metacode...";
	foreach($it as $file){
		// This will get an array of all licenses and authors in the file's phpdoc.
		$docelements = parse_for_documentation($file->getFilename());
		$licenses = array_merge($licenses, $docelements['licenses']);
		$authors = array_merge($authors, $docelements['authors']);

		// And then, scan this file for code, ie: classes, controllers, etc.
		$fname = substr($file->getFilename(), $basestrlen);

		if($viewdir && $file->inDirectory($viewdir)){
			// It's a template! (view)
			$viewfiles[] = array('file' => $fname, 'md5' => $file->getHash());
		}
		elseif($assetdir && $file->inDirectory($assetdir)){
			// It's an asset!
			$assetfiles[] = array('file' => $fname, 'md5' => $file->getHash());
		}
		else{
			// It's a something..... it goes in the "files" array!
			// This will be slightly different though, as it needs to also check for classes.
			$filedat = array(
				'file' => $fname,
				'md5' => $file->getHash(),
				'controllers' => array(),
				'classes' => array(),
				'interfaces' => array()
			);

			// PHP files get checked.
			if(preg_match('/\.php$/i', $fname)){
				$fconts = file_get_contents($file->getFilename());

				// Trim out the comments to prevent false readings.

				// Will remove /* ... */ multi-line comments.
				$fconts = preg_replace(':/\*.*\*/:Us', '', $fconts);
				// Will remove // single-line comments.
				$fconts = preg_replace('://.*$:', '', $fconts);


				// Well... get the classes!
				preg_match_all('/^\s*(abstract |final ){0,1}class[ ]*([a-z0-9_\-]*)[ ]*extends[ ]*controller_2_1/im', $fconts, $ret);
				foreach($ret[2] as $foundclass){
					$filedat['controllers'][] = $foundclass;
				}

				// Add any class found in this file. (skipping the ones I already found)
				preg_match_all('/^\s*(abstract |final ){0,1}class[ ]*([a-z0-9_\-]*)/im', $fconts, $ret);
				foreach($ret[2] as $foundclass){
					if(in_array($foundclass, $filedat['controllers'])) continue;
					$filedat['classes'][] = $foundclass;
				}

				// Allow interfaces to be associated as a provided element too.
				preg_match_all('/^\s*(interface)[ ]*([a-z0-9_\-]*)/im', $fconts, $ret);
				foreach($ret[2] as $foundclass){
					$filedat['interfaces'][] = $foundclass;
				}
			}


			// Empty classes?
			if(!sizeof($filedat['controllers'])) unset($filedat['controllers']);
			if(!sizeof($filedat['classes'])) unset($filedat['classes']);
			if(!sizeof($filedat['interfaces'])) unset($filedat['interfaces']);

			$otherfiles[] = $filedat;
		}
		echo ".";
	}
	echo "OK" . NL;

	// Remove dupes
	$authors = get_unique_authors($authors);
	$licenses = get_unique_licenses($licenses);

	$comp->setAuthors($authors);
	$comp->setLicenses($licenses);
	$comp->setFiles($otherfiles);
	$comp->setViewFiles($viewfiles);
	$comp->setAssetFiles($assetfiles);


	$ans = false;
	while($ans != 'save'){
		$opts = array(
			'editvers' => 'Edit Version Number',
			'editdesc' => 'Edit Description',
			'editchange' => 'Edit Changelog (for version ' . $version . ')',
			//'dbtables' => 'Manage DB Tables',
			'printdebug' => 'DEBUG - Print the XML',
			'save' => 'Finish Editing, Save it!',
			'exit' => 'Abort and exit without saving changes',
		);
		$ans = CLI::PromptUser('What do you want to edit for component ' . $component . ' ' . $version, $opts);

		switch($ans){
			case 'editvers':
				// Try to determine if it's an official package based on the author email.
				$original = false;
				foreach($authors as $aut){
					if(isset($aut['email']) && $aut['email'] == $packageremail) $original = true;
				}

				// Try to explode the version by a ~ sign, this signifies not the original packager/source.
				// ie: ForeignComponent 3.2.4 may be versioned 3.2.4.thisproject5
				// if it's the 5th revision of the upstream version 3.2.4 for 'thisproject'.
				$version = _increment_version($version, $original);

				$version = CLI::PromptUser('Please set the new version or', 'text', $version);
				$comp->setVersion($version);
				break;
			case 'editdesc':
				$comp->setDescription(CLI::PromptUser('Enter a description.', 'textarea', $comp->getDescription()));
				break;
			case 'editchange':
				// Lookup the changelog text of this current version.
				$file = $comp->getBaseDir();

				if($comp->getName() == 'core'){
					// Core's changelog is located in the core directory.
					$file .= 'core/CHANGELOG';
					$name = 'Core Plus';
				}
				else{
					// Nope, no extension.
					$file .= 'CHANGELOG';
					$name = $comp->getName();
				}

				manage_changelog($file, $name, $version);
				break;
			//case 'dbtables':
			//	$comp->setDBSchemaTableNames(explode("\n", CLI::PromptUser('Enter the tables that are included in this component', 'textarea', implode("\n", $comp->getDBSchemaTableNames()))));
			//	break;
			case 'printdebug':
				echo $comp->getRawXML() . NL;
				break;
			case 'exit':
				echo "Aborting build" . NL;
				exit;
				break;
		}
	}

	// User must have selected 'save'...
	$comp->save();

	// Reload the XML file, since it probably changed.
	$xml->setRootName('component');
	if(!$xml->loadFromFile(ROOT_PDIR . $cfile)){
		//@todo The XML file didn't load.... would this be a good time to revert a saved state?
		throw new Exception('Unable to load XML file ' . $cfile);
	}
	echo "Saved!" . NL;

	if($forcerelease){
		// if force release, don't give the user an option... just do it.
		$bundleyn = true;
	}
	else{
		$bundleyn = CLI::PromptUser('Package saved, do you want to bundle the changes into a package?', 'boolean');
	}
	if($bundleyn){


		// Update the changelog version first!
		// This is done here to signify that the version has actually been bundled up.
		// Lookup the changelog text of this current version.
		$file = $comp->getBaseDir();

		if($comp->getName() == 'core'){
			// Core's changelog is located in the core directory.
			$file .= 'core/CHANGELOG';
			$headerprefix = 'Core Plus';
			$header = 'Core Plus ' . $version . "\n";
		}
		else{
			// Nope, no extension.
			$file .= 'CHANGELOG';
			$headerprefix = $comp->getName();
			// The header line will be exactly [name] [version].
			$header = $comp->getName() . ' ' . $version . "\n";
		}

		add_release_date_to_changelog($file, $headerprefix, $header);

		// Create a temp directory to contain all these
		$dir = TMP_DIR . 'packager-' . $component . '/';

		// The destination depends on the type.
		switch($component){
			case 'core':
				$tgz = ROOT_PDIR . 'exports/core/' . $component . '-' . $version . '.tgz';
				break;
			default:
				$tgz = ROOT_PDIR . 'exports/components/' . $component . '-' . $version . '.tgz';
				break;
		}

		File_local_backend::_Mkdir(dirname($tgz));
		File_local_backend::_Mkdir($dir);
		File_local_backend::_Mkdir($dir . 'data/');

		// I already have a good iterator...just reuse it.
		$it->rewind();

		foreach($it as $file){
			$fname = substr($file->getFilename(), $basestrlen);
			$file->copyTo($dir . 'data/' . $fname);
		}

		// The core will have some additional files required to be created.
		if($component == 'core'){
			$denytext = <<<EOD
# This is specifically created to prevent access to ANYTHING in this directory.
#  Under no situation should anything in this directory be readable
#  by anyone at any time.

<Files *>
	Order deny,allow
	Deny from All
</Files>
EOD;
			$corecreates = array(
				'components', 'gnupg', 'themes'
			);
			$coresecures = array(
				'components', 'config', 'gnupg', 'core'
			);
			foreach($corecreates as $createdir){
				mkdir($dir . 'data/' . $createdir);
			}
			foreach($coresecures as $securedir){
				file_put_contents($dir . 'data/' . $securedir . '/.htaccess', $denytext);
			}
		}

		// Because the destination is relative...
		$xmldest = 'data/' . substr(ROOT_PDIR . $cfile, $basestrlen);
		$xmloutput = new File_local_backend($dir . $xmldest);
		$xmloutput->putContents($xml->asMinifiedXML());

		//$packager = 'Core Plus ' . ComponentHandler::GetComponent('core')->getVersion() . ' (http://corepl.us)';
		//$packagename = $c->getName();

		// Different component types require a different bundle type.
		//$bundletype = ($component == 'core')? 'core' : 'component';

		// Save the package.xml file.
		$comp->savePackageXML(true, $dir . 'package.xml');

		exec('tar -czf ' . $tgz . ' -C ' . $dir . ' --exclude-vcs --exclude=*~ --exclude=._* .');
		$bundle = $tgz;

		if(CLI::PromptUser('Package created, do you want to sign it?', 'boolean', true)){
			exec('gpg --homedir "' . GPG_HOMEDIR . '" --no-permission-warning -u "' . $packageremail . '" -a --sign "' . $tgz . '"');
			$bundle .= '.asc';
		}

		// And remove the tmp directory.
		exec('rm -fr "' . $dir . '"');

		echo "Created package of " . $component . ' ' . $version . NL . " as " . $bundle . NL;
	}
} // function process_component($component)


function process_theme($theme, $forcerelease = false){

	// Since "Themes" are not enabled for CLI by default, I need to manually include that file.
	require_once(ROOT_PDIR . 'components/theme/libs/Theme.class.php');

	global $packagername, $packageremail;

	$t = new Theme($theme);
	$t->load();

	$version = $t->getVersion();

	echo "Loading root path...";
	$it = new CAEDirectoryIterator();
	$it->setPath($t->getBaseDir());
	$it->addIgnore($t->getBaseDir() . 'theme.xml');
	$it->scan();
	echo "OK" . NL;

	$assetdir   = $t->getAssetDir();
	$skindir    = $t->getSkinDir();
	$viewdir    = $t->getViewSearchDir();
	$basestrlen = strlen($t->getBaseDir());
	$assetfiles = array();
	$skinfiles  = array();
	$viewfiles  = array();
	$name       = $t->getName();

	echo "Scanning files for metacode...";
	foreach($it as $file){
		// And then, scan this file for code, ie: classes, controllers, etc.
		$fname = substr($file->getFilename(), $basestrlen);

		if($assetdir && $file->inDirectory($assetdir)){
			// It's an asset!
			$assetfiles[] = array('file' => $fname, 'md5' => $file->getHash());
		}
		if($skindir && $file->inDirectory($skindir)){
			// It's a skin!
			$skinfiles[] = array('file' => $fname, 'md5' => $file->getHash());
		}
		elseif($viewdir && $file->inDirectory($viewdir)){
			// It's a template! (view)
			$viewfiles[] = array('file' => $fname, 'md5' => $file->getHash());
		}
		else{
			// It's a something..... I don't care.
			//$otherfiles[] = array('file' => $fname, 'md5' => $file->getHash());
		}
		echo ".";
	}
	echo "OK" . NL;

	$t->setAssetFiles($assetfiles);
	$t->setSkinFiles($skinfiles);
	$t->setViewFiles($viewfiles);

	$ans = false;

	while($ans != 'save'){
		$opts = array(
			'editvers'   => 'Edit Version',
			'editdesc'   => 'Edit Description',
			'editchange' => 'Edit Changelog (for version ' . $version . ')',
			'printdebug' => 'DEBUG - Print the XML',
			'save' => 'Finish Editing, Save it!',
			'exit' => 'Abort and exit without saving changes',
		);
		$ans = CLI::PromptUser('What do you want to edit for theme ' . $name . ' ' . $version, $opts);

		switch($ans){
			case 'editvers':
				$version = _increment_version($t->getVersion(), true);
				$version = CLI::PromptUser('Please set the version of the new release', 'text', $version);
				$t->setVersion($version);
				break;
			case 'editdesc':
				$t->setDescription(CLI::PromptUser('Enter a description.', 'textarea', $t->getDescription()));
				break;
			case 'editchange':
				// Lookup the changelog text of this current version.
				$file = $t->getBaseDir() . 'CHANGELOG';

				manage_changelog($file, 'Theme/' . $name, $version);
				break;
			case 'printdebug':
				echo $t->getRawXML() . NL;
				break;
		}
	}



	// User must have selected 'finish'...
	$t->save();
	echo "Saved!" . NL;

	if($forcerelease){
		// if force release, don't give the user an option... just do it.
		$bundleyn = true;
	}
	else{
		$bundleyn = CLI::PromptUser('Theme saved, do you want to bundle the changes into a package?', 'boolean');
	}


	if($bundleyn){

		$file = $t->getBaseDir() . 'CHANGELOG';
		$headerprefix = 'Theme/' . $name;
		// The header line will be exactly [name] [version].
		$header = 'Theme/' . $name . ' ' . $version . "\n";

		add_release_date_to_changelog($file, $headerprefix, $header);


		// Create a temp directory to contain all these
		$dir = TMP_DIR . 'packager-' . $theme . '/';

		// Destination tarball
		$tgz = ROOT_PDIR . 'exports/themes/' . $theme . '-' . $version . '.tgz';

		// Ensure the export directory exists.
		if(!is_dir(dirname($tgz))) exec('mkdir -p "' . dirname($tgz) . '"');
		//mkdir(dirname($tgz));

		if(!is_dir($dir)) mkdir($dir);
		if(!is_dir($dir . 'data/')) mkdir($dir . 'data/');

		// I already have a good iterator...just reuse it.
		$it->rewind();

		foreach($it as $file){
			$fname = substr($file->getFilename(), $basestrlen);
			$file->copyTo($dir . 'data/' . $fname);
		}

		// Because the destination is relative...
		$xmldest = 'data/theme.xml' ;
		$xmloutput = new File_local_backend($dir . $xmldest);
		$xmloutput->putContents($t->getRawXML(true));

		// Save the package.xml file.
		$t->savePackageXML(true, $dir . 'package.xml');

		exec('tar -czf ' . $tgz . ' -C ' . $dir . ' --exclude-vcs --exclude=*~ --exclude=._* .');
		$bundle = $tgz;

		if(CLI::PromptUser('Package created, do you want to sign it?', 'boolean', true)){
			exec('gpg --homedir "' . GPG_HOMEDIR . '" --no-permission-warning -u "' . $packageremail . '" -a --sign "' . $tgz . '"');
			$bundle .= '.asc';
		}

		// And remove the tmp directory.
		exec('rm -fr "' . $dir . '"');

		echo "Created package of " . $theme . ' ' . $version . NL . " as " . $bundle . NL;
	}
} // function process_theme($theme)


/**
 * Will return an array with the newest exported versions of each component.
 */
function get_exported_components(){
	$c = array();
	$dir = ROOT_PDIR . 'exports/components';
	$dh = opendir($dir);
	if(!$dh){
		// Easy enough, just return a blank array!
		return $c;
	}
	while(($file = readdir($dh)) !== false){
		if($file{0} == '.') continue;
		if(is_dir($dir . '/' . $file)) continue;
		// Get the extension type.

		if(preg_match('/\.tgz$/', $file)){
			$signed = false;
			$fbase = substr($file, 0, -4);
		}
		elseif(preg_match('/\.tgz\.asc$/', $file)){
			$signed = true;
			$fbase = substr($file, 0, -8);
		}
		else{
			continue;
		}

		// Split up the name and the version.
		// This is a little touchy because a dash in the package name is perfectly valid.
		// instead, grab the last dash in the string.

		$dash = strrpos($fbase, '-');
		$n = substr($fbase, 0, $dash);
		$v = substr($fbase, ($dash+1));
		// instead, I need to look for a dash followed by a number.  This should indicate the version number.
		//preg_match('/^(.*)\-([0-9]+.*)$/', $fbase, $matches);

		//$n = $matches[1];
		//$v = $matches[2];

		// Tack it on.
		if(!isset($c[$n])){
			$c[$n] = array('version' => $v, 'signed' => $signed, 'filename' => $dir . '/' . $file);
		}
		else{
			switch(Core::VersionCompare($c[$n]['version'], $v)){
				case -1:
					// Existing older, overwrite!
					$c[$n] = array('version' => $v, 'signed' => $signed, 'filename' => $dir . '/' . $file);
					break;
				case 0:
					// Same, check the signed status.
					if($signed) $c[$n] = array('version' => $v, 'signed' => $signed, 'filename' => $dir . '/' . $file);
					break;
				default:
					// Do nothing, current is at a higher version.
			}
		}
	}
	closedir($dh);

	return $c;
} // function get_exported_components()

/**
 * Will return an array with the newest exported version of the core.
 */
function get_exported_core(){
	$c = array();
	$dir = ROOT_PDIR . 'exports/core';
	$dh = opendir($dir);
	if(!$dh){
		// Easy enough, just return a blank array!
		return $c;
	}
	while(($file = readdir($dh)) !== false){
		if($file{0} == '.') continue;
		if(is_dir($dir . '/' . $file)) continue;
		// Get the extension type.

		if(preg_match('/\.tgz$/', $file)){
			$signed = false;
			$fbase = substr($file, 0, -4);
		}
		elseif(preg_match('/\.tgz\.asc$/', $file)){
			$signed = true;
			$fbase = substr($file, 0, -8);
		}
		else{
			continue;
		}

		// Split up the name and the version.
		preg_match('/([^-]*)\-(.*)/', $fbase, $matches);
		$n = $matches[1];
		$v = $matches[2];

		// Tack it on.
		if(!isset($c[$n])){
			$c[$n] = array('version' => $v, 'signed' => $signed, 'filename' => $dir . '/' . $file);
		}
		else{
			switch(version_compare($c[$n]['version'], $v)){
				case -1:
					// Existing older, overwrite!
					$c[$n] = array('version' => $v, 'signed' => $signed, 'filename' => $dir . '/' . $file);
					break;
				case 0:
					// Same, check the signed status.
					if($signed) $c[$n] = array('version' => $v, 'signed' => $signed, 'filename' => $dir . '/' . $file);
					break;
				default:
					// Do nothing, current is at a higher version.
			}
		}
	}
	closedir($dh);

	if(!isset($c['core'])) $c['core'] = array('version' => null, 'signed' => false);

	return $c['core'];
} // function get_exported_core()


function get_exported_component($component){
	global $_cversions;
	if(is_null($_cversions)){
		$_cversions = get_exported_components();
		// Tack on the core.
		$_cversions['core'] = get_exported_core();
	}

	if(!isset($_cversions[$component])) return array('version' => null, 'signed' => false);
	else return $_cversions[$component];
}

/**
 * Prompt the user for changes and write those changes back to a set changelog in the correct format.
 */
function manage_changelog($file, $name, $version){

	$headerprefix = $name;

	// The header line will be exactly [name] [version].
	$header = $name. ' ' . $version . "\n";

	$changelog = '';
	// I also need to remember what's before and after the changelog, (before is the more likely case).
	$beforechangelog = '';
	$afterchangelog = '';

	// Start reading the file contents until I find the header, (probably on line 1, but you never know).
	$fh = fopen($file, 'r');
	if(!$fh){
		// Hmm, create it?...
		touch($file);
		$fh = fopen($file, 'r');
	}
	// Still no?
	if(!$fh){
		die('Unable to create file ' . $file . ' for reading or writing!');
	}
	$inchange = false;
	while(!feof($fh)){
		$line = fgets($fh, 512);

		// Does this line look like the exact header?
		if($line == $header){
			$inchange = true;
			continue;
		}

		// Does this line look like the beginning of another header?...
		if($inchange && stripos($line, $headerprefix) === 0){
		//if($inchange && preg_match('/^' . $headerprefix . ' .+$/i', $line)){
			$inchange = false;
		}

		if($inchange){
			$line = trim($line); // trim whitespace and newlines.
			if(strpos($line, '* ') === 0){
				// line starts with *[space], it's a comment!
				$line = substr($line, 2);
				$changelog .= $line . "\n";
			}
			elseif(strpos($line, '--') === 0){
				$inchange = false;
			}
			elseif($line == ''){
				// Skip blank lines
			}
			else{
				// It seems to be a continuation of the last line.  Tack it onto there!
				$changelog = substr($changelog, 0, -1) . ' ' . $line . "\n";
			}
		}
		elseif($changelog){
			// After!
			$afterchangelog .= $line;
		}
		else{
			// Before!
			$beforechangelog .= $line;
		}
	}
	fclose($fh);

	// Is there even any changelog text here?  If not switch the "before" content to after.
	// This will ensure that new version entries are always at the top of the file!
	if(!$changelog && $beforechangelog){
		$afterchangelog = $beforechangelog;
		$beforechangelog = '';
	}

	// Put a note in the header.
	//$changelog = 'Enter the changelog items below, each item separated by a newline.' . "\n" . ';--- ENTER CHANGELOG BELOW ---;' . "\n\n" . $changelog;

	$changelog = CLI::PromptUser('Enter the changelog for this release.  Separate each different bullet point on a new line with no dashes or asterisks.', 'textarea', $changelog);

	// I need to transpose the text only changelog back to a regular format.
	$changeloglines = '';
	$x = 0;
	foreach(explode("\n", $changelog) as $line){
		++$x;
		//	if($x <= 2) continue;
		$line = trim($line);
		if(!$line) continue;
		// I need to produce a pretty line here, it takes some finesse.
		//$linearray = array_map('trim', explode("\n", wordwrap($line, 70, "\n")));
		//$changeloglines .= "\t* " . implode("\n\t  ", $linearray) . "\n";

		/// hehehe, just because I can do this all in one "line".... :p
		$changeloglines .= "\t* " .
			implode(
				"\n\t  ",
				array_map(
					'trim',
					explode(
						"\n",
						wordwrap($line, 90, "\n")
					)
				)
			) .
			"\n";

		//$changeloglines .= "\t* " . wordwrap($line, 70, "\n\t  ") . "\n";
	}

	//echo $changeloglines; die('halting'); // DEBUG

	// Write this back out to that file :)
	file_put_contents($file, $beforechangelog . $header . "\n" . $changeloglines . "\n" . $afterchangelog);
}

/**
 * Add the release date to the changelog for the current version.
 */
function add_release_date_to_changelog($file, $headerprefix, $header){
	// Update the changelog version first!
	// This is done here to signify that the version has actually been bundled up.
	// Lookup the changelog text of this current version.
	global $packagername, $packageremail;

	$timestamp = "\t--$packagername <$packageremail>  " . Time::GetCurrent(Time::TIMEZONE_DEFAULT, Time::FORMAT_RFC2822) . "\n\n";
	$changelog = '';

	// Start reading the file contents until I find the header, (probably on line 1, but you never know).
	$fh = fopen($file, 'r');
	if(!$fh){
		// Hmm, create it?...
		touch($file);
		$fh = fopen($file, 'r');
	}
	// Still no?
	if(!$fh){
		die('Unable to create file ' . $file . ' for reading or writing!');
	}
	$wrotetimestamp = false;
	$inchange = false;
	$previous = null;
	while(!feof($fh)){
		$line = fgets($fh, 512);

		// Does this line look like the exact header?
		if($line == $header){
			$inchange = true;
			$changelog .= $line;
			$previous = $line;
			continue;
		}

		if($inchange){
			// It's in the current change version,
			// previous line was a comment,
			// and current line is blank...
			// EXCELLENT!  Insert the timestamp here!
			if(strpos($previous, "\t* ") === 0 && trim($line) == ''){
				// line starts with [tab]*[space], it's a comment!
				$changelog .= $timestamp;
				$wrotetimestamp = true;
				$inchange = false;
			}
			elseif(strpos($previous, "\t* ") === 0 && strpos($line, '--') === 0){
				// Oh, this line already has a timestamp.... DON'T CARE! :p
				$changelog .= $timestamp;
				$wrotetimestamp = true;
				$inchange = false;
			}
			elseif(strpos($line, $headerprefix) === 0){
				// Wait, I found another header, but I haven't written the timestamp yet!
				// :/
				$changelog .= $timestamp . $line;
				$wrotetimestamp = true;
				$inchange = false;
			}
			else{
				// eh...
				$changelog .= $line;
			}
		}
		else{
			// Ok, I don't care anyway
			$changelog .= $line;
		}

		$previous = $line;
	}
	fclose($fh);

	// Did it never even write the timestamp?
	if(!$wrotetimestamp){
		$changelog = $header . "\n" . $timestamp . $changelog;
	}

	// Write this back out to that file :)
	file_put_contents($file, $changelog);
}

// I need a few variables first about the user...
$packagername = '';
$packageremail = '';

CLI::LoadSettingsFile('packager');

if(!$packagername){
	$packagername = CLI::PromptUser('Please provide your name you wish to use for packaging', 'text-required');
}
if(!$packageremail){
	$packageremail = CLI::Promptuser('Please provide your email you wish to use for packaging.', 'text-required');
}

CLI::SaveSettingsFile('packager', array('packagername', 'packageremail'));


$ans = CLI::PromptUser(
	"What operation do you want to do?",
	array(
		'component' => 'Manage a Component',
		'theme' => 'Manage a Theme',
	//	'bundle' => 'Installation Bundle',
		'exit' => 'Exit the script',
	),
	'component'
);

switch($ans){
	case 'component':
		echo "Scanning existing components...\n";

		// Open the "component" directory and look for anything with a valid component.xml file.
		$files = array();
		$longestname = 4;
		// Tack on the core component.
		$files[] = 'core';
		$dir = ROOT_PDIR . 'components';
		$dh = opendir($dir);
		while(($file = readdir($dh)) !== false){
			if($file{0} == '.') continue;
			if(!is_dir($dir . '/' . $file)) continue;
			if(!is_readable($dir . '/' . $file . '/' . 'component.xml')) continue;

			$longestname = max($longestname, strlen($file));
			$files[] = $file;
		}
		closedir($dh);
		// They should be in alphabetical order...
		sort($files);


		// Before prompting the user which one to choose, tack on the exported versions.
		$versionedfiles = array();
		foreach($files as $k => $f){
			$dir = ROOT_PDIR . (($f == 'core') ? 'core/' : 'components/' . $f . '/');

			$c = Core::GetComponent($f);

			// What's this file's version?
			$xml = new XMLLoader();
			$xml->setRootName('component');
			if(!$xml->loadFromFile($dir . 'component.xml')){
				throw new Exception('Unable to load XML file ' . $cfile);
			}

			// Get the current version, this will be used to autocomplete for the next version.
			$version = $xml->getRootDOM()->getAttribute("version");

			$line = str_pad($f, $longestname+1, ' ', STR_PAD_RIGHT);
			$lineflags = array();

			// Now give me the exported version.
			$lookup = get_exported_component($f);
			if($lookup['version'] != $version) $lineflags[] = '** needs exported **';

			// Change the changes
			if(
				sizeof($c->getChangedAssets()) ||
				sizeof($c->getChangedFiles()) ||
				sizeof($c->getChangedTemplates())
			){
				$lineflags[] = '** needs packaged **';
			}


			$versionedfiles[$k] = $line . ' ' . implode(' ', $lineflags);
		}

		$ans = CLI::PromptUser("Which component do you want to package/manage?", $versionedfiles);
		process_component($files[$ans]);
		break; // case 'component'
	case 'theme':
		// Open the "themes" directory and look for anything with a valid theme.xml file.
		$files = array();
		$dir = ROOT_PDIR . 'themes';
		$dh = opendir($dir);
		while(($file = readdir($dh)) !== false){
			if($file{0} == '.') continue;
			if(!is_dir($dir . '/' . $file)) continue;
			if(!is_readable($dir . '/' . $file . '/' . 'theme.xml')) continue;

			$files[] = $file;
		}
		closedir($dh);
		// They should be in alphabetical order...
		sort($files);
		$ans = CLI::PromptUser("Which theme do you want to package/manage?", $files);
		process_theme($files[$ans]);
		break; // case 'component'
	case 'exit':
		echo 'Bye bye' . NL;
		break;
	default:
		echo "Unknown option..." . NL;
		break;
}



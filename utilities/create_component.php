#!/usr/bin/env php
<?php
/**
 * Create a component from some scafolding templates.
 */

if(!isset($_SERVER['SHELL'])){
	die("Please run this script from the command line.");
}

// This is required to establish the root path of the system, (since it's always one directory up from "here"
$dir = realpath(dirname($_SERVER['PWD'] . '/' . $_SERVER['SCRIPT_FILENAME']) . '/..') . '/';

// Include the core bootstrap, this will get the system functional.
require_once($dir . 'core/bootstrap.php');

CLI::RequireEditor();


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



$component = CLI::PromptUser('Enter the name of the component to create', 'text-required');

// Sanitize this name.
$component = str_replace(' ', '-', $component);
$component = trim($component);
$component = preg_replace('/[^a-zA-Z\-]/', '', $component);
// The name can have capitals in it.
$componentname = $component;
// The directory will be all lowercase.
$component = strtolower($component);
$dirname = ROOT_PDIR . 'components/' . $component . '/';

if(is_dir($dirname)){
	die($component . ' already exists, corwardly refusing to overwrite with scaffolding' . "\n");
}

$directories = array(
	'components/' . $component,
	'components/' . $component . '/assets',
	'components/' . $component . '/controllers',
	'components/' . $component . '/models',
	'components/' . $component . '/templates',
	'components/' . $component . '/templates/pages',
);
$models = array();
$controllers = array();

echo 'Enter the models to create on this component initially, separated by a newline' . "\n";
echo "(Press enter to open the editor.)";
fgets(STDIN);
$modellines = CLI::PromptUser('Reading models...', 'textarea');
foreach(explode("\n", $modellines) as $line){
	if(!trim($line)) continue;
	$models[] = $line;
	$controllers[] = $line;
}

echo "Enter the controllers to create on this component initially, separated by a newline." . "\n";
echo "(Press enter to open the editor.)";
fgets(STDIN);
$controllerlines = CLI::PromptUser('Reading controllers...', 'textarea', implode("\n", $controllers));
$controllers = array();
foreach(explode("\n", $controllerlines) as $line){
	if(!trim($line)) continue;
	$controllers[] = $line;
}



/// The scaffolding code to insert.
$modelscaffolding = <<<EOF
<?php
/**
 * Class file for the model %CLASS%
 *
 * @package %COMPONENT%
 * @author %AUTHORNAME% <%AUTHOREMAIL%
 */
class %CLASS% extends Model {
	/**
	 * Schema definition for %CLASS%
	 * @todo Fill this in with your model structure
	 *
	 * @static
	 * @var array
	 */
	public static \$Schema = array(
		'id' => array(
			'type' => Model::ATT_TYPE_ID
		),
	);

	/**
	 * Index definition for %CLASS%
	 * @todo Fill this in with your model indexes
	 *
	 * @static
	 * @var array
	 */
	public static \$Indexes = array(
		'primary' => array('id'),
	);
}
EOF;

$controllerscaffolding = <<<EOF
<?php
/**
 * Class file for the controller %CLASS%
 *
 * @package %COMPONENT%
 * @author %AUTHORNAME% <%AUTHOREMAIL%
 */
class %CLASS% extends Controller_2_1 {
	// @todo Add your views here
	// Each controller can have many views, each defined by a different method.
	// These methods should be regular public functions that DO NOT begin with an underscore (_).
	// Any method that begins with an underscore or is static will be assumed as an internal method
	// and cannot be called externally via a url.
}
EOF;


// Start making the directories and writing everything.
File_local_backend::_Mkdir($dirname);
File_local_backend::_Mkdir($dirname . 'assets');
if(sizeof($controllers)) File_local_backend::_Mkdir($dirname . 'controllers');
if(sizeof($models))      File_local_backend::_Mkdir($dirname . 'models');
if(sizeof($controllers)) File_local_backend::_Mkdir($dirname . 'templates');
if(sizeof($controllers)) File_local_backend::_Mkdir($dirname . 'templates/pages');

foreach($controllers as $controller){
	if(sizeof($controllers)) File_local_backend::_Mkdir($dirname . 'templates/pages/' . strtolower($controller));
}


// I need to create a basic xml file for the component to use initially.
$implementation = new DOMImplementation();

$dtd = $implementation->createDocumentType('component', 'SYSTEM', 'http://corepl.us/api/2_4/component.dtd');
$xml = $implementation->createDocument('', '', $dtd);
$xml->encoding = 'UTF-8';
$root = $xml->createElement('component');
$root->setAttribute('name', $componentname);
$root->setAttribute('version', '1.0.0');
$xml->appendChild($root);
$xml->save($dirname . 'component.xml');



// Now write the various files
$allfiles = array();

foreach($models as $class){
	/** @var $fname String relative filename */
	$fname = 'models/' . $class . 'Model.php';
	$freplaces = array(
		'%CLASS%' => $class . 'Model',
		'%COMPONENT%' => $componentname,
		'%AUTHORNAME%' => $packagername,
		'%AUTHOREMAIL%' => $packageremail,
	);
	$fcontents = str_replace(array_keys($freplaces), array_values($freplaces), $modelscaffolding);
	file_put_contents($dirname . $fname, $fcontents);
	$md5 = md5_file($dirname . $fname);

	$allfiles[] = array(
		'file' => $fname,
		'md5' => $md5,
		'classes' => array($class . 'Model')
	);
}

foreach($controllers as $class){
	/** @var $fname String relative filename */
	$fname = 'controllers/' . $class . 'Controller.php';
	$freplaces = array(
		'%CLASS%' => $class . 'Controller',
		'%COMPONENT%' => $componentname,
		'%AUTHORNAME%' => $packagername,
		'%AUTHOREMAIL%' => $packageremail,
	);
	$fcontents = str_replace(array_keys($freplaces), array_values($freplaces), $controllerscaffolding);
	file_put_contents($dirname . $fname, $fcontents);
	$md5 = md5_file($dirname . $fname);

	$allfiles[] = array(
		'file' => $fname,
		'md5' => $md5,
		'controllers' => array($class . 'Controller')
	);
}


// Write the changelog
$now = Time::GetCurrentGMT(Time::FORMAT_RFC2822);
file_put_contents($dirname . 'CHANGELOG', "$componentname 1.0.0

	* Initial version

");
$allfiles[] = array(
	'file' => 'CHANGELOG',
	'md5' => md5_file($dirname . 'CHANGELOG')
);

// And the readme
file_put_contents($dirname . 'README.md', "# About $componentname

@todo Write something about this component.
");
$allfiles[] = array(
	'file' => 'README.md',
	'md5' => md5_file($dirname . 'README.md')
);


$componentobject = new Component_2_1($dirname . 'component.xml');
$componentobject->setAuthors(
	array(
		array('name' => $packagername, 'email' => $packageremail)
	)
);
$componentobject->setFiles($allfiles);
$componentobject->save();


echo "Created new component " . $componentname . "\n";
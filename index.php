<?php //----------------------------------------------------------------------------------------  PREPARATIONS

//	Benchmarking start
define('MEM', memory_get_usage());
define('BMK', microtime(true));

//	Disable errors until we have our error control class ready.
//	error_reporting('E_ALL');
//	ini_set('display_errors',0);

//	Kill magic quotes.(this is deprecated and should be removed in later versions).
@set_magic_quotes_runtime(0);

//	--------------------------------------------------------------------------------------  SUPPORTT CONSTANTS

define('_error', true);	 //	shows errors messages (bool) default:true
define('_class', false); //	name of the calling class (mixed) false:auto, null:core

// a safe shorthand for slashes
define('SLASH', DIRECTORY_SEPARATOR);
// Is apache running in CGI mode?
define('IS_CGI', function_exists('apache_get_modules')? false : true);
// Is the script is run from command line?
define('IS_CLI', strpos(php_sapi_name(),'cli') !== false ? true : false);
// is the framework being included or called directly?
define('IS_INC', count(get_included_files())>1? true : false);

//	-----------------------------------------------------------------------------------------------  CONSTANTS

// This file's name.
define('BASE', str_replace('/',SLASH,pathinfo(__FILE__,PATHINFO_BASENAME)));

// Store these in a tmp array so they can be easily checked afterwards.
$_E = array();
$_E['ROOT'] = str_replace('/',SLASH,pathinfo(__FILE__,PATHINFO_DIRNAME)).SLASH;	//	This file's abs path
$_E['SYS']  = $_E['ROOT'].'sys'.SLASH;											//	System
$_E['APP']  = $_E['ROOT'].'app'.SLASH;											//	Applications
$_E['PUB']  = $_E['ROOT'].'pub'.SLASH;											//	Public
$_E['TMP']  = $_E['ROOT'].'tmp'.SLASH;											//	Temporary Files
$_E['CORE'] = $_E['SYS'].'core'.SLASH;											//	Core Lib
$_E['LIBS'] = $_E['SYS'].'libs'.SLASH;											//	Libraries

foreach ($_E as $k=>$v){
	if (!file_exists($v) && !is_dir($v)) error("$k path does not exist.");
	define($k,$v);
}

define('EXT', BASE == ($ext = substr(BASE, strpos(BASE,'.')))? '' : $ext);			//	File extension

#	these two must not be used when running from CLI.
define('PATH',IS_CLI? '/' : str_replace($_SERVER['DOCUMENT_ROOT'],'',ROOT)); 		//	This RELATIVE path
define('URL','http://'.(IS_CLI? 'localhost' : $_SERVER['HTTP_HOST']).PATH);			//	Full URL

unset($k,$v,$ext,$_E);

// ---------------------------------------------------------------------------------------  START YOUR ENGINES 

if (!file_exists(CORE.'library'.EXT) || !file_exists(CORE.'core'.EXT)) error();
include_once CORE.'library'.EXT;
include_once CORE.'core'.EXT;

// Here's where the magin begins.
Core::_construct();

// Don't do anything more if this file was included. [to avoid infinite loops]
if (IS_INC) return;

//	Temporary routing, while a proper routing class is developed.
if (file_exists(APP.'root'.EXT)) return include(APP.'root'.EXT);
else error('Framework loaded, but no controllers are available'); 

exit(0);

// --------------------------------------------------------------------------------------------------  SUPPORT

// a simple error handler [we'll use this until the error class is loaded]
function error($msg='Core functionality missing.', $tit=false){
	$error = array('','Error');
	$arg = array($msg,$tit);
	for($i=0; $i<2; $i++){
		if (is_array($arg[$i])){
			array_unshift($arg[$i],'core');
			$arg[$i] = implode("::", $arg[$i]);
		}
		if (!is_string($arg[$i])) $arg[$i] = $error[$i];
	}
	if (IS_CLI)	$msg = '%2$s: %1$s'."\n";
	else {
		header('HTTP/1.1 500 Internal Server Error');
		$msg = '<h1 style="color:red">%2$s</h1><h2>%1$s</h2>';
	}
	printf($msg,$arg[0],$arg[1]);
	exit(1);
}
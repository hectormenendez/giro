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

define('EXT', '.php');
define('SLASH', DIRECTORY_SEPARATOR);
define('ISCGI', function_exists('apache_get_modules')? false : true);

//	-----------------------------------------------------------------------------------------------  CONSTANTS
//	These constants will be used as environment variables, so store them in an array.
//	$file 	= full path for this file.
//	$root 	= document root of the server
$v = 'DOCUMENT_ROOT';
$file = str_replace('/',SLASH,$_SERVER['SCRIPT_FILENAME']);
$root = str_replace('/',SLASH,isset($_SERVER[$v])?$_SERVER[$v]:substr($file,0,0-strlen($_SERVER['PHP_SELF'])));
if (substr($root,-1)!=SLASH) $root.=SLASH; // add a trailing slash

$_ENV = array();
$_ENV['ROOT']	= pathinfo($file,PATHINFO_DIRNAME).SLASH;			//	ABSOLUTE path to this file
$_ENV['FILE']	= pathinfo($file,PATHINFO_BASENAME);				//	The name of this file
$_ENV['PATH']	= str_replace($root,SLASH,$_ENV['ROOT']);			//	RELATIVE path to this file
$_ENV['URL']	= 'http://'.$_SERVER['HTTP_HOST'].$_ENV['PATH'];	//	framework's root URL
$_ENV['APP']	= $_ENV['ROOT'].'app'.SLASH;						//	Application folder
$_ENV['SYS']	= $_ENV['ROOT'].'sys'.SLASH;						//	System folder
$_ENV['PUB']	= $_ENV['ROOT'].'pub'.SLASH;						
$_ENV['TMP']	= $_ENV['ROOT'].'tmp'.SLASH;						//	Temporary Files *[set outside ROOT]
$_ENV['CORE']	= $_ENV['SYS'].'core'.SLASH;						//	Core Folder
$_ENV['LIBS']	= $_ENV['SYS'].'libs'.SLASH;						//	Libraries Folder

$nopath = array('URL','PATH');
foreach ($_ENV as $k=>$v){
	if (!in_array($k,$nopath) && (!file_exists($v) && !is_dir($v))) error("$k path does not exist.");
	define($k,$v);
}
unset($root,$file,$k,$v);

// -----------------------------------------------------------------------------------  SET CORE FUNCTIONALITY 

define('_error', true);	 //	shows errors messages (bool) default:true
define('_class', false); //	name of the calling class (mixed) false:auto, null:core

if (!file_exists(CORE.'library'.EXT) || !file_exists(CORE.'core'.EXT)) error();
include(CORE.'library'.EXT);
include(CORE.'core'.EXT);

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
	header('HTTP/1.1 500 Internal Server Error');
	printf('<h1 style="color:red">%2$s</h1><h2>%1$s</h2>',$arg[0],$arg[1]);
	exit(1);
}
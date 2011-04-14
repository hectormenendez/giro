<?php
# Benchmarking start
define('MEM', memory_get_usage());
define('BMK', microtime(true));

if (5.3 > (float)substr(phpversion(),0,3) )
	error('PHP 5.3+= required');
if (isset($_SERVER['SERVER_SOFTWARE']) && stripos($_SERVER['SERVER_SOFTWARE'],'win32') !== false)
	error('Windows? really? ...fuck off.');

# Disable errors until we have our error control class ready.
error_reporting('E_ALL');
ini_set('display_errors',0);

# Kill magic quotes.(deprecated. should be removed in later versions).
@set_magic_quotes_runtime(0);

# a safe shorthand for slashes
define('SLASH', DIRECTORY_SEPARATOR);
# Is apache running in CGI mode?
define('IS_CGI', function_exists('apache_get_modules')? false : true);
# Is the script is run from command line?
define('IS_CLI', strpos(php_sapi_name(),'cli') !== false ? true : false);
# is the framework being included or called directly?
define('IS_INC', count(get_included_files())>1? true : false);

#### CONSTANTS
# Basic Paths. Check for existance.
$_E = array();
$_E['BASE'] = $_SERVER['SCRIPT_FILENAME'];
$_E['ROOT']	= IS_CLI? exec('pwd -L') : pathinfo($_E['BASE'], PATHINFO_DIRNAME);
$_E['BASE'] = $_E['ROOT'].SLASH.pathinfo($_E['BASE'], PATHINFO_BASENAME);
$_E['ROOT'].= SLASH;

$_E['SYS']  = $_E['ROOT'].'sys'.SLASH;	#	System
$_E['APP']  = $_E['ROOT'].'app'.SLASH;	#	Applications
$_E['PUB']  = $_E['ROOT'].'pub'.SLASH;	#	Public
$_E['TMP']  = $_E['ROOT'].'tmp'.SLASH;	#	Temporary Files
$_E['CORE'] = $_E['SYS'].'core'.SLASH;	#	Core Lib
$_E['LIBS'] = $_E['SYS'].'libs'.SLASH;	#	Libraries

foreach ($_E as $k=>$v){
	if (!file_exists($v) && !is_dir($v)) error("$k path does not exist.");
	define($k,$v);
}

# Extension, got from this file name. All included script must match it.
define('EXT', BASE == ($ext = substr(BASE, strpos(BASE,'.')))? '' : $ext);
# Framework's relative path and url. Avoid these when in CLI.
define('PATH',IS_CLI? '/' : str_replace($_SERVER['DOCUMENT_ROOT'],'',ROOT));
define('URL','http://'.(IS_CLI? 'localhost' : $_SERVER['HTTP_HOST']).PATH);
unset($k,$v,$ext,$_E);


# if this file was included by another script, stop to avoid infinite loops.
if (IS_INC) return;

### FRAMEWORK START

if (!file_exists(CORE.'library'.EXT) || !file_exists(CORE.'core'.EXT)) error();
include_once CORE.'library'.EXT;
include_once CORE.'core'.EXT;
Core::_construct();
exit(0);	

# Simple error handler
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
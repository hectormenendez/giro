<?php
# Benchmarking start
define('MEM', memory_get_usage());
define('BMK', microtime(true));

# Get rid of Winshit and PHP < 5.3 users.
if ( 5.3 > (float)substr(phpversion(),0,3) )
	error('PHP 5.3+= required');
if (isset($_SERVER[$k='SERVER_SOFTWARE']) && stripos($_SERVER[$k],'win32') !== false)
	error('Windows? really? ...fuck off.');

# a safe shorthand for slashes
define('SLASH', DIRECTORY_SEPARATOR);
# Is apache running in CGI mode?
define('IS_CGI', function_exists('apache_get_modules')? false : true);
# Is the script is run from command line?
define('IS_CLI', strpos(php_sapi_name(),'cli') !== false ? true : false);
# is the framework being included by another file?
define('IS_INC', count(get_included_files())>1? true : false);

########################################################################## ERROR

# I will handle my own errors thank you.
# Enable all errors so our handlers take over. 

#ini_set('display_errors', false); # Added obscurity, harder developing.
error_reporting(-1);
set_error_handler('handler');
register_shutdown_function('handler', 'shutdown');
error_reporting(E_PARSE);

########################################################################## PATHS

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

################################################################ FRAMEWORK START

if (!file_exists(CORE.'library'.EXT) || !file_exists(CORE.'core'.EXT)) error();
include_once CORE.'library'.EXT;
include_once CORE.'core'.EXT;
Core::_construct();
error('Classless');

exit(0);

######################################################################## THE END

/**
 * Shutdown and Error Handler.
 * Don't get too excited, this is just a wrapper for the methods defined on core.
 *
 * @param	[bool] $shutdown	register_shutdown_function sends true.
 *
 * @todo	Send the error type nam instead and use a content replacer.
 *
 * @note	Please don't use trigger errror, expect th unexpected.
 */
function handler($action = null, $msg = null){
	# if this is a shutdown request, detect if there is pending errors to send
	# and if not, proceed to run the real shutdown process. or fail silently. xD
	if ($action === 'shutdown'){
		if (is_null($e = error_get_last()) === false && $e['type'] == 1)
			call_user_func_array('handler', $e);
		if (class_exists('Core',false) && method_exists('Core', 'shutdown'))
			call_user_func('Core::shutdown');
		#echo "called";
		exit(0);
	}
	# This is an error request then.
	# But wait, we need to catch the "user".
	switch($action){
		case 1: return true; # Parse Error, just bypass default handler.
		break;
		case E_USER_ERROR:
		case E_USER_WARNING:
		case E_USER_NOTICE:
			# Go back two steps ahead and capture file & line.
			$bt = array_slice(debug_backtrace(true), 2);
			$arg = array_shift($bt);
			$arg = array($action, $msg, $arg['file'], $arg['line']);
		break;
		default:
			# only one step to forget
			$bt = array_slice(debug_backtrace(true), 1);
			# since we cannot get a scope using backtace for our USER errors
			# let's mantain everything coherent and unset it here too.
			$arg = func_get_args();	
			unset($arg[4]);
	}
	array_push($arg, $bt);
	# Ok, we're all set, now, it's time to check if the actual error handling
	# method exist. if so, send the friggin' error.
	if (class_exists('Core',false) && method_exists('Core', 'error_show'))
		call_user_func_array('Core::error_show', $arg);
	# Or, fallback to  a simple error.
	else {
		# find out the error type string.
		$type = get_defined_constants(true);
		$type = array_search((int)$action, $type['Core'], true);
		$file = substr($arg[2], (int)strrpos($arg[2], '/')).":{$arg[3]}";
		if (!IS_CLI) echo '<pre>';
		echo "$type: {$arg[1]}\t[".str_replace(SLASH,'', $file)."]\n";
		if (!IS_CLI)echo '<pre>';
		return false;
	}
}

function error ($msg = ''){
	return call_user_func('handler', E_USER_ERROR, $msg);
}
function warning ($msg = ''){
	return call_user_func('handler', E_USER_WARNING, $msg);
}
function notice($msg = ''){
	return call_user_func('handler', E_USER_NOTICE, $msg);
}
<?php
# Benchmarking start
define('MEM', memory_get_usage());
define('BMK', microtime(true));

# This framework is an egomaniac.
define('VER', 2.105);
define('BRANCH','palmera');
define('WHOAMI',ucfirst(BRANCH).' v'.(is_int(VER)? VER.'.0' : substr((string)VER, 0,-2).' REVISION '.substr((string)VER, -2)));

# Get rid of Winshit and PHP < 5.3 users.
if ( 5.3 > (float)substr(phpversion(),0,3) )
	error('PHP 5.3+= required');
if (isset($_SERVER[$k='SERVER_SOFTWARE']) && stripos($_SERVER[$k],'win32') !== false)
	error('Windows? really? ...fuck off.');

# this should not be here, someone move me.
ini_set('zlib.output_compression', 0);

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
set_error_handler('_error');
register_shutdown_function('_error', 'shutdown');

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
$_E['HTML'] = $_E['SYS'].'html'.SLASH;  #   HTML Templates

foreach ($_E as $k=>$v){
	if (!file_exists($v) && !is_dir($v)) error("$k path does not exist.");
	define($k,$v);
}

# Extension, got from this file name. All included script must match it.
define('EXT', BASE == ($ext = substr(BASE, strpos(BASE,'.')))? '' : $ext);
# Framework's relative path and url. Avoid these when in CLI.
define('PATH',IS_CLI? '/' : str_replace($_SERVER['DOCUMENT_ROOT'],'',ROOT));

define('URL','http://'.(IS_CLI? 'localhost' : $_SERVER['HTTP_HOST']).PATH);

$x = strpos(ROOT, PATH);
define('PUB_URL', !$x? str_replace(ROOT, '/', PUB) : substr(PUB, $x));
 
########################################################################### UUID

# Obtain the IP trying to overpass, proxies.
if (!empty($_SERVER['HTTP_CLIENT_IP']))
	 $x = $_SERVER['HTTP_CLIENT_IP'];
elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
	 $x = $_SERVER['HTTP_X_FORWARDED_FOR'];
else $x = $_SERVER['REMOTE_ADDR'];
define('IP',$x);

# Identifier for this run. [just the UNIX time]
define('ID', (int)str_replace('.', '', (string)BMK));

# Append the user agent and a salt
define('UUID',md5(IP.$_SERVER['HTTP_USER_AGENT'].'GiRo23'));
# Define Core database location
define('DB', TMP.'DB');

unset($x, $k,$v,$ext,$_E);

################################################################ FRAMEWORK START
# if this file was included by another script, stop to avoid infinite loops.
if (IS_INC) return;

if (!file_exists(CORE.'library'.EXT) || !file_exists(CORE.'core'.EXT)) error();
include_once CORE.'library'.EXT;
include_once CORE.'core'.EXT;
Core::_construct();

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
function _error($action = null, $msg = null){
	# if this is a shutdown request, detect if there is pending errors to send
	# and if not, proceed to run the real shutdown process. or fail silently. xD
	if ($action === 'shutdown'){
		if (is_null($e = error_get_last()) === false && $e['type'] == 1)
			call_user_func_array('_error', $e);
		if (class_exists('Core',false) && method_exists('Core', 'shutdown'))
			call_user_func('Core::shutdown');
		#echo "called";
		exit(0);
	}
	# This is an error request then.
	# unles... are we trying to suppress errors with @
	if (0 == error_reporting()) return true;
	# But wait, we need to catch "user errors".
	switch($action){
		case 1: return true; # Parse Error, just bypass default handler.
		break;
		case E_USER_ERROR:
		case E_USER_WARNING:
		case E_USER_NOTICE:
			$bt = debug_backtrace(true);
			$bt = array_slice($bt, 2);
			# Go back two steps ahead and capture file & line.
			$bt = array_slice(debug_backtrace(true), 2);
			while(!empty($bt) && !isset($bt[0]['line'])) array_shift($bt);
			if (empty($bt)) die('You need to debug this right now!');
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
	if (!defined('DIED')) define('DIED',1);
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
		if (!IS_CLI)echo '</pre>';
		exit(2);
	}
}

function error   ($m=''){ return call_user_func('_error', E_USER_ERROR,   $m);}
function warning ($m=''){ return call_user_func('_error', E_USER_WARNING, $m);}
function notice  ($m=''){ return call_user_func('_error', E_USER_NOTICE,  $m);}

function stop ($var=null, $debug=false) {
	# destructors need to be told that execution stopped, yeah, they're that dumb.
	if (!defined('DIED')) define('DIED',1);
	# strings and ints are always echoed unless denug specified
	if ((is_int($var) || is_string($var)) && !$debug)
		echo $var;
	# anything else sent [except null] assume debug.
	elseif ($debug || ($var !== null && !is_int($var) && !is_string($var))){
		echo "<pre>\n";
		var_dump($var);
		echo '</pre>';
	}
	# stop execution
	exit(0);
}

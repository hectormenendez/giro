<?php
class Application_Model extends Application_Common {

	/**
	 * Model Constuctor
	 * @created 2011/AUG/25 19:57
	 */
	final public function __construct(){
		# this should be set by the framework
		# big TODO.
		date_default_timezone_set('America/Cancun');
		# if run a pseudo constructor if exist.
		if (method_exists($this, '_construct') && is_callable(array($this,'_construct'))) 
			return $this->_construct();
	}

	/**
	 * Common method for  static libraries to check if given object
	 * has a database object instantiated. if so, returns it.
	 *
	 * @return mixed  - reference of database object if found.
	 *                - false if nothing found.

	 * @log     2011/AUG/27 00:05 Changed visibility from protected to public
	 * @created 2011/AUG/25 18:14
	 */
	public static function &db_look(&$app=false){
		$false = false;
		if (!parent::is_model($app)) return $false;
		foreach($app as $k=>$o) if ($o instanceof DB) return $app->$k;
		return $false;
	}

	/**
	 * Post Data limiter vía Tokens
	 * Generates token so the framework can allow or deny external data parsing.
	 *
	 * Three Golden rules:
	 * - pub token doesn't exist, create it, store it on TMP and set it.
	 * - pub token exists, POST exists, check, allow or deny execution. generate new, set it.
	 * - pub token exists, no POST, get it, set it.
	 *
	 * @return bool false if post data found with no token specified, true otherwise.
	 *
	 * @working 2001/AUG/26 01:31
	 * @created 2011/AUG/25 20:32
	 */
	public function token(){
		# Secret 16bytes. Public 32 bytes.
		$path = TMP.UUID.'.token';
		# doesn't exists, generate and save
		if (!file_exists($path)) $token_secret = $this->token_secret();
		# exists.
		else {
			$token_secret = file_get_contents($path);
			# if has post request, get and compare, allow or deny-		
			if (!empty($_POST)){
				if (!isset($_POST['token']) ||
					 empty($_POST['token']) ||
					$token_secret !== Utils::cryptor('decrypt', $_POST['token'])
				) return false;
				# post matched, Allow to continue;
				unset($_POST['token']);
			}
			# keep same secret until post request found.
		}
		# generate new secret, define constants. allow to continue;
		#$token_secret = $this->token_secret();
		define('TOKEN_SECRET', $token_secret);
		define('TOKEN_PUBLIC', Utils::cryptor('encrypt', TOKEN_SECRET));
		return true;
	}

	private function token_secret(){
		$parts = explode('.', (string)BMK);
		$token_secret = substr(md5($parts[0].'2E(0)Wyn3'.$parts[1]),16);
		file_put_contents(TMP.UUID.'.token', $token_secret);
		return $token_secret;
	}

}
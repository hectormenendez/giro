<?php
/**
 * External file handler.
 * Catches request to pub folder, check if the user is tryng to load a 
 * dynamic file from the framework.
 *
 * @log 2011/AUG/29 20:24  file renamed due to new convention. [underscore > dot]
 *
 * @log 2011/AGO/20        This was originally Application::external, but it got
 *                         so big I decided to move it to its own class.
 * @log 2011/MAY/06        QuickFix: GET request were being considered part of the
 *                         file thus sending 404s. When dealing with cache 
 *                         [client side], using GET forces reload, so this was
 *                         most important to fix.
 *
 * @note                   I know it's a pain that the library doesn't give more 
 *                         informative errors, but since we'll be dealing with app
 *                         files directly, I think security comes first, after all...
 *                         you can alwats use backtracking provided when debug is ON.
 */

class Application_External extends Library {

	private $allow = null;
	private $mimes = null;
	private $file  = null;
	private $ext   = null;
	private $uri   = null;
	private $cache = null;
	private $scope = null;

	public function __construct(){
		# a rudimentary-yet-effective way of checking if this class
		# was instanced from Core.
		$bt = debug_backtrace();
		if (!isset($bt[1]['class']) || strtolower($bt[1]['class']) != 'application')
			error('External Application cannot be called from outside.');
		unset($bt);
		if (!is_array($this->allow = Application::config('external_allow')))
			error('Bad Allowed Types configuration');
		if (!is_array($this->mimes = Core::config('mime-types')))
			error('Bad Mime-Types configuration');
		# set a caché dir.
		$this->cache = TMP.'pub/';
		# separate any existing GET request variable, we'll ignore'em anyways.
		$this->uri  = str_replace(PUB_URL, '', URI);
		$this->file = explode('?', str_replace(PUB_URL, PUB, URI));
		$this->file = array_shift($this->file);
		# verify mime is allowed to show.
		if (
			!is_string($this->ext = pathinfo($this->file, PATHINFO_EXTENSION)) || 
			!array_key_exists($this->ext, $this->mimes)
		) parent::error_404('Not Found');
		# existent files are processed separatedly.
		if (file_exists($this->file)) return $this->render();
		# this could be a routing request, verify extension is allowed.
		if (!in_array($this->ext, $this->allow)) parent::error_403('Not Found');
		$this->route();
	}

	/**
	 * Render file to browser handling headers according to its modified time.
	 */
	private function render(){
		Core::headers_remove();
		header('Content-Type: '.$this->mimes[$this->ext].'; charset:UTF-8');
		$dtime = 1; # direction of time 1 = future; -1 = past;
		$mtime = filemtime($this->file);
		$db    = Core::DB();
		# check if we've this file cached on the database.
		$qry = $db->query('SELECT * FROM cache WHERE path=?',$this->file);
		# we set expired time on the past when the mtimes differ.
		if (!empty($qry) && (int)$qry[0]['mtime'] !== $mtime) {
			$dtime = -1;
			$sql = 'UPDATE cache SET mtime=?, updated=? WHERE "path"=?';
			$db->exec($sql, $mtime, (int)BMK, $this->file);
		# file has never been updated, insert it on DB and force expiring.
		} elseif (empty($qry)) {
			$sql = 'INSERT INTO cache ("mtime","updated","path") VALUES (?,?,?)';
			$db->exec($sql, $mtime, (int)BMK, $this->file);
			$dtime = -1;
		}
		header('Cache-Control: must-revalidate');
		header('Expires: '.gmdate('D, d M Y H:i:s',time()+($dtime*(60*60))).' GMT');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', $mtime).' GMT', true);
		# no cache then
		if ($dtime === -1){
			Core::header(200);
			header('Cache-control: private, no-cache, no-store, post-check=0, pre-check=0');
			header('Pragma: no-cache');
		}
		# not modified
		elseif($dtime === 1) {
			# Took me hours to catch this, I know, stupid me.
			# http://www.php.net/manual/en/function.ob-gzhandler.php#97385
			#Core::header(304);
			header('Cache-Control: max-age='.(60*60));
		}
		# ob_gzhandler doesn't like getting ob_end_flush calls, so just output
		# the buffer and let PHP's destruction process handle the rest.
		if (
			extension_loaded('zlib')                                &&
			stripos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false){
			# compressing mayhem
			ob_start('ob_gzhandler',(int)Application::config('compression'));
			echo file_get_contents($this->file);
		}
		else echo file_get_contents($this->file);
		stop();
	}

	/**
	 * Routes virtual files to its analogs residing on the APP folder.
	 * Examples:
	 * 
	 * /pub/app.css                > APP_PATH/app.css
	 * /pub/app/aa.[x.y.etc.].css  > APP_PATH/app.aa.[x.,y.etc.].css
	 * /pub/app/aa/[c/d/etc/]b.css > APP_APTH/a/[c/d/etc/]/app.css
	 */
	private function route(){
		$msg = 'Not Found';
		# extract uri pieces to determine file name to look.
		$var  = explode('.', substr($this->uri, 0, strpos($this->uri,$this->ext)-1));
		$dir  = explode('/', array_shift($var));
		$app  = array_shift($dir);
		$name = array_pop($dir).'.'.implode('.', $var);
		# make sure the app exists
		if (!$path = Application::path('',!$app? $name : $app)) parent::error_404($msg);
		$path = pathinfo($path, PATHINFO_DIRNAME).'/'.implode('/',$dir);
		if ($name{strlen($name)-1} == '.') $name = substr($name,0,-1);
		if ($path{strlen($path)-1} == '/') $path = substr($path,0,-1);
		$src = str_replace('..', '.', "$path/$app.$name.{$this->ext}"); # feeling lazy.
		if (!file_exists($src)) parent::error_404($msg);
		# if debug is on, don't compress or cache.
		if (Core::config('debug')) return $this->render($this->file=$src);
		# before doing anything verify there's a scope available for this file.
		$this->scope = Application_View::scope_get($app);
		if (empty($this->scope)) Library::error_503('Service Unavailable');
		# make sure the pub cache folder exists
		if (!file_exists($this->cache)) mkdir($this->cache, 0777, true);
		# if a cached version of the file does not exists or if the mtimes 
		# of both sourced and cached vesions differ, re compress, and render.
		$tmp = $this->cache.pathinfo($src,PATHINFO_BASENAME);
		# declare the constant that will hold included file's path
		define('__EXTERNAL__', $tmp);
		$src_mtime = filemtime($src);
		if (!file_exists($tmp) || filemtime($tmp) != $src_mtime)
			return $this->compress($src, $tmp, $src_mtime);
		# the file has not changed serve cached version.
		$this->file = $tmp;
		$this->render();
	}

	/**
	 * Select compressing method for external file.
	 */
	private function compress($orig, $dest, $mtime){
		$cont = '';
		switch($this->ext){
			case 'css': $cont = $this->cssmin($orig); break;
			case 'js' : $cont =  $this->jsmin($orig); break;
			default   : error('Do not know what to do');
		}
		# save file contents and update modified time.
		file_put_contents($dest, $cont);
		touch($dest, (int)$mtime);
		# make sure there's no record for this file on cache.
		Core::DB()->exec('DELETE FROM cache WHERE "path"=?',$dest);
		# now update the destiny file and render it.
		$this->file = $dest;
		$this->render();
	}


	/**
	 * Minify CSS 
	 * @todo  Add a fallback for when the user decides no to include Minify Lib.
	 *        since it's not a Core lib.
	 */
	private function cssmin($path){
		# Since the CSS is most likely to have php code in it
		# we've to clean only the non-php parts using a callback;
		$cnt = $this->php_apart($path, 'Minify::CSS');
		# parse the file with the same scope as its view analog.
		# return results so it can be rendered.
		return $this->scoper(array(
			'cont' => $cnt,
			'funs' => $this->scope['funs'],
			'vars' => $this->scope['vars'],
			'cons' => $this->scope['cons'],
		));
	}

	/**
	 * Minify JS
	 * @todo  Add a fallback for when the user decides no to include Minify Lib.
	 *        since it's not a Core lib.
	 */
	private function jsmin($path){
		define('_APP_URL',1);
		# eval code first
		$cnt = $this->scoper(array(
			'cont' => file_get_contents($path),
			'funs' => $this->scope['funs'],
			'vars' => $this->scope['vars'],
			'cons' => $this->scope['cons']
		));
		# now minify
		return Minify::js($cnt);
	}


	/**
	 * Us output buffer to parse php AND/OR set scope.
	 */
	private function scoper($_SCOPE = array()){
		ob_start();
		# set scope functions.
		if   (isset($_SCOPE['funs']) && is_array($_SCOPE['funs']))
			foreach($_SCOPE['funs']  as $_V) eval(base64_decode($_V));
		# set scope vars
		if   (isset($_SCOPE['vars']) && is_array($_SCOPE['vars']))
			foreach($_SCOPE['vars']  as $_K=>$_V) $$_K = $_V;
		# set scope constants
		if  (isset($_SCOPE['cons']) && is_array($_SCOPE['cons']))
			foreach($_SCOPE['cons'] as $_K=>$_V) if (!defined($_K)) define($_K,$_V);
		# unset temp vars;
		unset($_K,$_V);
		# eval code
		if (isset($_SCOPE['cont'])) eval('?'.'>'.$_SCOPE['cont']);
		$_SCOPE = ob_get_contents();
		ob_end_clean();
		return $_SCOPE;
	}

	/**
	 * Process only parts of string outside HTML chars.
	 */
	private function php_apart($path, $callback){
		$php = array(T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO,T_CLOSE_TAG);
		$cnt = '';
		$can = 1;
		foreach(token_get_all(file_get_contents($path)) as $token){
			if ( (!is_array($token) || !in_array($token[0],$php)) && $can===1)
				$cnt .= call_user_func($callback, is_array($token)? $token[1] : $token);
			elseif (is_array($token) && in_array($token[0],$php)){
				$can*=-1;
				$cnt.=$token[1];
			}
			elseif($can===-1) $cnt .= is_array($token)? $token[1] : $token;
		}
		return $cnt;
	}

}
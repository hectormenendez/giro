<?php
/**
 * @log  2011/AUG/29 20:24 file renamed due to new convention. [underscore > dot]
 */
class Application_View extends Application_Common {

	/**
	 * View Constuctor
	 * @created 2011/AUG/26 20:20
	 */
	final public function __construct(){
		# convert template name.
		if (!is_string($path = Application::config('template'))) $path = 'html5';
		self::$template =  Application::config('template', HTML.$path.'.html');
		# if run a pseudo constructor if exist.
		if (method_exists($this, '_construct') && is_callable(array($this,'_construct'))) 
			return $this->_construct();
	}

	# user can specify wether the view will produce cache headers
	# using $this->view->cache = true/false from within the Cotroller / View-
	private static $cache = false;

	# user can specify a template path 
	# it will be parsed with Application::path, so the user only needs to
	# specify a filename [not even an extension is needed];
	# the file can be:
	# APP/APP_NAME/filename[.htm]
	# or
	# APP/filename[.htm] << This will have precedence over the latter.
	private static $template = null;

	# user can add head tags byspecifying them using:
	# this->view->tag_{tagname} = value. 
	# or
	# this->view->tag_{tagtype}(tagname, tagcontent).
	private static $tags = array(
		'vars'  => array(),
		'func' => array()
	);

	/**
	 * Method redirector
	 */
	public function __call($name, $args){
		if (!is_string($name)) return false;
		# if user trying to add a tag to html.
		if (stripos(substr($name, 0, 4), 'tag_') !== false){
			# <%content%> is our template divisor, it cannot be replaced.
			if (($tag = substr($name, 4)) != 'content'){
				# prepend the name of this tag to the arguments.
				array_unshift($args, $tag);
				return call_user_func_array(array($this,'tags_add'), $args);
			}
		}
		# there's still no other application for this; return:
		error("Method '$name' does not exist.");
	}

	/**
	 * catch calls to variable aliases.
	 */
	public function __set($key, $val){
		# tag, don't set anything just return the value.
		if (stripos($key, 'tag_')!==false){
			unset($this->$key);
			$key = substr($key, 4);
			# <%content%> is our template divisor, it cannot be replaced.
			if ($key=='content') return null;
			$tmp = self::$tags['vars'][$key] = (string)$val;
			return null;
		}
		# set cache from here.
		elseif ((strtolower($key)=='cache')){
			self::$cache = $val;
			unset($this->cache);
			return null;
		}
		# set template
		elseif(strtolower($key)=='template'){
			unset($this->$key);
			if (!is_string($val = Application::path($val.'.html')))
				$val = Application::config('template');
			self::$template = $val;
			return null;
		}
		# normal variable assigment.
		$this->$key = $val;
		return $this->$key;
	}

	private static $rendered = null;

	/**
	 * View Renderer
	 * Render to browser passing the current view's scope
	 * as global scope [public methods will be translated to functions]
	 *
	 * @param string           Name of view, following the name space:
	 *                             APP_PATH/APP_NAME.{name}.html
	 *
	 * @note                   - No need of adding extension or any path.
	 *                         - If omited, it will look for the default view,
	 *                         - if it doesn't exist, it will just stop execution.
	 *
	 * @log 2011/AUG/26 18:54  - Fixed a bug, trailing "dot" wasn't being added.
	 *                         - Added comments and note.
	 */
	public function render($path = null){
		# this method can only run once.
		if (self::$rendered===true) return false;
		self::$rendered = true;
		# if no view is provided look for the default,
		# if that doesn't exists either, just stop.
		$path = Application::path(is_string($path)? '.'.$path.'.html' : '.html');
		if (!is_string($path) || !file_exists($path)) stop();
		# generate a shared scope.
		# since this view will mostly be shared by external files we've to 
		# be sure all view-variables and functions are available to them too.
		$_SCOPE = $this->scope();
		$_SCOPE['path']  = $path;
		$_SCOPE['cont'] = array($this->template_ini(), null, $this->template_end());
		foreach($_SCOPE['funs'] as $v) eval($v);
		foreach($_SCOPE['vars'] as $k=>$v) $$k=$v;
		unset($k,$v,$this,$path);
		# prepare headers for render.
		Core::headers_remove();
		# set base headers
		header('Content-Type: text/html; charset:UTF-8');
		header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($_SCOPE['path'])).' GMT', true);
		if(self::$cache){
			header('Cache-Control: must-revalidate,max-age='. (60*60*24));
			header('Expires: '.gmdate('D, d M Y H:i:s',time()+(60*60*24)).' GMT');
		} else { 
			Core::header(200);
			header('Cache-Control: must-revalidate,no-cache');
			header('Expires: '.gmdate('D, d M Y H:i:s',time()-(60*60*24*30)).' GMT');
		}
		# parse content with clean scope.
		ob_start();
		include $_SCOPE['path'];
		$_SCOPE['cont'][1] = ob_get_clean();
		# join content together
		$content = implode($_SCOPE['cont']);
		# if available use zlib to compress the generated html.
		# ob_gzhandler doesn't like getting ob_end_flush calls, so just output
		# the buffer and let PHP's destruction process handle the rest.
		if (extension_loaded('zlib')                                &&
			stripos($_SERVER['HTTP_ACCEPT_ENCODING'],'gzip') !== false) {
			ob_start('ob_gzhandler',(int)Application::config('compression'));
			ob_start();
			echo $content;
		}
		# zlib is not available, just echo.
		else echo $content;
		stop();
	}

	/**
	 * Saves the current view scope
	 * Serialize everything to a file, and add to Core database the expiring 
	 * dates, so the garbage control can take care of everything.
	 */
	private function scope(){
		# stores only public variables
		$vars = array();
		foreach($this as $k=>$v) {
			if (false !== strpos($k, ':private')) continue;
			$vars[$k] = $v;
		}
		# stores only user constants
		$cons = get_defined_constants(true);
		$cons = $cons['user'];
		# stores only public methods as standalone functions
		$meth = self::method_extract($this, __CLASS__);
		$funs = array();
		foreach($meth as $k=>$v) $funs[$k] = base64_encode($v);
		# prepares the scope to be saved.
		$scope = serialize(array('funs'=>$funs,'vars'=>$vars,'cons' => $cons)); 
		file_put_contents(TMP.APP_NAME.UUID, $scope);
		if (!is_int($length = Application::config('scope_length')))
			error('Bad Scope Length configuration');
		# insert/update data in database
		$sql = 'INSERT OR REPLACE INTO scope (uuid,appname,expires,updated) VALUES (?,?,?,?)';
		Core::DB()->exec($sql, UUID, APP_NAME, (int)$length, (int)BMK);
		# return only functions and vars, since constants are already loaded.
		return array('funs'=>$meth,'vars'=>$vars);
	}

	/**
	 * Returns an unserialized version of View's Scope
	 *
	 * This method is called by External Library and should never
	 * be used in another context. I put it here for readibilty.
	 */
	public static function scope_get($appname=false){
		# does a scope file even exists?
		if (!file_exists(TMP.$appname.UUID)) return array();
		# does the scope even exist on database?
		$sql = 'SELECT expires FROM scope WHERE uuid=? AND appname=? LIMIT 1';
		$qry = Core::DB()->query($sql,UUID,$appname);
		if (empty($qry)) return array();
		# scope expired.
		if ((int)BMK >= (int)$qry[0]['expires']){
			$sql = 'DELETE FROM scope WHERE uuid=? AND appname=?';
			Core::DB()->exec($sql,UUID,$appname);
			return array();
		}
		return unserialize(file_get_contents(TMP.$appname.UUID));
	}

	private function template_ini(){
		# make sure configs are set.
		if (!is_string(Application::config('tags_jspos')))
			Application::config('tags_jspos','ini');
		# add automagically css & js for the app, unless...
		if (Application::config('tags_auto')){
			if ($path = Application::path('.css'))
				$this->tag_link('stylesheet', PUB_URL.APP_NAME.'.css');
			if ($path = Application::path('.js'))
				$this->tag_js(PUB_URL.APP_NAME.'.js');
		}
		# parse template and include it.
		@ob_end_clean();
		ob_start();
		include self::$template;
		$template = ob_get_clean();
		# replace language declarations with current language key
		$language = Core::language();
		$template = str_ireplace("<%language%>",$language['key'],$template);
		# if there's a title positioner replace it. [and remove it from tagvars]
		if (isset(self::$tags['vars']['title']) && stripos($template, "<%title%>")!==false){
			$title = '<title>'.self::$tags['vars']['title'].'</title>';
			$template = str_ireplace("<%title%>",$title, $template);
			unset(self::$tags['vars']['title']);
		}
		# is there an analytics tag? 
		if (isset(self::$tags['vars']['analytics']) && stripos($template, "<%analytics%>")!==false){
			# pars the analytics template
			if (!file_exists(HTML.'analytics.html')) error('Analytics template not found');
			@ob_end_clean();
			ob_start();
			include HTML.'analytics.html';
			$analytics = ob_get_clean();
			$template = str_ireplace("<%analytics%>",
						str_ireplace("<%code%>", self::$tags['vars']['analytics'], $analytics), $template);
			unset(self::$tags['vars']['analytics']);
		}
		# do we even have a head element declared? 
		if (($pos = stripos($template, '</head>'))!==false){
			$head = array(substr($template, 0, $pos), null, substr($template, $pos));
			$head[1] = '';
			foreach(self::$tags['vars'] as $k=>$v) $head[1].="\t<$k>$v</$k>\n";
			$template = implode($head);
		}
		# add special tags
		$template = $this->tags_write($template);
		# make sure content tag is always lowercase and split content with it.
		$template = str_ireplace('<%content%>','<%content%>', $template);
		$template = explode('<%content%>', $template);
		$this->template_end = $template[1];
		return $template[0];
	}

	private function template_end(){
		return $this->template_end;
	}


	/**
	 * Add HTML tags
	 * Reeplace <%code%> with a tag template specified here.
	 */
	private function tags_add($name, $key='', $val=''){
		$key = (string)$key;
		$val = (string)$val;
		switch($name){
		  case 'og':
			$tag = "<meta property='og:$key' content='$val'>";
			break;
		  case 'fb':
			$tag = "<meta property='fb:$key' content='$val'>";
			break;
		  case 'meta':
			$tag = "<meta name='$key' content='$val'>";
			break;
		  case 'jsini':
		  case 'jsend':
		  case    'js':
			$tag = "<script src='$key'></script>";
			break;
		  case 'link':
			$tag = "<link rel='$key' href='$val'>";
			break;
		  default:
			warning("Invalid Tag '$name'");
		}
		if(!isset(self::$tags['func'][$name])) self::$tags['func'][$name] = array();
		return array_push(self::$tags['func'][$name], $tag);
	}


	private function tags_write($template){
		# if template does not have replacements to do, do nothing.
		if (!preg_match_all('/<%((?!content)\w+)%>/i', $template, $key))
			return $template;
		$tags = &self::$tags['func'];
		# iterate through each match and replace corresponding tag.
		foreach ($key[1] as $i=>$k) {
			# if no matching tag is found remove it from template
			# otherwise, replace with elements using original whitespace.
			$regex   = '/([\t ]*)'.$key[0][$i].'([ \t]*\s?(?<![ \t]))/';
			$replace = '';
			preg_match($regex, $template, $regex);
			# tag found, replicate original whitespace
			if (isset($tags[$k])) foreach($tags[$k] as $tag)
				$replace .= $regex[1].$tag.$regex[2];
			# replace original
			$template = str_replace($regex[0], $replace, $template);
		}
		return $template;
	}

	/**
	 * Method and Vars Getter
	 * This should be on Library, but haven't found a use for it there... yet.
	 */
	private static function method_extract($class=__CLASS__, $exclude=__CLASS__){
		if (!@class_exists($exclude, false))
			error('Excluded parent class must be expressed as string.');
		try   { $reflect = new ReflectionClass($class); }
		catch (Exception $e) { return array(); }
	 	$methods = array();
	 	# get only methods that are not defined here.
	 	$m = array_diff(get_class_methods($class), get_class_methods($exclude));
	 	foreach ($m as $methodname){
	 		$method = $reflect->getMethod($methodname);
			$file = Core::file($method->getFileName());
	 		if ($method->isPrivate() || $method->isProtected()) continue;
	 		# ignore methods starting with underscore
	 		if ($methodname[0] == '_') continue;
	 		# obtain method's source [removing comments and double spacing]
	 		$src = '';
	 		for($i = $method->getStartLine()-1; $i < $method->getEndLine(); $i++)
	 			$src .= $file[$i];
	 		$src = trim(preg_replace('/\s+/',' ', Core::nocomments($src)));
	 		if (empty($src)) continue; #{
	 		#	var_dump($method->getStartLine(), $method->getEndLine());
	 		#}
	 		$rx = '/(\s*(?:final|abstract|static|public|private|protected)\s)/i';
	 		$old = substr($src, 0, strpos($src, '{'));
	 		$new = preg_replace($rx, '',$old);
	 		$methods[$methodname] = str_replace($old, $new, $src);
	 	}
	 	return $methods;
	}

	#public function __destruct(){
	#	# destructors run even when script halted with die OR exit,
	#	# when any error is triggered or stop is called, a constant is set
	#	# so the user can prevent the execution like I do here.
	#	#
	#	# Again: If the script ends with a raw die() or exit() the constant
	#	#        won't be defined and this script will execute, use stop().
	#	#
	#	defined('DIED') && die;
	#	# render default view if the user didn't specify one.
	#	#$this->render();
	#}

}
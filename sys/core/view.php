<?php
/**
 * View Manager.
 * This methods will be available as normal functions inside the view file
 * private, protected, and methods starting with an underscore will be ignored.
 *
 * @note Remember that these function will run in the global scope, 
 * 		 so dont treat them as class members, technically speaking, they won't 
 *		 be inside any class.
 *
 * @note DO NOT USE MULTI-LINE COMMENTS INSIDE FUNCTIONS. sanitization hasn't
 		 been implemented yet.
 */
class View extends Application {

	public static $tags = array();


	/**
	 * Allow the user to store variables indistinctively.
	 * They will be later put un view's scope.
	 */
	public function &__set($key, $val){
		parent::$__vars[$key] = $val;
		return parent::$__vars[$key];
	}

	/**
	 * Method redirector
	 * Just works for taggin functions.
	 */
	public function __call($name, $args){
		# only acceot tag adds
		if (!is_string($name) || stripos(substr($name, 0, 4), 'tag_') === false)
			return null;
		array_unshift($args, substr($name, 4));
		call_user_func_array('self::__tag_add', $args);
	}


	/**
	 * HTML5 Template
	 * Adds the basic tags needed for a html5 experience.
	 *
	 * @todo Add the HTML folder as a constant. [templates]
	 * @todo Language and Charset, set by the framework.
	 */
	public static function html5($title = ''){
		static $html5 = false;
	
		$jspos = Library::config('js_position', null, 'view');
		if (!$jspos) $jspos = 'ini';

		if (is_string($html5)) return;
		$html5 = Library::file(SYS.'html/html5.html', false);
		View::__tag_add("js$jspos", '//code.jquery.com/jquery.min.js');
		# if existan, add JS and CSS
		if (file_exists(APP_PATH.APP_NAME.'.css'.EXT))
			View::__tag_add('link', 'stylesheet', URL_PUB.APP_NAME.'.css');
		if (file_exists(APP_PATH.APP_NAME.'.js'.EXT))
			View::__tag_add("js$jspos", URL_PUB.APP_NAME.'.js');		
		# this vars should be set by the framework, but until then, they'll be fixed
		$vars = array(
			'lang' => 'es-mx',
			'charset' => 'utf-8',
			'title' => $title,
			'favicon' => PATH.'pub/favicon.ico'
		);
		foreach ($vars as $k => $v) $html5 = str_replace("%$k%", $v, $html5);
		# write tags and split the file.
		$html5 = explode('<%content%>', View::__tag_write($html5));
		# write the latter piece after parsing the view;
		View::queue(array('View::_html5', $html5[1]));
		return $html5[0];
	}
	public static function _html5($html){ echo $html; }

	/**
	 * Add HTML tags
	 * Reeplace <%code%> with a tag template specified here.
	 */
	public static function __tag_add($name, $key = '', $cont = ''){
		if (!isset(self::$tags[$name])) self::$tags[$name] = array();
		switch ($name) {
		  case 'meta':
		    $tag = "<meta name='".((string)$key)."' content='".((string)$cont)."'>\n";
		  	break;
		  case 'jsini':
		  case 'jsend':
		  	$tag = "<script src='".((string)$key)."'></script>\n";
		  	break;
		  case 'link':
		  	$tag = "<link rel='".((string)$key)."' href='".((string)$cont)."'>\n";
		  	break;
		  	
		}
		self::$tags[$name][] = $tag;

	}

	/**
	 * Write Tags to buffer.
	 * Replaces all matches of tags in template.
	 */
	public static function __tag_write($content = ''){
		# Replaces Template tags with user sent ones.
		if (!preg_match_all('/<%((?!content)\w+)%>/i', (string)$content, $match))
			return $content;
		foreach($match[1] as $i=>$key){
			$rep = '';
			if (isset(self::$tags[$key])){
				$first = true;
				foreach(self::$tags[$key] as $tag){
					# add a tab so after the first write.
					$rep .= $first || $key=='jsend'? $tag : "\t".$tag;
					$first = false;
				}
			}
			$content = str_replace($match[0][$i],$rep, $content);
		}
		# remove empty lines.
		$content = preg_replace('/\s+$/m', '', $content);
		return $content;
	}
}
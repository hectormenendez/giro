<?php
/**
 * View
 * public non-underscored methods will be available as normal functions inside 
 * the view file.
 *
 * @note Remember that these function will run in the global scope, 
 * 		 so dont treat them as class members, technically speaking, they won't 
 *		 be inside any class.
 *
 * @note DO NOT USE MULTI-LINE COMMENTS INSIDE FUNCTIONS. sanitization hasn't
 		 been implemented yet.
 */
class View extends ApplicationCommon {

	public static $__vars = array();
	public static $__tags = array();

	/**
	 * Allow the user to store variables indistinctively.
	 * They will be later put un view's scope.
	 */
	public function &__set($key, $val){
		self::$__vars[$key] = $val;
		return self::$__vars[$key];
	}

	/**
	 * Method redirector
	 * Just works for taggin functions, falls back to parent's.
	 */
	public function __call($name, $args){
		# only acceot tag adds, fall back to parent's call magin method.
		if (!is_string($name) || stripos(substr($name, 0, 4), 'tag_') === false)
			return parent::__call($name, $args);

		array_unshift($args, substr($name, 4));
		call_user_func_array('self::__tag_add', $args);
	}

	/**
	 * HTML Template
	 * Adds the basic tags needed for a html experience.
	 *
	 * @todo Add the HTML folder as a constant. [templates]
	 * @todo Language and Charset, set by the framework.
	 */
	public static function html($title = ''){
		static $html = false;
	
		$jspos = Library::config('js_position', null, 'application');
		if (!$jspos) $jspos = 'ini';

		if (is_string($html)) return;
		$html = Library::file(SYS.'html/html5.html', false);
		View::__tag_add("js$jspos", '//code.jquery.com/jquery.min.js');
		# if existan, add JS and CSS
		if (file_exists(APP_PATH.APP_NAME.'.css'.EXT))
			View::__tag_add('link', 'stylesheet', PUB_URL.APP_NAME.'.css');
		if (file_exists(APP_PATH.APP_NAME.'.js'.EXT))
			View::__tag_add("js$jspos", PUB_URL.APP_NAME.'.js');		
		# this vars should be set by the framework, but until then, they'll be fixed
		$vars = array(
			'lang' => 'es-mx',
			'charset' => 'utf-8',
			'title' => $title,
			'favicon' => PATH.'pub/favicon.ico'
		);
		foreach ($vars as $k => $v) $html = str_replace("%$k%", $v, $html);
		# write tags and split the file.
		$html = explode('<%content%>', View::__tag_write($html));
		# write the latter piece after parsing the view;
		Application::queue('View::_html', $html[1]);
		return $html[0];
	}

	/**
	 * Closes html template.
	 */
	public static function _html($html){
		echo $html;
	}

	/**
	 * Add HTML tags
	 * Reeplace <%code%> with a tag template specified here.
	 */
	public static function __tag_add($name, $key = '', $cont = ''){
		if (!isset(self::$__tags[$name])) self::$__tags[$name] = array();
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
		  default:
		  	warning("Invalid Tag '$name'");
		}
		self::$__tags[$name][] = $tag;

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
			if (isset(self::$__tags[$key])){
				$first = true;
				foreach(self::$__tags[$key] as $tag){
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
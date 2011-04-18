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

	/**
	 * Allow the user to store variables indistinctively.
	 * They will be later put un view's scope.
	 */
	public function &__set($key, $val){
		parent::$__vars[$key] = $val;
		return parent::$__vars[$key];
	}

	public function __call($name, $args){
		# only acceot tag adds
		if (!is_string($name) || stripos(substr($name, 0, 4), 'tag_') === false)
			return null;
		array_unshift($args, substr($name, 4));
		call_user_func_array('self::__addtag', $args);
	}

	public static $tags = array();

	public static function __addtag($name, $key = '', $cont = ''){
		if (!isset(self::$tags[$name])) self::$tags[$name] = array();
		switch ($name) {
		  case 'meta':
		    $tag = "<meta name='".((string)$key)."' content='".((string)$cont)."'>\n";
		  	break;
		  case 'jsini':
		  case 'jsend':
		  	$tag = "<script src='".((string)$key)."'></script>";

		}
		self::$tags[$name][] = $tag;

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
		if (is_string($html5)) return;
		$html5 = Library::file(SYS.'html/html5.html', false);
		View::__addtag('jsini', '//code.jquery.com/jquery.min.js');
		# this vars should be set by the framework, but until then, they'll be fixed
		$vars = array(
			'lang' => 'es-mx',
			'charset' => 'utf-8',
			'title' => $title,
			'favicon' => PATH.'pub/favicon.ico'
		);
		foreach ($vars as $k => $v) $html5 = str_replace("%$k%", $v, $html5);

		if (preg_match_all('/<%((?!content)\w+)%>/i', $html5, $match)){
			foreach($match[1] as $i=>$key){
				$rep = '';
				if (isset(View::$tags[$key]))
					foreach(View::$tags[$key] as $tag) $rep .= $tag;
				$html5 = str_replace($match[0][$i],$rep, $html5);
			}
		}
		$html5 = explode('<%content%>', preg_replace('/\s+$/m', '', $html5));
		View::queue(array('View::_html5', $html5[1]));
		return $html5[0];
	}

	public static function _html5($html){
		echo $html;
	}

}
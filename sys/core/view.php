<?php
/**
 * @todo rename __tag methods and make sure they are not passed in App::render()
 */
class View extends ApplicationCommon {

	public static $__vars = array();
	public static $__tags = array(
		'view' => array(),
		'app'  => array()
	);

	/**
	 * Allow the user to store variables indistinctively.
	 * They will be later put un view's scope.
	 */
	public function &__set($key, $val){
		self::$__vars[$key] = $val;
		$this->$key = $val;
		return $this->$key;
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
	 * Add HTML tags
	 * Reeplace <%code%> with a tag template specified here.
	 */
	public static function __tag_add($name, $key = '', $cont = ''){
		# if the request comes from an instanced application, add the tags 
		# in reverse order, so the tags appear after the ones instanced by 
		# the templates on the View class.
		$type = stripos(get_called_class(), APP_NAME) !== false? 'app' : 'view';
		if (!isset(self::$__tags[$type][$name]))
			self::$__tags[$type][$name] = array();
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
		array_push(self::$__tags[$type][$name], $tag);
	}

	/**
	 * Write Tags to buffer.
	 * Replaces all matches of tags in template.
	 */
	public static function __tag_write($content = ''){
		# merge and get the right order of tags
		$tags = array_merge_recursive(self::$__tags['view'], self::$__tags['app']);
		# Replaces Template tags with user sent ones.
		if (!preg_match_all('/<%((?!content)\w+)%>/i', (string)$content, $match))
			return $content;
		foreach($match[1] as $i=>$key){
			$rep = '';
			if (isset($tags[$key])){
				$first = true;
				foreach($tags[$key] as $tag){
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
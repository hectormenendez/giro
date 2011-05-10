<?php
abstract class Utils extends Library {

	/**
	 * Url prettify
	 * Replaces non-us-ascii characters so the string 
	 * can be used as SEO friendly url.
	 */
	public static function urlify($url=false){
		if (!is_string($url)) error('Expecting String');
		# substitutes anything but letters, numbers and '_' 
		$url = preg_replace('~[^\\pL0-9_]+~u', '-', $url); 
		$url = trim($url, "-");
 		# TRANSLIT does the whole job
		$url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
		$url = strtolower($url);
		# keep only letters, numbers, '_' and separator
		$url = preg_replace('~[^-a-z0-9_]+~', '', $url);
		return $url;
	}

}
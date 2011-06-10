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
		setlocale(LC_CTYPE, 'en_US.utf8');
		$url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
		$url = strtolower($url);
		# keep only letters, numbers, '_' and separator
		$url = preg_replace('~[^-a-z0-9_]+~', '', $url);
		return $url;
	}

	/**
	 * Encryptor / Decryptor
	 * Replaces string according to key, and obfuscates a little bit by reversing
	 * al characters hex version. It's not bullet proof, but it will hold ok.
	 *
	 * @param [string]$action 	encrypt/decrypt, nothing more, nothing less.
	 * @param [string]$str		The string to encrypt
	 * @param [string]$key 		the secret key.
	 */
	public static function cryptor($action=false, $str=false, $key=false){
		if (($action != 'encrypt' && $action != 'decrypt') ||
			!is_string($str) || !is_string($key) || empty($str) || empty($key))
			error('All arguments are required and type string in Cryptor.');
		$encrypt = function ($str, $key){
			$str = le_crypt(strrev($str), $key);
			$lst = strlen($str);
			$res = '';
			for ($i=0; $i < $lst; $i++){
				if (strlen($tmp = dechex(ord($str[$i]))) == 1) $tmp = '0'.$tmp;
				$res .= strrev($tmp);
			}
			return $res;
		};
		$decrypt = function ($str, $key){
			$lst = strlen($str);
			$res = '';
			for ($i=0; $i < $lst; $i+=2) $res .= chr(hexdec(strrev(substr($str,$i, 2))));
			return strrev(le_crypt($res,$key));
		};
		if (!function_exists('le_crypt')):

		# the magic happens here.
		function le_crypt($str, $key){
			$res = '';
			$len_key = strlen($key);
			$len_str = strlen($str);
			$i = 0;
			for(; $i < $len_str; $i++)
				$res.= chr((ord($str[$i])^ord($key[$i % $len_key])) & 0xFF); #here
			return $res;		
		}
		endif;

		# redirect
		return $$action($str, $key);
	}

}

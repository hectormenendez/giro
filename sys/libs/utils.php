<?php
abstract class Utils extends Library {

	/**
	 * Url prettify
	 * Replaces non-us-ascii characters so the string 
	 * can be used as SEO friendly url.
	 */
	public static function urlify($url=false, $spacer='-'){
		if (!is_string($url)) error('Expecting String');
		# substitutes anything but letters, numbers and '_' 
		$url = preg_replace('~[^\\pL0-9_]+~u', $spacer, $url); 
		$url = trim($url, $spacer);
 		# TRANSLIT does the whole job
		setlocale(LC_CTYPE, 'en_US.utf8');
		$url = iconv("utf-8", "us-ascii//TRANSLIT", $url);
		$url = strtolower($url);
		# keep only letters, numbers, '_' and separator
		$url = preg_replace('~[^-a-z0-9_]+~', '', $url);
		return $url;
	}

	const palabras_no = '|a|e|y|o|u|si|tambien|tengo|tenia|tendre|tienes|tienen|tuve|tuviste|tuvieron|tendre|tendras|tendran|tenemos|tuvimos|tendremos|sido|dare|daras|daremos|dimos|di|diste|daras|daran|dieron|dan|das|estamos|estas|estoy|estan|estaremos|estuvimos|estuviste|estaran|estuvieron|estuve|di|dio|das|pido|pides|piden|pedimos|pediste|pedir|damos|dan|dimos|vas|voy|vamos|van|vayan|vayamos|ya|ha|he|has|han|hemos|heme|habra|habras|habran|habremos|no|un|unos|cuando|llego|llegas|llegan|llegamos|quiza|pasa|pasas|pasan|quizas|han|has|mejor|peor|siempre|nunca|igual|hemos|del|al|ni|asi|etc|solo|sabe|sabes|saben|mis|nuestros|tus|mas|menos|entre|por|soy|eres|somos|son|hoy|ayer|manana|porque|porques|pero|peros|es|fue|sera|seran|seras|seremos|ser|ante|bajo|cabe|como|con|contra|de|desde|durante|en|entre|excepto|hacia|hasta|mediante|para|por|pro|seg|sin|so|sobre|tras|versus|via|yo|mi|conmigo|tu|vos|usted|ti|contigo|el|ella|ello|si|consigo|nosotros|nosotras|ustedes|vosotros|vosotras|ellos|ellas|consigo|me|nos|te|os|lo|la|le|se|los|las|les|este|esta|esto|estos|estas|ese|esa|eso|esos|esas|aquel|aquella|aquello|aquellos|aquellas|mio|mia|mios|mias|tuyo|tuya|tuyos.tuyas|suyo|suya|suyos|suyas|nuestro|nuesta|nuestros|nuestras|vuestro|vuestra|vuestros|vuestras|que|quien|quienes|cual|cuales|cuanto|cuantos|cuanta|cuantas|uno|una|unos|unas|alguno|alguna|algunos|algunas|algo|ninguno|ninguna|ningunos|ningunas|nada|poco|poca|pocos|pocas|escaso|escasa|escasos|escasas|mucho|mucha|muchos|muchas|demasiado|demasiada|demasiados|demasiadas|todo|toda|todos|todas|vario|varia|varios|varias|otro|otra|otros|otras|mismo|misma|mismo|mismos|mismas|tan|tanto|tanta|tantos|tantas|alguien|nadie|cualquiera|cualesquiera|quienquiera|qienesquiera|demas|';
	public static function palabras($texto=false, $limit=false){
		# remove foreign characters
		setlocale(LC_CTYPE, 'en_US.utf8');
		#$texto = "    ÑEÑEÑEÑEÑE    \n\r\s\t\t     a12431234           jajajajá";
		$texto = preg_replace('~[^\\pL0-9_]+~u',' ',(string)$texto); 
		$texto = trim(preg_replace('/[^a-z\s]/',' ',strtolower(iconv("utf-8", "us-ascii//TRANSLIT", $texto))));
		# extract words, and remove unwanted ones.
		preg_match_all('/\w+/', $texto, $words);
		$words = array_filter($words[0],function($a){
			return strpos(Utils::palabras_no, "|$a|") === false;
		});
		# count repetitions and filter out non-repeated ones.
		$words = array_filter(array_count_values($words),function($a){ return $a > 1; });
		arsort($words);
		return (int)$limit? array_slice($words, 0, (int)$limit) : $words;
	}


	/**
	 * id shortener, two ways.
	 *
	 * @param $id [mixed] int to coded strings and vice versa.
	 */
	public static function urlmin($id=false){
		if (!is_int($id) && !is_string($id)) error('Expecting String or Integer');
		$alpha = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-';
		# if integer given, encode;
		if (is_int($id)){
			$st = '';
			do { 
				$rm = $id % 64;
				$id  = ($id - $rm) / 64;
				$st = $alpha{$rm}.$st;
			} while($id>0);
			return $st;
		}
		# string, decode.
		$int = 0; 
		$id  = strrev($id);
		for ($i=0; $i<strlen($id); $i++){
			if (($pos = strpos($alpha, $id{$i})) === false) error('Invalid String');
			$exp = pow(64, $i);
			if ($i == 0) $int = $pos;
			else $int += $pos*$exp;
		}
		return $int;			
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
		if (!is_string($key)) $key = Core::config('secret');
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

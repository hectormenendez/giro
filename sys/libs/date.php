<?php
abstract class Date extends Library{

	public static function _construct(){
		date_default_timezone_set('America/Cancun');
	}

	/**
	 * makesure a valid unix timestamp is inserted
	 */
	public static function validateunix($timestamp=null){
	    return ((string) (int) $timestamp === (string)$timestamp) 
	        && ($timestamp <= PHP_INT_MAX)
	        && ($timestamp >= ~PHP_INT_MAX);
	}

	public static function convert($format='U', $string=null){
		if (!is_string($string)){
			# if unix time given, convert
			if (is_int($string))
				$string = DateTime::createFromFormat('U', $string, new DateTimeZone('America/Cancun'));
			# default to today
			else $string = date(DATE_W3C);
		}
		if (!is_string($format)) return false;
		try {
			$dt = !is_string($string)? $string : new DateTime($string);
			$dt = $dt->format($format);
			return $dt;
		} catch (Exception $e) { 
			return false; 
		}
	}


	public static function toSpanish($string=null, $format='F j, Y. g:ia'){
		if (!is_string($string)) $string = date(DATE_W3C);
		if (!is_string($format)) return false;
		try { $dt = new DateTime($string);	} catch (Exception $e) { return false; }
		$month = $dt->format('F');
		switch($month){
			case 'January'  : $mes = 'Enero'     ; break;
			case 'February' : $mes = 'Febrero'   ; break;
			case 'March'    : $mes = 'Marzo'     ; break;
			case 'April'    : $mes = 'Abril'     ; break;
			case 'May'      : $mes = 'Mayo'      ; break;
			case 'June'     : $mes = 'Junio'     ; break;
			case 'July'     : $mes = 'Julio'     ; break;
			case 'August'   : $mes = 'Agosto'    ; break;
			case 'September': $mes = 'Septiembre'; break;
			case 'October'  : $mes = 'Octubre'   ; break;
			case 'November' : $mes = 'Noviembre' ; break;
			case 'December' : $mes = 'Diciembre' ; break;
			default: return false;
		}
		$day = $dt->format('l');
		switch($day){
			case 'Sunday'    : $dia = 'Domingo'  ; break;
			case 'Monday'    : $dia = 'Lunes'    ; break;
			case 'Tuesday'   : $dia = 'Martes'   ; break;
			case 'Wednesday' : $dia = 'Miércoles'; break;
			case 'Thursday'  : $dia = 'Jueves'   ; break;
			case 'Friday'    : $dia = 'Viernes'  ; break;
			case 'Saturday'  : $dia = 'Sábado'   ; break;
			default: return false;
		}
		$date = str_replace($day, $dia, str_replace($month, $mes, $dt->format($format)));
		# day contractions
		$date = str_replace(substr($day,0,3), substr($dia,0,3), $date);
		# month contractions
		$date = str_replace(substr($month,0,3), substr($mes,0,3), $date);
		return $date;
	}

	public static function fromSpanish($date=null, $format='F j, Y. g:ia'){
		if (!is_string($date)) $date = date(DATE_W3C);
		if (!is_string($format)) return false;
		$date = strtolower($date);
		$words = array(
			'enero'      => 'January',
			'febrero'    => 'February',
			'marzo'      => 'March',
			'abril'      => 'April',
			'mayo'       => 'May',
			'junio'      => 'June',
			'julio'      => 'July',
			'agosto'     => 'August',
			'septiembre' => 'September',
			'octubre'    => 'October',
			'noviembre'  => 'November',
			'diciembre'  => 'December',
			'domingo'    => 'Sunday',
			'lunes'      => 'Monday',
			'martes'     => 'Tuesday',
			'miercoles'  => 'Wednesday',
			'miércoles'  => 'Wednesday',
			'jueves'     => 'Thursday',
			'viernes'    => 'Friday',
			'sabado'     => 'Saturday',
			'sábado'     => 'Saturday'
		);
		foreach ($words as $key=>$word){
			if (strpos($date, $key) !== false)
				$date = str_replace($key, $word, $date);
			elseif (strpos($date, ($key = substr($key,0,3))) !== false)
				$date = str_replace($key, $word, $date);
		}
		try { $dt = new DateTime($date);	} catch (Exception $e) { return false; }
		return $dt->format($format);
	}

}
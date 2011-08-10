<?php 
class Image extends Library {

	private static $info  = array();

	/**
	 * Get an image resource
	 *
	 * @param $path [mixed] Path string or image resource.
	 */
	public static function &get($path=false){
		# if an object is sent, make sure it exists on our records.
		if (is_resource($path)) return $path;
		# a string is sent, make sure it's a valid image path.
		if (
			!is_string($path)              || 
			!file_exists($path)            ||
			!($info = getimagesize($path))   # will return false if not an image
		) error('Not a valid image path');
		# valid path, so return correct type of instance
		switch($info[2]){
			case IMAGETYPE_JPEG : $inst = imagecreatefromjpeg($path); break;
			case IMAGETYPE_GIF  : $inst = imagecreatefromgif ($path); break;
			case IMAGETYPE_PNG  : $inst = imagecreatefrompng ($path); break;
			default: error('Only JPEG, GIF and PNG is supported');
		}
		$name = (string)$inst;
		# store the info.
		self::$info[$name] = array(
			'width'  => $info[0],
			'height' => $info[1],
			'type'   => $info[2],
			'mime'   => $info['mime'],
			'bits'   => $info['bits'],
			'path'   => $path,
			'inst'   => &$inst
		);
		return $inst;
	}

	/**
	 * Retrieves information about given image resource
	 *
	 * @param [resource]
	 */
	public static function info($image=false, $instance=false){
		if (isset(self::$info[$name = (string)$image])){
			$instance = $instance? self::$info[$name]['inst'] : self::$info[$name];
			return $instance;
		}
		error("Invalid Resource: $name");
	}

	/**
	 * Outputs image info to buffer or to a file
	 *
	 * @param $image [mixed] Path string or gd image resource
	 * @param $path  [mixed] if null: output to buffer, string: to path,
	 *                       everything else: save to same path.
	 */
	public static function output($image=false, $path=null, $type=IMAGETYPE_PNG){
		$image = self::get($image);
		$name = (string)$image;
		# use the same declared on info? 
		if (!is_null($path) && !is_string($path) && isset(self::$info[$name]['path']))
			$path = self::$info[$name]['path'];
		# if there's no info about the image, default to png.
		$type = isset(self::$info[$name]['type'])? self::$info[$name]['type'] : $type;
		switch ($type) {
			case IMAGETYPE_JPEG : imagejpeg($image, $path); break;
			case IMAGETYPE_GIF  : imagegif ($image, $path); break;
			case IMAGETYPE_PNG  : imagepng ($image, $path); break;
			default: error('Only JPEG, GIF and PNG is supported');
		}
		self::destroy($image);
	}

	/**
	 * Retrieve most common colors in image.
	 */
	public static function colors($image=false){
		$image = self::get($image);
		$info  = self::info($image);
		if (
			($width  = $info['width'])  > 100 || 
			($height = $info['height']) > 100
		) error('Image must be exactly 100x100; resize it first.');
		$pixel  = function($color){ return ($px = intval(($color+15)/32)*32) >= 256? 240 : $px; };
		for ($y=0; $y < $height; $y++){
			for ($x=0; $x < $height; $x++){
				$rgb   = imagecolorsforindex($image, imagecolorat($image, $x, $y));
				$color = '';
				foreach($rgb as $key => $val){
					if ($key == 'alpha') continue;
					$color.= substr('0'.dechex($pixel($val)), -2);
				}
				$hex[] = $color;
			}
		}
		$hex = array_count_values($hex);
		natsort($hex);
		return array_reverse($hex, true);
	}

	/**
	 * Resize to any width
	 *
	 * @param $image  [mixed] Path string or gd image resource.
	 * @param $width  [int]   Desired width in pixels
	 * @param $height [int]   Desired height in pixels
	 * @param $save   [mixed] Wether to save the file or not, 
	 *                        can be a path string or bool for the same location.
	 */
	public static function resize($image=false, $width=false, $height=false, $save=null){
		$image  = self::get($image);
		$info   = self::info($image);
		$w      = $info['width'];
		$h      = $info['height'];
		$width  = !is_int($width)?  $w : $width;
		$height = !is_int($height)? $h : $height;
		$new    =  imagecreatetruecolor($width, $height);
		self::resample($image, $new, 0, 0, $w, $h, 0, 0, $width, $height);
		if ($save) self::output($new, $save);
		return $new;
	}

	/**
	 * Resize keeping aspect ratio [width]
	 *
	 * @param $image  [mixed] Path string or gd image resource.
	 * @param $width  [int]   Desired width in pixels
	 * @param $save   [mixed] Wether to save the file or not, 
	 */
	public static function towidth($image=false, $width=false, $save=null){
		if (!is_int($width) || !$width) error('Must provide an integer for width');
		$image  = self::get($image);
		$info   = self::info($image);
		$w      = $info['width'];
		$h      = $info['height'];
		$height = (int)(($width / $w) * $h);
		return self::resize($image, $width, $height, $save);
	}

	/**
	 * Resize keeping aspect ratio [width]
	 *
	 * @param $image  [mixed] Path string or gd image resource.
	 * @param $height [int]   Desired height in pixels
	 * @param $save   [mixed] Wether to save the file or not, 
	 */
	public static function toheight($image=false, $height=false, $save=null){
		if (!is_int($height) || !$height) error('Must provide an integer for height');
		$image  = self::get($image);
		$info   = self::info($image);
		$w      = $info['width'];
		$h      = $info['height'];
		$width = (int)(($height / $h) * $w);
		return self::resize($image, $width, $height, $save);
	}

	/**
	 * Crop image
	 *
	 * @param $image  [mixed] Path string or gd image resource.
	 * @param $left   [int]   where to stat the cropping X axis.
	 * @param $top    [int]   where to stat the cropping Y axis.
	 * @param $width  [mixed] Desired width in pixels, can be a string specifying a percentage.
	 * @param $height [mixed] Desired height in pixels, can be a string specifying a percentage.
	 * @param $save   [mixed] Wether to save the file or not, 
	 *                        can be a path string or bool for the same location.
	 */
	public static function crop($image=false, $left=0, $top=0, $width='100%', $height='100%', $save=null){
		$image  = self::get($image);
		$info   = self::info($image);
		$w      = $info['width'];
		$h      = $info['height'];
		$left   = self::argpercent($left,   $w);
		$top    = self::argpercent($top,    $h);
		$width  = self::argpercent($width,  $w); 
		$height = self::argpercent($height, $h);
		$new    =  imagecreatetruecolor($width-$left, $height-$top);
		self::resample($image, $new, 0, 0, $w, $h, $left, $top, $w, $h);
		if ($save) self::save($new, $save);
		return $new;
	}
	/**
	 * Oh, c'mon bare with me, I'm in a hurry and didn't want to write the whole thing.
	 * this was coded for a very specific work.
	 */
	public static function gradient_alpha_horizontal_solid($width=false, $height=false, $color=0){
		if (!is_int($width) || !is_int($height)) error('Wrong dimentions');
		$color = self::hex2rgb($color);
		$img = imagecreatetruecolor($width, $height);
		// create a transparent background
		$bg  = imagecolorallocatealpha($img, 255, 0, 255, 127);
		imagefill($img, 0, 0, $bg);
		for ($i=0; $i < $width; $i++){
			$alpha = floor(($i*127)/$width);
			$c = imagecolorallocatealpha($img, $color['R'], $color['G'], $color['B'], $alpha);
			imageline($img, $i, 0, $i, $height, $c);
		}
		imagealphablending($img,false);
		imagesavealpha($img, true);
		return $img;
	}

	/**
	 * This is a freaking quick implementation for image mergin, expects two image resources
	 * no continuity.
	 */
	public static function merge($dst=false, $src=false, $dstX=0, $dstY=0, $srcX=0, $srcY=0){
		if (!is_resource($src) || !is_resource($dst)) error('Resource is expected');
		imagealphablending($src, 1);
		imagealphablending($dst, 1);
		imagecopy($dst, $src, $dstX, $dstY, $srcX, $srcY, imagesx($src), imagesy($src));
		return $dst;
	}


	/**
	 * Destroys the image from $info and releases memory
	 *
	 * @param $image [resource] The gd resource to destroy.
	 */
	public static function destroy($image){
		$image = self::get($image);
		if (isset(self::$info[(string)$image]))
			unset(self::$info[(string)$image]);
		imagedestroy($image);
	}

	/**
	 * Updates an image resource on the self::$info array
	 * and gets rid of the old element.
	 *
	 * @param $old [resource] the original gd resource.
	 * @param $new [resource] the new gd resource.
	 */
	private static function update(&$old, &$new){
		$old_name = (string)$old;
		$new_name = (string)$new;
		self::$info[$new_name] = self::$info[$old_name];
		// update values
		self::$info[$new_name]['width']  = imagesx($new);
		self::$info[$new_name]['height'] = imagesy($new);
		self::$info[$new_name]['inst']   = &$new;
		self::destroy($old);
	}

	/**
	 * A wrapper for imagecopyresampled, also updating info on record.
	 *
	 * @param $img   [resource] The image resource to be resized.
	 * @param $new   [resource] The new image resource holder.
	 * @param $srcX1 [int]      Source first horizontal position.
	 * @param $srcY1 [int]      Source first vertical position.
	 * @param $srcX2 [int]      Source second horizontal position.
	 * @param $srcY2 [int]      Source second vertical position.
	 * @param $dstX1 [int]      Destiny first horizontal position.
	 * @param $dstY1 [int]      Destiny first vertical position.
	 * @param $dstX2 [int]      Destiny second horizontal position.
	 * @param $dstY2 [int]      Destiny second vertical position.
	 */
	private static function resample($img, $new, $srcX1, $srcY1, $srcX2, $srcY2, $dstX1, $dstY1, $dstX2, $dstY2){
		imagecopyresampled($new, $img, $dstX1, $dstY1, $srcX1, $srcY1 ,$dstX2, $dstY2, $srcX2, $srcY2);
		self::update($img, $new);
	}

	/**
	 * Just a shortcut for an aritmethical operation
	 * retrive the $arg percentage of $full.
	 */
	private static function argpercent($arg, $full){
		// if percentage sent in argument convert it
		if (is_string($arg) && substr($arg, -1)=='%') {
			return (int)(((int)$arg * $full)/100);
		} elseif (!is_int($arg)) error("Invalid Argument $width");
		return $arg;
	}

	/**
	 * Convert hex to an rgb array
	 */
	public static function hex2rgb($hex=false){
		if (!is_string($hex)) error('Hex String Expected');
		// cleanup string
		$hex = preg_replace("/[^0-9A-Fa-f]/", '', $hex);
		$arr = array();
		$len = strlen($hex);
		// if a normal hex is sent just do a [faster] bitwise conversion.
		if ($len == 6){
			$val = hexdec($hex);
			$arr['R'] = 0xFF & ($val >> 0x10);
			$arr['G'] = 0xFF & ($val >> 0x8);
			$arr['B'] = 0xFF &  $val;
		// shorthand [a wee bit slower]
		} elseif ($len == 3){
	        $arr['R'] = hexdec(str_repeat(substr($hex, 0, 1), 2));
	        $arr['G'] = hexdec(str_repeat(substr($hex, 1, 1), 2));
	        $arr['B'] = hexdec(str_repeat(substr($hex, 2, 1), 2));
		} else error('Invalid Hex');
		return $arr;
	}

}
?>
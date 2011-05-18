<?php

return array(

'core' => array(
	'debug'			=> true,
	'error'			=> true,
	'route_error'	=> 'error',
	'uri_sufix'	=> '.html',
	'mime-types'	=> array(
		'css'	=> 'text/css',
		'js'	=> 'application/x-javascript',
		'jpeg'  => 'image/jpeg',
		'jpg'	=> 'image/jpg',
		'png'	=> 'image/png',
		'gif'	=> 'image/gif',
		'eot'	=> 'application/vnd.ms-fontobject',
		'otf'	=> 'font/otf',
		'ttf'	=> 'font/ttf',
		'svg'	=> 'image/svg+xml',
		'woff'	=> 'application/octet-stream'
	)
),
'application' 	=> array(
	'clean_timeout'	=> 20,				  # time to wait before wiping out tmp data
	'default'	=> 'main',			  # default application
	'safe_chars'	=> 'a-zA-Z0-9~%.:_-', 		  # allowed chars in URI.
	'js_position' 	=> 'end'			  # default position for js scripts.
),

);

<?php

return array(

'core' => array(
	'debug'			=> true,
	'error'			=> true,
	'route_index'	=> 'main',
	'route_error'	=> 'error',
	'uri_sufix'		=> '.html',
	'uri_chars'		=> 'a-zA-Z0-9~%.:_-', # permited url characters
	'mime-types'	=> array(
						'css'	=> 'text/css',
						'js'	=> 'application/x-javascript',
						'jpg'	=> 'image/jpg',
						'png'	=> 'image/png',
						'gif'	=> 'image/gif'
					)
),

'view' => array(
	'js_position' => 'end'
)

);
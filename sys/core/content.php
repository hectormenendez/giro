<?php
/**
*	-	Declare your libs as keys using the class name in lowercase. 
*	-	You can have all your lib configs centralized here, or declared on each class.
*	-	The values set here will be overriden by the ones declared on classes.
*	-	IMPORTANT!	Pay attention to your commas. A generic error will trigger if you miss one.
*/

return array(

//	-------------------------------------------------------------------------------------------------  Español

'es' => array(

	'core' => array(
		'error'	=>	'Error de Nucleo',
		'config_class'		=>	'La clase <u>%s</u> no ha sido definida en la configuración.',
		'invalid_class'		=>	'La clase <u>%s</u> es Inválida o no existe.',
		'invalid_property'	=>	'La propiedad <u>%s</u> es Inválida o no existe.',
		'invalid_method'	=>	'El método <u>%s</u> es inválido o no existse.',
		'invalid_config'	=>	'La configuracion <u>%s</u> es inválida o no existe.',
		'invalid_type'		=>	'Tipo inválido, se esperaba: <u>%1$s</u>',
		'invalid_setting'	=>	'El archivo de parámetros <u>%s</u> es Inválido o no existe.',
		'invalid_option'	=>	'La opción <u>%s</u> es Inválida o no existe.',
		'invalid_language'	=>	'El Lenguage proporcionado es inválido o no existe.',
		'invalid_charset'	=>	'El juego de caracteres proporcionado es inválido o no existe',
		'array_number'		=>	'El array <u>%s</u> debe contenter <u>%s</u> argumentos.'
	),

)
);
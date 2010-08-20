<?php
/**
*	-	Declare your libs as keys using the class name in lowercase. 
*	-	You can have all your lib configs centralized here, or declared on each class.
*	-	The values set here will be overriden by the ones declared on classes.
*	-	IMPORTANT!	Pay attention to your commas. A generic error will trigger if you miss one.
*/

return array(
//	----------------------------------------------------------------------------------------------------  CORE

'core' => array(
	/**
	*	Specify the libraries you want to run their constructor at startup
	**/
	'startup' => array(),

	/**
	*	Available Languages for the framework content. 
	*	-	At least one must me defined.
	*	-	If nothing specified, the first one in the array will be considered default.
	*
	*	'lang-code' => 'Language Name'
	*/
	'language' => array(
		'es' => 'Español',
		'en' => 'English'
	),

	/**
	*	Available Character sets. 
	*	-	At least one must be defined.
	*	-	If nothing specified, the first one in the array will be considered default.
	*
	*	'charset-code' => array('Charset Name', 'db-alias', 'db-collation')
	*/
	'charset' => array(
		'utf-8'			=> array('Unicode',			'utf8',		'utf8_general_ci'),
		'iso-8859-1'	=> array('West European',	'latin1',	'latin1_swedish_ci')
	)
)
);
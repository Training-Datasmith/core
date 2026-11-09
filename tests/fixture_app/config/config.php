<?php

return array(
	'base_url' => null,
	'index_file' => false,
	'url_suffix' => '',
	'profiling' => false,
	'caching' => false,
	'locale' => null,
	'default_timezone' => 'UTC',
	'encoding' => 'UTF-8',
	'log_path' => APPPATH.'logs'.DS,
	'log_threshold' => 300,
	'log_file' => 'test.php',
	'log_date_format' => 'Y-m-d H:i:s',
	'always_load' => array(
		'packages' => array(),
		'modules' => array(),
		'classes' => array(),
		'config' => array(),
		'language' => array(),
	),
	'security' => array(
		'output_filter' => 'Security::htmlentities',
		'htmlentities_flags' => ENT_QUOTES,
		'htmlentities_double_encode' => false,
	),
);

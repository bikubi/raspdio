<?php
$stations=array(
	'byte' => array(
		'uri' => 'http://streamingserver01.byte.fm:8000/',
		'cache' => 1024,
		're' => '(.*) \- (.*)'
	),
	'wfmu' => array(
		'uri' => 'http://stream0.wfmu.org/freeform-128k',
		'cache' => 512,
		're' => '"(.*)" by (.*?) on '
	),
	'radioeins' => array(
		'uri' => 'http://rbb.ic.llnwd.net/stream/rbb_radioeins_mp3_m_a',
		'cache' => 512,
		're' => '(.*) \- (.*)'
	)
);
?>

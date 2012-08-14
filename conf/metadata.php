<?php
	/**
	* Metadata for configuration manager plugin
	* Additions for the dwinsect plugin
	*
	* @author     Dietrich Wittenberg <info.wittenberg@online.de>
	*/

	$meta['linklistname']		= array('string');
	$meta['insecttext']			= array('multichoice', '_choices' => array('none', 'plain', 'footnote'));
	$meta['insectlink']			= array('multichoice', '_choices' => array('none', 'plain', 'translate'));
	$meta['insectinclude']	= array('multichoice', '_choices' => array('none', 'plain', 'link'));
	?>

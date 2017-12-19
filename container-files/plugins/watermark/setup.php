<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

function plugin_watermark_install () {
	api_plugin_register_hook('watermark', 'rrd_graph_graph_options', 'watermark_rrd_graph_graph_options', 'setup.php');
	api_plugin_register_hook('watermark', 'config_settings', 'watermark_config_settings', 'setup.php');
}

function plugin_watermark_uninstall () {
}
function plugin_watermark_check_config () {
	return true;
}
function plugin_watermark_upgrade () {
	return false;
}
function watermark_version () {
	return plugin_watermark_version();
}
function plugin_watermark_version () {
	return array( 'name' 	=> 'watermark',
			'version' 	=> '0.1',
			'longname'	=> 'Watermark',
			'author'		=> 'Jimmy Conner',
			'homepage'	=> 'http://cactiusers.org',
			'email'		=> 'jimmy@sqmail.org',
			'url'		=> 'http://versions.cactiusers.org/'
			);
}

function watermark_rrd_graph_graph_options ($g) {
	$text = trim(read_config_option('plugin_watermark_text'));
	$text = trim(str_replace(array('|', "\\", '"'), '',  $text));
	if ($text != '') {
		$g['graph_defs'] .= '--watermark "' . $text . '" \\' . "\n";
	}
	return $g;
}

function watermark_config_settings () {
	global $settings;
	$settings['visual']['watermark_header'] = array(
			"friendly_name" => "Watermark",
			"method" => "spacer",
			);
	$settings['visual']['plugin_watermark_text'] = array(
			"friendly_name" => "Watermark",
			"description" => "This is visual text to place at the bottom of each graph.",
			"method" => "textbox",
			"max_length" => 255,
			);
}


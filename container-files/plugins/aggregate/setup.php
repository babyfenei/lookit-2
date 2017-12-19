<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2010 The Cacti Group                                 |
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

# define a debugging level specific to AUTOM8
define('AGGREGATE_DEBUG', read_config_option("aggregate_log_verbosity"), true);
	
	define("AGGREGATE_LOG_NONE", 1);
	define("AGGREGATE_LOG_FUNCTIONS", 2);
	define("AGGREGATE_LOG_DEBUG", 3);

/**
 * Initialize the plugin and setup all hooks
 */
function plugin_init_aggregate() {
	global $plugin_hooks;

	# Add a new dropdown Action for Graph Management
	$plugin_hooks['graphs_action_array']['aggregate'] = 'aggregate_graphs_action_array';
	# setup all arrays needed for aggregation
	$plugin_hooks['config_arrays']['aggregate'] = 'aggregate_config_arrays';
	$plugin_hooks['config_settings']['aggregate'] = 'aggregate_config_settings';
	# setup all forms needed for aggregation
	$plugin_hooks['config_form']['aggregate'] = 'aggregate_config_form';
	# provide navigation texts
	$plugin_hooks['draw_navigation_text'] ['aggregate'] = 'aggregate_draw_navigation_text';
	# Graph Management Action dropdown selected: prepare the list of graphs for a confirmation request
	$plugin_hooks['graphs_action_prepare']['aggregate'] = 'aggregate_graphs_action_prepare';
	# Graph Management Action dropdown selected: execute list of graphs
	$plugin_hooks['graphs_action_execute']['aggregate'] = 'aggregate_graphs_action_execute';
}

function plugin_aggregate_install () {

	# setup all arrays needed for aggregation
	api_plugin_register_hook('aggregate', 'config_arrays', 'aggregate_config_arrays', 'setup.php');
	api_plugin_register_hook('aggregate', 'config_settings', 'aggregate_config_settings', 'setup.php');
	# setup all forms needed for aggregation
	api_plugin_register_hook('aggregate', 'config_form', 'aggregate_config_form', 'setup.php');
	# provide navigation texts
	api_plugin_register_hook('aggregate', 'draw_navigation_text', 'aggregate_draw_navigation_text', 'setup.php');
	# sAdd a new dropdown Action for Graph Management
	api_plugin_register_hook('aggregate', 'graphs_action_array', 'aggregate_graphs_action_array', 'setup.php');
	# Graph Management Action dropdown selected: prepare the list of graphs for a confirmation request
	api_plugin_register_hook('aggregate', 'graphs_action_prepare', 'aggregate_graphs_action_prepare', 'aggregate.php');
	# Graph Management Action dropdown selected: execute list of graphs
	api_plugin_register_hook('aggregate', 'graphs_action_execute', 'aggregate_graphs_action_execute', 'aggregate.php');

	aggregate_setup_table_new ();
}

function plugin_aggregate_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_aggregate_check_config () {
	// Here we will check to ensure everything is configured
	aggregate_check_upgrade ();
	return true;
}

function plugin_aggregate_upgrade () {
	// Here we will upgrade to the newest version
	aggregate_check_upgrade ();
	return true;
}

function plugin_aggregate_version () {
	return aggregate_version();
}

function aggregate_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('aggregate_templates.php', 'aggregate_templates_items.php', 'color_templates.php', 'color_templates_items.php', 'plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = aggregate_version ();
	$current = $version['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='aggregate'");
		if ($current != $old) {
		# stub for updating tables
		#$_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM <table>"), "Field", "Field");
		#if (!in_array("<new column>", $_columns)) {
		#	db_execute("ALTER TABLE <table> ADD COLUMN <new column> VARCHAR(40) NOT NULL DEFAULT '' AFTER <old column>");
		#}

		# new hooks
		#api_plugin_register_hook('aggregate', 'config_settings',       'aggregate_config_settings', 'setup.php');
		#if (api_plugin_is_enabled('aggregate')) {
			# may sound ridiculous, but enables new hooks
		#	api_plugin_enable_hooks('aggregate');
		#}
		# register new version
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
			}
}

function aggregate_check_dependencies() {
	global $plugins, $config;
	return true;
}


function aggregate_setup_table_new () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	/* list all tables */
	$result = db_fetch_assoc("show tables from `" . $database_default . "`") or die (mysql_error());
	$tables = array();
	foreach($result as $index => $arr) {
		foreach ($arr as $t) {
			$tables[] = $t;
		}
	}
	/* V064 -> V065 tables were renamed */
	if (in_array('plugin_color_templates', $tables)) {
		db_execute("RENAME TABLE $database_default.`plugin_color_templates`  TO $database_default.`plugin_aggregate_color_templates`");
	}
	if (in_array('plugin_color_templates_item', $tables)) {
		db_execute("RENAME TABLE $database_default.`plugin_color_templates_item`  TO $database_default.`plugin_aggregate_color_template_items`");
	}

	$data = array();
	$data['columns'][] = array('name' => 'color_template_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'name', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'color_template_id';
	$data['keys'][] = '';					# lib/plugins.php _requires_ keys!
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Color Templates';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_color_templates', $data);

	$sql[] = "INSERT INTO `plugin_aggregate_color_templates` " .
			"(`color_template_id`, `name`) " .
			"VALUES " .
			"(1, 'Yellow: light -> dark, 4 colors'), " .
			"(2, 'Red: light yellow > dark red, 8 colors'), " .
			"(3, 'Red: light -> dark, 16 colors'), " .
			"(4, 'Green: dark -> light, 16 colors');";

	$data = array();
	$data['columns'][] = array('name' => 'color_template_item_id', 'type' => 'int(12)', 'unsigned' => 'unsigned', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'color_template_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'color_id', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['columns'][] = array('name' => 'sequence', 'type' => 'mediumint(8)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => 0);
	$data['primary'] = 'color_template_item_id';
	$data['keys'][] = '';					# lib/plugins.php _requires_ keys!
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Color Items for Color Templates';
	api_plugin_db_table_create ('aggregate', 'plugin_aggregate_color_template_items', $data);

	$sql[] = "INSERT INTO `plugin_aggregate_color_template_items` " .
			"(`color_template_item_id`, `color_template_id`, `color_id`, `sequence`) " .
			"VALUES " .
			"(1, 1, 4, 1), " .
			"(2, 1, 24, 2), " .
			"(3, 1, 98, 3), " .
			"(4, 1, 25, 4), " .
			"" .
			"(5, 2, 25, 1), " .
			"(6, 2, 29, 2), " .
			"(7, 2, 30, 3), " .
			"(8, 2, 31, 4), " .
			"(9, 2, 33, 5), " .
			"(10, 2, 35, 6), " .
			"(11, 2, 41, 7), " .
			"(12, 2, 9, 8), " .
			"" .
			"(13, 3, 15, 1), " .
			"(14, 3, 31, 2), " .
			"(15, 3, 28, 3), " .
			"(16, 3, 8, 4), " .
			"(17, 3, 34, 5), " .
			"(18, 3, 33, 6), " .
			"(19, 3, 35, 7), " .
			"(20, 3, 41, 8), " .
			"(21, 3, 36, 9), " .
			"(22, 3, 42, 10), " .
			"(23, 3, 44, 11), " .
			"(24, 3, 48, 12), " .
			"(25, 3, 9, 13), " .
			"(26, 3, 49, 14), " .
			"(27, 3, 51, 15), " .
			"(28, 3, 52, 16), " .
			"" .
			"(29, 4, 76, 1), " .
			"(30, 4, 84, 2), " .
			"(31, 4, 89, 3), " .
			"(32, 4, 17, 4), " .
			"(33, 4, 86, 5), " .
			"(34, 4, 88, 6), " .
			"(35, 4, 90, 7), " .
			"(36, 4, 94, 8), " .
			"(37, 4, 96, 9), " .
			"(38, 4, 93, 10), " .
			"(39, 4, 91, 11), " .
			"(40, 4, 22, 12), " .
			"(41, 4, 12, 13), " .
			"(42, 4, 95, 14), " .
			"(43, 4, 6, 15), " .
			"(44, 4, 92, 16);";

	# now run all SQL commands
	if (!empty($sql)) {
		for ($a = 0; $a < count($sql); $a++) {
			$result = db_execute($sql[$a]);
		}
	}

}

/**
 * Version information (used by update plugin)
 */
function aggregate_version () {
	return array(
		'name' 		=> 'aggregate',
		'version' 	=> '0.75',
		'longname'	=> 'Create Aggregate Graphs',
		'author'	=> 'Reinhard Scheck',
		'homepage'	=> 'http://docs.cacti.net/plugin:aggregate',
		'email'		=> 'gandalf@cacti.net',
		'url'		=> 'http://docs.cacti.net/plugin:aggregate'
		);
}

/**
 * Draw navigation texts
 * @arg $nav		all navigation texts
 */
function aggregate_draw_navigation_text ($nav) {
	// Displayed navigation text under the blue tabs of Cacti
	$nav["color_templates.php:"] 				= array("title" => "Color Templates", "mapping" => "index.php:", "url" => "color_templates.php", "level" => "1");
	$nav["color_templates.php:template_edit"] 	= array("title" => "(Edit)", "mapping" => "index.php:,color_templates.php:", "url" => "", "level" => "2");
	$nav["color_templates.php:actions"] 		= array("title" => "Actions", "mapping" => "index.php:,color_templates.php:", "url" => "", "level" => "2");
	$nav["color_templates_items.php:item_edit"] = array("title" => "Color Template Items", "mapping" => "index.php:,color_templates.php:,color_templates.php:template_edit", "url" => "", "level" => "3");

	return $nav;
}

/**
 * Setup the new dropdown action for Graph Management
 * @arg $action		actions to be performed from dropdown
 */
function aggregate_graphs_action_array($action) {
	$action['plugin_aggregate'] = 'Create Aggregate Graph';
	return $action;
}

/**
 * Setup forms needed for this plugin
 */
function aggregate_config_form () {
	# globals defined for use with Color Templates
	global $struct_aggregate, $struct_color_template, $struct_color_template_item;
	global $fields_color_template_template_edit, $help_file;
	global $agg_graph_types, $agg_totals, $agg_totals_type, $agg_order_types;
	global $config;

	# unless a hook for 'global_constants' is available, all DEFINEs go here
	define("AGGREGATE_GRAPH_TYPE_KEEP", 0);

	define("AGGREGATE_TOTAL_NONE", 1);
	define("AGGREGATE_TOTAL_ALL", 2);
	define("AGGREGATE_TOTAL_ONLY", 3);
	
	define("AGGREGATE_TOTAL_TYPE_SIMILAR", 1);
	define("AGGREGATE_TOTAL_TYPE_ALL", 2);

	define("AGGREGATE_ORDER_NONE", 1);
	define("AGGREGATE_ORDER_DS_GRAPH", 2);
	define("AGGREGATE_ORDER_GRAPH_DS", 3);

	$agg_graph_types = array(
		AGGREGATE_GRAPH_TYPE_KEEP 	=> "Keep Graph Types",
		GRAPH_ITEM_TYPE_STACK		=> "Convert to AREA/STACK Graph",
		GRAPH_ITEM_TYPE_LINE1 		=> "Convert to LINE1 Graph",
		GRAPH_ITEM_TYPE_LINE2 		=> "Convert to LINE2 Graph",
		GRAPH_ITEM_TYPE_LINE3 		=> "Convert to LINE3 Graph",
	);
	
	$agg_totals = array(
		AGGREGATE_TOTAL_NONE 		=> "No Totals",
		AGGREGATE_TOTAL_ALL	 		=> "Print all Legend Items",
		AGGREGATE_TOTAL_ONLY 		=> "Print totaling Legend Items Only",
	);
	
	$agg_totals_type = array(
		AGGREGATE_TOTAL_TYPE_SIMILAR=> "Total Similar Data Sources",
		AGGREGATE_TOTAL_TYPE_ALL 	=> "Total All Data Sources",
	);
	
	$agg_order_types = array(
		AGGREGATE_ORDER_NONE => "No Reordering",
		AGGREGATE_ORDER_DS_GRAPH => "Data Source, Graph",
		#AGGREGATE_ORDER_GRAPH_DS => "Graph, Data Source",
	);

	$help_file = $config['url_path'] . "/plugins/aggregate/aggregate_manual.pdf";

	# ------------------------------------------------------------
	# Main Aggregate Parameters
	# ------------------------------------------------------------
	/* file: aggregate.php */
	$struct_aggregate = array(
		"title_format" => array(
			"friendly_name" => "Title",
			"description" => "The new Title of the aggregated Graph.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:title_format|",
			),
		"gprint_prefix" => array(
			"friendly_name" => "Prefix",
			"description" => "A Prefix for all GPRINT lines to distinguish e.g. different hosts.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:gprint_prefix|",
			),
		"aggregate_graph_type" => array(
			"friendly_name" => "Graph Type",
			"description" => "Use this Option to create e.g. STACKed graphs." . "<br>" .
							"AREA/STACK: 1st graph keeps AREA/STACK items, others convert to STACK" . "<br>" .
							"LINE1: all items convert to LINE1 items" . "<br>" .
							"LINE2: all items convert to LINE2 items" . "<br>" .
							"LINE3: all items convert to LINE3 items",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_graph_type|",
			"array" => $agg_graph_types,
			"default" => GRAPH_ITEM_TYPE_STACK,
			),
		"aggregate_total" => array(
			"friendly_name" => "Totaling",
			"description" => "Please check those Items that shall be totaled in the 'Total' column, when selecting any totaling option here.",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_total|",
			"array" => $agg_totals,
			"default" => AGGREGATE_TOTAL_ALL,
			"on_change" => "changeTotals()",
			),
		"aggregate_total_type" => array(
			"friendly_name" => "Total Type",
			"description" => "Which type of totaling shall be performed.",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_total_type|",
			"array" => $agg_totals_type,
			"default" => AGGREGATE_TOTAL_TYPE_SIMILAR,
			"on_change" => "changeTotalsType()",
			),
		"aggregate_total_prefix" => array(
			"friendly_name" => "Prefix for GPRINT Totals",
			"description" => "A Prefix for all <strong>totaling</strong> GPRINT lines.",
			"method" => "textbox",
			"max_length" => "255",
			"value" => "|arg1:aggregate_total_prefix|",
			),
		"aggregate_order_type" => array(
			"friendly_name" => "Reorder Type",
			"description" => "Reordering of Graphs.",
			"method" => "drop_array",
			"value" => "|arg1:aggregate_order_type|",
			"array" => $agg_order_types,
			"default" => AGGREGATE_ORDER_NONE,
			),
	);

	# ------------------------------------------------------------
	# Color Templates
	# ------------------------------------------------------------
	/* file: color_templates.php, action: template_edit */
	$struct_color_template = array(
		"title" => array(
			"friendly_name" => "Title",
			"method" => "textbox",
			"max_length" => "255",
			"default" => "",
			"description" => "The name of this Color Template."
			),
		);

	/* file: color_templates.php, action: item_edit */
	$struct_color_template_item = array(
		"color_id" => array(
			"friendly_name" => "Color",
			"method" => "drop_color",
			"default" => "0",
			"description" => "A nice Color",
			"value" => "|arg1:color_id|",
			),
		);

	/* file: color_templates.php, action: template_edit */
	$fields_color_template_template_edit = array(
		"name" => array(
			"method" => "textbox",
			"friendly_name" => "Name",
			"description" => "A useful name for this Template.",
			"value" => "|arg1:name|",
			"max_length" => "255",
			),
		);

}

/**
 * aggregate_config_settings	- configuration settings for this plugin
 */
function aggregate_config_settings () {
	global $tabs, $settings, $config, $agg_log_verbosity;
	
	$agg_log_verbosity = array(
		AGGREGATE_LOG_NONE			=> "No AGGREGATE logging",
		AGGREGATE_LOG_FUNCTIONS		=> "Log function calls",
		AGGREGATE_LOG_DEBUG			=> "Log everything",
	);
	
	/* check for an upgrade */
	plugin_aggregate_check_config();

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$temp = array(
		"aggregate_header" => array(
			"friendly_name" => "AGGREGATE",
			"method" => "spacer",
		),
		"aggregate_log_verbosity" => array(
			"friendly_name" => "Poller Logging Level for AGGREGATE",
			"description" => "What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.",
			"method" => "drop_array",
			"default" => AGGREGATE_LOG_NONE,
			"array" => $agg_log_verbosity,
			),
	);

	/* create a new Settings Tab, if not already in place */
	if (!isset($tabs["misc"])) {
		$tabs["misc"] = "Misc";
	}

	/* and merge own settings into it */
	if (isset($settings["misc"]))
		$settings["misc"] = array_merge($settings["misc"], $temp);
	else
		$settings["misc"] = $temp;
}

/**
 * Setup arrays needed for this plugin
 */
function aggregate_config_arrays () {
	# globals changed
	global $user_auth_realms, $user_auth_realm_filenames;
	global $menu;

	aggregate_check_upgrade ();

	if (function_exists('api_plugin_register_realm')) {
		# register all php modules required for this plugin
		api_plugin_register_realm('aggregate', 'color_templates.php', 'Plugin Aggregate -> Create Color Templates', 1);
		api_plugin_register_realm('aggregate', 'color_templates_items.php', 'Plugin Aggregate -> Create Color Template Items', 1);
	} else {
		# realms
		# Check this Item for each user to allow access to Aggregate Templates
		$user_auth_realms[72]='Plugin Aggregate -> Create Color Templates';
		# these are the files protected by our realm id
		$user_auth_realm_filenames['aggregate_templates.php'] = 72;
		$user_auth_realm_filenames['aggregate_templates_items.php'] = 72;
		$user_auth_realm_filenames['color_templates.php'] = 72;
		$user_auth_realm_filenames['color_templates_items.php'] = 72;
	}

	# menu titles
	$menu["Templates"]['plugins/aggregate/color_templates.php'] = "Color Templates";
}

?>
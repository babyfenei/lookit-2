<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2008 The Cacti Group                                 |
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

define("CLOG_PERM_ADMIN", 0);
define("CLOG_PERM_USER",  1);
define("CLOG_PERM_NONE",  2);

function plugin_clog_install () {
	api_plugin_register_hook('clog', 'config_arrays',         'clog_config_arrays',        'setup.php');
	api_plugin_register_hook('clog', 'draw_navigation_text',  'clog_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('clog', 'config_settings',       'clog_config_settings',      'setup.php');
	api_plugin_register_hook('clog', 'top_header_tabs',       'clog_show_tab',             'setup.php');
	api_plugin_register_hook('clog', 'top_graph_header_tabs', 'clog_show_tab',             'setup.php');
	api_plugin_register_hook('clog', 'top_graph_refresh',     'clog_top_graph_refresh',    'setup.php');

	api_plugin_register_realm('clog', 'clog.php', 'Plugin -> View Cacti Log - Console Level', 1);
	api_plugin_register_realm('clog', 'clog_user.php', 'Plugin -> View Cacti Log - User Level', 1);

	clog_setup_table_new();
}

function clog_version () {
	return array(
		'name'     => 'CLog',
		'version'  => '1.7',
		'longname' => 'Cacti Log View',
		'author'   => 'Larry Adams',
		'homepage' => 'http://www.cacti.net',
		'email'    => 'larryjadams@comcast.net',
		'url'      => 'http://www.cacti.net'
		);
}

function plugin_clog_uninstall () {
	/* Do any extra Uninstall stuff here */
}

function plugin_clog_check_config () {
	/* Here we will check to ensure everything is configured */
	clog_check_upgrade();
	return true;
}

function plugin_clog_upgrade() {
	/* Here we will upgrade to the newest version */
	clog_check_upgrade();
	return false;
}

function plugin_clog_version() {
	return clog_version();
}

function clog_check_upgrade() {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'clog.php', 'clog_user.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = clog_version();
	$current = $current['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='clog'");

	if ($current != $old) {
		/* update realms for old versions */
		if ($old < "1.4") {
			api_plugin_register_realm('clog', 'clog.php', 'Plugin -> View Cacti Log - Console Level', 1);
			api_plugin_register_realm('clog', 'clog_user.php', 'Plugin -> View Cacti Log - User Level', 1);

			/* get the realm id's and change from old to new */
			$user  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='clog_user.php'")+100;
			$admin = db_fetch_cell("SELECT id FROM plugin_realms WHERE file='clog.php'")+100;

			$users = db_fetch_assoc("SELECT user_id FROM user_auth_realm WHERE realm_id=1002");
			if (sizeof($users)) {
				foreach($users as $u) {
					db_execute("INSERT INTO user_auth_realm
						(realm_id, user_id) VALUES ($user, " . $u["user_id"] . ")
						ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)");
					db_execute("DELETE FROM user_auth_realm
						WHERE user_id=" . $u["user_id"] . "
						AND realm_id=$user");
				}
			}

			$admins = db_fetch_assoc("SELECT user_id FROM user_auth_realm WHERE realm_id=1003");
			if (sizeof($admins)) {
				foreach($admins as $user) {
					db_execute("INSERT INTO user_auth_realm
						(realm_id, user_id) VALUES ($admin, " . $user["user_id"] . ")
						ON DUPLICATE KEY UPDATE realm_id=VALUES(realm_id)");
					db_execute("DELETE FROM user_auth_realm
						WHERE user_id=" . $user["user_id"] . "
						AND realm_id=$admin");
				}
			}
		}
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='clog'");
	}
}

function clog_check_dependencies() {
	global $plugins, $config;
	return true;
}

function clog_setup_table_new() {
	/* nothing to do */
}

function clog_config_arrays() {
	global $messages;

	$messages['clog_purged']      = array('message' => 'Cacti Log Purged Sucessfully', 'type' => 'info');
	$messages['clog_permissions'] = array('message' => 'Error: Unable to clear log, no write permissions', 'type' => 'error');
	$messages['clog_missing']     = array('message' => 'Error: Unable to clear log, file does not exist', 'type' => 'error');

	if (isset($_SESSION['clog_message']) && $_SESSION['clog_message'] != '') {
		$messages['clog_message'] = array('message' => $_SESSION['clog_message'], 'type' => 'info');
	}
	if (isset($_SESSION['clog_error']) && $_SESSION['clog_error'] != '') {
		$messages['clog_error'] = array('message' => $_SESSION['clog_error'], 'type' => 'error');
	}

	clog_check_upgrade();
}

function clog_config_settings () {
	global $tabs, $settings;

	$tabs["misc"] = "Misc";

	$temp = array(
		"clog_header" => array(
			"friendly_name" => "Cacti Log Viewer for Users",
			"method" => "spacer",
			),
		"clog_exclude" => array(
			"friendly_name" => "Exclusion Regex",
			"description" => "Any strings that match this regex will be excluded from the user display.
				<strong>For example, if you want to exclude all log lines that include the words 'Admin' or 'Login'
				you would type '(Admin || Login)'</strong>",
			"method" => "textarea",
			"textarea_rows" => "5",
			"textarea_cols" => "45",
			"max_length" => 512
			)
		);

	if (isset($settings["misc"])) {
		$settings["misc"] = array_merge($settings["misc"], $temp);
	}else {
		$settings["misc"] = $temp;
	}
}

function clog_draw_navigation_text ($nav) {
	$nav["clog.php:"] = array("title" => "View Cacti Log", "mapping" => "", "url" => "clog.php", "level" => "0");
	$nav["clog.php:preview"] = array("title" => "View Cacti Log", "mapping" => "", "url" => "clog.php", "level" => "0");
	$nav["clog_user.php:"] = array("title" => "View Cacti Log", "mapping" => "", "url" => "clog_user.php", "level" => "0");
	$nav["clog_user.php:preview"] = array("title" => "View Cacti Log", "mapping" => "", "url" => "clog_user.php", "level" => "0");

	return $nav;
}

function clog_admin() {
	if (!isset($_SESSION["sess_clog_level"])) {
		$_SESSION["sess_clog_level"] = clog_permissions();
	}

	if ($_SESSION["sess_clog_level"] == CLOG_PERM_ADMIN) {
		return true;
	}else{
		return false;
	}
}

function clog_authorized() {
	if (!isset($_SESSION["sess_clog_level"])) {
		$_SESSION["sess_clog_level"] = clog_permissions();
	}

	if ($_SESSION["sess_clog_level"] == CLOG_PERM_USER) {
		return true;
	}elseif ($_SESSION["sess_clog_level"] == CLOG_PERM_ADMIN) {
		return true;
	}else{
		return false;
	}
}

function clog_show_tab() {
	global $config;

	if (!isset($_SESSION["sess_clog_level"])) {
		$_SESSION["sess_clog_level"] = clog_permissions();
	}

	if ($_SESSION["sess_clog_level"] == CLOG_PERM_ADMIN || $_SESSION["sess_clog_level"] == CLOG_PERM_USER) {
		if (substr_count($_SERVER["REQUEST_URI"], "clog")) {
			if ($_SESSION["sess_clog_level"] == CLOG_PERM_ADMIN) {
				print '<a href="' . $config['url_path'] . 'plugins/clog/clog.php"><img src="' . $config['url_path'] . 'plugins/clog/images/tab_clog_down.png" alt="Cacti Log" align="absmiddle" border="0"></a>';
			}else{
				print '<a href="' . $config['url_path'] . 'plugins/clog/clog_user.php"><img src="' . $config['url_path'] . 'plugins/clog/images/tab_clog_down.png" alt="Cacti Log" align="absmiddle" border="0"></a>';
			}
		}else{
			if ($_SESSION["sess_clog_level"] == CLOG_PERM_ADMIN) {
				print '<a href="' . $config['url_path'] . 'plugins/clog/clog.php"><img src="' . $config['url_path'] . 'plugins/clog/images/tab_clog.png" alt="Cacti Log" align="absmiddle" border="0"></a>';
			}else{
				print '<a href="' . $config['url_path'] . 'plugins/clog/clog_user.php"><img src="' . $config['url_path'] . 'plugins/clog/images/tab_clog.png" alt="Cacti Log" align="absmiddle" border="0"></a>';
			}
		}
	}
}

function clog_permissions() {
	$admin_realm = db_fetch_cell("SELECT id FROM plugin_realms WHERE file LIKE '%clog.php%'") + 100;
	$user_realm  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file LIKE '%clog_user.php%'") + 100;

	if (sizeof(db_fetch_assoc("SELECT realm_id
		FROM user_auth_realm
		WHERE user_id=" . $_SESSION["sess_user_id"] . "
		AND realm_id=$admin_realm"))) {
		return CLOG_PERM_ADMIN;
	}elseif (sizeof(db_fetch_assoc("SELECT realm_id
		FROM user_auth_realm
		WHERE user_id=" . $_SESSION["sess_user_id"] . "
		AND realm_id=$user_realm"))) {
		return CLOG_PERM_USER;
	}else{
		return CLOG_PERM_NONE;
	}
}

function clog_top_graph_refresh ($refresh) {
	if (!substr_count(basename($_SERVER['PHP_SELF']), 'clog')) {
		return $refresh;
	}
	$r = get_request_var_request("refresh");
	if ($r == '' or $r < 1) return $refresh;
	return $r;
}

?>

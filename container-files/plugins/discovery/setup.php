<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
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

function plugin_discovery_install () {
	api_plugin_register_hook('discovery', 'top_header_tabs', 'discovery_show_tab', 'setup.php');
	api_plugin_register_hook('discovery', 'top_graph_header_tabs', 'discovery_show_tab', 'setup.php');
	api_plugin_register_hook('discovery', 'config_arrays', 'discovery_config_arrays', 'setup.php');
	api_plugin_register_hook('discovery', 'draw_navigation_text', 'discovery_draw_navigation_text', 'setup.php');
	api_plugin_register_hook('discovery', 'config_settings', 'discovery_config_settings', 'setup.php');
	api_plugin_register_hook('discovery', 'poller_bottom', 'discovery_poller_bottom', 'setup.php');
	api_plugin_register_hook('discovery', 'utilities_action', 'discovery_utilities_action', 'setup.php');
	api_plugin_register_hook('discovery', 'utilities_list', 'discovery_utilities_list', 'setup.php');

	api_plugin_register_realm('discovery', 'discover.php,discover_template.php', 'View Host Auto-Discovery', 1);

	discovery_setup_table();
}

function plugin_discovery_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_discovery_check_config () {
	// Here we will check to ensure everything is configured
	discovery_check_upgrade ();
	return true;
}

function plugin_discovery_upgrade () {
	// Here we will upgrade to the newest version
	discovery_check_upgrade ();
	return false;
}

function discovery_version () {
	return plugin_discovery_version();
}

function discovery_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'discover.php', 'discover_template.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = plugin_discovery_version ();
	$current = $version['version'];
	$old = read_config_option('plugin_discovery_version');
	if ($current != $old) {
		$discovery_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM plugin_discover_hosts"), "Field", "Field");
		if (!in_array("snmp_version", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_version tinyint(1) unsigned NOT NULL DEFAULT '1' AFTER community");
		}
		if (!in_array("snmp_username", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_username varchar(50) NULL AFTER snmp_version");
		}
		if (!in_array("snmp_password", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_password varchar(50) NULL AFTER snmp_username");
		}
		if (!in_array("snmp_auth_protocol", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_auth_protocol char(5) DEFAULT '' AFTER snmp_password");
		}
		if (!in_array("snmp_priv_passphrase", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_priv_passphrase varchar(200) DEFAULT '' AFTER snmp_auth_protocol");
		}
		if (!in_array("snmp_priv_protocol", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_priv_protocol char(6) DEFAULT '' AFTER snmp_priv_passphrase");
		}
		if (!in_array("snmp_context", $discovery_columns)) {
			db_execute("ALTER TABLE plugin_discover_hosts ADD COLUMN snmp_context varchar(64) DEFAULT '' AFTER snmp_priv_protocol");
		}

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='discovery'");
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
}

function plugin_discovery_version () {
	return array(
		'name'     => 'discovery',
		'version'  => '1.5',
		'longname' => 'Network Discovery',
		'author'   => 'Jimmy Conner',
		'homepage' => 'http://cactiusers.org',
		'email'    => 'jimmy@sqmail.org',
		'url'      => 'http://versions.cactiusers.org/'
	);
}

function discovery_utilities_action ($action) {
	if ($action == 'discovery_clear') {
		mysql_query('DELETE FROM plugin_discover_hosts');

		include_once('./include/top_header.php');
		utilities();
		include_once('./include/bottom_footer.php');
	}
	return $action;
}

function discovery_utilities_list () {
	global $colors;

	html_header(array("Discovery Results"), 2);
	?>
	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=discovery_clear'>Clear Discovery Results</a>
		</td>
		<td class="textArea">
			This will clear the results from the discovery polling.
		</td>
	</tr>
	<?php
}

function discovery_config_settings () {
	global $tabs, $settings, $discover_poller_frequencies;
	$tabs["misc"] = "Misc";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$temp = array(
		"discovery_header" => array(
			"friendly_name" => "Discover",
			"method" => "spacer",
			),
		"discovery_subnet" => array(
			"friendly_name" => "Subnet(s) to scan",
			"description" => "This is the subnet we will scan.  (Use commas for multiple subnets.  ex: 192.168.100.*,192.168.0.0/24)",
			"method" => "textarea",
			"textarea_rows" => 5,
			"textarea_cols" => 60,
			"class" => "textAreaNotes"
			),
		"discovery_dns" => array(
			"friendly_name" => "DNS Server",
			"description" => "This is the DNS Server used to resolve names.  Leave blank to disable resolving.",
			"method" => "textbox",
			"max_length" => 255,
			),
		"discovery_protocol" => array(
			"friendly_name" => "Ping Method",
			"description" => "This is the type of protocol used by Ping to determine if the host is responding.
			Once it pings, it will be scanned for snmp availability.",
			"method" => "drop_array",
			"array" => array(0 => 'UDP', 1 => 'TCP', 2 => 'ICMP'),
			"default" => 0
			),
		"discovery_readstrings" => array(
			"friendly_name" => "SNMP Communities",
			"description" => "Fill in the list of available SNMP Community Names to test for this device. Each Community Name must be separated by a colon ':'. These will be tested sequentially.",
			"method" => "textbox",
			"max_length" => 255,
			"default" => "public"
			),
		"discovery_collection_timing" => array(
			"friendly_name" => "Poller Frequency",
			"description" => "Choose how often to attempt to find devices on  your network.",
			"method" => "drop_array",
			"default" => "disabled",
			"array" => $discover_poller_frequencies,
			),
		"discovery_base_time" => array(
			"friendly_name" => "Start Time for Polling",
			"description" => "When would you like the first polling to take place.  All future polling times will be based upon this start time.  A good example would be 12:00AM.",
			"default" => "12:00am",
			"method" => "textbox",
			"max_length" => "10"
			),
		'discovery_query_rerun' => array(
			'friendly_name' => 'Rerun Data Queries',
			'description' => 'This option will rerun all data queries on current hosts, and will create graphs for all assigned graph templates and data queries.',
			'method' => 'checkbox',
			),
		'discovery_interface_up_only' => array(
			'friendly_name' => 'Create Graphs for Up Interfaces Only',
			'description' => 'This option will create graphs for interfaces that are showing as Up.',
			'method' => 'checkbox',
			),
	);
	if (isset($settings["misc"]))
		$settings["misc"] = array_merge($settings["misc"], $temp);
	else
		$settings["misc"]=$temp;
}

function discovery_show_tab () {
	global $config, $discovery_tab;
	include_once($config["library_path"] . "/database.php");
	include_once($config["base_path"] . "/plugins/discovery/config.php");
	if (api_user_realm_auth('discover.php')) {
		if (!substr_count($_SERVER["REQUEST_URI"], "discover.php")) {
			print '<a href="' . $config['url_path'] . 'plugins/discovery/discover.php"><img src="' . $config['url_path'] . 'plugins/discovery/images/tab_discover.gif" alt="discover" align="absmiddle" border="0"></a>';
		}else{
			print '<a href="' . $config['url_path'] . 'plugins/discovery/discover.php"><img src="' . $config['url_path'] . 'plugins/discovery/images/tab_discover_down.gif" alt="discover" align="absmiddle" border="0"></a>';
		}
	}
}

function discovery_config_arrays () {
	global $menu, $config, $discovery_tab, $discover_poller_frequencies;

	include_once($config["base_path"] . "/plugins/discovery/config.php");

	$menu["Templates"]['plugins/discovery/discover_template.php'] = "Discovery Templates";

	if (!$discovery_tab) {
		$temp = $menu["Utilities"]['logout.php'];
		unset($menu["Utilities"]['logout.php']);
		$menu["Utilities"]['plugins/discovery/discover.php'] = "Discovery";
		$menu["Utilities"]['logout.php'] = $temp;
	}

	$discover_poller_frequencies = array(
		"disabled" => "Disabled",
		"60" => "Every 1 Hour",
		"120" => "Every 2 Hours",
		"240" => "Every 4 Hours",
		"360" => "Every 6 Hours",
		"480" => "Every 8 Hours",
		"720" => "Every 12 Hours",
		"1440" => "Every Day",
		"10080" => "Every Week",
		"20160" => "Every 2 Weeks",
		"40320" => "Every 4 Weeks"
		);

}

function discovery_draw_navigation_text ($nav) {
	$nav["discover.php:"] = array("title" => "Discover", "mapping" => "", "url" => "discover.php", "level" => "0");
	$nav["discover_template.php:"] = array("title" => "Discover Templates", "mapping" => "index.php:", "url" => "discover_template.php", "level" => "1");
	$nav["discover_template.php:edit"] = array("title" => "Discover Templates", "mapping" => "index.php:", "url" => "discover_template.php", "level" => "1");
	$nav["discover_template.php:actions"] = array("title" => "Discover Templates", "mapping" => "index.php:", "url" => "discover_template.php", "level" => "1");
	$nav["utilities.php:discovery_clear"] = array("title" => "Clear Discover Results", "mapping" => "index.php:,utilities.php:", "url" => "discover.php", "level" => "1");
	return $nav;
}

function discovery_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	$data = array();
	$data['columns'][] = array('name' => 'hostname', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'ip', 'type' => 'varchar(17)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'hash', 'type' => 'varchar(12)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'community', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp_version', 'type' => 'tinyint(1)', 'unsigned' => 'unsigned', 'NULL' => false, 'default' => '1');
	$data['columns'][] = array('name' => 'snmp_username', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_password', 'type' => 'varchar(50)', 'NULL' => true);
	$data['columns'][] = array('name' => 'snmp_auth_protocol', 'type' => 'char(5)', 'default' =>  '');
	$data['columns'][] = array('name' => 'snmp_priv_passphrase', 'type' => 'varchar(200)', 'default' => '');
	$data['columns'][] = array('name' => 'snmp_priv_protocol', 'type' => 'char(6)', 'default' => '');
	$data['columns'][] = array('name' => 'snmp_context', 'type' => 'varchar(64)', 'default' => '');
	$data['columns'][] = array('name' => 'sysName', 'type' => 'varchar(100)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'sysLocation', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'sysContact', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'sysDescr', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'sysUptime', 'type' => 'int(32)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'os', 'type' => 'varchar(64)', 'NULL' => false, 'default' => '');
	$data['columns'][] = array('name' => 'snmp', 'type' => 'tinyint(4)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'known', 'type' => 'tinyint(4)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'up', 'type' => 'tinyint(4)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'time', 'type' => 'int(11)', 'NULL' => false, 'default' => '0');
	$data['primary'] = 'ip';
	$data['keys'][] = array('name' => 'hostname', 'columns' => 'hostname');
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Plugin Discovery - Table of discovered hosts';
	api_plugin_db_table_create('discovery', 'plugin_discover_hosts', $data);

	$data = array();
	$data['columns'][] = array('name' => 'id', 'type' => 'int(8)', 'NULL' => false, 'auto_increment' => true);
	$data['columns'][] = array('name' => 'host_template', 'type' => 'int(8)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'tree', 'type' => 'int(12)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'snmp_version', 'type' => 'tinyint(3)', 'NULL' => false, 'default' => '0');
	$data['columns'][] = array('name' => 'sysdescr', 'type' => 'varchar(255)', 'NULL' => false, 'default' => '');
	$data['primary'] = 'id';
	$data['keys'][] = array();
	$data['type'] = 'MyISAM';
	$data['comment'] = 'Plugin Discovery - Templates of SysDesc matches to use to auto-add graphs to devices';
	api_plugin_db_table_create('discovery', 'plugin_discover_template', $data);
}

function discovery_poller_bottom () {
	global $config;

	include_once($config["library_path"] . "/database.php");

	if (read_config_option("discovery_collection_timing") == "disabled")
		return;

	$t = read_config_option("discovery_last_poll");

	/* Check for the polling interval, only valid with the Multipoller patch */
	$poller_interval = read_config_option("poller_interval");
	if (!isset($poller_interval)) {
		$poller_interval = 300;
	}

	if ($t != '' && (time() - $t < $poller_interval))
		return;

	$command_string = trim(read_config_option("path_php_binary"));

	// If its not set, just assume its in the path
	if (trim($command_string) == '')
		$command_string = "php";
	$extra_args = ' -q ' . $config['base_path'] . '/plugins/discovery/findhosts.php';

	exec_background($command_string, $extra_args);

	if ($t == "")
		$sql = "insert into settings values ('discovery_last_poll','" . time() . "')";
	else
		$sql = "update settings set value = '" . time() . "' where name = 'discovery_last_poll'";
	$result = mysql_query($sql) or die (mysql_error());
}

?>

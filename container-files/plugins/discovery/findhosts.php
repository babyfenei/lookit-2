<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2011 The Cacti Group                                 |
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

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* let PHP run just as long as it has to */
ini_set("max_execution_time", "0");

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include("./include/global.php");
include_once($config["base_path"] . '/lib/snmp.php');
include_once($config["base_path"] . '/lib/ping.php');
include_once($config["base_path"] . '/lib/utility.php');
include_once($config["base_path"] . '/lib/api_data_source.php');
include_once($config["base_path"] . '/lib/api_graph.php');
include_once($config["base_path"] . '/lib/snmp.php');
include_once($config["base_path"] . '/lib/data_query.php');
include_once($config["base_path"] . '/lib/api_device.php');

include_once($config["base_path"] . '/lib/sort.php');
include_once($config["base_path"] . '/lib/html_form_template.php');
include_once($config["base_path"] . '/lib/template.php');

include_once($config["base_path"] . '/lib/api_tree.php');
include_once($config["base_path"] . '/lib/tree.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);

$debug = FALSE;
$forcerun = FALSE;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode('=', $parameter);

	switch ($arg) {
	case "-r":
		discover_recreate_tables();
		break;
	case "-d":
		$debug = TRUE;
		break;
	case "-h":
		display_help();
		exit;
	case "-f":
		$forcerun = TRUE;
		break;
	case "-v":
		display_help();
		exit;
	case "--version":
		display_help();
		exit;
	case "--help":
		display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
		exit;
	}
}

if (read_config_option("discovery_collection_timing") == "disabled") {
	discover_debug("Discovery Polling is set to disabled.\n");
	exit;
}

discover_debug("Checking to determine if it's time to run.\n");

$seconds_offset = read_config_option("discovery_collection_timing");
$seconds_offset = $seconds_offset * 60;
$base_start_time = read_config_option("discovery_base_time");
$last_run_time = read_config_option("discovery_last_run_time");
$previous_base_start_time = read_config_option("discovery_prev_base_time");

if ($base_start_time == '') {
	discover_debug("Base Starting Time is blank, using '12:00am'\n");
	$base_start_time = '12:00am';
}

/* see if the user desires a new start time */
discover_debug("Checking if user changed the start time\n");
if (!empty($previous_base_start_time)) {
	if ($base_start_time <> $previous_base_start_time) {
		discover_debug("   User changed the start time from '$previous_base_start_time' to '$base_start_time'\n");
		unset($last_run_time);
		db_execute("DELETE FROM settings WHERE name='discovery_last_run_time'");
	}
}

/* Check for the polling interval, only valid with the Multipoller patch */
$poller_interval = read_config_option("poller_interval");
if (!isset($poller_interval)) {
	$poller_interval = 300;
}

/* set to detect if the user cleared the time between polling cycles */
db_execute("REPLACE INTO settings (name, value) VALUES ('discovery_prev_base_time', '$base_start_time')");

/* determine the next start time */
$current_time = strtotime("now");
if (empty($last_run_time)) {
	if ($current_time > strtotime($base_start_time)) {
		/* if timer expired within a polling interval, then poll */
		if (($current_time - $poller_interval) < strtotime($base_start_time)) {
			$next_run_time = strtotime(date("Y-m-d") . " " . $base_start_time);
		}else{
			$next_run_time = strtotime(date("Y-m-d") . " " . $base_start_time) + $seconds_offset;
		}
	}else{
		$next_run_time = strtotime(date("Y-m-d") . " " . $base_start_time);
	}
}else{
	$next_run_time = $last_run_time + $seconds_offset;
}
$time_till_next_run = $next_run_time - $current_time;

if ($time_till_next_run < 0) {
	discover_debug("The next run time has been determined to be NOW\n");
}else{
	discover_debug("The next run time has been determined to be at\n   " . date("Y-m-d G:i:s", $next_run_time) . "\n");
}

if ($time_till_next_run > 0 && $forcerun == FALSE) {
	exit;
}

if ($forcerun) {
	discover_debug("Scanning has been forced\n");
}

if ($forcerun == FALSE) {
	db_execute("REPLACE INTO settings (name, value) VALUES ('discovery_last_run_time', '$current_time')");
}

/* Search new graph, auto create and remove dublicate, down interface graphs */
$graph_search = TRUE;
/* Add new graph only Up interface ports */
$graph_interface_only_up = TRUE;


$graph_search = read_config_option("discovery_query_rerun");
if ($graph_search != true) {
	$graph_search = false;
}

$graph_interface_only_up = read_config_option("discovery_interface_up_only");
if ($graph_interface_only_up != true) {
	$graph_interface_only_up = false;
}

discover_debug("Setting 'Rerun Data Queries' = " . ($graph_search ? 'true' : 'false') . "\n");
discover_debug("Setting 'Create Graphs for Up Interfaces Only' = " . ($graph_interface_only_up ? 'true' : 'false') . "\n");

cacti_log("Network Discover is now running", true, "POLLER");

/* determine data queries to rerun */
if ($graph_search) {
	discover_debug("Rerunning Data Queries on Existing Hosts\n");
	$data_queries = db_fetch_assoc("select id,description from host where disabled != 'on'");
	$i = 1;
	foreach ($data_queries as $data_query) {
		discover_debug("  Host : " . $data_query["description"] . "\n");
		discover_create_graphs($data_query["id"]);
		discover_remove_graphs($data_query["id"]);
		$i++;
	}
}

// Get an array of our current hostnames
$temp = db_fetch_assoc("SELECT hostname FROM host");
$known = array();
foreach ($temp as $d) {
	$known[] = $d['hostname'];
}

// Get an array of our current host discr
$temp = db_fetch_assoc("SELECT description FROM host");
$known_descr = array();
foreach ($temp as $d) {
	$known_descr[] = $d['description'];
}

// Get Oses
$temp = db_fetch_assoc("SELECT plugin_discover_template.*, host_template.name 
	FROM plugin_discover_template
	LEFT JOIN host_template 
	ON (plugin_discover_template.host_template=host_template.id)");

$os = array();
if (is_array($temp)) {
	foreach ($temp as $d) {
		$os[] = $d;
	}
}

$dns = trim(read_config_option("discovery_dns"));

$ping_type = read_config_option("discovery_protocol");
if ($ping_type < 0 || $ping_type > 2) {
	$ping_type = 0;
}

switch ($ping_type) {
	case 2:
		$method = PING_ICMP;
		break;
	case 1:
		$method = PING_TCP;
		break;
	case 0:
		$method = PING_UDP;
		break;
}


// This is our current hosts

$hline = trim(read_config_option("discovery_subnet"));
if ($hline == '') {
	cacti_log("ERROR: Network Discover subnet setting is not set!", true, "POLLER");
	return;
}

$hline = str_replace(' ', '', $hline);
$hosts = explode(',', $hline);

$arg = 0;

$t = time() - 3600;
if ($forcerun) {
	db_execute("DELETE FROM plugin_discover_hosts");
}else{
	db_execute("DELETE FROM plugin_discover_hosts WHERE time<$t");
}

db_execute("UPDATE plugin_discover_hosts SET up=0");

$cnames = read_config_option("discovery_readstrings");

if ($cnames == '') {
	$cnames = 'public';
}

discover_debug("Community Names    : $cnames\n");

/* Let's do some stats! */
$stats = array();
$stats['scanned'] = 0;
$stats['ping']    = 0;
$stats['snmp']    = 0;
$stats['added']   = 0;
$count_graph      = 0;

for ($arg = 0; $arg < count($hosts); $arg++) {
	$count = 0;

	discover_debug("Calculating Info for Subnet :" . $hosts[$arg] . "\n");
	$start = discover_calculate_start($hosts, $arg);
	$total = discover_calculate_total_ips($hosts, $arg);

	$snmp_version = read_config_option("snmp_ver");
	$snmp_port    = read_config_option("snmp_port");
	$snmp_timeout = read_config_option("snmp_timeout");

	discover_debug("Start IP is " . $start . "\n");
	discover_debug("Total IPs is " . $total . "\n");

	while ($host = discover_get_next_host($start, $total, $count, $hosts, $arg)) {
		$count++;
		discover_debug($host);
		if (!in_array($host, $known)) {
			if (substr($host, -3) < 255) {
				$stats['scanned']++;
				$hash = discover_ip_hash($host);
				$device = array(
					'snmp_status'      => 0,
					'ping_status'      => 0,
					'hostname'         => $host,
					'community'        => '',
					'snmp_username'	=> '',
					'snmp_password'	=> '',
					'dnsname'          => '',
					'dnsname_short'		=> '',
					'snmp_readstring'  => '',
					'snmp_readstrings' => $cnames,
					'snmp_version'     => $snmp_version,
					'snmp_port'        => $snmp_port,
					'snmp_timeout'     => $snmp_timeout,
					'snmp_sysObjectID' => '',
					'snmp_sysName'     => '',
					'snmp_sysName_short'	=> '',
					'snmp_sysLocation' => '',
					'snmp_sysContact'  => '',
					'snmp_sysDescr'    => '',
					'snmp_sysUptime'   => 0,
					'os'               => '',
					'hash'             => $hash);

				/* create new ping socket for host pinging */
				$ping = new Net_Ping;
				$ping->host["hostname"] = $host;
				$ping->retries = 1;
				$ping->port    = 0;

				/* perform the appropriate ping check of the host */
				if (function_exists("socket_create") && phpversion() > "4.3") {
					$result = $ping->ping(AVAIL_PING, $method, $device['snmp_timeout'], 1);

					if (!$result) {
						discover_debug(" - Does not respond to ping!");
					}else{
						discover_debug(" - Responded to ping!");
						$stats['ping']++;
					}
					$force  = false;
				}else{
					$force  = true;
				}

				if (($result || $force) && discover_valid_snmp_device($device)){
					if ($dns != '') {
						$dnsname = discover_get_dns_from_ip($host, $dns, 300);
						if ($dnsname != $host && $dnsname != 'timed_out') {
							$device['dnsname'] = $dnsname;
						}
						$device['dnsname_short'] = preg_split('/[\.]+/', strtolower($dnsname), -1, PREG_SPLIT_NO_EMPTY);
					}else{
						$dnsname = $host;
						$device['dnsname'] = '';
						$device['dnsname_short'] = '';
					}

					$snmp_sysName = preg_split('/[\s.]+/', $device['snmp_sysName'], -1, PREG_SPLIT_NO_EMPTY);
					if(!isset($snmp_sysName[0])) {
						$snmp_sysName[0] = '';
					}
					$snmp_sysName_short = preg_split('/[\.]+/', strtolower($snmp_sysName[0]), -1, PREG_SPLIT_NO_EMPTY);

					if (($dns != '' && in_array($device['dnsname'], $known)) || 
						($dns != '' && in_array($device['dnsname_short'][0], $known)) || 
						(isset($snmp_sysName[0]) && $snmp_sysName[0] != '' && in_array($snmp_sysName[0], $known)) ||
						(isset($snmp_sysName_short[0]) && $snmp_sysName_short[0] != '' && in_array($snmp_sysName_short[0], $known))){
						discover_debug(" - Host DNS is already in hosts table!");
						discover_debug(" DNS: " . $device['dnsname'] . " - " . $device['dnsname_short'][0] . " SNMP: " . $snmp_sysName[0] . " - " . $snmp_sysName_short[0]);
					} else {
						$isDuplicateSysName = sizeof(db_fetch_assoc("SELECT * FROM plugin_discover_hosts WHERE sysName='" . $snmp_sysName[0] . "'"));

						if ($isDuplicateSysName) {
							discover_debug(" - Ignoring Address Already Discovered as Another IP!\n");
							continue;
						}

						if ($force) $stats['ping']++;
						$stats['snmp']++;
						$host_id = 0;
						discover_debug(" - Is a valid device! DNS: " . $device['dnsname'] . " SNMP: " . $snmp_sysName[0]);
						$fos = discover_find_os($device['snmp_sysDescr']);
						if ($fos != false) {
							$device['os'] = $fos['name'];
							discover_debug("\n     Host Template: " . $fos['name']);
							$device['host_template'] = $fos['host_template'];
							$device['tree'] = $fos['tree']; # this will be skipped when using autom8, see discover_add_device
							$device['community'] = sql_sanitize($device['community']);
							$device['snmp_version'] = sql_sanitize($device['snmp_version']);
							$device['snmp_username'] = sql_sanitize($device['snmp_username']);
							$device['snmp_password'] = sql_sanitize($device['snmp_password']);
							$device['snmp_auth_protocol'] = sql_sanitize($device['snmp_auth_protocol']);
							$device['snmp_priv_passphrase'] = sql_sanitize($device['snmp_priv_passphrase']);
							$device['snmp_priv_protocol'] = sql_sanitize($device['snmp_priv_protocol']);
							$device['snmp_context'] = sql_sanitize($device['snmp_context']);
							$device['snmp_sysName'] = sql_sanitize($device['snmp_sysName']);
							$device['snmp_sysLocation'] = sql_sanitize($device['snmp_sysLocation']);
							$device['snmp_sysContact'] = sql_sanitize($device['snmp_sysContact']);
							$device['snmp_sysDescr'] = sql_sanitize($device['snmp_sysDescr']);
							$device['snmp_sysUptime'] = sql_sanitize($device['snmp_sysUptime']);
							$host_id = discover_add_device($device);
							$stats['added']++;
						}
						if (!$host_id) {
							db_execute("REPLACE INTO plugin_discover_hosts (hostname, ip, hash, community, snmp_version, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, sysName, sysLocation, sysContact, sysDescr, sysUptime, os, snmp, up, time) VALUES ('"
								. sql_sanitize($device['dnsname'])
								. "', '$host', '"
								. $device['hash'] . "', '"
								. sql_sanitize($device['community']) . "', '"
								. sql_sanitize($device['snmp_version']) . "', '"
								. sql_sanitize($device['snmp_username']) . "', '"
								. sql_sanitize($device['snmp_password']) . "', '"
								. sql_sanitize($device['snmp_auth_protocol']) . "', '"
								. sql_sanitize($device['snmp_priv_passphrase']) . "', '"
								. sql_sanitize($device['snmp_priv_protocol']) . "', '"
								. sql_sanitize($device['snmp_context']) . "', '"
								. sql_sanitize($device['snmp_sysName']) . "', '"
								. sql_sanitize($device['snmp_sysLocation']) . "', '"
								. sql_sanitize($device['snmp_sysContact']) . "', '"
								. sql_sanitize($device['snmp_sysDescr']) . "', '"
								. sql_sanitize($device['snmp_sysUptime']) . "', '"
								. sql_sanitize($device['os']) . "', "
								. "1, 1,".time() . ')' );
						}
					}
				}else if ($result) {
					if ($dns != '') {
						$dnsname = discover_get_dns_from_ip($host, $dns, 300);
						if ($dnsname != $host && $dnsname != 'timed_out') {
							$device['dnsname'] = $dnsname;
						}
						$device['dnsname_short'] = preg_split('/[\.]+/', strtolower($dnsname), -1, PREG_SPLIT_NO_EMPTY);
					}else{
						$dnsname = $host;
						$device['dnsname'] = '';
						$device['dnsname_short'] = '';
					}
					db_execute("REPLACE INTO plugin_discover_hosts (hostname, ip, hash, community, snmp_version, snmp_username, snmp_password, snmp_auth_protocol, snmp_priv_passphrase, snmp_priv_protocol, snmp_context, sysName, sysLocation, sysContact, sysDescr, sysUptime, os, snmp, up, time) VALUES ('"
						. sql_sanitize($device['dnsname'])
						. "', '$host', '"
						. $device['hash'] . "', '"
						. sql_sanitize($device['community']) . "', '"
						. sql_sanitize($device['snmp_version']) . "', '"
						. sql_sanitize($device['snmp_username']) . "', '"
						. sql_sanitize($device['snmp_password']) . "', '"
						. sql_sanitize($device['snmp_auth_protocol']) . "', '"
						. sql_sanitize($device['snmp_priv_passphrase']) . "', '"
						. sql_sanitize($device['snmp_priv_protocol']) . "', '"
						. sql_sanitize($device['snmp_context']) . "', '"
						. sql_sanitize($device['snmp_sysName']) . "', '"
						. sql_sanitize($device['snmp_sysLocation']) . "', '"
						. sql_sanitize($device['snmp_sysContact']) . "', '"
						. sql_sanitize($device['snmp_sysDescr']) . "', "
						. sql_sanitize($device['snmp_sysUptime']) . ", "
						. "'', 0, 1,".time() . ')' );
					discover_debug(" - Host $dnsname is alive but no SNMP!");
				}
			} else {
				discover_debug(" - Ignoring Address (PHP Bug does not allow us to ping .255 as it thinks its a broadcast IP)!");
			}
		} else {
			discover_debug(" - Host is already in hosts table!");
		}

		discover_debug("\n");
	}
}

cacti_log($stats['scanned'] . " IPs Scanned, " . $stats['ping'] . " IPs Responded to Ping, " . $stats['snmp'] . " Responded to SNMP, " . $stats['added'] . " Device Added, " . $count_graph .  " Graphs Added to Cacti", true, "DISCOVER");

function discover_add_device ($device) {
	global $plugins, $config;

	$template_id          = $device['host_template'];
	$snmp_sysName         = preg_split('/[\s.]+/', $device['snmp_sysName'], -1, PREG_SPLIT_NO_EMPTY);
	$description          = ($snmp_sysName[0] != '' ? $snmp_sysName[0] : $device['hostname']);
	$ip                   = $device['hostname'];
	$community            = $device['community'];
	$snmp_ver             = $device['snmp_version'];
	$snmp_username	      = $device['snmp_username'];
	$snmp_password	      = $device['snmp_password'];
	$snmp_port            = $device['snmp_port'];
	$snmp_timeout         = read_config_option('snmp_timeout');
	$disable              = false;
	$tree                 = $device['tree'];
	$availability_method  = read_config_option("ping_method");
	$ping_method          = read_config_option("ping_method");
	$ping_port            = read_config_option("ping_port");
	$ping_timeout         = read_config_option("ping_timeout");
	$ping_retries         = read_config_option("ping_retries");
	$notes                = 'Added by Discovery Plugin';
	$snmp_auth_protocol   = $device['snmp_auth_protocol'];
	$snmp_priv_passphrase = $device['snmp_priv_passphrase'];
	$snmp_priv_protocol   = $device['snmp_priv_protocol'];
	$snmp_context	      = $device['snmp_context'];
	$device_threads       = 1;
	$max_oids             = 10;

	$host_id = api_device_save(0, $template_id, $description, $ip,
		$community, $snmp_ver, $snmp_username, $snmp_password,
		$snmp_port, $snmp_timeout, $disable, $availability_method,
		$ping_method, $ping_port, $ping_timeout, $ping_retries,
		$notes, $snmp_auth_protocol, $snmp_priv_passphrase,
		$snmp_priv_protocol, $snmp_context, $max_oids, $device_threads);

	if ($host_id) {
		/* prefer autom8 plugin if it exists */
		if (!api_plugin_is_enabled('autom8')) {
			discover_create_graphs($host_id);
			discover_add_tree ($host_id, $tree);
		}

		/* Use the thold plugin if it exists */
		if (api_plugin_is_enabled('thold')) {
			discover_debug("     Creating Thresholds\n");
			if (file_exists($config["base_path"] . "/plugins/thold/thold-functions.php")) {
				include_once($config["base_path"] . "/plugins/thold/thold-functions.php");
				autocreate($host_id);
			} else if (file_exists($config["base_path"] . "/plugins/thold/thold_functions.php")) {
				include_once($config["base_path"] . "/plugins/thold/thold_functions.php");
				autocreate($host_id);
			}
		}
		db_execute("DELETE FROM plugin_discover_hosts WHERE ip = '$ip' LIMIT 1");
	}
	//api_device_remove($host_id);
	return $host_id;
}

function discover_add_tree ($host_id, $tree) {
	discover_debug("     Adding to tree\n");
	if ($tree > 1000000) {
		$tree_id = $tree - 1000000;
		$parent = 0;
	} else {
		$tree_item = db_fetch_row('select * from graph_tree_items where id = ' . $tree);

		if (!isset($tree_item['graph_tree_id']))
			return;
		$tree_id = $tree_item['graph_tree_id'];
		$parent = $tree;
	}

	$nodeId = api_tree_item_save(0, $tree_id, 3, $parent, '', 0, 0, $host_id, 1, 1, false);
}

function discover_remove_graphs ($host_id) {
	$snmp_queries = db_fetch_assoc("select snmp_query_id as id from host_snmp_query where host_id=" . $host_id);
	foreach ($snmp_queries as $snmp_query) {
		$data_query_graphs = db_fetch_assoc("select graph_template_id from snmp_query_graph where snmp_query_id=" . $snmp_query["id"] . " GROUP BY graph_template_id order by name");
		$data_query_sources = db_fetch_assoc("select data_template_id from snmp_query_graph_rrd inner join snmp_query_graph on snmp_query_graph_id=id where snmp_query_id=" . $snmp_query["id"] . " and graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " GROUP BY data_template_id");
		$cont_graphs = db_fetch_assoc("select count(*) as num ,snmp_index from graph_local where host_id=" . $host_id . " and snmp_query_id=" . $snmp_query["id"] . " and graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " GROUP BY snmp_index");
		$cont_sources = db_fetch_assoc("select count(*) as num ,snmp_index from data_local where host_id=" . $host_id . " and snmp_query_id=" . $snmp_query["id"] . " and data_template_id=" . $data_query_sources[0]['data_template_id'] . " GROUP BY snmp_index");

		foreach ($cont_graphs as $cont_graph) {
			if ($cont_graph["num"] > 1 ) {
				$rm_graphs = db_fetch_assoc("select id from graph_local where host_id=" . $host_id . " and snmp_query_id=" . $snmp_query["id"] . " and graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " and snmp_index=" . $cont_graph["snmp_index"] . " order by id LIMIT 1,30");
				foreach ($rm_graphs as $rm_graph) {
					api_graph_remove($rm_graph["id"]);
				}
			}
		}
		foreach ($cont_sources as $cont_source) {
			if ($cont_source["num"] > 1 ) {
				$rm_sources = db_fetch_assoc("select id from data_local where host_id=" . $host_id . " and snmp_query_id=" . $snmp_query["id"] . " and data_template_id=" . $data_query_sources[0]['data_template_id'] . " and snmp_index=" . $cont_source["snmp_index"] . " order by id LIMIT 1,30");
				foreach ($rm_sources as $rm_source) {
					api_data_source_remove($rm_source["id"]);
				}
			}
		}
		if ($snmp_query["id"] == 1) {
			$rm_graphs = db_fetch_assoc("select id from graph_local where host_id=" . $host_id . " and snmp_query_id=1 and graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " and snmp_index in (select snmp_index from host_snmp_cache where host_id=" . $host_id . " and field_name='ifOperStatus' and field_value='Down' and snmp_query_id=1)");
			$rm_sources = db_fetch_assoc("select id from data_local where host_id=" . $host_id . " and snmp_query_id=1 and data_template_id=" . $data_query_sources[0]['data_template_id'] . " and snmp_index in (select snmp_index from host_snmp_cache where host_id=" . $host_id . " and field_name='ifOperStatus' and field_value='Down' and snmp_query_id=1)");
			foreach ($rm_graphs as $rm_graph) {
				api_graph_remove($rm_graph["id"]);
			}
			foreach ($rm_sources as $rm_source) {
				api_data_source_remove($rm_source["id"]);
			}
		}
	}
}

function discover_remove_sources ($host_id) {
	$snmp_queries = db_fetch_assoc("select snmp_query_id as id from host_snmp_query where host_id=" . $host_id);
	foreach ($snmp_queries as $snmp_query) {
		$data_query_graphs = db_fetch_assoc("select graph_template_id from snmp_query_graph where snmp_query_id=" . $snmp_query["id"] . " GROUP BY graph_template_id order by name");
		$cont_graphs = db_fetch_assoc("select count(*) as num ,snmp_index from graph_local where graph_local.host_id=" . $host_id . " and snmp_query_id=" . $snmp_query["id"] . " and graph_local.graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " GROUP BY snmp_index");
		foreach ($cont_graphs as $cont_graph) {
			if ($cont_graph["num"] > 1 ) {
				$rm_graphs = db_fetch_assoc("select id from graph_local where host_id=" . $host_id . " and snmp_query_id=" . $snmp_query["id"] . " and graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " and snmp_index=" . $cont_graph["snmp_index"] . " order by graph_local.id LIMIT 1,30");
				foreach ($rm_graphs as $rm_graph) {
					api_graph_remove($rm_graph["id"]);
				}
			}
		}
		if ($snmp_query["id"] == 1) {
			$rm_graphs = db_fetch_assoc("select id from graph_local where host_id=" . $host_id . " and snmp_query_id=1 and graph_template_id=" . $data_query_graphs[0]['graph_template_id'] . " and snmp_index in (select snmp_index from host_snmp_cache where host_id=" . $host_id . " and field_name='ifOperStatus' and field_value='Down' and snmp_query_id=1)");
			foreach ($rm_graphs as $rm_graph) {
				api_graph_remove($rm_graph["id"]);
			}
		}
	}
}

function discover_create_graphs ($host_id) {
	global $graph_interface_only_up;
	$sgraphs = array();
	discover_debug("    Creating Graphs\n");
	$graph_templates = db_fetch_assoc("select
		graph_templates.id as graph_template_id,
		graph_templates.name as graph_template_name
		from (host_graph,graph_templates)
		where host_graph.graph_template_id=graph_templates.id
		and graph_templates.id not in
		(select graph_local.graph_template_id from graph_local where snmp_query_id = 0 and graph_local.host_id=" . $host_id . ")
		and host_graph.host_id=" . $host_id . "
		order by graph_templates.name");

	$template_graphs = db_fetch_assoc("select
		graph_local.graph_template_id
		from (graph_local,host_graph)
		where graph_local.graph_template_id=host_graph.graph_template_id
		and graph_local.host_id=host_graph.host_id
		and graph_local.host_id=" . $host_id . "
		group by graph_local.graph_template_id");

	foreach ($graph_templates as $graph_template) {
		 $query_row = $graph_template["graph_template_id"];
		 $sgraphs['cg_' . $query_row] = 1;
	}

	$snmp_queries = db_fetch_assoc("select
		snmp_query.id,
		snmp_query.name,
		snmp_query.xml_path
		from (snmp_query,host_snmp_query)
		where host_snmp_query.snmp_query_id=snmp_query.id
		and host_snmp_query.host_id=" . $host_id . "
		order by snmp_query.name");

	if (sizeof($snmp_queries) > 0) {
		foreach ($snmp_queries as $snmp_query) {
			$xml_array = get_data_query_array($snmp_query["id"]);
			$num_input_fields = 0;
			$num_visible_fields = 0;
			$data_query_graphs = db_fetch_assoc("select snmp_query_graph.id,snmp_query_graph.name,snmp_query_graph.graph_template_id from snmp_query_graph where snmp_query_graph.snmp_query_id=" . $snmp_query["id"] . " order by snmp_query_graph.name");
			if ($xml_array != false) {
				$html_dq_header = "";
				$snmp_query_indexes = array();

				reset($xml_array["fields"]);
				while (list($field_name, $field_array) = each($xml_array["fields"])) {
					if ($field_array["direction"] == "input") {
						$raw_data = '';
						if ($snmp_query["id"] == 1 && $graph_interface_only_up) {
							$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where snmp_index in
									(select snmp_index from host_snmp_cache where snmp_index not in
									(select snmp_index from graph_local WHERE host_id =" . $host_id . " and graph_template_id = " . $data_query_graphs[0]['graph_template_id'] . ")
									and host_id=" . $host_id . " and field_name='ifOperStatus' and field_value='Up' and snmp_query_id=" . $snmp_query["id"] .")
									and host_id=" . $host_id . " and field_name='$field_name' and snmp_query_id=" . $snmp_query["id"]);
						} elseif ($snmp_query["id"] != 8 ){
							$raw_data = db_fetch_assoc("select field_value,snmp_index from host_snmp_cache where snmp_index not in
									(select snmp_index from graph_local WHERE host_id =" . $host_id . " and snmp_query_id =" . $snmp_query["id"] ." and graph_template_id = " . $data_query_graphs[0]['graph_template_id'] . ")
									and host_id=" . $host_id . " and field_name='$field_name' and snmp_query_id=" . $snmp_query["id"]);
						}

						/* don't even both to display the column if it has no data */
						if (sizeof($raw_data) > 0 && $raw_data != '') {
							/* draw each header item <TD> */

							foreach ($raw_data as $data) {
								$snmp_query_data[$field_name]{$data["snmp_index"]} = $data["field_value"];

								if (!in_array($data["snmp_index"], $snmp_query_indexes,TRUE)) {
									array_push($snmp_query_indexes, $data["snmp_index"]);
								}
							}
							$num_visible_fields++;
						}
					}
				}

				if (isset($xml_array["index_order_type"])) {
					if ($xml_array["index_order_type"] == "numeric") {
						usort($snmp_query_indexes, "usort_numeric");
					}else if ($xml_array["index_order_type"] == "alphabetic") {
						usort($snmp_query_indexes, "usort_alphabetic");
					}else if ($xml_array["index_order_type"] == "natural") {
						usort($snmp_query_indexes, "usort_natural");
					}
				}

				if (sizeof($snmp_query_indexes) > 0) {
					while (list($id, $snmp_index) = each($snmp_query_indexes)) {
						$query_row = $snmp_query["id"] . "_" . encode_data_query_index($snmp_index);
						$sgraphs['sg_' . $query_row] = 1;
					}
				}
			}

			$sgraphs['sgg_' . $snmp_query["id"]] = $data_query_graphs[0]['id'];

		}
	}

	$selected_graphs = array();
	while (list($var, $val) = each($sgraphs)) {
		if (preg_match('/^cg_(\d+)$/', $var, $matches)) {
			$selected_graphs["cg"]{$matches[1]}{$matches[1]} = true;
		}elseif (preg_match('/^cg_g$/', $var)) {
			if ($_POST["cg_g"] > 0) {
				$selected_graphs["cg"]{$sgraphs["cg_g"]}{$sgraphs["cg_g"]} = true;
			}
		}elseif (preg_match('/^sg_(\d+)_([a-f0-9]{32})$/', $var, $matches)) {
			$selected_graphs["sg"]{$matches[1]}{$sgraphs{"sgg_" . $matches[1]}}{$matches[2]} = true;
		}
	}

	if (!empty($selected_graphs)) {
		discover_host_new_graphs_save($selected_graphs, $host_id);
	}
}

function discover_host_new_graphs_save($selected_graphs, $host_id) {
	global $count_graph;
	$selected_graphs_array = $selected_graphs;
	/* form an array that contains all of the data on the previous form */
	while (list($var, $val) = each($_POST)) {
		if (preg_match("/^g_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["graph_template"]{$matches[3]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["graph_template"]{$matches[3]} = $val;
			}
		}elseif (preg_match("/^gi_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: graph_template_input_id, 4:field_name */
			/* ================= input validation ================= */
			input_validate_input_number($matches[3]);
			/* ==================================================== */

			/* we need to find out which graph items will be affected by saving this particular item */
			$item_list = db_fetch_assoc("select
				graph_template_item_id
				from graph_template_input_defs
				where graph_template_input_id=" . $matches[3]);

			/* loop through each item affected and update column data */
			if (sizeof($item_list) > 0) {
			foreach ($item_list as $item) {

				if (empty($matches[1])) { /* this is a new graph from template field */
					$values["cg"]{$matches[2]}["graph_template_item"]{$item["graph_template_item_id"]}{$matches[4]} = $val;
				}else{ /* this is a data query field */
					$values["sg"]{$matches[1]}{$matches[2]}["graph_template_item"]{$item["graph_template_item_id"]}{$matches[4]} = $val;
				}
			}
			}
		}elseif (preg_match("/^d_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["data_template"]{$matches[3]}{$matches[4]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["data_template"]{$matches[3]}{$matches[4]} = $val;
			}
		}elseif (preg_match("/^c_(\d+)_(\d+)_(\d+)_(\d+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:data_input_field_id */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["custom_data"]{$matches[3]}{$matches[4]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["custom_data"]{$matches[3]}{$matches[4]} = $val;
			}
		}elseif (preg_match("/^di_(\d+)_(\d+)_(\d+)_(\d+)_(\w+)/", $var, $matches)) { /* 1: snmp_query_id, 2: graph_template_id, 3: data_template_id, 4:local_data_template_rrd_id, 5:field_name */
			if (empty($matches[1])) { /* this is a new graph from template field */
				$values["cg"]{$matches[2]}["data_template_item"]{$matches[4]}{$matches[5]} = $val;
			}else{ /* this is a data query field */
				$values["sg"]{$matches[1]}{$matches[2]}["data_template_item"]{$matches[4]}{$matches[5]} = $val;
			}
		}
	}

	while (list($form_type, $form_array) = each($selected_graphs_array)) {
		$current_form_type = $form_type;
		while (list($form_id1, $form_array2) = each($form_array)) {
			/* enumerate information from the arrays stored in post variables */
			if ($form_type == "cg") {
				$graph_template_id = $form_id1;
			}elseif ($form_type == "sg") {
				while (list($form_id2, $form_array3) = each($form_array2)) {
					$snmp_index_array = $form_array3;

					$snmp_query_array["snmp_query_id"] = $form_id1;
					$snmp_query_array["snmp_index_on"] = get_best_data_query_index_type($host_id, $form_id1);
					$snmp_query_array["snmp_query_graph_id"] = $form_id2;
				}

				$graph_template_id = db_fetch_cell("select graph_template_id from snmp_query_graph where id=" . $snmp_query_array["snmp_query_graph_id"]);
			}

			if ($current_form_type == "cg") {
				$values = array();
				$return_array = create_complete_graph_from_template($graph_template_id, $host_id, "", $values["cg"]);
				debug_log_insert("new_graphs", "Created graph: " . get_graph_title($return_array["local_graph_id"]));
				discover_debug("        Created graph: " . get_graph_title($return_array["local_graph_id"]) . "\n");
				$count_graph++;
			}elseif ($current_form_type == "sg") {
				while (list($snmp_index, $true) = each($snmp_index_array)) {
					$snmp_query_array["snmp_index"] = decode_data_query_index($snmp_index, $snmp_query_array["snmp_query_id"], $host_id);
					$return_array = create_complete_graph_from_template($graph_template_id, $host_id, $snmp_query_array, $values["sg"]{$snmp_query_array["snmp_query_id"]});
					debug_log_insert("new_graphs", "Created graph: " . get_graph_title($return_array["local_graph_id"]));
					discover_debug("        Created graph: " . get_graph_title($return_array["local_graph_id"]) . "\n");
					$count_graph++;
				}
			}
		}
	}

	/* lastly push host-specific information to our data sources */
	push_out_host($host_id,0);
}

function discover_recreate_tables () {
	discover_debug("Request received to recreate the Discover Plugin's tables\n");
	discover_debug("   Dropping the tables\n");
	db_execute("drop table plugin_discover_hosts");
#	db_execute("drop table plugin_discover_os");

	discover_debug("   Creating the tables\n");
	discovery_setup_table ();
}

function discover_ip_hash($ip) {
	$ips = explode('.',$ip);
	$hash = ($ips[0] * 16777216) + ($ips[1] * 65536) + ($ips[2] * 256) + $ips[3];
	return $hash;
}

function discover_find_os($text) {
	global $os;
	for ($a = 0; $a < count($os); $a++) {
		if (stristr($text, $os[$a]['sysdescr'])) {
			return $os[$a];
		}
	}
	return false;
}

function discover_debug($text) {
	global $debug;
	if ($debug)	print $text;
}

function discover_calculate_start($hosts, $arg) {
	if (!isset($hosts[$arg])) return false;
	$h = trim($hosts[$arg]);

	// 10.1.0.1
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $h)) {
		return $h;
	}

	// 10.1.0.*
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\*$/", $h)) {
		return substr($h,0,-1) . "1";
	}
	// 10.1.*.1
	if (preg_match("/^([0-9]{1,3}\.[0-9]{1,3}\.)\*(\.[0-9]{1,3})$/", $h, $matches)) {
		return $matches[1] . "1" . $matches[2];
	}

	// 10.1.0.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\./", $h, $matches);
		return $matches[0][0] . "1";
	}

	// 10.1.0./24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\./", $h, $matches);
		return $matches[0][0] . "1";
	}

	// 10.1.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/", $h, $matches);
		return $matches[0][0] . ".1";
	}

	// 10.1.0.172/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/", $h, $matches);
		return $matches[0][0];
	}

	// 10.1.0.0/255.255.255.0
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/255\.255\.[0-9]{1,3}\.0$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\./", $h, $matches);
		return $matches[0][0] . "1";
	}

	// 10.1.0.19/255.255.255.240
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/255\.255\.255\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}/", $h, $matches);
		return $matches[0][0];
	}

	// 10.1.0.19-10.1.0.27
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-/", $h, $matches);
		return substr($matches[0][0], 0, -1);
	}
	discover_debug("  Could not calculate starting address!\n");
	return false;
}

function discover_calculate_total_ips($hosts, $arg) {
	if (!isset($hosts[$arg])) return false;
	$h = trim($hosts[$arg]);

	// 10.1.0.1
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $h)) {
		return 1;
	}

	// 10.1.0.*
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\*$/", $h)) {
		return "254";
	}

	// 10.1.*.1
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.\*\.[0-9]{1,3}$/", $h)) {
		return "254";
	}

	// 10.1.0.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0./24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0.172/24
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/[0-9]{1,2}$/", $h)) {
		preg_match_all("/\/[0-9]{1,2}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		$total = 1;
		for ($x = 0; $x < (32-$subnet); $x++) {
			$total = $total * 2;
		}
		return $total-2;
	}

	// 10.1.0.0/255.255.255.0
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.0\/255\.255\.[0-9]{1,3}\.0$/", $h)) {
		preg_match_all("/[0-9]{1,3}.0$/", $h, $matches);
		$subnet = substr($matches[0][0], 0, -2);
		return ((256 - $subnet)*256) - 2;
	}

	// 10.1.0.19/255.255.255.240
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\/255\.255\.255\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/\.[0-9]{1,3}$/", $h, $matches);
		$subnet = substr($matches[0][0], 1);
		return (256 - $subnet)-1;
	}

	// 10.1.0.19-10.1.0.27
	if (preg_match("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\$/", $h)) {
		preg_match_all("/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}-/", $h, $matches);
		preg_match_all("/-[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $h, $matches2);
		$start = substr($matches[0][0], 0, -1);
		$end = substr($matches2[0][0], 1);
		$starta = explode('.', $start);
		$enda = explode('.', $end);
		$start = ($starta[0] * 16777216) + ($starta[1] * 65536) + ($starta[2] * 256) + $starta[3];
		$end = ($enda[0] * 16777216) + ($enda[1] * 65536) + ($enda[2] * 256) + $enda[3];
		return $end - $start + 1;
	}
	discover_debug("  Could not calculate total IPs!\n");
	return false;
}

function discover_get_next_host ($start, $total, $count, $hosts, $arg) {
	if (!isset($hosts[$arg])) return false;
	$h = trim($hosts[$arg]);

	if ($count == $total || $total < 1)
		return false;

	if (preg_match("/^([0-9]{1,3}\.[0-9]{1,3}\.)\*(\.[0-9]{1,3})$/", $h, $matches)) {
		// 10.1.*.1
		return $matches[1] . ++$count . $matches[2];
	} else {
		// other cases
		$ip = explode('.', $start);
		$y = 16777216;
		for ($x = 0; $x < 4; $x++) {
			$ip[$x] += intval($count/$y);
			$count -= ((intval($count/$y))*256);
			$y = $y / 256;
			if ($ip[$x] == 256 && $x > 0) {
				$ip[$x] = 0;
				$ip[$x-1] += 1;
			}
		}
		return implode('.',$ip);
	}
}

function discover_valid_snmp_device (&$device) {
	/* initialize variable */
	$host_up = FALSE;
	$device["snmp_status"] = HOST_DOWN;
	$device["ping_status"] = 0;

	/* force php to return numeric oid's */
	if (function_exists("snmp_set_oid_numeric_print")) {
		snmp_set_oid_numeric_print(TRUE);
	}

	$snmp_username = read_config_option('snmp_username');
	$snmp_password = read_config_option('snmp_password');
	$snmp_auth_protocol = read_config_option('snmp_auth_protocol');
	$snmp_priv_passphrase = read_config_option('snmp_priv_passphrase');
	$snmp_priv_protocol = read_config_option('snmp_priv_protocol');
	$snmp_context = '';

	$device['snmp_auth_username'] = '';
	$device['snmp_password'] = '';
	$device['snmp_auth_protocol'] = '';
	$device['snmp_priv_passphrase'] = '';
	$device['snmp_priv_protocol'] = '';
	$device['snmp_context'] = '';

	$version = array(2 => '1', 1 => '2');
	if ($snmp_username != '' && $snmp_password != '') {
		$version[0] = '3';
	}
	$version = array_reverse($version);

	if ($device['snmp_readstrings'] != '') {
		/* loop through the default and then other common for the correct answer */
		$read_strings = explode(':', $device['snmp_readstrings']);

		$device['snmp_status'] = HOST_DOWN;
		$host_up = FALSE;

		foreach ($version as $v) {
			discover_debug(" - checking SNMP V$v");
			if ($v == 3) {
				$device['snmp_username'] = $snmp_username;
				$device['snmp_password'] = $snmp_password;
				$device['snmp_auth_protocol'] = $snmp_auth_protocol;
				$device['snmp_priv_passphrase'] = $snmp_priv_passphrase;
				$device['snmp_priv_protocol'] = $snmp_priv_protocol;
				$device['snmp_context'] = $snmp_context;

				/* Community string is not used for v3 */
				$snmp_sysObjectID = @cacti_snmp_get($device['hostname'], '', 	'.1.3.6.1.2.1.1.2.0', $v,
						$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
						$device['snmp_port'], $device['snmp_timeout']);
				$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
				$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
				$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);
			if ((strlen($snmp_sysObjectID) > 0) &&
					(!substr_count($snmp_sysObjectID, 'No Such Object')) && 
					(!substr_count($snmp_sysObjectID, 'Error In'))) {
					$snmp_sysObjectID = trim(str_replace('"', '', $snmp_sysObjectID));
					$device['snmp_readstring'] = '';
					$device['snmp_status'] = HOST_UP;
					$device['snmp_version'] = $v;
				$host_up = TRUE;
				break;
			}
			} else {
				$device['snmp_username'] = '';
				$device['snmp_password'] = '';
				$device['snmp_auth_protocol'] = '';
				$device['snmp_priv_passphrase'] = '';
				$device['snmp_priv_protocol'] = '';
				$device['snmp_context'] = '';

				foreach ($read_strings as $snmp_readstring) {
					discover_debug(" - checking community $snmp_readstring");
					$snmp_sysObjectID = @cacti_snmp_get($device['hostname'], $snmp_readstring, 	'.1.3.6.1.2.1.1.2.0', $v,
							$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
							$device['snmp_port'], $device['snmp_timeout']);
					$snmp_sysObjectID = str_replace('enterprises', '.1.3.6.1.4.1', $snmp_sysObjectID);
					$snmp_sysObjectID = str_replace('OID: ', '', $snmp_sysObjectID);
					$snmp_sysObjectID = str_replace('.iso', '.1', $snmp_sysObjectID);
					if ((strlen($snmp_sysObjectID) > 0) && 
						(!substr_count($snmp_sysObjectID, 'No Such Object')) && 
						(!substr_count($snmp_sysObjectID, 'Error In'))) {
						$snmp_sysObjectID = trim(str_replace('"', '', $snmp_sysObjectID));
						$device['snmp_readstring'] = $snmp_readstring;
						$device['snmp_status'] = HOST_UP;
						$device['snmp_version'] = $v;
						$host_up = TRUE;
						break;
					}
				}
			}
			if ($host_up == TRUE) {
				break;
			}

		}
	}

	if ($host_up) {
		$device["snmp_sysObjectID"] = $snmp_sysObjectID;
		$device["community"] = $device["snmp_readstring"];
		/* get system name */
		$snmp_sysName = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.5.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout']);

		if (strlen($snmp_sysName) > 0) {
			$snmp_sysName = trim(strtr($snmp_sysName,"\""," "));
			$device["snmp_sysName"] = $snmp_sysName;
		}

		/* get system location */
		$snmp_sysLocation = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.6.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout']);

		if (strlen($snmp_sysLocation) > 0) {
			$snmp_sysLocation = trim(strtr($snmp_sysLocation,"\""," "));
			$device["snmp_sysLocation"] = $snmp_sysLocation;
		}

		/* get system contact */
		$snmp_sysContact = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.4.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout']);

		if (strlen($snmp_sysContact) > 0) {
			$snmp_sysContact = trim(strtr($snmp_sysContact,"\""," "));
			$device["snmp_sysContact"] = $snmp_sysContact;
		}

		/* get system description */
		$snmp_sysDescr = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.1.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout']);

		if (strlen($snmp_sysDescr) > 0) {
			$snmp_sysDescr = trim(strtr($snmp_sysDescr,"\""," "));
			$device["snmp_sysDescr"] = $snmp_sysDescr;
		}

		/* get system uptime */
		$snmp_sysUptime = @cacti_snmp_get($device['hostname'], $device['snmp_readstring'],
					'.1.3.6.1.2.1.1.3.0', $device['snmp_version'],
					$device['snmp_username'], $device['snmp_password'], $device['snmp_auth_protocol'], $device['snmp_priv_passphrase'], $device['snmp_priv_protocol'], $device['snmp_context'],
					$device['snmp_port'], $device['snmp_timeout']);

		if (strlen($snmp_sysUptime) > 0) {
			$snmp_sysUptime = trim(strtr($snmp_sysUptime,"\""," "));
			$device["snmp_sysUptime"] = $snmp_sysUptime;
		}
	}

	return $host_up;
}

/*	gethostbyaddr_wtimeout - This function provides a good method of performing
  a rapid lookup of a DNS entry for a host so long as you don't have to look far.
*/
function discover_get_dns_from_ip($ip, $dns, $timeout = 1000) {
	/* random transaction number (for routers etc to get the reply back) */
	$data = rand(10, 99);

	/* trim it to 2 bytes */
	$data = substr($data, 0, 2);

	/* create request header */
	$data .= "\1\0\0\1\0\0\0\0\0\0";

	/* split IP into octets */
	$octets = explode(".", $ip);

	/* perform a quick error check */
	if (count($octets) != 4) return "ERROR";

	/* needs a byte to indicate the length of each segment of the request */
	for ($x=3; $x>=0; $x--) {
		switch (strlen($octets[$x])) {
		case 1: // 1 byte long segment
			$data .= "\1"; break;
		case 2: // 2 byte long segment
			$data .= "\2"; break;
		case 3: // 3 byte long segment
			$data .= "\3"; break;
		default: // segment is too big, invalid IP
			return "ERROR";
		}

		/* and the segment itself */
		$data .= $octets[$x];
	}

	/* and the final bit of the request */
	$data .= "\7in-addr\4arpa\0\0\x0C\0\1";

	/* create UDP socket */
	$handle = @fsockopen("udp://$dns", 53);

	@stream_set_timeout($handle, floor($timeout/1000), ($timeout*1000)%1000000);
	@stream_set_blocking($handle, 1);

	/* send our request (and store request size so we can cheat later) */
	$requestsize = @fwrite($handle, $data);

	/* get the response */
	$response = @fread($handle, 1000);

	/* check to see if it timed out */
	$info = @stream_get_meta_data($handle);

	/* close the socket */
	@fclose($handle);

	if ($info["timed_out"]) {
		return "timed_out";
	}

	/* more error handling */
	if ($response == "") { return $ip; }

	/* parse the response and find the response type */
	$type = @unpack("s", substr($response, $requestsize+2));

	if (isset($type[1]) && $type[1] == 0x0C00) {
		/* set up our variables */
		$host = "";
		$len = 0;

		/* set our pointer at the beginning of the hostname uses the request
		   size from earlier rather than work it out.
		*/
		$position = $requestsize + 12;

		/* reconstruct the hostname */
		do {
			/* get segment size */
			$len = unpack("c", substr($response, $position));

			/* null terminated string, so length 0 = finished */
			if ($len[1] == 0) {
				/* return the hostname, without the trailing '.' */
				return strtoupper(substr($host, 0, strlen($host) -1));
			}

			/* add the next segment to our host */
			$host .= substr($response, $position+1, $len[1]) . ".";

			/* move pointer on to the next segment */
			$position += $len[1] + 1;
		} while ($len != 0);

		/* error - return the hostname we constructed (without the . on the end) */
		return strtoupper($ip);
	}

	/* error - return the hostname */
	return strtoupper($ip);
}

/*	display_help - displays the usage of the function */
function display_help () {
	print "Network Discovery v1.0, Copyright 2006 - Jimmy Conner\n\n";
	print "usage: findhosts.php [-d] [-h] [--help] [-v] [--version]\n\n";
	print "-f            - Force the execution of a discovery process\n";
	print "-d            - Display verbose output during execution\n";
	print "-r            - Drop and Recreate the Discover Plugin's tables before running\n";
	print "-v --version  - Display this help message\n";
	print "-h --help     - display this help message\n";
}
?>

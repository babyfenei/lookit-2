<?php
/*
 ex: set tabstop=3 shiftwidth=3 autoindent:
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

if (!defined("BOOST_TIMER_START")) {
	define("BOOST_TIMER_START", 1);
	define("BOOST_TIMER_END", 0);
	define("BOOST_TIMER_STOP", BOOST_TIMER_END);
	define("BOOST_TIMER_TOTAL", 2);
	define("BOOST_TIMER_CYCLES", 3);
	define("BOOST_TIMER_OVERHEAD_MULTIPLIER", 20000);
}

function plugin_boost_install () {
	api_plugin_register_hook('boost', 'config_arrays',                      'boost_config_arrays',        "setup.php");
	api_plugin_register_hook('boost', 'config_settings',                    'boost_config_settings',      "setup.php");
	api_plugin_register_hook('boost', 'poller_on_demand',                   'boost_poller_on_demand',     "setup.php");
	api_plugin_register_hook('boost', 'poller_bottom',                      'boost_poller_bottom',        "setup.php");
	api_plugin_register_hook('boost', 'poller_command_args',                'boost_poller_command_args',  "setup.php");
	api_plugin_register_hook('boost', 'draw_navigation_text',               'boost_draw_navigation_text', "setup.php");
	api_plugin_register_hook('boost', 'rrdtool_function_graph_cache_check', 'boost_graph_cache_check',    "setup.php");
	api_plugin_register_hook('boost', 'rrdtool_function_fetch_cache_check', 'boost_fetch_cache_check',    "setup.php");
	api_plugin_register_hook('boost', 'rrdtool_function_graph_set_file',    'boost_graph_set_file',       "setup.php");
	api_plugin_register_hook('boost', 'prep_graph_array',                   'boost_prep_graph_array',     "setup.php");
	api_plugin_register_hook('boost', 'utilities_list',                     'boost_utilities_list',       "setup.php");
	api_plugin_register_hook('boost', 'utilities_action',                   'boost_utilities_action',     "setup.php");

	boost_setup_table_new ();
}

function plugin_boost_uninstall () {
	db_execute("DELETE FROM settings WHERE name LIKE '%boost%'");
	db_execute("DROP TABLE poller_output_boost");
	db_execute("DROP TABLE poller_output_boost_processes");
}

function plugin_boost_check_config () {
	/* Here we will check to ensure everything is configured */
	boost_check_upgrade();
	return TRUE;
}

function plugin_boost_upgrade () {
	/* Here we will upgrade to the newest version */
	boost_check_upgrade();
	return FALSE;
}

function plugin_boost_version () {
	return boost_version();
}

function boost_check_upgrade () {
	global $config;

	$files = array('index.php', 'plugins.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$current = plugin_boost_version();
	$current = $current['version'];
	$old     = db_fetch_row("SELECT * FROM plugin_config WHERE directory='boost'");
	if (sizeof($old) && $current != $old["version"]) {
		/* if the plugin is installed and/or active */
		if ($old["status"] == 1 || $old["status"] == 4) {
			/* re-register the hooks */
			plugin_boost_install();

			/* perform a database upgrade */
			boost_database_upgrade();
		}

		/* update the plugin information */
		$info = plugin_boost_version();
		$id   = db_fetch_cell("SELECT id FROM plugin_config WHERE directory='boost'");
		db_execute("UPDATE plugin_config
			SET name='" . $info["longname"] . "',
			author='"   . $info["author"]   . "',
			webpage='"  . $info["homepage"] . "',
			version='"  . $info["version"]  . "'
			WHERE id='$id'");
	}

	if ($old < 5.0) {
		boost_recreate_output_table_indexes();
	}
}

function boost_database_upgrade() {
	global $plugins, $config;
	return TRUE;
}

function boost_recreate_output_table_indexes() {
	$query_sched = "ALTER TABLE poller_output_boost ";
	$indexes = db_fetch_assoc("SHOW INDEXES FROM poller_output_boost");
	$old_indexes = array();
	if (sizeof($indexes)) {
		foreach($indexes as $index) {
			$old_indexes[] = $index["Key_name"];
		}
	}elseif ($old  < 4.3) {
		api_plugin_register_hook('boost', 'rrdtool_function_fetch_cache_check', 'boost_fetch_cache_check', "setup.php");
		db_execute("UPDATE plugin_hooks SET status=1 WHERE name='boost'");
	}
	$old_indexes = array_unique($old_indexes);
	foreach(array_unique($old_indexes) as $index) {
		if($index == "PRIMARY")
			$query_sched .= "DROP PRIMARY KEY, ";
		else
			$query_sched .= "DROP KEY $index, ";
	}
	$query_sched .=	"ADD PRIMARY KEY USING BTREE (`local_data_id`,`time`,`rrd_name`)";
	db_execute($query_sched);
}

function boost_check_dependencies() {
	global $plugins, $config;
	return TRUE;
}

function boost_setup_table_new () {
	db_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost` (
			`local_data_id` mediumint(8) unsigned NOT NULL default '0',
			`rrd_name` varchar(19) NOT NULL default '',
			`time` datetime NOT NULL default '0000-00-00 00:00:00',
			`output` varchar(512) NOT NULL,
			PRIMARY KEY USING BTREE (`local_data_id`,`time`,`rrd_name`)
		)
		ENGINE=MEMORY;");

	db_execute("CREATE TABLE IF NOT EXISTS `poller_output_boost_processes` (
			`sock_int_value` bigint(20) unsigned NOT NULL auto_increment,
			`status` varchar(255) default NULL,
		PRIMARY KEY (`sock_int_value`))
		ENGINE=MEMORY;");

	boost_recreate_output_table_indexes();
}

function boost_draw_navigation_text ($nav) {
	$nav["utilities.php:view_boost_status"] = array("title" => "Boost Status", "mapping" => "index.php:,utilities.php:", "url" => "utilities.php", "level" => "2");
	return $nav;
}

function boost_utilities_action($action) {
	global $config, $colors;

	if ($action == 'view_boost_status') {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_request("refresh"));
		/* ==================================================== */

		load_current_session_value("refresh", "sess_boost_utilities_refresh", "30");

		$refresh["seconds"] = $_REQUEST["refresh"];
		$refresh["page"] = "utilities.php?action=view_boost_status";

		include_once($config['base_path'] . "/include/top_header.php");

		boost_display_run_status();

		include_once($config['base_path'] . "/include/bottom_footer.php");

		exit;
	}
	return $action;

}

function boost_file_size_display($file_size, $digits = 2) {
	if ($file_size > 1024) {
		$file_size = round(($file_size / 1024), 0);

		if ($file_size > 1024) {
			$file_size = round(($file_size / 1024), 0);

			if ($file_size > 1024) {
				$file_size = round(($file_size / 1024), 0);
				$file_suffix = " GBytes";
			}else{
				$file_suffix = " MBytes";
			}
		}else{
			$file_suffix = " KBytes";
		}
	}else{
		$file_suffix = " Bytes";
	}

	$file_size = round($file_size, $digits) . $file_suffix;

	return $file_size;
}

function boost_get_total_rows() {
	return db_fetch_cell("SELECT SUM(TABLE_ROWS) FROM INFORMATION_SCHEMA.TABLES
				WHERE table_schema=SCHEMA()
				AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')
				GROUP BY table_schema");
}

function boost_display_run_status() {
	global $colors, $config, $refresh_interval, $boost_utilities_interval, $boost_refresh_interval, $boost_max_runtime;

	$last_run_time   = read_config_option("boost_last_run_time", TRUE);
	$next_run_time   = read_config_option("boost_next_run_time", TRUE);

	$rrd_updates     = read_config_option("boost_rrd_update_enable", TRUE);
	$boost_server    = read_config_option("boost_server_enable", TRUE);
	$boost_cache     = read_config_option("boost_png_cache_enable", TRUE);

	$max_records     = read_config_option("boost_rrd_update_max_records", TRUE);
	$max_runtime     = read_config_option("boost_rrd_update_max_runtime", TRUE);
	$update_interval = read_config_option("boost_rrd_update_interval", TRUE);
	$peak_memory     = read_config_option('boost_peak_memory', TRUE);
	$detail_stats    = read_config_option('stats_detail_boost', TRUE);

	html_start_box("<strong>Boost Status</strong>", "100%", $colors["header"], "1", "center", "");
	?>
	<script type="text/javascript">
	<!--
	function applyStatsRefresh(objForm) {
		strURL = '?action=view_boost_status&refresh=' + objForm.refresh[objForm.refresh.selectedIndex].value;
		document.location = strURL;
	}
	-->
	</script>
	<tr bgcolor="<?php print $colors["panel"];?>">
		<form name="form_boost_utilities_stats" method="post">
		<td>
			<table cellpadding="1" cellspacing="0">
				<tr>
					<td width="120" style="white-space:nowrap;">
						&nbsp;Refresh Interval:
					</td>
					<td width="1">
						<select name="refresh" onChange="applyStatsRefresh(document.form_boost_utilities_stats)">
						<?php
						foreach ($boost_utilities_interval as $key => $interval) {
							print '<option value="' . $key . '"'; if ($_REQUEST["refresh"] == $key) { print " selected"; } print ">" . $interval . "</option>";
						}
						?>
					</td>
					<td>
						&nbsp;<input type="submit" value="Refresh">
					</td>
				</tr>
			</table>
		</td>
		</form>
	</tr>
	<?php
	html_end_box(TRUE);
	html_start_box("", "100%", $colors["header"], "1", "center", "");

	/* get the boost table status */
	$boost_table_status = db_fetch_assoc("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE table_schema=SCHEMA()
						AND (table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost')");
	$pending_records = 0;
	$arch_records = 0;
	$data_length = 0;
	$engine = "";
	$max_data_length = 0;
	foreach($boost_table_status as $table) {
		if ($table["TABLE_NAME"] == "poller_output_boost") {
			$pending_records += $table["TABLE_ROWS"];
		} else {
			$arch_records += $table["TABLE_ROWS"];
		}
		$data_length += $table["DATA_LENGTH"];
		$data_length -= $table["DATA_FREE"];
		$engine = $table["ENGINE"];
		$max_data_length = $table["MAX_DATA_LENGTH"];
	}
	$total_records = $pending_records + $arch_records;
	$avg_row_length = intval($data_length / $total_records);

	$total_data_sources = db_fetch_cell("SELECT COUNT(*) FROM poller_item");

	$boost_status = read_config_option("boost_poller_status", TRUE);
	if (strlen($boost_status)) {
		$boost_status_array = explode(":", $boost_status);

		$boost_status_date = $boost_status_array[1];

		if (substr_count($boost_status_array[0], "complete")) $boost_status_text = "Idle";
		elseif (substr_count($boost_status_array[0], "running"))  $boost_status_text = "Running";
		elseif (substr_count($boost_status_array[0], "overrun"))    $boost_status_text = "Overrun Warning";
		elseif (substr_count($boost_status_array[0], "timeout"))  $boost_status_text = "Timed Out";
		else   $boost_status_text = "Other";
	}else{
		$boost_status_text = "Never Run";
		$boost_status_date = "";
	}

	$stats_boost = read_config_option("stats_boost", TRUE);
	if (strlen($stats_boost)) {
		$stats_boost_array = explode(" ", $stats_boost);

		$stats_duration = explode(":", $stats_boost_array[0]);
		$boost_last_run_duration = $stats_duration[1];

		$stats_rrds = explode(":", $stats_boost_array[1]);
		$boost_rrds_updated = $stats_rrds[1];
	}else{
		$boost_last_run_duration = "";
		$boost_rrds_updated = "";
	}


	/* get cache directory size/contents */
	$cache_directory = read_config_option("boost_png_cache_directory", TRUE);
	$directory_contents = array();

	if (is_dir($cache_directory)) {
		if ($handle = @opendir($cache_directory)) {
			/* This is the correct way to loop over the directory. */
			while (FALSE !== ($file = readdir($handle))) {
				$directory_contents[] = $file;
			}

			closedir($handle);

			/* get size of directory */
			$directory_size = 0;
			$cache_files = 0;
			if (sizeof($directory_contents)) {
				/* goto the cache directory */
				chdir($cache_directory);

				/* check and fry as applicable */
				foreach($directory_contents as $file) {
					/* only remove jpeg's and png's */
					if ((substr_count(strtolower($file), ".png")) ||
						(substr_count(strtolower($file), ".jpg"))) {
						$cache_files++;
						$directory_size += filesize($file);
					}
				}
			}

			$directory_size = boost_file_size_display($directory_size);
			$cache_files = $cache_files . " Files";
		}else{
			$directory_size = "<strong>WARNING:</strong> Can not open directory";
			$cache_files = "<strong>WARNING:</strong> Unknown";
		}
	}else{
		$directory_size = "<strong>WARNING:</strong> Directory Does NOT Exist!!";
		$cache_files = "<strong>WARNING:</strong> N/A";
	}

	$i = 0;

	/* boost status display */
	html_header(array("Current Boost Status"), 2);

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Boost On Demand Updating:</strong></td><td><strong>" . ($rrd_updates == "" ? "Disabled" : $boost_status_text) . "</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Total Data Sources:</strong></td><td>" . $total_data_sources . "</td>";

	if ($total_records > 0) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print "<td><strong>Pending Boost Records:</strong></td><td>" . $pending_records . "</td>";

		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print "<td><strong>Archived Boost Records:</strong></td><td>" . $arch_records . "</td>";

		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print "<td><strong>Total Boost Records:</strong></td><td>" . $total_records . "</td>";
	}

	/* boost status display */
	html_header(array("Boost Storage Statistics"), 2);

	/* describe the table format */
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Database Engine:</strong></td><td>" . $engine . "</td>";

	/* tell the user how big the table is */
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Current Boost Tables Size:</strong></td><td>" . boost_file_size_display($data_length, 2) . "</td>";

	/* tell the user about the average size/record */
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Avg Bytes/Record:</strong></td><td>" . boost_file_size_display($avg_row_length) . "</td>";

	/* tell the user about the average size/record */
	$output_length = read_config_option("boost_max_output_length");
	if (strlen($output_length)) {
		$parts = explode(":", $output_length);
		if ((time()-1200) > $parts[0]) {
			$refresh = TRUE;
		}else{
			$refresh = FALSE;
		}
	}else{
		$refresh = TRUE;
	}

	if ($refresh) {
		if (strcmp($engine, "MEMORY") == 0) {
			$max_length = db_fetch_cell("SELECT MAX(LENGTH(output)) FROM poller_output_boost");
		}else{
			$max_length = "0";
		}
		db_execute("REPLACE INTO settings (name,value) VALUES ('boost_max_output_length', '" . time() . ":" . $max_length . "')");
	}else{
		$max_length = $parts[1];
	}

	if ($max_length != 0) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print "<td><strong>Max Record Length:</strong></td><td>" . $max_length . " Bytes</td>";
	}

	/* tell the user about the "Maximum Size" this table can be */
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	if (strcmp($engine, "MEMORY")) {
		$max_table_allowed = "Unlimited";
		$max_table_records = "Unlimited";
	}else{
		$max_table_allowed = boost_file_size_display($max_data_length, 2);
		$max_table_records = round($max_data_length/$avg_row_length, 0);
	}
	print "<td><strong>Max Allowed Boost Table Size:</strong></td><td>" . $max_table_allowed . "</td>";

	/* tell the user about the estimated records that "could" be held in memory */
	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Estimated Maximum Records:</strong></td><td>" . $max_table_records  . " Records</td>";

	/* boost last runtime display */
	html_header(array("Runtime Statistics"), 2);

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Last Start Time:</strong></td><td>" . $last_run_time . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Last Run Duration:</strong></td><td>";
	print (($boost_last_run_duration > 60) ? (int)($boost_last_run_duration/60) . " minutes " : "" ) . $boost_last_run_duration%60 . " seconds";
	if ($rrd_updates != ""){ print " (" . round(100*$boost_last_run_duration/$update_interval/60) . "% of update frequency)";}
	print "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>RRD Updates:</strong></td><td>" . $boost_rrds_updated . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Peak Poller Memory:</strong></td><td>" . ((read_config_option('boost_peak_memory') != '') ? (round(read_config_option('boost_peak_memory')/1024/1024,2)) . " MBytes" : "N/A") . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Detailed Runtime Timers:</strong></td><td>" . (($detail_stats != '') ? $detail_stats:"N/A") . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Max Poller Memory Allowed:</strong></td><td>" . ((read_config_option('boost_poller_mem_limit') != '') ? (read_config_option('boost_poller_mem_limit')) . " MBytes" : "N/A") . "</td>";

	/* boost runtime display */
	html_header(array("Run Time Configuration"), 2);

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Update Frequency:</strong></td><td><strong>" . ($rrd_updates == "" ? "N/A" : $boost_refresh_interval[$update_interval]) . "</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Next Start Time:</strong></td><td>" . $next_run_time . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Maximum Records:</strong></td><td>" . $max_records . " Records</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td width=200><strong>Maximum Allowed Runtime:</strong></td><td>" . $boost_max_runtime[$max_runtime] . "</td>";

	/* boost runtime display */
	html_header(array("Boost Server Details"), 2);

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Server Config Status:</strong></td><td><strong>" . ($boost_server == "" ? "Disabled" : "Enabled") . "</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Multiprocess Server:</td><td>" . (read_config_option("boost_server_multiprocess", TRUE) == "1" ? "Multiple Process" : "Single Process") . "</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Update Timeout:</td><td>" . read_config_option("boost_server_timeout", TRUE) . " Seconds</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Server/Port:</td><td>" . read_config_option("boost_server_hostname", TRUE) . "@" . read_config_option("boost_server_listen_port", TRUE) . "</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Authorized Update Web Servers:</td><td>" . read_config_option("boost_server_clients", TRUE) . "</strong></td>";

	if (strlen(read_config_option("boost_path_rrdupdate")) && (read_config_option("boost_server_multiprocess") == 1)) {
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print "<td><strong>RRDtool Binary Used:</td><td>" . read_config_option("boost_path_rrdupdate") . "</strong></td>";
	}else{
		form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
		print "<td><strong>RRDtool Binary Used:</td><td>" . read_config_option("path_rrdtool") . "</strong></td>";
	}

	/* boost caching */
	html_header(array("Image Caching"), 2);

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Image Caching Status:</strong></td><td><strong>" . ($boost_cache == "" ? "Disabled" : "Enabled") . "</strong></td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Cache Directory:</strong></td><td>" . $cache_directory . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Cached Files:</strong></td><td>" . $cache_files . "</td>";

	form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
	print "<td><strong>Cached Files Size:</strong></td><td>" . $directory_size . "</td>";

	html_end_box(TRUE);
}

function boost_utilities_list() {
	global $config, $colors;

	html_header(array("Boost Utilities"), 2); ?>

	<tr bgcolor="#<?php print $colors["form_alternate1"];?>">
		<td class="textArea">
			<a href='utilities.php?action=view_boost_status'>View Boost Status</a>
		</td>
		<td class="textArea">
			This menu pick allows you to view various boost settings and statistics associated with the current running Boost configuration.
		</td>
	</tr>
	<?php
}

function boost_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
		/* define all error types */
		$errortype = array(
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Runtime Notice'
		);

		if (defined("E_RECOVERABLE_ERROR")) {
			$errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
		}

		if (defined("E_DEPRECATED")) {
			$errortype[E_DEPRECATED] = 'Deprecated Warning';
		}

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, "date_default_timezone")) return;
		if (substr_count($errmsg, "Only variables")) return;

		/* log the error to the Cacti log */
		cacti_log("PROGERR: " . $err, FALSE, "BOOST");
	}

	return;
}

function boost_check_correct_enabled() {
	if ((read_config_option("boost_rrd_update_enable") == "on") ||
		(read_config_option("boost_rrd_update_system_enable") == "on")) {
		/* turn on the system level updates as that is what dictates "off" */
		if (read_config_option("boost_rrd_update_system_enable") != "on") {
			db_execute("REPLACE INTO settings (name,value)
				VALUES ('boost_rrd_update_system_enable','on')");
		}

		/* if boost is not enabled remove all entries and disable */
		if (db_fetch_cell("SELECT status FROM plugin_config WHERE status=1 AND directory='boost'") != 1) {
			db_execute("TRUNCATE TABLE poller_output_boost");
			db_execute("REPLACE INTO settings (name,value) VALUES ('boost_rrd_update_system_enable', ''");
			db_execute("REPLACE INTO settings (name,value) VALUES ('boost_rrd_update_enable', ''");
			restore_error_handler();
			return FALSE;
		}
	}else{
		return FALSE;
	}

	return TRUE;
}

function boost_poller_on_demand(&$results) {
	global $config;

	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler("boost_error_handler");

	$outbuf      = "";
	$sql_prefix  = "INSERT INTO poller_output_boost (local_data_id, rrd_name, time, output) VALUES";
	$sql_suffix  = " ON DUPLICATE KEY UPDATE output=VALUES(output)";
	$overhead    = strlen($sql_prefix) + strlen($sql_suffix);

	if (boost_check_correct_enabled()) {
		/* if boost redirect is on, rows are being inserted directly */
		if (read_config_option("boost_redirect") == "on") {
			if (read_config_option("poller_type") == "1") {
				cacti_log("WARNING: Boost Redirect is enabled, but you are using cmd.php", false, "BOOST");
			}else{
			restore_error_handler();
			return FALSE;
		}
		}

		$max_allowed_packet = db_fetch_row("SHOW VARIABLES LIKE 'max_allowed_packet'");
		$max_allowed_packet = $max_allowed_packet["Value"];

		if (sizeof($results)) {
			$i          = 1;
			$out_length = 0;

			foreach($results as $result) {
				if ($i == 1) {
					$delim = " ";
				}else{
					$delim = ", ";
				}

				$outbuf .=
					$delim . "('" .
					$result["local_data_id"] . "','" .
					$result["rrd_name"] . "','" .
					$result["time"] . "','" .
					$result["output"] .	"')";

				$out_length += strlen($outbuf);

				if (($out_length + $overhead) > $max_allowed_packet) {
					db_execute($sql_prefix . $outbuf . $sql_suffix);
					$outbuf     = "";
					$out_length = 0;
					$i          = 1;
				}else{
					$i++;
				}
			}

			if (strlen($outbuf)) {
				db_execute($sql_prefix . $outbuf . $sql_suffix);
			}
		}

		$return_value = FALSE;
	}else{
		$return_value = TRUE;
	}

	/* restore original error handler */
	restore_error_handler();

	return $return_value;
}

function boost_fetch_cache_check($local_data_id) {
	global $config, $rrdtool_pipe, $rrdtool_read_pipe;

	/* include poller processing routinges */
	include_once($config["library_path"] . "/poller.php");

	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler("boost_error_handler");

	/* process input parameters */
	$rrdtool_pipe      = rrd_init();
	$rrdtool_read_pipe = rrd_init();

	/* get the information to populate into the rrd files */
	if (boost_check_correct_enabled()) {
		$updates += boost_process_poller_output(TRUE, $local_data_id);
	}

	/* restore original error handler */
	restore_error_handler();

	/* close rrdtool */
	rrd_close($rrdtool_pipe);
	rrd_close($rrdtool_read_pipe);

	return $local_data_id;
}

function boost_graph_cache_check($data) {
	global $config;

	/* include poller processing routinges */
	include_once($config["library_path"] . "/poller.php");

	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler("boost_error_handler");

	/* process input parameters */
	$local_graph_id = $data['local_graph_id'];
	$rra_id = $data['rra_id'];
	$rrdtool_pipe = $data['rrd_struc'];
	$graph_data_array = $data['graph_data_array'];

	/* if we are just printing the rrd command return */
	if (isset($graph_data_array["print_source"])) {
		/* restore original error handler */
		restore_error_handler();

		return FALSE;
	}

	/* if we want to view the error message, then don't show the cache */
	if ((isset($graph_data_array["output_type"])) &&
		($graph_data_array["output_type"] == RRDTOOL_OUTPUT_STDERR)) {
		/* restore original error handler */
		restore_error_handler();

		return FALSE;
	}

	/* get the information to populate into the rrd files */
	if (boost_check_correct_enabled()) {
		/* before we make a graph, we need to check for rrd updates and perform them. */
		$local_data_ids = db_fetch_assoc("SELECT DISTINCT
			data_template_rrd.local_data_id
			FROM graph_templates_item
			INNER JOIN data_template_rrd
			ON (graph_templates_item.task_item_id=data_template_rrd.id)
			WHERE graph_templates_item.local_graph_id='$local_graph_id'
			AND data_template_rrd.local_data_id > 0");

		/* first update the RRD files */
		if (sizeof($local_data_ids)) {
			$updates = 0;
			foreach($local_data_ids as $local_data_id) {
				$updates += boost_process_poller_output(TRUE, $local_data_id["local_data_id"]);
			}
			if ($updates) {
				/* restore original error handler */
				restore_error_handler();

				return FALSE;
			}
		}
	}

	if (isset($_SESSION["sess_current_timespan"])) {
		$timespan = $_SESSION["sess_current_timespan"];
	}else{
		$timespan = 0;
	}

	/* check the graph cache and use it if it is valid, otherwise turn over to
	 * cacti's graphing fucntions.
	 */
	if ((read_config_option("boost_png_cache_enable")) &&
		(boost_determine_caching_state())) {
		/* if timespan is greater than 1, it is a predefined, if it does not
		 * exist, it is the old fasioned MRTG type graph
		 */
		$cache_directory = read_config_option("boost_png_cache_directory");

		if (strlen($cache_directory)) {
			if (is_dir($cache_directory)) {
				if (is_writable($cache_directory)) {
					if ($rra_id > 0) {
						$cache_file = $cache_directory . "/lgi_" . $data["local_graph_id"] . "_rrai_" . $data["rra_id"];
					}else{
						$cache_file = $cache_directory . "/lgi_" . $data["local_graph_id"] . "_rrai_" . $data["rra_id"] . "_tsi_" . $timespan;
					}

					if (isset($graph_data_array["graph_height"])) {
						$cache_file .= "_height_" . $graph_data_array["graph_height"];
					}
					if (isset($graph_data_array["graph_width"])) {
						$cache_file .= "_width_" . $graph_data_array["graph_width"];
					}

					if (isset($graph_data_array["graph_nolegend"])) {
						$cache_file .= "_thumb.png";
					}else{
						$cache_file .= ".png";
					}

					if (file_exists($cache_file)) {
						$mod_time = filemtime($cache_file);
						$poller_interval = read_config_option("poller_interval");

						if (!isset($poller_interval)) {
							$poller_interval = "300";
						}

						if (($mod_time + $poller_interval) > time()) {
							if ($fileptr = fopen($cache_file, "rb")) {
								$data['return'] = fread($fileptr, filesize($cache_file));
								fclose($fileptr);

								/* restore original error handler */
								restore_error_handler();

								return $data;
							}else{
								if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
									cacti_log("Attempting to open cache file '$cache_file' failed", FALSE, "BOOST");
								}
							}
						}else{
							if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
								cacti_log("Boost Cache PNG Expired.  Image '$cache_file' will be recreated", FALSE, "BOOST");
							}
						}
					}
				}else{
					cacti_log("ERROR: Boost Cache Directory is not writable!  Can not cache images", FALSE, "BOOST");
				}
			}else{
				cacti_log("ERROR: Boost Cache Directory does not exist! Can not cache images", FALSE, "BOOST");
			}
		}else{
			cacti_log("ERROR: Boost Cache Directory variable is not set! Can not cache images", FALSE, "BOOST");
		}
	}

	/* restore original error handler */
	restore_error_handler();

	return FALSE;
}

function boost_prep_graph_array($graph_data_array) {
	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler("boost_error_handler");

	if (boost_determine_caching_state()) {
		if (!isset($graph_data_array["output_flag"])) {
			if (!isset($graph_data_array["print_source"])) {
				$graph_data_array["output_flag"] = RRDTOOL_OUTPUT_STDOUT;
			}
		}
	}

	/* restore original error handler */
	restore_error_handler();

	return $graph_data_array;
}

function boost_graph_set_file($data) {
	global $config, $boost_sock, $graph_data_array;

	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler("boost_error_handler");

	if (isset($_SESSION["sess_current_timespan"])) {
		$timespan = $_SESSION["sess_current_timespan"];
	}else{
		$timespan = 0;
	}

	/* check the graph cache and use it if it is valid, otherwise turn over to
	 * cacti's graphing fucntions.
	 */
	if ((read_config_option("boost_png_cache_enable")) &&
		(boost_determine_caching_state())) {
		$cache_directory = read_config_option("boost_png_cache_directory");

		if (strlen($cache_directory)) {
			if (is_dir($cache_directory)) {
				if ($data["rra_id"] > 0) {
					$cache_file = $cache_directory . "/lgi_" . $data["local_graph_id"] . "_rrai_" . $data["rra_id"];
				}else{
					$cache_file = $cache_directory . "/lgi_" . $data["local_graph_id"] . "_rrai_" . $data["rra_id"] . "_tsi_" . $timespan;
				}

				if (isset($graph_data_array["graph_height"])) {
					$cache_file .= "_height_" . $graph_data_array["graph_height"];
				}
				if (isset($graph_data_array["graph_width"])) {
					$cache_file .= "_width_" . $graph_data_array["graph_width"];
				}

				if (isset($graph_data_array["graph_nolegend"])) {
					$cache_file .= "_thumb.png";
				}else{
					$cache_file .= ".png";
				}

				if (is_writable($cache_directory)) {
					/* if the cache file was created in a prior step, save it */
					if (strlen($data["output"]) > 10) {
						if ($fileptr = fopen($cache_file, "w")) {
							fwrite($fileptr, $data["output"], strlen($data["output"]));
							fclose($fileptr);
							chmod($cache_file, 666);
						}
					}
				}else{
					cacti_log("ERROR: Boost Cache Directory is not writable!  Can not cache images", FALSE, "BOOST");
				}
			}else{
				cacti_log("ERROR: Boost Cache Directory does not exist! Can not cache images", FALSE, "BOOST");
			}
		}else{
			cacti_log("ERROR: Boost Cache Directory variable is not set! Can not cache images", FALSE, "BOOST");
		}
	}

	/* restore original error handler */
	restore_error_handler();
}

/* boost_timer - allows you to time events in boost and provide stats
   @arg $area - a text string that determines what area is being measured
   @arg $type - either 'start' or 'end' to start or end the timing */
function boost_timer($area, $type) {
	global $boost_stats_log;

	/* get the time */
	list($micro,$seconds) = split(" ", microtime());
	$btime = $seconds + $micro;

	if ($type == BOOST_TIMER_START) {
		$boost_stats_log[$area][BOOST_TIMER_START] = $btime;
	}elseif ($type == BOOST_TIMER_END) {
		if (isset($boost_stats_log[$area][BOOST_TIMER_START])) {
			if (!isset($boost_stats_log[$area][BOOST_TIMER_TOTAL])) {
				$boost_stats_log[$area][BOOST_TIMER_TOTAL] = 0;
				$boost_stats_log[$area][BOOST_TIMER_CYCLES] = 0;
			}
			$boost_stats_log[$area][BOOST_TIMER_TOTAL] += $btime - $boost_stats_log[$area][BOOST_TIMER_START];
			$boost_stats_log[$area][BOOST_TIMER_CYCLES]++;
			unset($boost_stats_log[$area][BOOST_TIMER_START]);
		}
	}
}

function boost_timer_get_overhead() {
	global $boost_stats_log;

	$start = microtime(TRUE);
	$area = "calibrate";
	for ($i = 0; $i < BOOST_TIMER_OVERHEAD_MULTIPLIER; $i++) {
		boost_timer($area, BOOST_TIMER_START);
		boost_timer($area, BOOST_TIMER_END);
	}
	unset($boost_stats_log[$area]);
	return (microtime(TRUE) - $start);
}

/* boost_get_arch_table_name - returns current archive boost table or FALSE if no arch table is present currently */
function boost_get_arch_table_name() {
	$res = db_fetch_cell("	SELECT table_name
				FROM information_schema.tables
					WHERE table_schema=SCHEMA()
					AND table_name LIKE 'poller_output_boost_arch_%'
					AND table_rows>0 LIMIT 1;
				");
	if(strlen($res)) {
		return $res;
	} else {
		return FALSE;
	}
}

/* boost_process_poller_output - grabs data from the 'poller_output' table and feeds the *completed*
     results to RRDTool for processing
   @arg $use_server - determines wether or not the server should be used if enabled
   @arg $local_data_id - if you are using boost, you need to update only an rra id at a time */
function boost_process_poller_output($use_server = FALSE, $local_data_id = "") {
	global $config, $boost_sock, $boost_timeout, $debug, $get_memory, $memory_used;
	global $rrdtool_pipe, $rrdtool_read_pipe;

	include_once($config["library_path"] . "/rrd.php");

	/* suppress warnings */
	if (defined("E_DEPRECATED")) {
		error_reporting(E_ALL ^ E_DEPRECATED);
	}else{
		error_reporting(E_ALL);
	}

	/* install the boost error handler */
	set_error_handler("boost_error_handler");

	/* load system variables needed */
	$log_verbosity		= read_config_option("log_verbosity");
	$upd_string_len		= read_config_option("boost_rrd_update_string_length");

	/* tiny SQL where addendum for boost */
	if (strlen($local_data_id)) {
		/* we can simplify the delete process if only one local_data_id */
		$single_local_data_id = TRUE;
		$orig_local_data_id   = $local_data_id;

		/* aquire lock in order to prevent race conditions */
		while (!db_fetch_cell("SELECT GET_LOCK('boost.single_ds.$local_data_id', 1)")) {
			usleep(50000);
		}
	}else{
		$single_local_data_id = FALSE;

		$poller_interval	= read_config_option("poller_interval");
		$rrd_update_interval	= read_config_option("boost_rrd_update_interval");
		$data_ids_to_get	= read_config_option("boost_rrd_update_max_records_per_select");

		$archive_table = boost_get_arch_table_name();

		if($archive_table === FALSE) {
			cacti_log("Failed to determine archive table", FALSE, "BOOST");
			return 0;
		}
	}

	/* get the records */
	if ($single_local_data_id) {
		$query_string = "";
		$arch_tables = db_fetch_assoc("	SELECT table_name AS name
						FROM information_schema.tables
							WHERE table_schema=SCHEMA()
							AND table_name LIKE 'poller_output_boost_arch_%'
							AND table_rows>0;
						");
		if(count($arch_tables)) {
		foreach($arch_tables as $table) {
			if (strlen($query_string)) {
				$query_string .= " UNION ";
			}
			$query_string .= " ( SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output FROM " . $table["name"] . " WHERE local_data_id='$local_data_id' ) ";
		}
		}

		if (strlen($query_string)) {
			$query_string .= " UNION ";
		}

		$timestamp = time();

		$query_string .= " ( SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output FROM poller_output_boost WHERE local_data_id='$local_data_id' AND time < FROM_UNIXTIME('$timestamp') ) ";
		$query_string .= "  ORDER BY local_data_id ASC, timestamp ASC, rrd_name ASC ";

	} else {
		$query_string = "SELECT local_data_id, UNIX_TIMESTAMP(time) AS timestamp, rrd_name, output FROM $archive_table FORCE INDEX (PRIMARY)
				ORDER BY local_data_id ASC, time ASC, rrd_name ASC
				LIMIT $data_ids_to_get ";

	}

	boost_timer("get_records", BOOST_TIMER_START);
	$results = db_fetch_assoc($query_string);
	boost_timer("get_records", BOOST_TIMER_END);

	/* log memory */
	if ($get_memory) {
		$cur_memory    = memory_get_usage();
		if ($cur_memory > $memory_used) {
			$memory_used = $cur_memory;
		}
	}

	if ($single_local_data_id && ($debug || $log_verbosity >= POLLER_VERBOSITY_MEDIUM)){
		cacti_log("NOTE: Updating Local Data ID:'$local_data_id', Total of '" . sizeof($results) . "' Updates to Process", FALSE, "BOOST");
	}

	if (sizeof($results) > 0) {
		/* open the boost socket connection if applicable */
		if ((read_config_option("boost_server_enable") == "on") &&
			($local_data_id != "") &&
			($use_server)) {
			$boost_timeout = read_config_option("boost_server_timeout");
			$boost_sock    = boost_server_connect();

			if ($boost_sock < 0) {
				/* restore original error handler */
				restore_error_handler();

				return 0;
			}
		}

		/* create an array keyed off of each .rrd file */
		$local_data_id  = -1;
		$time           = -1;
		$outbuf         = "";
		$last_update    = -1;
		$last_item	= array("local_data_id" => -1, "timestamp" => -1, "rrd_name" => "");

		/* we are going to blow away all record if ok */
		$vals_in_buffer = 0;

		/* cut last DS (likely to be incomplete due to LIMIT)*/
		if (!$single_local_data_id) {
			reset($results);
			$first_ds = $results[key($results)];
			$first_ds = intval($first_ds["local_data_id"]);
			end($results);
			$last_ds = $results[key($results)];
			$last_ds = intval($last_ds["local_data_id"]);
			reset($results);
			if ($first_ds == $last_ds) {
				if (sizeof($results) == $data_ids_to_get) {
					cacti_log("FALURE: Current LIMIT ($data_ids_to_get) is too low to run multiple DS RRD writes, consider raising it", FALSE, "BOOST");
				}
				restore_error_handler();
				return boost_process_poller_output($use_server, $first_ds);
			}
		}


		boost_timer("results_cycle", BOOST_TIMER_START);
		/* go through each poller_output_boost entries and process */
		foreach ($results as $item) {
			$item["timestamp"] = trim($item["timestamp"]);

			if (	$single_local_data_id && /* duplicate entry, possible when fetching data while rotating poller_output_boost */
				$last_item["local_data_id"] == $item["local_data_id"] &&
				$last_item["timestamp"] == $item["timestamp"] &&
				strcmp($last_item["rrd_name"], $item["rrd_name"]) == 0
			)
				continue;

			if (!$single_local_data_id && $first_ds != $last_ds && $last_ds == $item["local_data_id"]) {
				/* we faced last and possibly incomplete DS, bail out */
				break;
			}

			/* if the local_data_id changes, we need to flush the buffer */
			if ($local_data_id != $item["local_data_id"]) {
				/* update the rrd for the previous local_data_id */
				if ($vals_in_buffer) {
					if ($debug || $log_verbosity >= POLLER_VERBOSITY_MEDIUM) {
						cacti_log("NOTE: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ", Output:" . $outbuf, FALSE, "BOOST");
					}

					boost_timer("rrdupdate", BOOST_TIMER_START);
					$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
					boost_timer("rrdupdate", BOOST_TIMER_END);

					$outbuf = "";
					$vals_in_buffer = 0;

					/* check return status for delete operation */
					if (trim($return_value) != "OK") {
						cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", FALSE, "BOOST");
					}
				}

				/* reset the rrd file path and templates, assume non multi output */
				boost_timer("rrd_filename_and_template", BOOST_TIMER_START);
				$rrd_data    = boost_get_rrd_filename_and_template($item["local_data_id"]);
				$rrd_tmpl    = $rrd_data["rrd_template"];
				$rrd_path    = $rrd_data["rrd_path"];
				boost_timer("rrd_filename_and_template", BOOST_TIMER_END);

				$pipe	= is_resource($rrdtool_read_pipe) || is_array($rrdtool_read_pipe) ? $rrdtool_read_pipe:$rrdtool_pipe;
				boost_timer("rrd_lastupdate", BOOST_TIMER_START);
				$last_update = boost_rrdtool_get_last_update_time($rrd_path, $pipe);
				boost_timer("rrd_lastupdate", BOOST_TIMER_END);

				$local_data_id  = $item["local_data_id"];
				$time           = $item["timestamp"];
				$initial_time   = $time;
				$outbuf         = " " . $time;
				$multi_vals_set = FALSE;
			}

			/* don't generate error messages if the RRD has already been updated */
			if ($time <= $last_update){
				cacti_log("WARNING: Stale Poller Data Found! Item Time:'" . $time . "', RRD Time:'" . $last_update . "' Ignoring Value!", FALSE, "BOOST");
				$value = 'DNP';
			}else{
				$value = trim($item["output"]);
			}

			if ($time != $item["timestamp"]) {
				if (strlen($outbuf) > $upd_string_len) {
					if ($log_verbosity >= POLLER_VERBOSITY_MEDIUM) {
						cacti_log("NOTE: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ", Output:" . $outbuf, FALSE, "BOOST");
					}

					boost_timer("rrdupdate", BOOST_TIMER_START);
					$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
					boost_timer("rrdupdate", BOOST_TIMER_END);

					$outbuf         = "";
					$vals_in_buffer = 0;

					/* check return status for delete operation */
					if (trim($return_value) != "OK") {
						cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", FALSE, "BOOST");
					}
				}
				$outbuf .= " " . $item["timestamp"];
				$time    = $item["timestamp"];
			}

			/* single one value output */
			if (strcmp($value, 'DNP') == 0) {
				/* continue, bad time */
			}elseif ((is_numeric($value)) || (strcmp($value, "U") == 0)) {
				$outbuf .= ":" . $value;
				$vals_in_buffer++;
			}elseif ((function_exists("is_hexadecimal")) && (is_hexadecimal($value))) {
				$outbuf .= ":" . hexdec($value);
				$vals_in_buffer++;
			}elseif (strlen($value)) {
				/* break out multiple value output to an array */
				$values = explode(" ", $value);

				if (!$multi_vals_set) {
					$rrd_field_names = array_rekey(db_fetch_assoc("SELECT
						data_template_rrd.data_source_name,
						data_input_fields.data_name
						FROM (data_template_rrd,data_input_fields)
						WHERE data_template_rrd.data_input_field_id=data_input_fields.id
						AND data_template_rrd.local_data_id=" . $item["local_data_id"]), "data_name", "data_source_name");

					$rrd_tmpl = "";
				}

				$first_tmpl = 1;
				$multi_ok   = FALSE;
				for ($i=0; $i<count($values); $i++) {
					if (preg_match("/^([a-zA-Z0-9_\.-]+):([eE0-9\+\.-]+)$/", $values[$i], $matches)) {
						if (isset($rrd_field_names{$matches[1]})) {
							$multi_ok = TRUE;

							if ($log_verbosity == POLLER_VERBOSITY_DEBUG) {
								cacti_log("Parsed MULTI output field '" . $matches[0] . "' [map " . $matches[1] . "->" . $rrd_field_names{$matches[1]} . "]" , FALSE, "BOOST");
							}

							if (!$multi_vals_set) {
								if (!$first_tmpl) $rrd_tmpl .= ":";
								$rrd_tmpl .= $rrd_field_names{$matches[1]};
								$first_tmpl=0;
							}

							if (is_numeric($matches[2]) || ($matches[2] == "U")) {
								$outbuf .= ":" . $matches[2];
							}elseif ((function_exists("is_hexadecimal")) && (is_hexadecimal($matches[2]))) {
								$outbuf .= ":" . hexdec($matches[2]);
							}else{
								$outbuf .= ":U";
							}
						}
					}
				}

				/* we only want to process the template and gather the fields once */
				$multi_vals_set = TRUE;

				if ($multi_ok) {
					$vals_in_buffer++;
				}
			}else{
				cacti_log("WARNING: Local Data Id [" . $item["local_data_id"] . "] Contains an empty value", FALSE, "BOOST");
			}
		}

		/* process the last rrdupdate if applicable */
		if ($vals_in_buffer) {
			if ($log_verbosity >= POLLER_VERBOSITY_MEDIUM) {
				cacti_log("NOTE: Updating Local Data Id:'$local_data_id', Template:" . $rrd_tmpl . ", Output:" . $outbuf, FALSE, "BOOST");
			}

			boost_timer("rrdupdate", BOOST_TIMER_START);
			$return_value = boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_tmpl, $initial_time, $outbuf, $rrdtool_pipe);
			boost_timer("rrdupdate", BOOST_TIMER_END);

			/* check return status for delete operation */
			if (trim($return_value) != "OK") {
				cacti_log("WARNING: RRD Update Warning '" . $return_value . "' for Local Data ID '$local_data_id'", FALSE, "BOOST");
			}
		}
		boost_timer("results_cycle", BOOST_TIMER_END);

		/* remove the entries from the table */
		boost_timer("delete", BOOST_TIMER_START);
		if ($single_local_data_id) {
			$tables = db_fetch_assoc("SELECT table_name AS name
						FROM information_schema.tables
						WHERE table_schema=SCHEMA()
						AND ( table_name LIKE 'poller_output_boost_arch_%' OR table_name LIKE 'poller_output_boost' )
						AND table_rows>0;
						");
			if (count($tables)) {
			foreach($tables as $table) {
				db_execute("DELETE FROM " . $table["name"] . " WHERE local_data_id='$local_data_id' AND time < FROM_UNIXTIME('$timestamp')");
			}
			}
		} else {
			db_execute("DELETE FROM $archive_table WHERE local_data_id BETWEEN '$first_ds' AND '" . ($last_ds - 1) . "'");
		}
		boost_timer("delete", BOOST_TIMER_END);

		/* close the boost server connection, if applicable */
		if ((read_config_option("boost_server_enable") == "on") &&
			($local_data_id != "") &&
			($use_server)) {
			boost_server_disconnect($boost_sock);
		}
	}

	if ($single_local_data_id) {
		db_execute("SELECT RELEASE_LOCK('boost.single_ds.$orig_local_data_id')");
	}

	/* restore original error handler */
	restore_error_handler();

	return sizeof($results);
}

function boost_rrdtool_get_last_update_time($rrd_path, $rrdtool_pipe) {
	$return_value = 0;

	if (file_exists($rrd_path)){
		$return_value = boost_rrdtool_execute_internal("last $rrd_path", true, RRDTOOL_OUTPUT_STDOUT, "BOOST");
	}

	return trim($return_value);
}

function boost_determine_caching_state() {
	if (isset($_REQUEST["action"])) {
		$action = $_REQUEST["action"];
	}else{
		$action = "";
	}

	/* turn off image caching if viewing thold vrules */
	if ((isset($_SESSION['sess_config_array']['thold_draw_vrules'])) &&
		($_SESSION['sess_config_array']['thold_draw_vrules'] == "on")) {
		return FALSE;
	}

	/* turn off image caching for the following actions */
	if ($action == "properties" ||
		$action == "zoom"       ||
		$action == "edit"       ||
		$action == "graph_edit") {
		$cache = FALSE;
	}else{
		$cache = TRUE;
	}

	if (!isset($_SESSION["custom"])) {
		$custom = FALSE;
	}else{
		$custom = $_SESSION["custom"];
	}

	if (($cache) && (!$custom)) {
		return TRUE;
	}else{
		return FALSE;
	}
}

/* boost_get_rrd_filename_and_template - pulls
   1) the rrd_update template from the database in form of
      update decisions for multi-output RRD's
   2) rrd filename
   @arg $local_data_id - the data source to obtain information from */
function boost_get_rrd_filename_and_template($local_data_id) {
	$rrd_path     = "";
	$rrd_template = "";
	$all_nulls    = TRUE;
	$ds_null      = array();
	$ds_nnull     = array();

	$ds_names = db_fetch_assoc("SELECT data_source_name, rrd_path
		FROM data_template_rrd AS dtr
		INNER JOIN poller_item AS pi
		ON (pi.local_data_id=dtr.local_data_id
		AND (pi.rrd_name=dtr.data_source_name OR pi.rrd_name=''))
		WHERE dtr.local_data_id=$local_data_id
		ORDER BY data_source_name ASC;");

	if (sizeof($ds_names)) {
		foreach($ds_names as $ds_name) {
			if ($rrd_path == '') {
				$rrd_path = $ds_name['rrd_path'];
			}

			if ($ds_name['rrd_name'] == '') {
				$ds_null[] = $ds_name['data_source_name'];
			}elseif ($ds_name['rrd_name'] == $ds_name['data_source_name']) {
				$ds_nnull[] = $ds_name['data_source_name'];
				$all_nulls = FALSE;
			}
		}
	}

	if ($all_nulls) {
		$rrd_template = implode(":", $ds_null);
	}else{
		$rrd_template = implode(":", $ds_nnull);
	}

	return array("rrd_path" => $rrd_path, "rrd_template" => trim($rrd_template));
}

function boost_rrdtool_function_create($local_data_id, $initial_time, $show_source) {
	global $config;

	include ($config["include_path"] . "/global_arrays.php");

	$data_source_path = get_data_source_path($local_data_id, TRUE);

	/* ok, if that passes lets check to make sure an rra does not already
	exist, the last thing we want to do is overright data! */
	if ($show_source != TRUE) {
		if (file_exists($data_source_path) == TRUE) {
			return -1;
		}
	}

	/* the first thing we must do is make sure there is at least one
	rra associated with this data source... *
	UPDATE: As of version 0.6.6, we are splitting this up into two
	SQL strings because of the multiple DS per RRD support. This is
	not a big deal however since this function gets called once per
	data source */

	$rras = db_fetch_assoc("select
		data_template_data.rrd_step,
		rra.x_files_factor,
		rra.steps,
		rra.rows,
		rra_cf.consolidation_function_id,
		(rra.rows*rra.steps) as rra_order
		from data_template_data
		left join data_template_data_rra on (data_template_data.id=data_template_data_rra.data_template_data_id)
		left join rra on (data_template_data_rra.rra_id=rra.id)
		left join rra_cf on (rra.id=rra_cf.rra_id)
		where data_template_data.local_data_id=$local_data_id
		and (rra.steps is not null or rra.rows is not null)
		order by rra_cf.consolidation_function_id,rra_order");

	/* if we find that this DS has no RRA associated; get out.  This would
	 * indicate that a data sources has been deleted
	 */
	if (sizeof($rras) <= 0) {
		return FALSE;
	}

	/* back off the initial time to allow updates */
	$initial_time -= 300;

	/* create the "--step" line */
	$create_ds = RRD_NL . "--start " . $initial_time . " --step ". $rras[0]["rrd_step"] . " " . RRD_NL;

	/* query the data sources to be used in this .rrd file */
	$data_sources = db_fetch_assoc("SELECT
		data_template_rrd.id,
		data_template_rrd.rrd_heartbeat,
		data_template_rrd.rrd_minimum,
		data_template_rrd.rrd_maximum,
		data_template_rrd.data_source_type_id
		FROM data_template_rrd
		WHERE data_template_rrd.local_data_id=$local_data_id
		ORDER BY local_data_template_rrd_id");

	/* ONLY make a new DS entry if:
	- There is multiple data sources and this item is not the main one.
	- There is only one data source (then use it) */

	if (sizeof($data_sources) > 0) {
	foreach ($data_sources as $data_source) {
		/* use the cacti ds name by default or the user defined one, if entered */
		$data_source_name = get_data_source_item_name($data_source["id"]);

		if (empty($data_source["rrd_maximum"])) {
			/* in case no maximum is given, use "Undef" value */
			$data_source["rrd_maximum"] = "U";
		} elseif (strpos($data_source["rrd_maximum"], "|query_") !== false) {
			if ($data_source["rrd_maximum"] == "|query_ifSpeed|" || $data_source["rrd_maximum"] == "|query_ifHighSpeed|") {
				$highSpeed = db_fetch_cell("SELECT field_value
					FROM host_snmp_cache
					WHERE host_id=" . $data_local["host_id"] . "
					AND snmp_query_id=" . $data_local["snmp_query_id"] . "
					AND snmp_index='" . $data_local["snmp_index"] . "'
					AND field_name='ifHighSpeed'");

				if (!empty($highSpeed)) {
					$data_source["rrd_maximum"] = $highSpeed * 1000000;
				}else{
					$data_source["rrd_maximum"] = substitute_snmp_query_data("|query_ifSpeed|",$data_local["host_id"], $data_local["snmp_query_id"], $data_local["snmp_index"]);
				}
			}else{
				$data_source["rrd_maximum"] = substitute_snmp_query_data($data_source["rrd_maximum"],$data_local["host_id"], $data_local["snmp_query_id"], $data_local["snmp_index"]);
			}
		} elseif (($data_source["rrd_maximum"] != "U") && (int)$data_source["rrd_maximum"]<=(int)$data_source["rrd_minimum"]) {
			/* max > min required, but take care of an "Undef" value */
			$data_source["rrd_maximum"] = (int)$data_source["rrd_minimum"]+1;
		}

		/* min==max==0 won't work with rrdtool */
		if ($data_source["rrd_minimum"] == 0 && $data_source["rrd_maximum"] == 0) {
			$data_source["rrd_maximum"] = "U";
		}

		$create_ds .= "DS:$data_source_name:" . $data_source_types{$data_source["data_source_type_id"]} . ":" . $data_source["rrd_heartbeat"] . ":" . $data_source["rrd_minimum"] . ":" . $data_source["rrd_maximum"] . RRD_NL;
	}
	}

	$create_rra = "";
	/* loop through each available RRA for this DS */
	foreach ($rras as $rra) {
		$create_rra .= "RRA:" . $consolidation_functions{$rra["consolidation_function_id"]} . ":" . $rra["x_files_factor"] . ":" . $rra["steps"] . ":" . $rra["rows"] . RRD_NL;
	}

	/* check for structured path configuration, if in place verify directory
	   exists and if not create it.
	 */
	if (read_config_option("extended_paths") == "on") {
		if (!is_dir(dirname($data_source_path))) {
			if (mkdir(dirname($data_source_path), 0775)) {
				if ($config["cacti_server_os"] != "win32") {
					$owner_id      = fileowner($config["rra_path"]);
					$group_id      = filegroup($config["rra_path"]);

					if ((chown(dirname($data_source_path), $owner_id)) &&
						(chgrp(dirname($data_source_path), $group_id))) {
						/* permissions set ok */
					}else{
						cacti_log("ERROR: Unable to set directory permissions for '" . dirname($data_source_path) . "'", FALSE);
					}
				}
			}else{
				cacti_log("ERROR: Unable to create directory '" . dirname($data_source_path) . "'", FALSE);
			}
		}
	}

	if ($show_source == TRUE) {
		return read_config_option("path_rrdtool") . " create" . RRD_NL . "$data_source_path$create_ds$create_rra";
	}else{
		return boost_rrdtool_execute("create $data_source_path $create_ds$create_rra", FALSE, RRDTOOL_OUTPUT_STDOUT);
	}
}

/* boost_rrdtool_function_update - a re-write of the Cacti rrdtool update command
   specifically designed for bulk updates.
   @arg $local_data_id - the data source to obtain information from
   @arg $rrd_path      - the path to the RRD file
   @arg $rrd_update_template  - the order in which values need to be added
   @arg $rrd_update_values    - values to include in the database */
function boost_rrdtool_function_update($local_data_id, $rrd_path, $rrd_update_template, $initial_time, &$rrd_update_values) {
	global $rrdtool_pipe;

	/* lets count the number of rrd files processed */
	$rrds_processed = 0;

	/* let's check for deleted Data Sources */
	$valid_entry = TRUE;

	/* create the rrd if one does not already exist */
	if (!file_exists($rrd_path)) {
		$valid_entry = boost_rrdtool_function_create($local_data_id, $initial_time, FALSE, $rrdtool_pipe);
	}

	if ($valid_entry) {
		return boost_rrdtool_execute("update $rrd_path --template $rrd_update_template $rrd_update_values", FALSE, RRDTOOL_OUTPUT_STDOUT);
	}else{
		return "OK";
	}
}

function boost_rrdtool_execute_internal($command_line, $log_to_stdout, $output_flag, $logopt = "WEBLOG") {
	global $config, $rrdtool_pipe;

	static $last_command;

	if (!is_numeric($output_flag)) {
		$output_flag = RRDTOOL_OUTPUT_STDOUT;
	}

	/* WIN32: before sending this command off to rrdtool, get rid
	of all of the '\' characters. Unix does not care; win32 does.
	Also make sure to replace all of the fancy \'s at the end of the line,
	but make sure not to get rid of the "\n"'s that are supposed to be
	in there (text format) */
	$command_line = str_replace("\\\n", " ", $command_line);

	/* output information to the log file if appropriate */
	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_DEBUG) {
		cacti_log("CACTI2RRD: " . read_config_option("path_rrdtool") . " $command_line", $log_to_stdout, $logopt);
	}

	/* if we want to see the error output from rrdtool; make sure to specify this */
	if ($output_flag == RRDTOOL_OUTPUT_STDERR) {
		if (!(is_resource($rrdtool_pipe) || is_array($rrdtool_pipe))) {
			$command_line .= " 2>&1";
		}
	}

	/* an empty $rrdtool_pipe resource or no array means no fd is available */
	if (!(is_resource($rrdtool_pipe) || is_array($rrdtool_pipe))) {
		if ($config["cacti_server_os"] == "unix") {
			$popen_type = "r";
		}else{
			$popen_type = "rb";
		}

		session_write_close();
		$fp = popen(read_config_option("path_rrdtool") . escape_command(" $command_line"), $popen_type);
	}else{
		$i = 0;
		while (1) {
			if (function_exists("rrd_get_fd")) {
				$fd = rrd_get_fd($rrdtool_pipe, RRDTOOL_PIPE_CHILD_READ);
			}else{
				$fd = $rrdtool_pipe;
			}

			if (fwrite($fd, escape_command(" $command_line") . "\r\n") == false) {
				cacti_log("ERROR: Detected RRDtool Crash on '$command_line'.  Last command was '$last_command'");

				/* close the invalid pipe */
				rrd_close($rrdtool_pipe);

				/* open a new rrdtool process */
				$rrdtool_pipe = rrd_init();

				if ($i > 4) {
					cacti_log("FATAL: RRDtool Restart Attempts Exceeded.  Giving up on command.");

					break;
				}else{
					$i++;
				}

				continue;
			}else{
				fflush($fd);

				break;
			}
		}
	}

	/* store the last command to provide rrdtool segfault diagnostics */
	$last_command = $command_line;

	switch ($output_flag) {
		case RRDTOOL_OUTPUT_NULL:
			return; break;
		case RRDTOOL_OUTPUT_STDOUT:
			if (isset($fp) && is_resource($fp)) {
				$line = "";
				while (!feof($fp)) {
					$line .= fgets($fp, 4096);
				}

				pclose($fp);

				return $line;
			}

			break;
		case RRDTOOL_OUTPUT_STDERR:
			if (isset($fp) && is_resource($fp)) {
				$line = "";
				while (!feof($fp)) {
					$line .= fgets($fp, 4096);
				}

				pclose($fp);

				if (substr($output, 1, 3) == "PNG") {
					return "OK";
				}

				if (substr($output, 0, 5) == "GIF87") {
					return "OK";
				}

				print $output;
			}

			break;
		case RRDTOOL_OUTPUT_GRAPH_DATA:
			if (isset($fp) && is_resource($fp)) {
				$line = "";
				while (!feof($fp)) {
					$line .= fgets($fp, 4096);
				}

				pclose($fp);

				return $line;
			}

			break;
	}
}

function boost_rrdtool_execute($command, $output_to_stdout, $rrd_tool_output_options) {
	global $boost_sock;

	$return_value = "";

	if ((read_config_option("boost_server_enable") == "") ||
		($boost_sock == "")) {
		boost_rrdtool_execute_internal($command, $output_to_stdout, $rrd_tool_output_options, "BOOST");
		return "OK";
	}else{
		return boost_server_run($command);
	}
}

function boost_server_connect() {
	/* get some information about the server */
	$boost_server = read_config_option("boost_server_hostname");
	$boost_port = read_config_option("boost_server_listen_port");

	/* create a streaming socket, of type TCP/IP */
	$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
	socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
	socket_bind($sock, 0);

	if (!@socket_connect($sock, $boost_server, $boost_port)) {
		cacti_log("ERROR: Socket Error. Boost server is down.  Contact support immediately!!", FALSE, "BOOST");
		$sock = -1;
	}

	return $sock;
}

function boost_server_run($command) {
	global $boost_sock, $boost_timeout;

	$data = "FAILED";

	if ($boost_sock > 0) {
		socket_set_block($boost_sock);

		$len = strlen($command);
		socket_write($boost_sock, $command);

		$read[] = $boost_sock;

		if ((socket_select($read, $write = NULL, $except = NULL, $boost_timeout) > 0)) {
			$data = @socket_read($boost_sock, 1024);
		}else{
			cacti_log("ERROR: Timeout detected.  Boost server is down.  Contact support immediately!!", TRUE, "BOOST");
			socket_close($boost_sock);
		}
	}

	return trim($data);
}

function boost_server_disconnect($sock) {
	global $eol;

	/* do not display warnings or errors, it messes up rrd graphical output */
	if ($sock > 0) {
		@socket_write($sock, "quit" . $eol , strlen("quit" . $eol));
		@socket_close($sock);
	}
}

function boost_poller_command_args ($args) {
	boost_memory_limit();

	return $args;
}

function boost_memory_limit() {
	ini_set("memory_limit", read_config_option("boost_poller_mem_limit") . "M");
}

function boost_poller_bottom ($output) {
	global $config;
	include_once($config["base_path"] . "/lib/poller.php");

	$command_string = read_config_option("path_php_binary");

	if (read_config_option("path_boost_log") != "") {
		if ($config["cacti_server_os"] == "unix") {
			$extra_args = "-q " . $config["base_path"] . "/plugins/boost/poller_boost.php >> " . read_config_option("path_boost_log") . " 2>&1";
		}else{
			$extra_args = "-q " . $config["base_path"] . "/plugins/boost/poller_boost.php >> " . read_config_option("path_boost_log");
		}
	}else{
		$extra_args = "-q " . $config["base_path"] . "/plugins/boost/poller_boost.php";
	}

	exec_background($command_string, "$extra_args");
}

function boost_version () {
	return array(
		'name'      => 'boost',
		'version'   => '5.1',
		'longname'  => 'Large Site Performance Booster',
		'author'    => 'The Cacti Group',
		'homepage'  => 'http://www.cacti.net',
		'email'	    => '',
		'url'       => 'http://www.cacti.net'
	);
}

function boost_config_settings () {
	global $tabs, $settings, $boost_refresh_interval, $boost_max_runtime, $boost_max_memory, $boost_max_rows_per_select;

	/* check for an upgrade */
	plugin_boost_check_config();

	$tabs["boost"] = "Boost";

	$settings["boost"] = array(
		"boost_hq_header" => array(
			"friendly_name" => "On Demand RRD Update Settings",
			"method" => "spacer",
		),
		"boost_rrd_update_enable" => array(
			"friendly_name" => "Enable On Demand RRD Updating",
			"description" => "Should Boost enable on demand RRD updating in Cacti?  If you disable, this change will not take affect until after the next polling cycle.",
			"method" => "checkbox",
			"default" => ""
		),
		"boost_rrd_update_system_enable" => array(
			"friendly_name" => "System Level RRD Updater",
			"description" => "Before RRD On Demand Update Can be Cleared, a poller run must alway pass",
			"method" => "hidden",
			"default" => ""
		),
		"boost_rrd_update_interval" => array(
			"friendly_name" => "How Often Should Boost Update All RRD's",
			"description" => "When you enable boost, your RRD files are only updated when they are requested by a user, or when this time period elapses.",
			"default" => "60",
			"method" => "drop_array",
			"default" => "60",
			"array" => $boost_refresh_interval
		),
		"boost_rrd_update_max_records" => array(
			"friendly_name" => "Maximum Records",
			"description" => "If the boost output table exceeds this size, in records, an update will take place.",
			"method" => "textbox",
			"default" => "1000000",
			"max_length" => "20"
		),
		"boost_rrd_update_max_records_per_select" => array(
			"friendly_name" => "Maximum Data Source Items Per Pass",
			"description" => "To optimize performance, the boost RRD updater needs to know how many Data Source Items
			should be retrieved in one pass.  Please be careful not to set too high as graphing performance during
			major updates can be compromised.  If you encounter graphing or polling slowness during updates, lower this
			number.  The default value is 50000.",
			"method" => "drop_array",
			"default" => "50000",
			"array" => $boost_max_rows_per_select
		),
		"boost_rrd_update_string_length" => array(
			"friendly_name" => "Maximum Argument Length",
			"description" => "When boost sends update commands to RRDtool, it must not exceed the operating systems
			Maximum Argument Length.  This varies by operating system and kernel level.  For example:
			Windows 2000 <= 2048, FreeBSD <= 65535, Linux 2.6.22-- <= 131072, Linux 2.6.23++ unlimited",
			"method" => "textbox",
			"default" => "2000",
			"max_length" => "20"
		),
		"boost_poller_mem_limit" => array(
			"friendly_name" => "Memory Limit for Boost and Poller",
			"description" => "The maximum amount of memory for the Cacti Poller and Boost's Poller",
			"method" => "drop_array",
			"default" => "1024",
			"array" => $boost_max_memory
		),
		"boost_rrd_update_max_runtime" => array(
			"friendly_name" => "Maximum RRD Update Script Run Time",
			"description" => "The maximum boot poller run time allowed prior to boost issuing warning
			messages relative to possible hardware/software issues preventing proper updates.",
			"method" => "drop_array",
			"default" => "1200",
			"array" => $boost_max_runtime
		),
		"boost_redirect" => array(
			"friendly_name" => "Enable direct population of poller_output_boost table by spine",
			"description" => "Enables direct insert of records into poller output boost with results in a 25% time reduction in each poll cycle.",
			"method" => "checkbox",
			"default" => ""
		),
		"boost_srv_header" => array(
			"friendly_name" => "Boost Server Settings",
			"method" => "spacer",
		),
		"boost_server_enable" => array(
			"friendly_name" => "Enable Boost Server",
			"description" => "Should Boost server be used to RRDupdates?  If you do not select, then your web server needs R/W to rra directories.",
			"method" => "checkbox",
			"default" => ""
		),
		"boost_server_effective_user" => array(
			"friendly_name" => "Effective User ID",
			"description" => "This UNIX/LINUX only option allows you to use this users UID to create and otherwise operate on RRD's.  In order to use this, you must also be using the 'posix' module in PHP.  A Boost Server restart is required to enact any changes in this setting.",
			"method" => "textbox",
			"default" => "root",
			"max_length" => "20"
		),
		"boost_server_multiprocess" => array(
			"friendly_name" => "Multiprocess Server",
			"description" => "Do you want the boost server to fork a seprate update process for each boost request?  A Boost Server restart is required to enact any changes in this setting.",
			"method" => "drop_array",
			"default" => "1",
			"array" => array("0" => "No", "1" => "Yes")
		),
		"boost_path_rrdupdate" => array(
			"friendly_name" => "RRDUpdate Path",
			"description" => "If you are using the Multiprocess Boost server, it is best to utilize the 'rrdupdate' binary to update your RRDs.  Specify it's path here.  Otherwise, boost will use the 'rrdtool' binary.",
			"method" => "textbox",
			"default" => "",
			"max_length" => "255"
		),
		"boost_server_hostname" => array(
			"friendly_name" => "Hostname or IP for Boost Server",
			"description" => "The Hostname/IP for the boost server.",
			"method" => "textbox",
			"default" => "localhost",
			"max_length" => "100"
		),
		"boost_server_listen_port" => array(
			"friendly_name" => "TCP Port to Communicate On",
			"description" => "The boost server will listen on this port and the client will talk to this port.  A Boost Server restart is required to enact any changes in this setting.",
			"method" => "textbox",
			"default" => "9050",
			"max_length" => "10"
		),
		"boost_server_timeout" => array(
			"friendly_name" => "Boost Server Timeout",
			"description" => "The timeout, in seconds, that the client should wait on the boost server before giving up.",
			"method" => "textbox",
			"default" => "2",
			"max_length" => "10"
		),
		"boost_server_clients" => array(
			"friendly_name" => "Allowed Web Hosts",
			"description" => "A comma separated list of host IP's allowed to connect to the boost server.",
			"method" => "textbox",
			"default" => "127.0.0.1",
			"max_length" => "512"
		),
		"boost_png_header" => array(
			"friendly_name" => "Image Caching",
			"method" => "spacer",
		),
		"boost_png_cache_enable" => array(
			"friendly_name" => "Enable Image Caching",
			"description" => "Should image caching be enabled?",
			"method" => "checkbox",
			"default" => ""
		),
		"boost_png_cache_directory" => array(
			"friendly_name" => "Location for Image Files",
			"description" => "Specify the location where Boost should place your image files.  These files will be automatically purged by the poller when they expire.",
			"method" => "textbox",
			"max_length" => "255",
			"default" => ""
		),
		"boost_process_header" => array(
			"friendly_name" => "Process Interlocking",
			"method" => "spacer",
		),
		"boost_log_header" => array(
			"friendly_name" => "Debug Logging",
			"method" => "spacer",
		),
		"path_boost_log" => array(
			"friendly_name" => "Boost Debug Log",
			"description" => "If this field is non-blank, Boost will log RRDupdate output from the boost
			poller process.",
			"method" => "filepath",
			"default" => "",
			"max_length" => "255"
		)
	);
}

function boost_config_arrays () {
	global $boost_refresh_interval, $boost_max_runtime, $boost_max_memory, $boost_utilities_interval, $boost_max_rows_per_select;

	$boost_max_rows_per_select = array(
		"2000" => "2,000 Data Source Items",
		"5000" => "5,000 Data Source Items",
		"10000" => "10,000 Data Source Items",
		"15000" => "15,000 Data Source Items",
		"25000" => "25,000 Data Source Items",
		"50000" => "50,000 Data Source Items (Default)",
		"100000" => "100,000 Data Source Items",
		"200000" => "200,000 Data Source Items",
		"400000" => "400,000 Data Source Items");

	$boost_utilities_interval = array(
		"999999" => "Disabled",
		"5"   => "5 Seconds",
		"10"  => "10 Seconds",
		"15"  => "15 Seconds",
		"20"  => "20 Seconds",
		"30"  => "30 Seconds",
		"60"  => "1 Minute",
		"300" => "5 Minutes");

	$boost_refresh_interval = array(
		"30"  => "30 Minutes",
		"60"  => "1 Hour",
		"120" => "2 Hours",
		"240" => "4 Hours",
		"360" => "6 Hours");

	$boost_max_runtime = array(
		"1200" => "20 Minutes",
		"2400" => "40 Minutes",
		"3600" => "1 Hour",
		"4800" => "1.5 Hours");

	$boost_max_memory = array(
		"32" => "32 MBytes",
		"64" => "64 MBytes",
		"128" => "128 MBytes",
		"256" => "256 MBytes",
		"512" => "512 MBytes",
		"1024" => "1 GBytes",
		"1536" => "1.5 GBytes",
		"2048" => "2 GBytes",
		"3072" => "3 GBytes");
}

?>

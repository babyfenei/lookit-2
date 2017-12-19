<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 Reinhard Scheck aka gandalf                          |
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
 */

/** duplicate_nectar  duplicates a report and all items
 * @param int $_id			- id of the report
 * @param string $_title	- title of the new report
 */
function duplicate_nectar($_id, $_title) {
	global $fields_nectar_edit, $current_user;
	nectar_log(__FUNCTION__ . ", id: " . $_id, false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);

	$nectar = db_fetch_row("SELECT * FROM plugin_nectar WHERE id=$_id");
	$nectar_items = db_fetch_assoc("SELECT * FROM plugin_nectar_items WHERE report_id=$_id");

	$save = array();
	reset($fields_nectar_edit);
	while (list($field, $array) = each($fields_nectar_edit)) {
		if (!preg_match("/^hidden/", $array["method"]) &&
			!preg_match("/^spacer/", $array["method"])) {
			$save[$field] = $nectar[$field];
		}
	}

	/* duplicate to your id */
	$save["user_id"] = $_SESSION["sess_user_id"];

	/* substitute the title variable */
	$save["name"] = str_replace("<name>", $nectar["name"], $_title);
	/* create new rule */
	$save["enabled"] = "";
	$save["id"] = 0;
	$nectar_id  = sql_save($save, "plugin_nectar");

	/* create new rule items */
	if (sizeof($nectar_items) > 0) {
		foreach ($nectar_items as $nectar_item) {
			$save = $nectar_item;
			$save["id"] = 0;
			$save["report_id"] = $nectar_id;
			$nectar_item_id = sql_save($save, "plugin_nectar_items");
		}
	}
}

/** nectar_date_time_format		fetches the date/time formatting information for current user
 * @return string	- string defining the datetime format specific to this user
 */
function nectar_date_time_format() {
	global $config;

	$graph_date = "";

	/* setup date format */
	$date_fmt = read_graph_config_option("default_date_format");
	$datechar = read_graph_config_option("default_datechar");

	switch ($datechar) {
		case GDC_HYPHEN: 	$datechar = "-"; break;
		case GDC_SLASH: 	$datechar = "/"; break;
		case GDC_DOT:	 	$datechar = "."; break;
	}

	switch ($date_fmt) {
		case GD_MO_D_Y:
			$graph_date = "m" . $datechar . "d" . $datechar . "Y H:i:s";
			break;
		case GD_MN_D_Y:
			$graph_date = "M" . $datechar . "d" . $datechar . "Y H:i:s";
			break;
		case GD_D_MO_Y:
			$graph_date = "d" . $datechar . "m" . $datechar . "Y H:i:s";
			break;
		case GD_D_MN_Y:
			$graph_date = "d" . $datechar . "M" . $datechar . "Y H:i:s";
			break;
		case GD_Y_MO_D:
			$graph_date = "Y" . $datechar . "m" . $datechar . "d H:i:s";
			break;
		case GD_Y_MN_D:
			$graph_date = "Y" . $datechar . "M" . $datechar . "d H:i:s";
			break;
	}

	nectar_log(__FUNCTION__ . ", datefmt: " . $graph_date, false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);

	return $graph_date;
}

/** nectar_interval_start	computes the next start time for the given set of parameters
 * @param int $interval		- given interval
 * @param int $count		- given repeat count
 * @param int $offset		- offset in seconds to be added to the new start time
 * @param int $timestamp	- current start time for nectar
 * @return					- new timestamp
 */
function nectar_interval_start($interval, $count, $offset, $timestamp) {
	global $nectar_interval;

	nectar_log(__FUNCTION__ . ", interval: " . $interval . " count: " . $count . " offset: " . $offset . " timestamp: " . $timestamp, false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);

	switch ($interval) {
		case NECTAR_SCHED_INTVL_DAY:
			# add $count days to current mailtime
			$ts = utime_add($timestamp,0,0,$count,0,0,$offset);
			break;
			# add $count weeks = 7*$count days to current mailtime
		case NECTAR_SCHED_INTVL_WEEK:
			$ts = utime_add($timestamp,0,0,7*$count,0,0,$offset);
			break;
		case NECTAR_SCHED_INTVL_MONTH_DAY:
			# add $count months to current mailtime
			$ts = utime_add($timestamp,0,$count,0,0,0,$offset);
			break;
		case NECTAR_SCHED_INTVL_MONTH_WEEKDAY:
			# add $count months to current mailtime, but if this is the nth weekday, it must be the same nth weekday in the new month
			# e.g. if this is currently the 3rd Monday of current month
			# ist must be the 3rd Monday of the new month as well
			$weekday      = date('l', $timestamp);
			$day_of_month = date('j', $timestamp);
			$nth_weekday  = ceil($day_of_month/7);

			$date_str     = "+" . $count . " months";
			$month_base   = strtotime($date_str, $timestamp);
			$new_month    = mktime(date("H", $month_base), date("i", $month_base), date("s", $month_base), date("m", $month_base), 1, date("Y", $month_base));

			$date_str     = "+" . ($nth_weekday -1) . " week " . $weekday;
			$base         = strtotime($date_str, $new_month);
			$ts           = mktime(date("H", $month_base), date("i", $month_base), date("s", $month_base), date("m", $base), date("d", $base), date("Y", $base));
			break;
		case NECTAR_SCHED_INTVL_YEAR:
			# add $count years to current mailtime
			$ts = utime_add($timestamp,$count,0,0,0,0,$offset);
			break;
		default:
			$ts = 0;
			break;
	}

	$now = time();
	if ($ts < $now) {
		$ts = nectar_interval_start($interval, $count, $offset, $now);
	}

	return $ts;
}

/** utime_add			add offsets to given timestamp
 * @param int $timestamp- base timestamp
 * @param int $yr		- offset in years
 * @param int $mon		- offset in months
 * @param int $day		- offset in days
 * @param int $hr		- offset in hours
 * @param int $min		- offset in minutes
 * @param int $sec		- offset in seconds
 * @return				- unix time
 */
function utime_add($timestamp, $yr=0, $mon=0, $day=0, $hr=0, $min=0, $sec=0) {
	$dt = localtime($timestamp, true);
	$unixnewtime = mktime(
	$dt['tm_hour']+$hr, $dt['tm_min']+$min, $dt['tm_sec']+$sec,
	$dt['tm_mon']+1+$mon, $dt['tm_mday']+$day, $dt['tm_year']+1900+$yr);
	return $unixnewtime;
}

/** nectar_log - logs a string to Cacti's log file or optionally to the browser
 @param string $string 	- the string to append to the log file
 @param bool $output 	- whether to output the log line to the browser using pring() or not
 @param string $environ - tell's from where the script was called from */
function nectar_log($string, $output = false, $environ="NECTAR", $level=POLLER_VERBOSITY_NONE) {
	# if current verbosity >= level of current message, print it
	if (strstr($string, "STATS")) {
		cacti_log($string, $output, "SYSTEM");
	}elseif (NECTAR_DEBUG >= $level) {
		cacti_log($string, $output, $environ);
	}
}

/** generate_nectar		create the complete mail for a single report and send it
 * @param array $nectar	- complete row of plugin_nectar table for the report to work upon
 * @param bool $force	- when forced, lastsent time will not be entered (e.g. Send Now)
 */
function generate_nectar($nectar, $force = false) {
	global $config, $alignment;
	include_once($config["base_path"] . "/lib/time.php");
	include_once($config["base_path"] . "/lib/rrd.php");

	nectar_log(__FUNCTION__ . ", report_id: " . $nectar["id"], false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);

	$message     = nectar_generate_html($nectar["id"], NECTAR_OUTPUT_EMAIL);

	$time = time();
	# get config option for first-day-of-the-week
	$first_weekdayid = read_graph_config_option("first_weekdayid");

	$offset          = 0;
	$graphids        = array();
	$mail_data_array = array();
	while ( true ) {
		$pos = strpos($message, "<GRAPH:", $offset);

		if ($pos) {
			$offset   = $pos+7;
			$graph    = substr($message, $pos+7, 10);
			$arr      = explode(":", $graph);
			$arr1     = explode(">", $arr[1]);
			$graphid  = $arr[0];
			$timespan = $arr1[0];

			$graphids[$graphid . ":" . $timespan] = $graphid;
		}else{
			break;
		}
	}

	if (sizeof($graphids)) {
	foreach($graphids as $key => $graphid) {
		$arr = explode(":", $key);
		$timesp = $arr[1];

		$timespan = array();
		# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
		get_timespan($timespan, $time, $timesp, $first_weekdayid);

		# provide parameters for rrdtool graph
		$graph_data_array = array(
			'graph_start'    => $timespan["begin_now"],
			'graph_end'      => $timespan["end_now"],
			'graph_width'    => $nectar["graph_width"],
			'graph_height'   => $nectar["graph_height"],
			'output_flag'    => RRDTOOL_OUTPUT_STDOUT
		);

		if ($nectar["thumbnails"] == "on") {
			$graph_data_array['graph_nolegend'] = true;
		}

		switch($nectar["attachment_type"]) {
			case NECTAR_TYPE_INLINE_PNG:
				$mail_data_array[] = array(
					'attachment' => @rrdtool_function_graph($graphid, "", $graph_data_array),
					'filename'   => 'graph_' . $graphid . ".png",
					'mime_type'  => 'image/png',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'inline'
				);
				break;
			case NECTAR_TYPE_INLINE_JPG:
				$mail_data_array[] = array(
					'attachment' => png2jpeg(@rrdtool_function_graph($graphid, "", $graph_data_array)),
					'filename'   => 'graph_' . $graphid . ".jpg",
					'mime_type'  => 'image/jpg',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'inline'
				);
				break;
			case NECTAR_TYPE_INLINE_GIF:
				$mail_data_array[] = array(
					'attachment' => png2gif(@rrdtool_function_graph($graphid, "", $graph_data_array)),
					'filename'   => 'graph_' . $graphid . ".gif",
					'mime_type'  => 'image/gif',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'inline'
				);
				break;
			case NECTAR_TYPE_ATTACH_PNG:
				$mail_data_array[] = array(
					'attachment' => @rrdtool_function_graph($graphid, "", $graph_data_array),
					'filename'   => 'graph_' . $graphid . ".png",
					'mime_type'  => 'image/png',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'attachment'
				);
				break;
			case NECTAR_TYPE_ATTACH_JPG:
				$mail_data_array[] = array(
					'attachment' => png2jpeg(@rrdtool_function_graph($graphid, "", $graph_data_array)),
					'filename'   => 'graph_' . $graphid . ".jpg",
					'mime_type'  => 'image/jpg',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'attachment'
				);
				break;
			case NECTAR_TYPE_ATTACH_GIF:
				$mail_data_array[] = array(
					'attachment' => png2gif(@rrdtool_function_graph($graphid, "", $graph_data_array)),
					'filename'   => 'graph_' . $graphid . ".gif",
					'mime_type'  => 'image/gif',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'attachment'
				);
				break;
			case NECTAR_TYPE_INLINE_PNG_LN:
				$mail_data_array[] = array(
					'attachment' => @rrdtool_function_graph($graphid, "", $graph_data_array),
					'filename'   => '',	# LN does not accept filenames for inline attachments
					'mime_type'  => 'image/png',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'inline'
				);
				break;
			case NECTAR_TYPE_INLINE_JPG_LN:
				$mail_data_array[] = array(
					'attachment' => png2jpeg(@rrdtool_function_graph($graphid, "", $graph_data_array)),
					'filename'   => '',	# LN does not accept filenames for inline attachments
					'mime_type'  => 'image/jpg',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'inline'
				);
				break;
			case NECTAR_TYPE_INLINE_GIF_LN:
				$mail_data_array[] = array(
					'attachment' => png2gif(@rrdtool_function_graph($graphid, "", $graph_data_array)),
					'filename'   => '',	# LN does not accept filenames for inline attachments
					'mime_type'  => 'image/gif',
					'graphid'    => $graphid,
					'timespan'   => $timesp,
					'inline'     => 'inline'
				);
				break;
			case NECTAR_TYPE_ATTACH_PDF:
#				$mail_data_array[] = array(
#					'attachment' => png2gif(@rrdtool_function_graph($graphid, "", $graph_data_array)),
#					'filename'   => 'graph_' . $graphid . ".gif",
#					'mime_type'  => 'image/gif',
#					'graphid'    => $graphid,
#					'timespan'   => $timesp,
#					'inline'     => 'attachment'
#				);
				break;
		}
	}
	}

	if ($nectar["subject"] != '') {
		$subject = $nectar["subject"];
	}else{
		$subject = $nectar["name"];
	}

	if(!isset($nectar['bcc'])) {
		$nectar['bcc'] = '';
	}

	$header = '';
	$error = mg_send_mail($nectar['email'], $nectar['from_email'], $nectar['from_name'], $subject, $message, $mail_data_array, $header, $nectar['bcc']);

	session_start();
	if (strlen($error)) {
		if (isset($_REQUEST["id"])) {
			$_SESSION['nectar_error'] = "Problems sending Nectar Report '" . $nectar['name'] . "'.  Problem with e-mail Subsystem Error is '$error'";

			if (!isset($_POST["selected_items"])) {
				raise_message("nectar_error");
			}
		}else{
			nectar_log(__FUNCTION__ . ", Problems sending Nectar Report '" . $nectar['name'] . "'.  Problem with e-mail Subsystem Error is '$error'", false, "NECTAR", POLLER_VERBOSITY_LOW);
		}
	}elseif (isset($_REQUEST)) {
		$_SESSION['nectar_message'] = "Nectar Report '" . $nectar['name'] . "' Sent Successfully";

		if (!isset($_POST["selected_items"])) {
			raise_message("nectar_message");
		}
	}

	if (!isset($_REQUEST["id"]) && !$force) {
		$int  = read_config_option("poller_interval");
		if ($int == '') $int = 300;
		$next = nectar_interval_start($nectar["intrvl"], $nectar["count"], $nectar["offset"], $nectar['mailtime']);
		$next = floor($next / $int) * $int;
		$sql = "UPDATE plugin_nectar SET mailtime=$next, lastsent=" . time() . " WHERE id = " . $nectar['id'];
		nectar_log(__FUNCTION__ . ", update sql: " . $sql, false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
		db_execute($sql);
	}
}

/** nectar_load_format_file  read the format file from disk and determines it's formating
 * @param string $format_file		- the file to read from the formats directory
 * @param string $output			- the html and css output from that file
 * @param bool $report_tag_included - a boolean that informs the caller if the report tag is present
 * @return bool						- wether or not the format file was processed correctly
 */
function nectar_load_format_file($format_file, &$output, &$report_tag_included) {
	global $config;

	$contents = file($config["base_path"] . "/plugins/nectar/formats/" . $format_file);
	$output   = "";
	$report_tag_included = false;

	if (sizeof($contents)) {
		foreach($contents as $line) {
			$line = trim($line);
			if (substr_count($line, "<REPORT>")) {
				$report_tag_included = true;
			}

			if (substr($line, 0, 1) != "#") {
				$output .= $line . "\n";
			}
		}
	}else{
		return false;
	}

	return true;
}


/**
 * determine, if the given tree has graphs; taking permissions into account
 * @param int $tree_id			- tree id
 * @param int $branch_id		- branch id
 * @param int $effective_user	- user id
 * @param string $search_key	- search key
 */
function nectar_tree_has_graphs($tree_id, $branch_id, $effective_user, $search_key) {
	global $config;

	include_once($config["library_path"] . "/tree.php");
	include_once($config["library_path"] . "/html_tree.php");

	$sql_where  = "";
	$graphs     = array();
	$corder_key = "___" . str_repeat('0', (MAX_TREE_DEPTH - 1) * CHARS_PER_TIER);

	if ($branch_id > 0) {
		$order_key  = db_fetch_cell("SELECT order_key FROM graph_tree_items WHERE graph_tree_id=$tree_id AND id=$branch_id");
		$corder_key = rtrim($order_key, '0');
	}

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=$effective_user");

		/* get policy information for the sql where clause */
		$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
		$sql_where = (empty($sql_where) ? "" : " AND $sql_where");
		$sql_join = "
			LEFT JOIN host ON (host.id=graph_local.host_id)
			LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id=" . $current_user["id"] . ") OR (host.id=user_auth_perms.item_id AND user_auth_perms.type=3 and user_auth_perms.user_id=" . $current_user["id"] . ") OR (graph_templates.id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=" . $current_user["id"] . "))";
	}

	if (strlen($search_key)) {
		$sql_where .= " AND title_cache REGEXP '" . $search_key . "'";
	}

	/* get host graphs first */
	$hosts  = db_fetch_assoc("SELECT host_id FROM graph_tree_items WHERE graph_tree_id=$tree_id AND host_id>0 AND order_key LIKE '$corder_key%'");
	/* verify permissions */
	if (sizeof($hosts)) {
	foreach($hosts as $host) {
		$tgraphs = array_rekey(db_fetch_assoc("SELECT graph_local.id
			FROM graph_local
			INNER JOIN graph_templates_graph
			ON graph_local.id=graph_templates_graph.local_graph_id
			$sql_join
			WHERE graph_local.host_id=" . $host["host_id"] . $sql_where), "id", "id");

		if (sizeof($tgraphs)) {
			$graphs = array_merge($graphs, $tgraphs);
		}
	}
	}

	$tree_graphs = db_fetch_assoc("SELECT graph_tree_items.local_graph_id
		FROM graph_tree_items
		LEFT JOIN graph_templates_graph ON (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id)
		LEFT JOIN graph_local ON (graph_tree_items.local_graph_id=graph_local.id)
		$sql_join
		WHERE graph_tree_id=$tree_id
		AND graph_tree_items.local_graph_id>0
		AND order_key LIKE '$corder_key%'" . $sql_where);

	/* verify permissions */
	if (sizeof($tree_graphs)) {
		$graphs = array_merge($graphs, $tree_graphs);
	}

	return sizeof($graphs);
}


/** nectar_generate_html  print report to html for online verification
 * @param int $nectar_id	- id of nectar report
 * @param int $output		- type of output
 * @return string			- generated html output
 */
function nectar_generate_html ($nectar_id, $output = NECTAR_OUTPUT_STDOUT) {
	global $config, $colors;
	global $alignment;
	include_once($config["base_path"] . "/lib/time.php");

	$outstr       = "";
	$nectar       = db_fetch_row("SELECT * FROM plugin_nectar WHERE id=$nectar_id");
	$nectar_items = db_fetch_assoc("SELECT * FROM plugin_nectar_items WHERE report_id=". $nectar['id'] . ' ORDER BY sequence');
	$format_data  = "";
	$report_tag   = false;
	$format_ok    = false;

	$time = time();
	# get config option for first-day-of-the-week
	$first_weekdayid = read_graph_config_option("first_weekdayid");

	/* process the format file as applicable */
	if ($nectar["cformat"] == "on") {
		$format_ok = nectar_load_format_file($nectar["format_file"], $format_data, $report_tag);
	}

	if ($output == NECTAR_OUTPUT_STDOUT) {
		$format_data = str_replace("<html>", "", $format_data);
		$format_data = str_replace("</html>", "", $format_data);
		$format_data = str_replace("<body>", "", $format_data);
		$format_data = str_replace("</body>", "", $format_data);
	}

	if ($format_ok && $report_tag) {
		$include_body = false;
	}else{
		$include_body = true;
	}

	nectar_log(__FUNCTION__ . ", items found: " . sizeof($nectar_items), false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);

	if (sizeof($nectar_items)) {
		if ($output == NECTAR_OUTPUT_EMAIL && $include_body) {
			$outstr .= "<body>\n";
		}
		if ($format_ok) {
			$outstr .= "\t<table class='report_table'>\n";
		}else{
			$outstr .= "\t<table class='report_table' " . ($output == NECTAR_OUTPUT_STDOUT ? "style='background-color:#F9F9F9;'":"") . " cellspacing='0' cellpadding='0' border='0' align='center' width='100%'>\n";
		}

		$outstr .= "\t\t<tr class='title_row'>\n";
		if ($format_ok) {
			$outstr .= "\t\t\t<td class='title' align='" . $alignment[$nectar["alignment"]] . "'>\n";
		}else{
			$outstr .= "\t\t\t<td class='title' align='" . $alignment[$nectar["alignment"]] . "' style='text-align:center; font-size:" . $nectar["font_size"] . "pt;'>\n";
		}
		$outstr .= "\t\t\t\t" . $nectar['name'] . "\n";
		$outstr .= "\t\t\t</td>\n";
		$outstr .= "\t\t</tr>\n";
		# this function should be called only at the appropriate targeted time when in batch mode
		# but for preview mode we can't use the targeted time
		# so let's use time()
		$time = time();
		# get config option for first-day-of-the-week
		$first_weekdayid = read_graph_config_option("first_weekdayid");

		/* don't cache previews */
		$_SESSION["custom"] = "true";

		$column = 0;
		foreach($nectar_items as $item) {
			nectar_log(__FUNCTION__ . ", item_id: " . $item["id"] . " local_graph_id: " . $item["local_graph_id"], false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
			if ($item['item_type'] == NECTAR_ITEM_GRAPH) {
				$timespan = array();
				# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
				get_timespan($timespan, $time, $item["timespan"], $first_weekdayid);

				if ($column == 0) {
					$outstr .= "\t\t<tr class='image_row'>\n";
					$outstr .= "\t\t\t<td>\n";
					if ($format_ok) {
						$outstr .= "\t\t\t\t<table width='100%' class='image_table'>\n";
					}else{
						$outstr .= "\t\t\t\t<table cellpadding='0' cellspacing='0' border='0' width='100%'>\n";
					}
					$outstr .= "\t\t\t\t\t<tr>\n";
				}
				if ($format_ok) {
					$outstr .= "\t\t\t\t\t\t<td class='image_column' align='" . $alignment[$item["align"]] . "'>\n";
				}else{
					$outstr .= "\t\t\t\t\t\t<td style='padding:5px;' align='" . $alignment[$item["align"]] . "'>\n";
				}
				$outstr .= "\t\t\t\t\t\t\t" . nectar_graph_image($nectar, $item, $timespan, $output) . "\n";
				$outstr .= "\t\t\t\t\t\t</td>\n";

				if ($nectar["graph_columns"] > 1) {
					$column = ($column + 1) % ($nectar["graph_columns"]);
				}

				if ($column == 0) {
					$outstr .= "\t\t\t\t\t</tr>\n";
					$outstr .= "\t\t\t\t</table>\n";
					$outstr .= "\t\t\t</td>\n";
					$outstr .= "\t\t</tr>\n";
				}
			} elseif ($item['item_type'] == NECTAR_ITEM_TEXT) {
				$outstr .= "\t\t<tr class='text_row'>\n";
				if ($format_ok) {
					$outstr .= "\t\t\t<td align='" . $alignment[$item["align"]] . "' class='text'>\n";
				}else{
					$outstr .= "\t\t\t<td align='" . $alignment[$item["align"]] . "' class='text' style='font-size: " . $item["font_size"] . "pt;'>\n";
				}
				$outstr .= "\t\t\t\t" . $item["item_text"] . "\n";
				$outstr .= "\t\t\t</td>\n";
				$outstr .= "\t\t</tr>\n";

				/* start a new section */
				$column = 0;
			} elseif ($item['item_type'] == NECTAR_ITEM_TREE) {
				if (nectar_tree_has_graphs($item["tree_id"], 0, $nectar["user_id"], $item["graph_name_regexp"])) {
					$outstr .= nectar_expand_tree($nectar, $item, $output, $format_ok);
				}

				if ($item['tree_cascade'] == 'on') {
					$order_key  = db_fetch_cell("SELECT order_key FROM graph_tree_items WHERE graph_tree_id=" . $item['tree_id'] . " AND id=" . $item['branch_id']);
					$corder_key = rtrim($order_key, '0');

					$tree_branches = db_fetch_assoc("SELECT id FROM graph_tree_items WHERE graph_tree_id=" . $item['tree_id'] . " AND order_key LIKE '" . $corder_key . "%' AND order_key!='$order_key' ORDER BY order_key");
					if (sizeof($tree_branches)) {
					foreach ($tree_branches as $branch) {
						$item["branch_id"] = $branch["id"];
						if (nectar_tree_has_graphs($item["tree_id"], $item["branch_id"], $nectar["user_id"], $item["graph_name_regexp"])) {
							$outstr .= nectar_expand_tree($nectar, $item, $output, $format_ok, true);
						}
					}
					}
				}
			} else {
				$outstr .= "<tr><td><br><hr><br></td></tr>";
			}
		}
		$outstr .= "\t</table>\n";
		if ($output == NECTAR_OUTPUT_EMAIL && $include_body) {
			$outstr .= "</body>";
		}
	}

	if ($format_ok) {
		if ($report_tag) {
			return str_replace("<REPORT>", $outstr, $format_data);
		}else{
			return $format_data . "\n" . $outstr;
		}
	}else{
		return $outstr;
	}
}


/**
 * return html code for an embetted image
 * @param array $nectar	- parameters for this nectar mail report
 * @param $item			- current graph item
 * @param $timespan		- timespan
 * @param $output		- type of output
 * @return string		- generated html
 */
 function nectar_graph_image($nectar, $item, $timespan, $output) {
 	global $config;

	$out = "";
	if ($output == NECTAR_OUTPUT_STDOUT) {
		$out = "<img cellpadding='0' cellspacing='0' border='0' class='image' src=" . htmlspecialchars("'../../graph_image.php" .
				"?graph_width=" . $nectar["graph_width"] .
				"&graph_height=" . $nectar["graph_height"] .
				"&graph_nolegend=" . ($nectar["thumbnails"] == "on" ? "true":"") .
				"&local_graph_id=" . $item['local_graph_id'] .
				"&graph_start=" . $timespan["begin_now"] .
				"&graph_end=" . $timespan["end_now"] .
				"&rra_id=0'") . ">";
	}else{
		$out = "<GRAPH:" . $item['local_graph_id'] . ":" . $item["timespan"] . ">";
	}

	if ($nectar["graph_linked"] == "on" ) {
		/* determine our hostname/servername in different environments */
		if (isset($_SERVER["SERVER_NAME"])) {
			$hostname = $_SERVER["SERVER_NAME"];
		} elseif (isset($_SERVER["HOSTNAME"])) {
			$hostname = $_SERVER["HOSTNAME"];
		} elseif (isset($_ENV["HOSTNAME"])) {
			$hostname = $_ENV["HOSTNAME"];
		} elseif (isset($_ENV["COMPUTERNAME"])) {
			$hostname = $_ENV["COMPUTERNAME"];
		} else {
			$hostname = "";
		}

		$out = "<a href='http://" . $hostname . $config['url_path'] . "graph.php?action=view&local_graph_id=".$item['local_graph_id']."&rra_id=all'>" . $out . "</a>";
		nectar_log(__FUNCTION__ . ", Link: " . $out, false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
	}

	return $out . "\n";
}


/**
 * expand a tree for including into report
 * @param array $nectar		- parameters for this nectar mail report
 * @param int $item			- current graph item
 * @param int $output		- type of output
 * @param bool $format_ok	- use css styling
 * @param bool $nested		- nested tree?
 * @return string			- html
 */
function nectar_expand_tree($nectar, $item, $output, $format_ok, $nested = false) {
	global $colors, $config, $alignment;

	include($config["include_path"] . "/global_arrays.php");
	include_once($config["library_path"] . "/data_query.php");
	include_once($config["library_path"] . "/tree.php");
	include_once($config["library_path"] . "/html_tree.php");
	include_once($config["library_path"] . "/html_utility.php");

	$tree_id = $item["tree_id"];
	$leaf_id = $item["branch_id"];

	$time = time();
	# get config option for first-day-of-the-week
	$first_weekdayid = read_graph_config_option("first_weekdayid");

	/* check if we have enough data */
	if (isset($_SESSION["sess_current_user"])) {
		$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $_SESSION["sess_user_id"]);
	}else{
		$current_user = db_fetch_row("SELECT * FROM user_auth WHERE id=" . $nectar["user_id"]);
	}

	/*todo*/
	$host_group_data = "";

	$timespan = array();
	# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
	get_timespan($timespan, $time, $item["timespan"], $first_weekdayid);

	if (empty($tree_id)) { return; }

	$sql_where       = "";
	$sql_join        = "";
	$title           = "";
	$title_delimeter = "";
	$search_key      = "";
	$outstr          = "";

	$leaf         = db_fetch_row("SELECT order_key, title, host_id, host_grouping_type FROM graph_tree_items WHERE id=$leaf_id");
	$leaf_type    = get_tree_item_type($leaf_id);

	/* get the "starting leaf" if the user clicked on a specific branch */
	if (!empty($leaf_id)) {
		$search_key = substr($leaf["order_key"], 0, (tree_tier($leaf["order_key"]) * CHARS_PER_TIER));
	}

	/* graph permissions */
	if (read_config_option("auth_method") != 0) {
		/* get policy information for the sql where clause */
		$sql_where = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);
		$sql_where = (empty($sql_where) ? "" : "AND $sql_where");
		$sql_join = "
			LEFT JOIN host ON (host.id=graph_local.host_id)
			LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id AND user_auth_perms.type=1 AND user_auth_perms.user_id=" . $current_user["id"] . ") OR (host.id=user_auth_perms.item_id AND user_auth_perms.type=3 and user_auth_perms.user_id=" . $current_user["id"] . ") OR (graph_templates.id=user_auth_perms.item_id AND user_auth_perms.type=4 AND user_auth_perms.user_id=" . $current_user["id"] . "))";
	}

	/* get information for the headers */
	if (!empty($tree_id)) { $tree_name = db_fetch_cell("SELECT name FROM graph_tree WHERE id=$tree_id"); }
	if (!empty($leaf_id)) { $leaf_name = $leaf["title"]; }
	if (!empty($leaf_id)) { $host_name = db_fetch_cell("SELECT host.description FROM (graph_tree_items,host) WHERE graph_tree_items.host_id=host.id AND graph_tree_items.id=$leaf_id"); }

	$host_group_data_array = explode(":", $host_group_data);

	if ($host_group_data_array[0] == "graph_template") {
		$host_group_data_name = "<strong>Graph Template:</strong> " . db_fetch_cell("SELECT name FROM graph_templates WHERE id=" . $host_group_data_array[1]) . "</td></tr>";
		$graph_template_id    = $host_group_data_array[1];
	}elseif ($host_group_data_array[0] == "data_query") {
		$host_group_data_name = "<strong>Graph Template:</strong> " . (empty($host_group_data_array[1]) ? "Non Query Based" : db_fetch_cell("SELECT name FROM snmp_query WHERE id=" . $host_group_data_array[1])) . "</td></tr>";
		$data_query_id        = $host_group_data_array[1];
	}elseif ($host_group_data_array[0] == "data_query_index") {
		$host_group_data_name = "<strong>Graph Template:</strong> " . (empty($host_group_data_array[1]) ? "Non Query Based" : db_fetch_cell("SELECT name FROM snmp_query WHERE id=" . $host_group_data_array[1])) . "-> " . (empty($host_group_data_array[2]) ? "Template Based" : get_formatted_data_query_index($leaf["host_id"], $host_group_data_array[1], $host_group_data_array[2])) . "</td></tr>";
		$data_query_id        = $host_group_data_array[1];
		$data_query_index     = $host_group_data_array[2];
	}

	if (!empty($tree_name) && empty($leaf_name) && empty($host_name) && !$nested) {
		$title .= $title_delimeter . "<strong>Tree:</strong> $tree_name"; $title_delimeter = "-> ";
	}elseif (!empty($leaf_name)) {
		$title .= $title_delimeter . "<strong>Leaf:</strong> $leaf_name"; $title_delimeter = "-> ";
	}elseif (!empty($host_name)) {
		$title .= $title_delimeter . "<strong>Host:</strong> $host_name"; $title_delimeter = "-> ";
	}elseif (!empty($host_group_data_name)) {
		$title .= $title_delimeter . " $host_group_data_name"; $title_delimeter = "-> ";
	}

	if (isset($_REQUEST["tree_id"])) {
		$nodeid = "tree_" . $_REQUEST["tree_id"];
	}

	if (isset($_REQUEST["leaf_id"])) {
		$nodeid .= "_leaf" . $_REQUEST["leaf_id"];
	}

	/* start graph display */
	if (strlen($title)) {
		$outstr .= "\t\t<tr class='text_row'>\n";
		if ($format_ok) {
			$outstr .= "\t\t\t<td class='text' align='" . $alignment[$item["align"]] . "'>\n";
		}else{
			$outstr .= "\t\t\t<td class='text' align='" . $alignment[$item["align"]] . "' style='font-size: " . $item["font_size"] . "pt;'>\n";
		}
		$outstr .= "\t\t\t\t$title\n";
		$outstr .= "\t\t\t</td>\n";
		$outstr .= "\t\t</tr>\n";
	}

	if (strlen($item["graph_name_regexp"])) {
		$sql_where .= " AND title_cache REGEXP '" . $item["graph_name_regexp"] . "'";
	}

	if (($leaf_type == "header") || (empty($leaf_id))) {
		$heirarchy = db_fetch_assoc("SELECT
			graph_tree_items.local_graph_id
			FROM (graph_tree_items,graph_local)
			LEFT JOIN graph_templates_graph ON (graph_tree_items.local_graph_id=graph_templates_graph.local_graph_id AND graph_tree_items.local_graph_id>0)
			$sql_join
			WHERE graph_tree_items.graph_tree_id=$tree_id
			AND graph_local.id=graph_templates_graph.local_graph_id
			AND graph_tree_items.order_key like '$search_key" . str_repeat('_', CHARS_PER_TIER) . str_repeat('0', (MAX_TREE_DEPTH * CHARS_PER_TIER) - (strlen($search_key) + CHARS_PER_TIER)) . "'
			AND graph_tree_items.local_graph_id>0
			$sql_where
			GROUP BY graph_tree_items.id
			ORDER BY graph_tree_items.order_key");

		$outstr .= nectar_graph_area($heirarchy, $nectar, $item, $timespan, $output, $format_ok);
	}elseif ($leaf_type == "host") {
		/* graph template grouping */
		if ($leaf["host_grouping_type"] == HOST_GROUPING_GRAPH_TEMPLATE) {
			$graph_templates = db_fetch_assoc("SELECT
				graph_templates.id,
				graph_templates.name
				FROM (graph_local,graph_templates,graph_templates_graph)
				WHERE graph_local.id=graph_templates_graph.local_graph_id
				AND graph_templates_graph.graph_template_id=graph_templates.id
				AND graph_local.host_id=" . $leaf["host_id"] . "
				" . (empty($graph_template_id) ? "" : "AND graph_templates.id=$graph_template_id") . "
				GROUP BY graph_templates.id
				ORDER BY graph_templates.name");

			/* for graphs without a template */
			array_push($graph_templates, array(
				"id" => "0",
				"name" => "(No Graph Template)"
				));

			$outgraphs = array();
			if (sizeof($graph_templates) > 0) {
				foreach ($graph_templates as $graph_template) {
					$graphs = db_fetch_assoc("SELECT
						graph_templates_graph.title_cache,
						graph_templates_graph.local_graph_id
						FROM (graph_local,graph_templates_graph)
						$sql_join
						WHERE graph_local.id=graph_templates_graph.local_graph_id
						AND graph_local.graph_template_id=" . $graph_template["id"] . "
						AND graph_local.host_id=" . $leaf["host_id"] . "
						$sql_where
						ORDER BY graph_templates_graph.title_cache");

					$outgraphs = array_merge($outgraphs, $graphs);
				}

				if (sizeof($outgraphs) > 0) {
					/* let's sort the graphs naturally */
					usort($outgraphs, 'necturally_sort_graphs');

					$outstr .= nectar_graph_area($outgraphs, $nectar, $item, $timespan, $output, $format_ok);
				}
			}
		/* data query index grouping */
		}elseif ($leaf["host_grouping_type"] == HOST_GROUPING_DATA_QUERY_INDEX) {
			$data_queries = db_fetch_assoc("SELECT
				snmp_query.id,
				snmp_query.name
				FROM (graph_local,snmp_query)
				WHERE graph_local.snmp_query_id=snmp_query.id
				AND graph_local.host_id=" . $leaf["host_id"] . "
				" . (!isset($data_query_id) ? "" : "AND snmp_query.id=$data_query_id") . "
				GROUP BY snmp_query.id
				ORDER BY snmp_query.name");

			/* for graphs without a data query */
			if (empty($data_query_id)) {
				array_push($data_queries, array(
					"id" => "0",
					"name" => "Non Query Based"
					));
			}

			if (sizeof($data_queries) > 0) {
			foreach ($data_queries as $data_query) {
				/* fetch a list of field names that are sorted by the preferred sort field */
				$sort_field_data = get_formatted_data_query_indexes($leaf["host_id"], $data_query["id"]);

				/* grab a list of all graphs for this host/data query combination */
				$graphs = db_fetch_assoc("SELECT
					graph_templates_graph.title_cache,
					graph_templates_graph.local_graph_id,
					graph_local.snmp_index
					FROM (graph_local,graph_templates_graph)
					$sql_join
					WHERE graph_local.id=graph_templates_graph.local_graph_id
					AND graph_local.snmp_query_id=" . $data_query["id"] . "
					AND graph_local.host_id=" . $leaf["host_id"] . "
					" . (empty($data_query_index) ? "" : "AND graph_local.snmp_index='$data_query_index'") . "
					$sql_where
					GROUP BY graph_templates_graph.local_graph_id
					ORDER BY graph_templates_graph.title_cache");

				/* re-key the results on data query index */
				if (sizeof($graphs) > 0) {
					$outstr .= "\t\t<tr class='text_row'>\n";
					if ($format_ok) {
						$outstr .= "\t\t\t<td class='text' align='" . $alignment[$item["align"]] . "'><strong>Data Query:</strong> " . $data_query["name"] . "\n";
						$outstr .= "\t\t\t</td>\n";
						$outstr .= "\t\t</tr>\n";
					}else{
						$outstr .= "\t\t\t<td class='text' align='" . $alignment[$item["align"]] . "' style='font-size: " . $item["font_size"] . "pt;'><strong>Data Query:</strong> " . $data_query["name"] . "\n";
						$outstr .= "\t\t\t</td>\n";
						$outstr .= "\t\t</tr>\n";
					}

					/* let's sort the graphs naturally */
					usort($graphs, 'necturally_sort_graphs');

					foreach ($graphs as $graph) {
						$snmp_index_to_graph{$graph["snmp_index"]}{$graph["local_graph_id"]} = $graph["title_cache"];
					}
				}

				/* using the sorted data as they key; grab each snmp index from the master list */
				$graph_list = array();
				while (list($snmp_index, $sort_field_value) = each($sort_field_data)) {
					/* render each graph for the current data query index */
					if (isset($snmp_index_to_graph[$snmp_index])) {

						while (list($local_graph_id, $graph_title) = each($snmp_index_to_graph[$snmp_index])) {
							/* reformat the array so it's compatable with the html_graph* area functions */
							array_push($graph_list, array("local_graph_id" => $local_graph_id, "title_cache" => $graph_title));
						}
					}
				}

				if (sizeof($graph_list)) {
					$outstr .= nectar_graph_area($graph_list, $nectar, $item, $timespan, $output, $format_ok);
				}
			}
			}
		}
	}

	return $outstr;
}


/**
 * natural sort function
 * @param $a
 * @param $b
 */
function necturally_sort_graphs($a, $b) {
	return strnatcasecmp($a['title_cache'], $b['title_cache']);
}


/**
 * draw graph area
 * @param array $graphs		- array of graphs
 * @param array $nectar		- report parameters
 * @param int $item			- current item
 * @param int $timespan		- requested timespan
 * @param int $output		- type of output
 * @param bool $format_ok	- use css styling
 */
function nectar_graph_area($graphs, $nectar, $item, $timespan, $output, $format_ok) {
	global $alignment;

	$column = 0;
	$outstr = "";

	if (sizeof($graphs)) {
	foreach($graphs as $graph) {
		$item["local_graph_id"] = $graph["local_graph_id"];

		if ($column == 0) {
			$outstr .= "\t\t<tr class='image_row'>\n";
			$outstr .= "\t\t\t<td>\n";
			$outstr .= "\t\t\t\t<table cellpadding='0' cellspacing='0' border='0' width='100%'>\n";
			$outstr .= "\t\t\t\t\t<tr>\n";
		}
		if ($format_ok) {
			$outstr .= "\t\t\t\t\t\t<td class='image_column' align='" . $alignment{$item["align"]} . "'>\n";
		}else{
			$outstr .= "\t\t\t\t\t\t<td style='padding:5px;' align='" . $alignment{$item["align"]} . "'>\n";
		}
		$outstr .= "\t\t\t\t\t\t\t" . nectar_graph_image($nectar, $item, $timespan, $output) . "\n";
		$outstr .= "\t\t\t\t\t\t</td>\n";

		if ($nectar["graph_columns"] > 1) {
			$column = ($column + 1) % ($nectar["graph_columns"]);
		}

		if ($column == 0) {
			$outstr .= "\t\t\t\t\t</tr>\n";
			$outstr .= "\t\t\t\t</table>\n";
			$outstr .= "\t\t\t</td>\n";
			$outstr .= "\t\t</tr>\n";
		}
	}
	}

	if ($column > 0) {
		$outstr .= "\t\t\t\t\t</tr>\n";
		$outstr .= "\t\t\t\t</table>\n";
		$outstr .= "\t\t\t</td>\n";
		$outstr .= "\t\t</tr>\n";
	}

	return $outstr;
}

/** This function is stolen from SETTINGS
 * and modified in a way, that rrdtool graph is kept outside this send_mail function
 * Using this function with current MAIL servers using extended authentication throws errors.
 *
 * @param string $to
 * @param string $from
 * @param string $fromname
 * @param string $subject
 * @param string $message
 * @param array $data_array
 * @param array $headers
 */
function mg_send_mail($to, $from, $fromname, $subject, $message, $data_array = '', $headers = '', $bcc = '') {
	global $config;
	include_once($config["base_path"] . "/plugins/settings/include/mailer.php");

	nectar_log("Sending NECTAR Report to Email to: '" . $to . "', with Subject: '" . $subject . "'", false, "SYSTEM", POLLER_VERBOSITY_LOW);

	$message = str_replace('<SUBJECT>', $subject, $message);
	$message = str_replace('<TO>', $to, $message);
	$message = str_replace('<FROM>', $from, $message);

	$how = read_config_option("settings_how");
	if ($how < 0 || $how > 2) $how = 0;

	/* setup the mail connection settings */
	if ($how == 0) {
		$Mailer = new Mailer(array('Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer   = new Mailer(array('Type' => 'DirectInject', 'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host     = read_config_option("settings_smtp_host");
		$smtp_port     = read_config_option("settings_smtp_port");
		$smtp_username = read_config_option("settings_smtp_username");
		$smtp_password = read_config_option("settings_smtp_password");

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	/* setup the max size of the e-mail message */
	$Mailer->Config["AttachMaxSize"] = read_config_option("nectar_max_attach");
	$Mailer->Config["MaxSize"]       = read_config_option("nectar_max_attach");

	/* setup the from information */
	if ($from == '') {
		$from     = read_config_option('settings_from_email');
		$fromname = read_config_option('settings_from_name');
		if ($from == "") {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@cactiusers.org';
			}
		}
		if ($fromname == '') $fromname = 'Cacti';

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	} else {
		if ($fromname == '') read_config_option('settings_from_name');
		if ($fromname == '') $fromname = 'Cacti';

		$from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	/* validate the to information */
	if ($to == '') {
		return "Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.";
	}
	$to = explode(',', $to);

	/* initialize the e-mail 'to' addresses */
	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set("To", $t)) {
			print "ERROR: " . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	/* append bcc if any */
	if ($bcc != '') {
		$carbons = explode(',', $bcc);
		foreach($carbons as $carbon) {
			if (trim($carbon) != '' && !$Mailer->header_set("Bcc", $carbon)) {
				print "ERROR: " . $Mailer->error() . "\n";
				return $Mailer->error();
			}
		}
	}

	/* initialize the e-mail 'wordwrap' */
	$wordwrap = read_config_option("settings_wordwrap");
	if ($wordwrap == '')  $wordwrap = 76;
	if ($wordwrap > 9999) $wordwrap = 9999;
	if ($wordwrap < 0)    $wordwrap = 76;
	$Mailer->Config["Mail"]["WordWrap"] = $wordwrap;

	/* initialize the e-mail 'subject' */
	if (!$Mailer->header_set("Subject", $subject)) {
		print "ERROR: " . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	/* initialize the e-mail 'graph' content */
	if (is_array($data_array) && !empty($data_array) && strstr($message, '<GRAPH:')) {
		foreach($data_array as $data_item) {
			if ($data_item["attachment"] != "") {
				/* get content id and create attachment */
				$cid = $Mailer->content_id();
				if ($Mailer->attach($data_item["attachment"], $data_item['filename'], $data_item["mime_type"], $data_item["inline"], $cid) == false) {
					print "ERROR: " . $Mailer->error() . "\n";
					return $Mailer->error();
				}
				/* handle the message text */
				switch ($data_item["inline"]) {
					case "inline":
				$message = str_replace('<GRAPH:' . $data_item["graphid"] . ':' . $data_item["timespan"] . '>', "<img border='0' src='cid:$cid' >", $message);
						break;
					case "attachment":
						$message = str_replace('<GRAPH:' . $data_item["graphid"] . ':' . $data_item["timespan"] . '>', "", $message);
						break;
				}
			} else {
				$message = str_replace('<GRAPH:' . $data_item["graphid"] . ':' . $data_item["timespan"] . '>', "<img border='0' src='" . $data_item['filename'] . "' ><br>Could not open!<br>" . $data_item['filename'], $message);
			}
		}
	}

	/* get rid of html content if this is test only */
	$text = array('text' => '', 'html' => '');
	if ($data_array == '') {
		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags($message);
	} else {
		$text['html'] = $message;
		$message_text = "An HTML capable email reader is required to view Nectar emails";
		$text['text'] = $message_text;
	}

	/* send the e-mail */
	$v = nectar_version();
	$Mailer->header_set('X-Mailer', 'Cacti-Nectar-v' . $v['version']);
	$Mailer->header_set('User-Agent', 'Cacti-Nectar-v' . $v['version']);
    if ($Mailer->send($text) == false) {
		print "ERROR: " . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	return '';
}


/**
 * convert png images stream to jpeg using php-gd
 *
 * @param string $png_data	- the png image as a stream
 * @return string			- the jpeg image as a stream
 */
function png2jpeg ($png_data) {
	global $config;

	if ($png_data != "") {
		$fn = "/tmp/" . time() . '.png';

		/* write rrdtool's png file to scratch dir */
		$f = fopen($fn, 'wb');
		fwrite($f, $png_data);
		fclose($f);

		/* create php-gd image object from file */
		$im = imagecreatefrompng($fn);
		if (!$im) {								/* check for errors */
			$im = ImageCreate (150, 30);		/* create an empty image */
			$bgc = ImageColorAllocate ($im, 255, 255, 255);
			$tc  = ImageColorAllocate ($im, 0, 0, 0);
			ImageFilledRectangle ($im, 0, 0, 150, 30, $bgc);
			/* print error message */
			ImageString($im, 1, 5, 5, "Error while opening: $imgname", $tc);
		}

        ob_start(); // start a new output buffer to capture jpeg image stream
		imagejpeg($im);	// output to buffer
		$ImageData = ob_get_contents(); // fetch image from buffer
		$ImageDataLength = ob_get_length();
		ob_end_clean(); // stop this output buffer
		imagedestroy($im); //clean up

		unlink($fn); // delete scratch file
	}
	return $ImageData;
}

/**
 * convert png images stream to gif using php-gd
 *
 * @param string $png_data	- the png image as a stream
 * @return string			- the gif image as a stream
 */
function png2gif ($png_data) {
	global $config;

	if ($png_data != "") {
		$fn = "/tmp/" . time() . '.png';

		/* write rrdtool's png file to scratch dir */
		$f = fopen($fn, 'wb');
		fwrite($f, $png_data);
		fclose($f);

		/* create php-gd image object from file */
		$im = imagecreatefrompng($fn);
		if (!$im) {								/* check for errors */
			$im = ImageCreate (150, 30);		/* create an empty image */
			$bgc = ImageColorAllocate ($im, 255, 255, 255);
			$tc  = ImageColorAllocate ($im, 0, 0, 0);
			ImageFilledRectangle ($im, 0, 0, 150, 30, $bgc);
			/* print error message */
			ImageString($im, 1, 5, 5, "Error while opening: $imgname", $tc);
		}

        ob_start(); // start a new output buffer to capture gif image stream
		imagegif($im);	// output to buffer
		$ImageData = ob_get_contents(); // fetch image from buffer
		$ImageDataLength = ob_get_length();
		ob_end_clean(); // stop this output buffer
		imagedestroy($im); //clean up

		unlink($fn); // delete scratch file
	}
	return $ImageData;
}

?>

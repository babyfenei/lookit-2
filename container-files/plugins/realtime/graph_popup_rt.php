<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008 Mathieu Virbel <mathieu.v@capensis.fr>               |
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

$guest_account = true;

chdir('../..');
include("./include/auth.php");

global $realtime_refresh, $realtime_window, $realtime_sizes;

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("ds_step"));
input_validate_input_number(get_request_var_request("local_graph_id"));
input_validate_input_number(get_request_var_request("graph_start"));
/* ==================================================== */

load_current_session_value("sync", "sess_realtime_sync", read_config_option("realtime_sync"));

if ($_REQUEST['sync'] == "on") {
	load_current_session_value("ds_step", "sess_realtime_ds_step", read_config_option("realtime_interval"));
	load_current_session_value("graph_start", "sess_realtime_graph_start", read_config_option("realtime_gwindow"));
	$init = "init";
}else{
	$init = "";

	if (!isset($_SESSION["sess_realtime_ds_step"])) {
		load_current_session_value("ds_step", "sess_realtime_ds_step", read_config_option("realtime_interval"));
	}else{
		$_REQUEST["ds_step"] = $_SESSION["sess_realtime_ds_step"];
	}

	if (!isset($_SESSION["sess_realtime_graph_start"])) {
		load_current_session_value("graph_start", "sess_realtime_graph_start", read_config_option("realtime_gwindow"));
	}else{
		$_REQUEST["graph_start"] = $_SESSION["sess_realtime_graph_start"];
	}
}

if (!is_dir(read_config_option("realtime_cache_path"))) {
	print "<html>\n";
	print "<body>\n";
	print "	<p><strong>The Image Cache Directory directory does not exist.  Please first create it and set permissions and then attempt to open another realtime graph.</strong></p>\n";
	print "</body>\n";
	print "</html>\n";
	exit;
}else{
	if (!is_writable(read_config_option("realtime_cache_path"))) {
		print "<html>\n";
		print "<body>\n";
		print "	<p><strong>The Image Cache Directory is not writable.  Please set permissions and then attempt to open another realtime graph.</strong></p>\n";
		print "</body>\n";
		print "</html>\n";
		exit;
	}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
	<title>Cacti - Realtime</title>
	<?php if (read_config_option("realtime_ajax") != "on") {
	print "<meta http-equiv=refresh content='" .  $_REQUEST['ds_step'] . "'>\n";
	}?>
	<link href="../../include/main.css" type="text/css" rel="stylesheet">
	<script src="realtime.js" type="text/javascript"></script>
</head>
<body style="text-align: center; padding: 5px 0px 5px 0px; margin: 5px 0px 5px 0px;" onLoad="imageOptionsChanged('<?php print $init;?>')">
<form method="post" action="graph_popup_rt.php" id="gform">
	<div>
			<strong>Timespan:</strong>
			<select name="graph_start" id="graph_start" onChange="self.imageOptionsChanged('timespan')">
			<?php
			foreach ($realtime_window as $interval => $text) {
				printf('<option value="%d"%s>%s</option>',
					$interval, $interval == abs($_REQUEST['graph_start']) ? ' selected="selected"' : '', $text
				);
			}
			?>
			</select>
			&nbsp;<strong>Interval:</strong>
			<select name="ds_step" id="ds_step" onChange="self.imageOptionsChanged('interval')">
			<?php
			foreach ($realtime_refresh as $interval => $text) {
				printf('<option value="%d"%s>%s</option>',
					$interval, $interval == $_REQUEST['ds_step'] ? ' selected="selected"' : '', $text
				);
			}
			?>
			</select>
			&nbsp;<strong>Synchronize:</strong>
			<input type="checkbox" id="sync" name="Synchronize" <?php echo (($_REQUEST["sync"] == "on") ? "checked='checked'": "");?> onChange="self.imageOptionsChanged('sync')"/>
			<br><br>
			<span id="countdown"><strong><?php echo $_REQUEST['ds_step']; ?> seconds left.</strong></span>
	</div>
	<br>
	<div id="image">
		<img id="realtime" align="absmiddle" src="./loading.gif">
	</div>
	<input type="hidden" id="url_path" name="url_path" value="<?php echo $config["url_path"];?>"/>
	<input type="hidden" id="local_graph_id" name="local_graph_id" value="<?php echo $_REQUEST['local_graph_id']; ?>"/>
</form>
</body>
</html>

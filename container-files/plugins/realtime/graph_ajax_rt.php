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

/* since we'll have additional headers, tell php when to flush them */
ob_start();

$guest_account = true;

chdir('../..');
include("./include/auth.php");

/* ================= input validation ================= */
input_validate_input_number(get_request_var_request("graph_start"));
input_validate_input_number(get_request_var_request("graph_end"));
input_validate_input_number(get_request_var_request("graph_height"));
input_validate_input_number(get_request_var_request("graph_width"));
input_validate_input_number(get_request_var_request("local_graph_id"));
input_validate_input_number(get_request_var_request("ds_step"));
input_validate_input_number(get_request_var_request("count"));
/* ==================================================== */

/* clean up sync string */
if (isset($_REQUEST["sync"])) {
	$_REQUEST["sync"] = sanitize_search_string(get_request_var("sync"));
}

/* clean up action string */
if (isset($_REQUEST["action"])) {
	$_REQUEST["action"] = sanitize_search_string(get_request_var("action"));
}

switch ($_REQUEST["action"]) {
case 'init':
	load_current_session_value("ds_step",     "sess_realtime_ds_step",     read_config_option("realtime_interval"));
	load_current_session_value("graph_start", "sess_realtime_graph_start", read_config_option("realtime_gwindow"));
	load_current_session_value("sync",        "sess_realtime_sync",        read_config_option("realtime_sync"));

	break;
case 'timespan':
	load_current_session_value("graph_start", "sess_realtime_graph_start", read_config_option("realtime_gwindow"));

	break;
case 'interval':
	load_current_session_value("ds_step",     "sess_realtime_ds_step",     read_config_option("realtime_interval"));

	break;
case 'sync':
	load_current_session_value("sync",        "sess_realtime_sync",        read_config_option("realtime_sync"));

	break;
case 'countdown':
	/* do nothing */

	break;
}

$graph_data_array = array();

/* ds */
$graph_data_array["ds_step"] = read_config_option("realtime_interval");
if (!empty($_REQUEST["ds_step"]))
	$graph_data_array["ds_step"] = (int)$_REQUEST["ds_step"];

/* override: graph height (in pixels) */
if (!empty($_REQUEST["graph_height"]) && $_REQUEST["graph_height"] < 3000) {
	$graph_data_array["graph_height"] = $_REQUEST["graph_height"];
}

/* override: graph width (in pixels) */
if (!empty($_REQUEST["graph_width"]) && $_REQUEST["graph_width"] < 3000) {
	$graph_data_array["graph_width"] = $_REQUEST["graph_width"];
}

/* override: skip drawing the legend? */
if (!empty($_REQUEST["graph_nolegend"])) {
	$graph_data_array["graph_nolegend"] = $_REQUEST["graph_nolegend"];
}

/* override: graph start */
if (!empty($_REQUEST["graph_start"])) {
	$graph_data_array["graph_start"] = $_REQUEST["graph_start"];
}

/* override: graph end */
if (!empty($_REQUEST["graph_end"])) {
	$graph_data_array["graph_end"] = $_REQUEST["graph_end"];
}

/* print RRDTool graph source? */
if (!empty($_REQUEST["show_source"])) {
	$graph_data_array["print_source"] = $_REQUEST["show_source"];
}

/* check ds */
if ($graph_data_array["ds_step"] < 1) {
	$graph_data_array["ds_step"] = read_config_option("realtime_interval");
}

/* call poller */
$command = read_config_option("path_php_binary");
$args    = sprintf('plugins/realtime/poller_rt.php --graph=%s --interval=%d', $_REQUEST["local_graph_id"], $graph_data_array["ds_step"]);

shell_exec("$command $args");

/* return image syntax to the browser */
$source = $config["url_path"] . "plugins/realtime/graph_image_rt.php?graph_start=" . $_REQUEST['graph_start'] . "&graph_end=0&local_graph_id=" . $_REQUEST['local_graph_id'] . "&ds_step=" . $_REQUEST['ds_step'] . "&count=" . $_REQUEST['count'] . "&timetweak=" . time();

if (isset($_SESSION["sess_realtime_sync"]) && strlen($_SESSION["sess_realtime_sync"])) {
	$sync = "on";
}else{
	$sync = "off";
}

/* send text information back to browser as well as image information */
echo $source . "!!!" . (isset($_SESSION["sess_realtime_ds_step"]) ? $_SESSION["sess_realtime_ds_step"]:$graph_data_array["ds_step"]) . "!!!" . (isset($_SESSION["sess_realtime_graph_start"]) ? $_SESSION["sess_realtime_graph_start"]:$graph_data_array["graph_start"]) . "!!!" . $sync;

?>

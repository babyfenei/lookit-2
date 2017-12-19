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

function nectar_form_save() {
	global $config, $messages;
	# when using cacti_log: include_once($config['library_path'] . "/functions.php");

	if (isset($_POST["save_component_nectar"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("id"));
		input_validate_input_number(get_request_var_post("font_size"));
		input_validate_input_number(get_request_var_post("graph_width"));
		input_validate_input_number(get_request_var_post("graph_height"));
		input_validate_input_number(get_request_var_post("graph_columns"));
		/* ==================================================== */
		$now = time();

		if ($_POST['id'] == 0 || $_POST['id'] == '') {
			$save['user_id'] = $_SESSION['sess_user_id'];
		}else{
			$save['user_id'] = db_fetch_cell("SELECT user_id FROM plugin_nectar WHERE id=" . $_POST['id']);
		}

		$save['id']				= $_POST['id'];
		$save['name']			= sql_sanitize(form_input_validate($_POST['name'], 'name', '', false, 3));
		$save['email']			= sql_sanitize(form_input_validate($_POST['email'], 'email', '', false, 3));
		$save['enabled']		= (isset($_POST['enabled']) ? 'on' : '');

		$save['cformat']		= (isset($_POST['cformat']) ? 'on' : '');
		$save['format_file']	= sql_sanitize($_POST['format_file']);
		$save['font_size']		= form_input_validate($_POST['font_size'], 'font_size', '^[0-9]+$', false, 3);
		$save['alignment']		= form_input_validate($_POST['alignment'], 'alignment', '^[0-9]+$', false, 3);
		$save['graph_linked']	= (isset($_POST['graph_linked']) ? 'on' : '');

		$save['graph_columns']	= form_input_validate($_POST['graph_columns'], 'graph_columns', '^[0-9]+$', false, 3);
		$save['graph_width']	= form_input_validate($_POST['graph_width'], 'graph_width', '^[0-9]+$', false, 3);
		$save['graph_height']	= form_input_validate($_POST['graph_height'], 'graph_height', '^[0-9]+$', false, 3);
		$save['thumbnails']		= form_input_validate((isset($_POST['thumbnails']) ? $_POST['thumbnails']:''), 'thumbnails', '', true, 3);

		$save['intrvl']			= form_input_validate($_POST['intrvl'], 'intrvl', '^[0-9]+$', false, 3);
		$save['count']			= form_input_validate($_POST['count'], 'count', '^[0-9]+$', false, 3);
		$save['offset']			= '0';

		/* adjust mailtime according to rules */
		$timestamp = strtotime($_POST['mailtime']);
		if ($timestamp === false) {
			$timestamp  = $now;
		} elseif (($timestamp + read_config_option('poller_interval')) < $now) {
			$timestamp += 86400;

			/* if the time is far into the past, make it the correct time, but tomorrow */
			if (($timestamp + read_config_option('poller_interval')) < $now) {
				$timestamp = strtotime("12:00am") + 86400 + date("H", $timestamp) * 3600 + date("i", $timestamp) * 60 + date("s", $timestamp);
			}
			$_SESSION['nectar_message'] = "Date/Time moved to the same time Tomorrow";

			raise_message('nectar_message');
		}

		$save['mailtime']     = form_input_validate($timestamp, 'mailtime', '^[0-9]+$', false, 3);

		if (strlen($_POST['subject'])) {
			$save['subject'] = sql_sanitize($_POST['subject']);
		}else{
			$save['subject'] = $save['name'];
		}

		$save['from_name']        = sql_sanitize($_POST['from_name']);
		$save['from_email']       = sql_sanitize($_POST['from_email']);
		$save['bcc']              = sql_sanitize($_POST['bcc']);
		if (($_POST['attachment_type'] != NECTAR_TYPE_INLINE_PNG) &&
			($_POST['attachment_type'] != NECTAR_TYPE_INLINE_JPG) &&
			($_POST['attachment_type'] != NECTAR_TYPE_INLINE_GIF) &&
			($_POST['attachment_type'] != NECTAR_TYPE_ATTACH_PNG) &&
			($_POST['attachment_type'] != NECTAR_TYPE_ATTACH_JPG) &&
			($_POST['attachment_type'] != NECTAR_TYPE_ATTACH_GIF) &&
			($_POST['attachment_type'] != NECTAR_TYPE_INLINE_PNG_LN) &&
			($_POST['attachment_type'] != NECTAR_TYPE_INLINE_JPG_LN) &&
			($_POST['attachment_type'] != NECTAR_TYPE_INLINE_GIF_LN) &&
			($_POST['attachment_type'] != NECTAR_TYPE_ATTACH_PDF)) {
			$_POST['attachment_type'] = NECTAR_TYPE_INLINE_PNG;
		}
		$save['attachment_type']  = form_input_validate($_POST['attachment_type'], 'attachment_type', '^[0-9]+$', false, 3);
		$save['lastsent']         = 0;

		if (!is_error_message()) {
			$id = sql_save($save, 'plugin_nectar');

			if ($id) {
				raise_message('nectar_save');
			}else{
				raise_message('nectar_save_failed');
			}
		}

		header("Location: " . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=edit&id=" . (empty($id) ? $_POST["id"] : $id));
		exit;
	}elseif (isset($_POST["save_component_nectar_item"])) {
		/* ================= input validation ================= */
		input_validate_input_number(get_request_var_post("report_id"));
		input_validate_input_number(get_request_var_post("id"));
		/* ==================================================== */

		$save = array();

		$save["id"]                = $_POST["id"];
		$save["report_id"]         = form_input_validate($_POST["report_id"], "report_id", "^[0-9]+$", false, 3);
		$save["sequence"]          = form_input_validate($_POST["sequence"], "sequence", "^[0-9]+$", false, 3);
		$save["item_type"]         = form_input_validate($_POST["item_type"], "item_type", "^[-0-9]+$", false, 3);
		$save["tree_id"]           = (isset($_POST["tree_id"]) ? form_input_validate($_POST["tree_id"], "tree_id", "^[-0-9]+$", true, 3) : 0);
		$save["branch_id"]         = (isset($_POST["branch_id"]) ? form_input_validate($_POST["branch_id"], "branch_id", "^[-0-9]+$", true, 3) : 0);
		$save["tree_cascade"]      = (isset($_POST["tree_cascade"]) ? "on":"");
		$save["graph_name_regexp"] = sql_sanitize($_POST["graph_name_regexp"]);
		$save["host_template_id"]  = (isset($_POST["host_template_id"]) ? form_input_validate($_POST["host_template_id"], "host_template_id", "^[-0-9]+$", true, 3) : 0);
		$save["host_id"]           = (isset($_POST["host_id"]) ? form_input_validate($_POST["host_id"], "host_id", "^[-0-9]+$", true, 3) : 0);
		$save["graph_template_id"] = (isset($_POST["graph_template_id"]) ? form_input_validate($_POST["graph_template_id"], "graph_template_id", "^[-0-9]+$", true, 3) : 0);
		$save["local_graph_id"]    = (isset($_POST["local_graph_id"]) ? form_input_validate($_POST["local_graph_id"], "local_graph_id", "^[0-9]+$", true, 3) : 0);
		$save["timespan"]          = (isset($_POST["timespan"]) ? form_input_validate($_POST["timespan"], "timespan", "^[0-9]+$", true, 3) : 0);
		$save["item_text"]         = (isset($_POST["item_text"]) ? sql_sanitize(form_input_validate($_POST["item_text"], "item_text", "", true, 3)) : '');
		$save["align"]             = (isset($_POST["align"]) ? form_input_validate($_POST["align"], "align", "^[0-9]+$", true, 3) : NECTAR_ALIGN_LEFT);
		$save["font_size"]         = (isset($_POST["font_size"]) ? form_input_validate($_POST["font_size"], "font_size", "^[0-9]+$", true, 3) : NECTAR_FONT_SIZE);

		if (!is_error_message()) {
			$item_id = sql_save($save, "plugin_nectar_items");

			if ($item_id) {
				raise_message('nectar_save');
			}else{
				raise_message('nectar_save_failed');
			}
		}

		if (is_error_message()) {
			header("Location: " . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=item_edit&id=" . $_POST["report_id"] . "&item_id=" . (empty($item_id) ? $_POST["id"] : $item_id));
		}else{
			header("Location: " . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=edit&id=" . $_POST["report_id"]);
		}
	} else {
		header("Location: " . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php"));
	}
	exit;
}


/* ------------------------
 The "actions" function
 ------------------------ */
function nectar_form_actions() {
	global $colors, $config, $nectar_actions;
	include_once($config["base_path"]."/plugins/nectar/nectar_functions.php");

	/* if we are to save this form, instead of display it */
	if (isset($_POST["selected_items"])) {
		$selected_items = unserialize(stripslashes($_POST["selected_items"]));

		if ($_POST["drp_action"] == NECTAR_DELETE) { /* delete */
			db_execute("DELETE FROM plugin_nectar WHERE " . array_to_sql_or($selected_items, "id"));
			db_execute("DELETE FROM plugin_nectar_items WHERE " . str_replace("id", "report_id", array_to_sql_or($selected_items, "id")));
		}elseif ($_POST["drp_action"] == NECTAR_OWN) { /* take ownership */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				nectar_log(__FUNCTION__ . ", takeown: " . $selected_items[$i] . " user: " . $_SESSION["sess_user_id"], false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_nectar SET user_id=" . $_SESSION["sess_user_id"] . " WHERE id=" . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == NECTAR_DUPLICATE) { /* duplicate */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				nectar_log(__FUNCTION__ . ", duplicate: " . $selected_items[$i] . " name: " . $_POST["name_format"], false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
				duplicate_nectar($selected_items[$i], $_POST["name_format"]);
			}
		}elseif ($_POST["drp_action"] == NECTAR_ENABLE) { /* enable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				nectar_log(__FUNCTION__ . ", enable: " . $selected_items[$i], false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_nectar SET enabled='on' WHERE id=" . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == NECTAR_DISABLE) { /* disable */
			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */
				nectar_log(__FUNCTION__ . ", disable: " . $selected_items[$i], false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
				db_execute("UPDATE plugin_nectar SET enabled='' WHERE id=" . $selected_items[$i]);
			}
		}elseif ($_POST["drp_action"] == NECTAR_SEND_NOW) { /* send now */
			include_once($config["base_path"] . '/plugins/nectar/nectar_functions.php');
			$message = '';

			for ($i=0;($i<count($selected_items));$i++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$i]);
				/* ==================================================== */

				$_SESSION['nectar_message'] = '';
				$_SESSION['nectar_error']   = '';

				nectar_send($selected_items[$i]);

				if (isset($_SESSION['nectar_message']) && strlen($_SESSION['nectar_message'])) {
					$message .= (strlen($message) ? "<br>":"") . $_SESSION['nectar_message'];
				}
				if (isset($_SESSION['nectar_error']) && strlen($_SESSION['nectar_error'])) {
					$message .= (strlen($message) ? "<br>":"") . "<span style='color:red;'>" . $_SESSION['nectar_error'] . "</span>";
				}
			}

			if (strlen($message)) {
				$_SESSION['nectar_message'] = $message;
				raise_message('nectar_message');
			}
		}

		header("Location: " . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php"));
		exit;
	}

	/* setup some variables */
	$nectar_list = ""; $i = 0;
	/* loop through each of the graphs selected on the previous page and get more info about them */
	while (list($var,$val) = each($_POST)) {
		if (preg_match("/^chk_([0-9]+)$/", $var, $matches)) {
			/* ================= input validation ================= */
			input_validate_input_number($matches[1]);
			/* ==================================================== */
			$nectar_list .= "<li>" . db_fetch_cell("SELECT name FROM plugin_nectar WHERE id=" . $matches[1]) . "<br>";
			$nectar_array[$i] = $matches[1];
			$i++;
		}
	}

	include_once($config["base_path"]."/plugins/nectar/top_general_header.php");

	display_output_messages();

	?>
	<script type="text/javascript">
	<!--
	function goTo(location) {
		document.location = location;
	}
	-->
	</script><?php

	print '<form name="nectar" action="nectar' . ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN ? '':'_user') . '.php" method="post">';

	html_start_box("<strong>" . $nectar_actions{$_POST["drp_action"]} . "</strong>", "60%", $colors["header_panel"], "3", "center", "");

	if (!isset($nectar_array)) {
		print "<tr><td bgcolor='#" . $colors["form_alternate1"]. "'><span class='textError'>You must select at least one Report.</span></td></tr>\n";
		$save_html = "";
	}else{
		$save_html = "<input type='submit' value='Yes' name='save'>";

		if ($_POST["drp_action"] == NECTAR_DELETE) { /* delete */
			print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to delete the following Reports?</p>
					<p><ul>$nectar_list</ul></p>
				</td>
			</tr>\n
			";
		}elseif ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN && $_POST["drp_action"] == NECTAR_OWN) { /* take ownership */
			print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you wish to take ownership of the following reports?</p>
					<p><ul>$nectar_list</ul></p>
				</td>
			</tr>\n
			";
		}elseif ($_POST["drp_action"] == NECTAR_DUPLICATE) { /* duplicate */
			print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>When you click save, the following Reports will be duplicated. You can
					optionally change the title format for the new Reports.</p>
					<p><ul>$nectar_list</ul></p>
					<p><strong>Name Format:</strong><br>"; form_text_box("name_format", "<name> (1)", "", "255", "30", "text"); print "</p>
				</td>
			</tr>\n
			";
		}elseif ($_POST["drp_action"] == NECTAR_ENABLE) { /* enable */
			print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you wish to enable the following reports?</p>
					<p><ul>$nectar_list</ul></p>
					<p><strong>Make sure, that those Reports have successfully been tested!</strong></p>
				</td>
			</tr>\n
			";
		}elseif ($_POST["drp_action"] == NECTAR_DISABLE) { /* disable */
			print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you wish to disable the following reports?</p>
					<p><ul>$nectar_list</ul></p>
				</td>
			</tr>\n
			";
		}elseif ($_POST["drp_action"] == NECTAR_SEND_NOW) { /* send now */
			print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Are you sure you want to send the following reports now?</p>
					<p><ul>$nectar_list</ul></p>
				</td>
			</tr>\n
			";
		}
	}

	print "	<tr>
		<td align='right' bgcolor='#eaeaea'>
			<input type='hidden' name='action' value='actions'>
			<input type='hidden' name='selected_items' value='" . (isset($nectar_array) ? serialize($nectar_array) : '') . "'>
			<input type='hidden' name='drp_action' value='" . $_POST["drp_action"] . "'>
			<input type='button' onClick='goTo(\"" . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "\")' value='" . ($save_html == '' ? 'Return':'No') . "' name='cancel'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	include_once($config["base_path"]."/include/bottom_footer.php");
}

/* --------------------------
 Nectar Item Functions
 -------------------------- */
function nectar_send($id) {
	global $config;

	/* ================= input validation ================= */
	input_validate_input_number($id);
	/* ==================================================== */
	include_once($config["base_path"]."/plugins/nectar/nectar_functions.php");

	$nectar = db_fetch_row("SELECT * FROM plugin_nectar WHERE id=" . $id);

	if (!sizeof($nectar)) {
		/* set error condition */
	}elseif ($nectar["user_id"] == $_SESSION["sess_user_id"]) {
		nectar_log(__FUNCTION__ . ", send now, report_id: " . $id, false, "NECTAR TRACE", POLLER_VERBOSITY_MEDIUM);
		/* use report name as default EMail title */
		if (!strlen($nectar['subject'])) {
			$nectar['subject'] = $nectar['name'];
		};

		if (!strlen($nectar['email'])) {
			$_SESSION['nectar_error'] = "Unable to send Nectar Report '" . $nectar['name'] . "'.  Please set destination e-mail addresses";
			if (!isset($_POST["selected_items"])) {
				raise_message("nectar_error");
			}
		}elseif (!strlen($nectar['subject'])) {
			$_SESSION['nectar_error'] = "Unable to send Nectar Report '" . $nectar['name'] . "'.  Please set an e-mail subject";
			if (!isset($_POST["selected_items"])) {
				raise_message("nectar_error");
			}
		}elseif (!strlen($nectar['from_name'])) {
			$_SESSION['nectar_error'] = "Unable to send Nectar Report '" . $nectar['name'] . "'.  Please set an e-mail From Name";
			if (!isset($_POST["selected_items"])) {
				raise_message("nectar_error");
			}
		}elseif (!strlen($nectar['from_email'])) {
			$_SESSION['nectar_error'] = "Unable to send Nectar Report '" . $nectar['name'] . "'.  Please set an e-mail from address";
			if (!isset($_POST["selected_items"])) {
				raise_message("nectar_error");
			}
		}else{
			generate_nectar($nectar, true);
		}
	}
}

function nectar_item_movedown() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	move_item_down("plugin_nectar_items", get_request_var("item_id"), "report_id=" . get_request_var("id"));
}

function nectar_item_moveup() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */
	move_item_up("plugin_nectar_items", get_request_var("item_id"), "report_id=" . get_request_var("id"));
}

function nectar_item_remove() {
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("item_id"));
	/* ==================================================== */
	db_execute("DELETE FROM plugin_nectar_items WHERE id=" . get_request_var("item_id"));
}

function nectar_item_edit() {
	global $config, $colors;
	global $fields_nectar_item_edit;

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	input_validate_input_number(get_request_var("item_id"));
	input_validate_input_number(get_request_var("item_type"));
	input_validate_input_number(get_request_var("branch_id"));
	input_validate_input_number(get_request_var("tree_id"));
	input_validate_input_number(get_request_var("host_id"));
	input_validate_input_number(get_request_var("host_template_id"));
	input_validate_input_number(get_request_var("graph_template_id"));
	/* ==================================================== */

	# fetch the current nectar record
	$nectar = db_fetch_row("SELECT * " .
			"FROM plugin_nectar " .
			"WHERE id=" . get_request_var_request("id"));

	# if an existing item was requested, fetch data for it
	if (isset($_REQUEST["item_id"]) && ($_REQUEST["item_id"] > 0)) {
		$nectar_item = db_fetch_row("SELECT * " .
					"FROM plugin_nectar_items" .
					" WHERE id=" . get_request_var_request("item_id"));
		$header_label = "[edit Report Item: " . $nectar['name'] . "]";
	}else{
		$header_label = "[new Report Item: " . $nectar['name'] . "]";
		$nectar_item = array();
		$nectar_item["report_id"] = get_request_var_request("id");
		$nectar_item["sequence"]  = get_sequence('', 'sequence', 'plugin_nectar_items', 'report_id=' . get_request_var_request("id"));
		$nectar_item["host_id"]   = NECTAR_HOST_NONE;
	}

	# if a different host_template_id was selected, use it
	if (get_request_var_request("item_type", '') !== '') {
		$nectar_item["item_type"] = get_request_var_request("item_type");
	}

	if (get_request_var_request("tree_id", '') !== '') {
		$nectar_item["tree_id"] = get_request_var_request("tree_id");
	}else if (!isset($nectar_item["tree_id"])) {
		$nectar_item["tree_id"] = 0;
	}

	if (get_request_var_request("host_template_id", '') !== '') {
		$nectar_item["host_template_id"] = get_request_var_request("host_template_id");
	}else if (!isset($nectar_item["host_template_id"])) {
		$nectar_item["host_template_id"] = 0;
	}

	# if a different host_id was selected, use it
	if (get_request_var_request("host_id", '') !== '') {
		$nectar_item["host_id"] = get_request_var_request("host_id");
	}

	# if a different graph_template_id was selected, use it
	if (get_request_var_request("graph_template_id", '') !== '') {
		$nectar_item["graph_template_id"] = get_request_var_request("graph_template_id");
	}else if (!isset($nectar_item["graph_template_id"])) {
		$nectar_item["graph_template_id"] = 0;
	}

	load_current_session_value("host_template_id", "sess_nectar_edit_host_template_id", 0);
	load_current_session_value("host_id", "sess_nectar_edit_host_id", 0);
	load_current_session_value("tree_id", "sess_nectar_edit_tree_id", 0);

	/* set the default item alignment */
	$fields_nectar_item_edit['align']['default'] = $nectar['alignment'];

	/* set the default item alignment */
	$fields_nectar_item_edit['font_size']['default'] = $nectar['font_size'];

	print "<form method='post' action='" .  basename($_SERVER["PHP_SELF"]) . "' name='nectar_item_edit'>\n";

	# ready for displaying the fields
	html_start_box("<strong>Report Item</strong> $header_label", "100%", $colors["header"], "3", "center", "");

	draw_edit_form(array(
		"config" => array("no_form_tag" => true),
		"fields" => inject_form_variables($fields_nectar_item_edit, (isset($nectar_item) ? $nectar_item : array()), (isset($nectar) ? $nectar : array()))
	));

	html_end_box();

	form_hidden_box("id", (isset($nectar_item["id"]) ? $nectar_item["id"] : "0"), "");
	form_hidden_box("report_id", (isset($nectar_item["report_id"]) ? $nectar_item["report_id"] : "0"), "");
	form_hidden_box("save_component_nectar_item", "1", "");

	echo "<table id='graphdiv' style='display:none;' align='center' width='100%'><tr><td align='center' id='graph'></td></tr></table>";

	nectar_save_button(htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=edit&tab=items&id=" . get_request_var_request("id")));

	if (isset($item['item_type']) && $item['item_type'] == NECTAR_ITEM_GRAPH) {
		$timespan = array();
		# get config option for first-day-of-the-week
		$first_weekdayid = read_graph_config_option("first_weekdayid");
		# get start/end time-since-epoch for actual time (now()) and given current-session-timespan
		get_timespan($timespan, time(), $item["timespan"], $first_weekdayid);
	}

	/* don't cache previews */
	$_SESSION["custom"] = "true";

	?>
	<script type="text/javascript">

	useCss=<?php print ($nectar["cformat"] == "on" ? "true":"false");?>;

	toggle_item_type();
	graphImage(document.getElementById("local_graph_id").value);

	function toggle_item_type() {
		// right bracket ")" does not come with a field
		if (document.getElementById('item_type').value == '<?php print NECTAR_ITEM_GRAPH;?>') {
			document.getElementById('row_align').style.display='';
			document.getElementById('row_tree_id').style.display='none';
			document.getElementById('row_branch_id').style.display='none';
			document.getElementById('row_tree_cascade').style.display='none';
			document.getElementById('row_graph_name_regexp').style.display='none';
			document.getElementById('row_host_template_id').style.display='';
			document.getElementById('row_host_id').style.display='';
			document.getElementById('row_graph_template_id').style.display='';
			document.getElementById('row_local_graph_id').style.display='';
			document.getElementById('row_timespan').style.display='';
			document.getElementById('item_text').value = '';
			document.getElementById('row_item_text').style.display='none';
			document.getElementById('row_font_size').style.display='none';
		} else if (document.getElementById('item_type').value == '<?php print NECTAR_ITEM_TEXT;?>') {
			document.getElementById('row_align').style.display='';
			document.getElementById('row_tree_id').style.display='none';
			document.getElementById('row_branch_id').style.display='none';
			document.getElementById('row_tree_cascade').style.display='none';
			document.getElementById('row_graph_name_regexp').style.display='none';
			document.getElementById('row_host_template_id').style.display='none';
			document.getElementById('row_host_id').style.display='none';
			document.getElementById('row_graph_template_id').style.display='none';
			document.getElementById('row_local_graph_id').style.display='none';
			document.getElementById('row_timespan').style.display='none';
			document.getElementById('row_item_text').style.display='';
			if (useCss) {
				document.getElementById('row_font_size').style.display='none';
			}else{
				document.getElementById('row_font_size').style.display='';
			}
		} else if (document.getElementById('item_type').value == '<?php print NECTAR_ITEM_TREE;?>') {
			document.getElementById('row_align').style.display='';
			document.getElementById('row_tree_id').style.display='';
			document.getElementById('row_branch_id').style.display='';
			document.getElementById('row_tree_cascade').style.display='';
			document.getElementById('row_graph_name_regexp').style.display='';
			document.getElementById('row_host_template_id').style.display='none';
			document.getElementById('row_host_id').style.display='none';
			document.getElementById('row_graph_template_id').style.display='none';
			document.getElementById('row_local_graph_id').style.display='none';
			document.getElementById('row_timespan').style.display='';
			document.getElementById('row_item_text').style.display='none';
			if (useCss) {
				document.getElementById('row_font_size').style.display='none';
			}else{
				document.getElementById('row_font_size').style.display='';
			}
		} else {
			document.getElementById('row_tree_id').style.display='none';
			document.getElementById('row_branch_id').style.display='none';
			document.getElementById('row_tree_cascade').style.display='none';
			document.getElementById('row_graph_name_regexp').style.display='none';
			document.getElementById('row_host_template_id').style.display='none';
			document.getElementById('row_host_id').style.display='none';
			document.getElementById('row_graph_template_id').style.display='none';
			document.getElementById('row_local_graph_id').style.display='none';
			document.getElementById('row_timespan').style.display='none';
			document.getElementById('row_item_text').style.display='none';
			document.getElementById('row_font_size').style.display='none';
			document.getElementById('row_align').style.display='none';
		}
	}

	function applyChange(objForm) {
		strURL = '?action=item_edit'
		strURL = strURL + '&id=' + objForm.report_id.value
		strURL = strURL + '&item_id=' + objForm.id.value
		strURL = strURL + '&item_type=' + objForm.item_type.value;
		strURL = strURL + '&tree_id=' + objForm.tree_id.value;
		strURL = strURL + '&branch_id=' + objForm.branch_id.value;
		strURL = strURL + '&host_template_id=' + objForm.host_template_id.value;
		strURL = strURL + '&host_id=' + objForm.host_id.value;
		strURL = strURL + '&graph_template_id=' + objForm.graph_template_id.value;
		document.location = strURL;
	}

	function graphImage(graphId) {
		if (graphId > 0) {
			document.getElementById('graphdiv').style.display='';
			document.getElementById('graph').innerHTML="<img align='center' src='<?php print $config['url_path'];?>graph_image.php"+
					"?local_graph_id="+graphId+
					"<?php print (($nectar["graph_width"] > 0) ? "&graph_width=" . $nectar["graph_width"]:"");?>"+
					"<?php print (($nectar["graph_height"] > 0) ? "&graph_height=" . $nectar["graph_height"]:"");?>"+
					"<?php print (($nectar["thumbnails"] == "on") ? "&graph_nolegend=true":"");?>"+
					"<?php print ((isset($timespan["begin_now"])) ? "&graph_start=" . $timespan["begin_now"]:"");?>"+
					"<?php print ((isset($timespan["end_now"])) ? "&graph_end=" . $timespan["end_now"]:"");?>"+
					"&rra_id=0'>";
		}else{
			document.getElementById('graphdiv').style.display='none';
			document.getElementById('graph').innerHTML='';
		}
	}
	</script>
	<?php
}


/* ---------------------
 Nectar Functions
 --------------------- */
function nectar_remove() {
	global $config;
	/* ================= input validation ================= */
	input_validate_input_number(get_request_var("id"));
	/* ==================================================== */

	if ((read_config_option("deletion_verification") == "on") && (!isset($_GET["confirm"]))) {
		include($config["base_path"]."/plugins/nectar/top_general_header.php");
		form_confirm("Are You Sure?", "Are you sure you want to delete the Report <strong>'" . db_fetch_cell("SELECT name FROM nectar WHERE id=" . $_GET["id"]) . "'</strong>?", ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php"), ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=remove&id=" . $_GET["id"]);
		include($config["base_path"]."/include/bottom_footer.php");
		exit;
	}

	if ((read_config_option("deletion_verification") == "") || (isset($_GET["confirm"]))) {
		db_execute("DELETE FROM plugin_nectar_items WHERE report_id=" . $_GET["id"]);
		db_execute("DELETE FROM plugin_nectar WHERE id=" . $_GET["id"]);
	}
}

function nectar_edit() {
	global $colors, $config;
	global $fields_nectar_edit;
	include_once($config["base_path"]."/plugins/nectar/nectar_functions.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("id"));
	input_validate_input_number(get_request_var_request("page"));
	/* ==================================================== */

	/* clean up rule name */
	if (isset($_REQUEST["name"])) {
		$_REQUEST["name"] = sanitize_search_string(get_request_var("name"));
	}

	/* clean up tab name */
	if (isset($_REQUEST["tab"])) {
		$_REQUEST["tab"] = sanitize_search_string(get_request_var("tab"));
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_nectar_edit_current_page", "1");
	load_current_session_value("tab", "sess_nectar_edit_tab", "detail");
	load_current_session_value("rows", "sess_nectar_edit_rows", read_config_option("num_rows_data_query"));

	/* display the nectar */
	$nectar = array();
	if (!empty($_REQUEST["id"])) {
		$nectar = db_fetch_row("SELECT * FROM plugin_nectar WHERE id=" . $_REQUEST["id"]);
		# reformat mailtime to human readable format
		$nectar["mailtime"] = date(nectar_date_time_format(), $nectar["mailtime"]);
		# setup header
		$header_label = "[edit: " . $nectar["name"] . "]";
	}else{
		$header_label = "[new]";
		# initialize mailtime with current timestamp
		$nectar["mailtime"] = date(nectar_date_time_format(), floor(time() / read_config_option("poller_interval")) * read_config_option("poller_interval"));
	}

	/* if there was an error on the form, display the date in the correct format */
	if (isset($_SESSION["sess_field_values"]["mailtime"])) {
		$_SESSION["sess_field_values"]["mailtime"] = date(nectar_date_time_format(), $_SESSION["sess_field_values"]["mailtime"]);
	}

	$nectar_tabs = array('details' => 'Details', 'items' => 'Items', 'preview' => 'Preview', 'events' => 'Events');

	/* set the default settings category */
	if (!isset($_REQUEST["tab"])) $_REQUEST["tab"] = 'details';
	$current_tab = $_REQUEST["tab"];

	/* draw the categories tabs on the top of the page */
	print "<table class='tabs' width='100%' cellspacing='0' cellpadding='3' align='center'><tr>\n";

	if (sizeof($nectar_tabs)) {
	foreach (array_keys($nectar_tabs) as $tab_short_name) {
		if ($tab_short_name == 'details' || (!empty($nectar["id"]))) {
			print "<td " . (($tab_short_name == $current_tab) ? "bgcolor='silver'" : "bgcolor='#DFDFDF'") . " nowrap='nowrap' width='" . (strlen($nectar_tabs[$tab_short_name]) * 9) . "' align='center' class='tab'>
				<span class='textHeader'><a href='" . htmlspecialchars($config["url_path"] . "plugins/nectar/" . ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=edit&id=" . (isset($nectar["id"]) ? $nectar["id"]:"") . "&tab=$tab_short_name") . "'>$nectar_tabs[$tab_short_name]</a></span>
				</td>\n
				<td width='1'></td>\n";
		}
	}
	}

	if (!empty($_REQUEST["id"])) {
		print "<td align='right'><a class='textHeader' href='" . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=send&id=" . $_REQUEST["id"] . "&tab=" . $_REQUEST["tab"]) . "'>Send Report</a></td>\n</tr></table>\n";
	}else{
		print "<td align='right'></td>\n</tr></table>\n";
	}

	switch($_REQUEST["tab"]) {
	case 'details':
		print '<form name="nectar" action="nectar' . ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN ? '':'_user') . '.php" method="post">';
		html_start_box("<strong>Report Details</strong> $header_label", "100%", $colors["header"], "3", "center", "");

		draw_edit_form(array(
			"config" => array("no_form_tag" => true),
			"fields" => inject_form_variables($fields_nectar_edit, $nectar)
		));

		html_end_box();
		form_hidden_box("id", (isset($nectar["id"]) ? $nectar["id"] : "0"), "");
		form_hidden_box("save_component_nectar", "1", "");

		?>
		<script type="text/javascript">
		var cformat = document.getElementById('cformat');
		cformat.setAttribute('onclick', 'changeFormat()');

		function addLoadEvent(func) {
			if(typeof window.onload != 'function')
				window.onload = func;
			else {
				var oldLoad = window.onload;

				window.onload = function() {
					if(oldLoad) oldLoad();
					func();
				}
			}
		}

		function changeFormat() {
			if (cformat && cformat.checked) {
				document.getElementById('row_font_size').style.display='none';
				document.getElementById('row_format_file').style.display='';
			}else{
				document.getElementById('row_font_size').style.display='';
				document.getElementById('row_format_file').style.display='none';
			}
		}

		addLoadEvent(changeFormat);
		</script>
		<?php

		nectar_save_button(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php"));

		break;
	case 'items':
		html_start_box("<strong>Report Items</strong> $header_label", "100%", $colors["header"], "3", "center", ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=item_edit&id=" . $_GET["id"]);

		/* display the items */
		if (!empty($nectar["id"])) {
			display_nectar_items($nectar["id"]);
		}

		html_end_box();

		break;
	case 'events':
		if (($timestamp = strtotime($nectar["mailtime"])) === false) {
			$timestamp = time();
		}
		$poller_interval = read_config_option("poller_interval");
		if ($poller_interval == '') $poller_interval = 300;

		$timestamp   = floor($timestamp / $poller_interval) * $poller_interval;
		$next        = nectar_interval_start($nectar["intrvl"], $nectar["count"], $nectar["offset"], $timestamp);
		$date_format = nectar_date_time_format() . " - l";

		html_start_box("<strong>Scheduled Events</strong> $header_label", "100%", $colors["header"], "3", "center", "");
		for ($i=0; $i<14; $i++) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $i);
			form_selectable_cell(date($date_format, $next), $i);
			form_end_row();
			$next = nectar_interval_start($nectar["intrvl"], $nectar["count"], $nectar["offset"], $next);
		}
		html_end_box(false);

		break;
	case 'preview':
		html_start_box("<strong>Report Preview</strong> $header_label", "100%", $colors["header"], "3", "center", "");
		print "\t\t\t\t\t<tr><td>\n";
		print nectar_generate_html($nectar["id"], NECTAR_OUTPUT_STDOUT);
		print "\t\t\t\t\t</td></tr>\n";
		html_end_box(false);

		break;
	}
}

/* display_nectar_items		display the list of all items related to a single nectar
 * @arg $nectar_id				id of the nectar
 */
function display_nectar_items($nectar_id) {
	global $colors, $graph_timespans;
	global $item_types, $alignment;

	$items = db_fetch_assoc("SELECT *
		FROM plugin_nectar_items
		WHERE report_id=" . $nectar_id . "
		ORDER BY sequence");

	$css = db_fetch_cell("SELECT cformat FROM plugin_nectar WHERE id=" . $nectar_id);

	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
	DrawMatrixHeaderItem("Item",$colors["header_text"],1);
	DrawMatrixHeaderItem("Sequence",$colors["header_text"],1);
	DrawMatrixHeaderItem("Type",$colors["header_text"],1);
	DrawMatrixHeaderItem("Item Details",$colors["header_text"],1);
	DrawMatrixHeaderItem("Timespan",$colors["header_text"],1);
	DrawMatrixHeaderItem("Alignment",$colors["header_text"],1);
	DrawMatrixHeaderItem("Font Size",$colors["header_text"],1);
	DrawMatrixHeaderItem("&nbsp;",$colors["header_text"],2);
	print "</tr>";

	$i = 0;
	if (sizeof($items) > 0) {
		foreach ($items as $item) {
			switch ($item["item_type"]) {
			case NECTAR_ITEM_GRAPH:
				$item_details = get_graph_title($item["local_graph_id"]);
				$align = ($item["align"] > 0 ? $alignment[$item["align"]] : '');
				$size = '';
				$timespan = ($item["timespan"] > 0 ? $graph_timespans[$item["timespan"]] : '');
				break;
			case NECTAR_ITEM_TEXT:
				$item_details = $item["item_text"];
				$align = ($item["align"] > 0 ? $alignment[$item["align"]] : '');
				$size = ($item["font_size"] > 0 ? $item["font_size"] : '');
				$timespan = '';
				break;
			case NECTAR_ITEM_TREE:
				if ($item["branch_id"] > 0) {
					$branch_details = db_fetch_row("SELECT * FROM graph_tree_items WHERE id=" . $item["branch_id"]);
				}else{
					$branch_details = array();
				}
				$tree_name      = db_fetch_cell("SELECT name FROM graph_tree WHERE id=" . $item["tree_id"]);

				$item_details = "Tree: " . $tree_name;
				if ($item["branch_id"] > 0) {
					if ($branch_details["host_id"] > 0) {
						$item_details .= ", Host: " . db_fetch_cell("SELECT description FROM host WHERE id=" . $branch_details["host_id"]);
					}else{
						$item_details .= ", Branch: " . $branch_details["title"];

						if ($item["tree_cascade"] == "on") {
							$item_details .= " (All Branches)";
						}else{
							$item_details .= " (Current Branch)";
						}
					}
				}

				$align = ($item["align"] > 0 ? $alignment[$item["align"]] : '');
				$size = ($item["font_size"] > 0 ? $item["font_size"] : '');
				$timespan = ($item["timespan"] > 0 ? $graph_timespans[$item["timespan"]] : '');
				break;
			default:
				$item_details = '';
				$align = '';
				$size = '';
				$timespan = '';
			}

			if ($css == "on") {
				$size = '';
			}


			form_alternate_row_color($colors["alternate"],$colors["light"],$i); $i++;
			$form_data = '<td><a class="linkEditMain" href="' . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=item_edit&id=" . $nectar_id. "&item_id=" . $item["id"]) . '">Item#' . $i . '</a></td>';
			$form_data .= '<td>' . $item["sequence"] . '</td>';
			$form_data .= '<td>' . $item_types{$item["item_type"]} . '</td>';
			$form_data .= '<td>' . $item_details . '</td>';
			$form_data .= '<td>' . $timespan . '</td>';
			$form_data .= '<td>' . $align . '</td>';
			$form_data .= '<td>' . $size . '</td>';
			$form_data .= '<td><a href="' . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . '?action=item_movedown&item_id=' . $item["id"] . '&id=' . $nectar_id) .
							'"><img src="../../images/move_down.gif" border="0" alt="Move Down"></a>' .
							'<a	href="' . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . '?action=item_moveup&item_id=' . $item["id"] .	'&id=' . $nectar_id) .
							'"><img src="../../images/move_up.gif" border="0" alt="Move Up"></a>' . '</td>';
			$form_data .= '<td align="right"><a href="' . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . '?action=item_remove&item_id=' . $item["id"] .	'&id=' . $nectar_id) .
							'"><img src="../../images/delete_icon.gif" border="0" width="10" height="10" alt="Delete"></a>' . '</td></tr>';
			print $form_data;
		}
	} else {
		print "<tr><td><em>No Report Items</em></td></tr>\n";
	}
}

function nectar() {
	global $colors, $config, $item_rows, $nectar_interval;
	global $nectar_actions, $attach_types;
	#print "<pre>Post: "; print_r($_POST); print "Get: "; print_r($_GET); print "Request: ";  print_r($_REQUEST);  print "Session: ";  print_r($_SESSION); print "</pre>";
	include_once($config["base_path"]."/plugins/nectar/nectar_functions.php");

	/* ================= input validation ================= */
	input_validate_input_number(get_request_var_request("page"));
	input_validate_input_number(get_request_var_request("status"));
	input_validate_input_number(get_request_var_request("rows"));
	/* ==================================================== */

	/* clean up search string */
	if (isset($_REQUEST["filter"])) {
		$_REQUEST["filter"] = sanitize_search_string(get_request_var("filter"));
	}

	/* clean up sort_column string */
	if (isset($_REQUEST["sort_column"])) {
		$_REQUEST["sort_column"] = sanitize_search_string(get_request_var("sort_column"));
	}

	/* clean up sort_direction string */
	if (isset($_REQUEST["sort_direction"])) {
		$_REQUEST["sort_direction"] = sanitize_search_string(get_request_var("sort_direction"));
	}

	/* if the user pushed the 'clear' button */
	if (isset($_REQUEST["clear_x"])) {
		kill_session_var("sess_nectar_current_page");
		kill_session_var("sess_nectar_filter");
		kill_session_var("sess_nectar_sort_column");
		kill_session_var("sess_nectar_sort_direction");
		kill_session_var("sess_status");
		kill_session_var("sess_rows");

		unset($_REQUEST["page"]);
		unset($_REQUEST["filter"]);
		unset($_REQUEST["sort_column"]);
		unset($_REQUEST["sort_direction"]);
		unset($_REQUEST["status"]);
		unset($_REQUEST["rows"]);
	}

	if ((!empty($_SESSION["sess_status"])) && (!empty($_REQUEST["status"]))) {
		if ($_SESSION["sess_status"] != $_REQUEST["status"]) {
			$_REQUEST["page"] = 1;
		}
	}

	/* remember these search fields in session vars so we don't have to keep passing them around */
	load_current_session_value("page", "sess_nectar_current_page", "1");
	load_current_session_value("filter", "sess_nectar_filter", "");
	load_current_session_value("sort_column", "sess_nectar_sort_column", "name");
	load_current_session_value("sort_direction", "sess_nectar_sort_direction", "ASC");
	load_current_session_value("status", "sess_nectar_status", "-1");
	load_current_session_value("rows", "sess_rows", read_config_option("num_rows_device"));

	/* if the number of rows is -1, set it to the default */
	if ($_REQUEST["rows"] == -1) {
		$_REQUEST["rows"] = read_config_option("num_rows_device");
	}

	print ('<form name="nectar" action="nectar' . ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN ? '':'_user') . '.php" method="get">');

	html_start_box("<strong>Reports</strong>" . ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN ? " [ Administrator Level ]":" [ User Level ]"), "100%", $colors["header"], "3", "center", ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=edit&tab=details");

	$filter_html = '<tr bgcolor="#' . $colors["panel"] . '">
					<td>
					<table width="100%" cellpadding="0" cellspacing="0">
						<tr>
							<td nowrap style="white-space: nowrap;" width="50">
								Search:&nbsp;
							</td>
							<td width="1"><input type="text" name="filter" size="40" value="' . get_request_var_request("filter") . '">
							</td>
							<td nowrap style="white-space: nowrap;" width="50">
								&nbsp;Status:&nbsp;
							</td>
							<td width="1">
								<select name="status" onChange="applyViewNectarFilterChange(document.nectar)">
									<option value="-1"';

	if (get_request_var_request("status") == "-1") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Any</option>					<option value="-2"';
	if (get_request_var_request("status") == "-2") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Enabled</option>				<option value="-3"';
	if (get_request_var_request("status") == "-3") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Disabled</option>';
	$filter_html .= '					</select>
							</td>
							<td nowrap style="white-space: nowrap;" width="50">
								&nbsp;Rows:&nbsp;
							</td>
							<td width="1">
								<select name="rows" onChange="applyViewNectarFilterChange(document.nectar)">
								<option value="-1"';
	if (get_request_var_request("rows") == "-1") {
		$filter_html .= 'selected';
	}
	$filter_html .= '>Default</option>';
	if (sizeof($item_rows) > 0) {
		foreach ($item_rows as $key => $value) {
			$filter_html .= "<option value='" . $key . "'";
			if (get_request_var_request("rows") == $key) {
				$filter_html .= " selected";
			}
			$filter_html .= ">" . $value . "</option>\n";
		}
	}
	$filter_html .= '					</select>
							</td>
							<td nowrap style="white-space: nowrap;">&nbsp;
								<input type="submit" value="Go" name="go">
								<input type="submit" value="Clear" name="clear_x">
							</td>
						</tr>
					</table>
					</td>
					<td><input type="hidden" name="page" value="1"></td>
				</tr>';

	print $filter_html;

	html_end_box(TRUE);
	print "</form>\n";

	/* form the 'where' clause for our main sql query */
	if (strlen(get_request_var_request("filter"))) {
		$sql_where = "WHERE (plugin_nectar.name LIKE '%%" . get_request_var_request("filter") . "%%')";
	}else{
		$sql_where = "";
	}

	if (get_request_var_request("status") == "-1") {
		/* Show all items */
	}elseif (get_request_var_request("status") == "-2") {
		$sql_where .= (strlen($sql_where) ? " AND plugin_nectar.enabled='on'" : " WHERE plugin_nectar.enabled='on'");
	}elseif (get_request_var_request("status") == "-3") {
		$sql_where .= (strlen($sql_where) ? " AND plugin_nectar.enabled=''" : " WHERE plugin_nectar.enabled=''");
	}

	/* account for permissions */
	if ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN) {
		$sql_join = "LEFT JOIN user_auth ON user_auth.id=plugin_nectar.user_id";
	}else{
		$sql_where .= (strlen($sql_where) ? " AND ":"WHERE ") . " user_auth.id=" . $_SESSION["sess_user_id"];
		$sql_join = "INNER JOIN user_auth ON user_auth.id=plugin_nectar.user_id";
	}

	$total_rows = db_fetch_cell("SELECT
		COUNT(plugin_nectar.id)
		FROM plugin_nectar
		$sql_join
		$sql_where");

	$nectar_list = db_fetch_assoc("SELECT
		user_auth.full_name,
		plugin_nectar.*,
		CONCAT_WS('', intrvl, ' ', count, ' ', offset, '') AS cint
		FROM plugin_nectar
		$sql_join
		$sql_where
		ORDER BY " .
		get_request_var_request("sort_column") . " " .
		get_request_var_request("sort_direction") .
		" LIMIT " . (get_request_var_request("rows")*(get_request_var_request("page")-1)) . "," . get_request_var_request("rows"));

	if ($total_rows > 0) {
		/* generate page list */
		$url_page_select = get_page_list(get_request_var_request("page"), MAX_DISPLAY_PAGES, get_request_var_request("rows"), $total_rows, ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?filter=" . get_request_var_request("filter"));

		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='12'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='left' class='textHeaderDark'>
							<strong>&lt;&lt; "; if (get_request_var_request("page") > 1) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?filter=" . get_request_var_request("filter") . "&status=" . get_request_var_request("status") . "&page=" . (get_request_var_request("page")-1)) . "'>"; } $nav .= "Previous"; if (get_request_var_request("page") > 1) { $nav .= "</a>"; } $nav .= "</strong>
						</td>\n
						<td align='center' class='textHeaderDark'>
							 Showing Rows " . ((get_request_var_request("rows")*(get_request_var_request("page")-1))+1) . " to " . ((($total_rows < get_request_var_request("rows")) || ($total_rows < (get_request_var_request("rows")*get_request_var_request("page")))) ? $total_rows : (get_request_var_request("rows") * get_request_var_request("page"))) . " of $total_rows [$url_page_select]
						</td>\n
						<td align='right' class='textHeaderDark'>
							<strong>"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "<a class='linkOverDark' href='" . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?filter=" . get_request_var_request("filter") . "&status=" . get_request_var_request("status") . "&page=" . (get_request_var_request("page")+1)) . "'>"; } $nav .= "Next"; if ((get_request_var_request("page") * get_request_var_request("rows")) < $total_rows) { $nav .= "</a>"; } $nav .= " &gt;&gt;</strong>
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";
	}else{
		$nav = "<tr bgcolor='#" . $colors["header"] . "'>
			<td colspan='12'>
				<table width='100%' cellspacing='0' cellpadding='0' border='0'>
					<tr>
						<td align='center' class='textHeaderDark'>
							No Rows Found
						</td>\n
					</tr>
				</table>
			</td>
		</tr>\n";
	}

	/* print checkbox form for validation */
	print "<form name='chk' method='post' action='nectar.php'>\n";
	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print $nav;

	if ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN) {
		$display_text = array(
			"name"            => array("Report Title", "ASC"),
			"full_name"       => array("Owner", "ASC"),
			"cint"            => array("Frequency", "ASC"),
			"lastsent"        => array("Last Run", "ASC"),
			"mailtime"        => array("Next Run", "ASC"),
			"from_name"       => array("From", "ASC"),
			"nosort"          => array("To", "ASC"),
			"attachment_type" => array("Type", "ASC"),
			"enabled"         => array("Enabled", "ASC"),
		);
	}else{
		$display_text = array(
			"name"            => array("Report Title", "ASC"),
			"cint"            => array("Frequency", "ASC"),
			"lastsent"        => array("Last Run", "ASC"),
			"mailtime"        => array("Next Run", "ASC"),
			"from_name"       => array("From", "ASC"),
			"nosort"          => array("To", "ASC"),
			"attachment_type" => array("Type", "ASC"),
			"enabled"         => array("Enabled", "ASC"),
		);
	}

	html_header_sort_checkbox($display_text, get_request_var_request("sort_column"), get_request_var_request("sort_direction"), false);

	$i = 0;
	if (sizeof($nectar_list) > 0) {
		$date_format = nectar_date_time_format();

		foreach ($nectar_list as $nectar) {
			form_alternate_row_color($colors["alternate"], $colors["light"], $i, 'line' . $nectar["id"]); $i++;

			form_selectable_cell("<a style='white-space:nowrap;' class='linkEditMain' href='" . htmlspecialchars(($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php") . "?action=edit&tab=details&id=" . $nectar["id"] . "&page=1 ' title='" . $nectar["name"]) . "'>" . ((get_request_var_request("filter") != "") ? preg_replace("/(" . preg_quote(get_request_var_request("filter")) . ")/i", "<span style='background-color: #F8D93D;'>\\1</span>", title_trim($nectar["name"], read_config_option("max_title_graph"))) : title_trim($nectar["name"], read_config_option("max_title_graph"))) . "</a>", $nectar["id"]);
			if ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN) form_selectable_cell($nectar["full_name"], $nectar["id"]);

			$interval = "Every " . $nectar["count"] . " " . $nectar_interval[$nectar["intrvl"]];

			form_selectable_cell($interval, $nectar["id"]);
			form_selectable_cell(($nectar["lastsent"] == 0) ? 'Never' : date($date_format, $nectar["lastsent"]), $nectar["lastsent"]);
			form_selectable_cell(date($date_format, $nectar["mailtime"]), $nectar["id"]);
			form_selectable_cell($nectar["from_name"], $nectar["id"]);
			form_selectable_cell((substr_count($nectar["email"], ",") ? "Multiple": $nectar["email"]), $nectar["id"]);
			form_selectable_cell((isset($attach_types{$nectar["attachment_type"]})) ? $attach_types{$nectar["attachment_type"]} : "Invalid", $nectar["id"]);
			form_selectable_cell($nectar["enabled"] ? "Enabled" : "Disabled", $nectar["id"]);
			form_checkbox_cell($nectar["name"], $nectar["id"]);

			form_end_row();
		}
		print $nav;
	}else{
		print "<tr><td><em>No Reports</em></td></tr>\n";
	}
	html_end_box(false);

	/* draw the dropdown containing a list of available actions for this form */
	nectar_actions_dropdown($nectar_actions);

	print "</form>\n";
	?>
	<script type="text/javascript">
	<!--
	function applyViewNectarFilterChange(objForm) {
		strURL = '<?php print ($_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN ? "nectar_user.php":"nectar.php");?>?status=' + objForm.status.value;
		strURL = strURL + '&rows=' + objForm.rows.value;
		strURL = strURL + '&filter=' + objForm.filter.value;
		document.location = strURL;
	}
	-->
	</script>
	<?php
}

function nectar_actions_dropdown($actions_array) {
	global $config;

	?>
	<table align='center' width='100%'>
		<tr>
			<td width='1' valign='top'>
				<img src='<?php echo $config['url_path']; ?>images/arrow.gif' alt='' align='absmiddle'>&nbsp;
			</td>
			<td align='right'>
				Choose an action:
				<?php form_dropdown("drp_action",$actions_array,"","","1","","");?>
			</td>
			<td width='1' align='right'>
				<input type='submit' name='go' value='Go'>
			</td>
		</tr>
	</table>

	<input type='hidden' name='action' value='actions'>
	<?php
}

/* nectar_save_button - draws a (save|create) and cancel button at the bottom of
     an html edit form
   @arg $cancel_url - the url to go to when the user clicks 'cancel'
   @arg $force_type - if specified, will force the 'action' button to be either
     'save' or 'create'. otherwise this field should be properly auto-detected */
function nectar_save_button($cancel_url, $force_type = "", $key_field = "id") {
	global $config;

	if (empty($force_type)) {
		if (empty($_GET[$key_field])) {
			$value = "Create";
		}else{
			$value = "Save";
		}
	}elseif ($force_type == "save") {
		$value = "Save";
	}elseif ($force_type == "create") {
		$value = "Create";
	}
	?>
	<script type="text/javascript">
	<!--
	function returnTo(location) {
		document.location = location;
	}
	-->
	</script>
	<table align='center' width='100%' style='background-color: #ffffff; border: 1px solid #bbbbbb;'>
		<tr>
			<td bgcolor="#f5f5f5" align="right">
				<input type='hidden' name='action' value='save'>
				<input type='button' onClick='returnTo("<?php print $cancel_url;?>")' value='Cancel'>
				<input type='submit' value='<?php print $value;?>'>
			</td>
		</tr>
	</table>
	</form>
	<?php
}

?>

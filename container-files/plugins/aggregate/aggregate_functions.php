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


/**
 * aggregate_error_handler	- PHP error handler
 * @param int $errno		- error id
 * @param string $errmsg	- error message
 * @param string $filename	- file name
 * @param int $linenum		- line of error
 * @param array $vars		- additional variables
 */
function aggregate_error_handler($errno, $errmsg, $filename, $linenum, $vars) {

	$errno = $errno & error_reporting();
	# return if error handling disabled by @
	if($errno == 0) return;
	# define constants not available with PHP 4
	if(!defined('E_STRICT'))            define('E_STRICT', 2048);
	if(!defined('E_RECOVERABLE_ERROR')) define('E_RECOVERABLE_ERROR', 4096);

	if (read_config_option("log_verbosity") >= AGGREGATE_LOG_DEBUG) {
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
		E_STRICT            => 'Runtime Notice',
		E_RECOVERABLE_ERROR => 'Catchable Fatal Error'
		);

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, "date_default_timezone")) return;
		if (substr_count($errmsg, "Only variables")) return;

		/* log the error to the Cacti log */
		aggregate_log("PROGERR: " . $err, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
		print("PROGERR: " . $err . "<br><pre>");# print_r($vars); print("</pre>");

		# backtrace, if available
		if (function_exists('debug_backtrace')) {
			//print "backtrace:\n";
			$backtrace = debug_backtrace();
			array_shift($backtrace);
			foreach($backtrace as $i=>$l) {
				print "[$i] in function <b>{$l['class']}{$l['type']}{$l['function']}</b>";
				if($l['file']) print " in <b>{$l['file']}</b>";
				if($l['line']) print " on line <b>{$l['line']}</b>";
				print "\n";
			}
		}
		if (isset($GLOBALS['error_fatal'])) {
			if($GLOBALS['error_fatal'] & $errno) die('fatal');
		}
	}

	return;
}

/**
 * get_next_sequence 			- returns the next available sequence id
 * @param int $id 				- the current id
 * @param string $field 		- the field name that contains the target id
 * @param string $table_name 	- the table name that contains the target id
 * @param string $group_query 	- an SQL "where" clause to limit the query
 + @returns int					- the next available sequence id
 */
function get_next_sequence($id, $field, $table_name, $group_query, $key_field="id") {
	aggregate_log(__FUNCTION__ . "  called. Id: " . $id . " field: " . $field . " table: " . $table_name, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	if (empty($id)) {
		$data = db_fetch_row("select max($field)+1 as seq from $table_name where $group_query");

		if ($data["seq"] == "") {
			return 1;
		}else{
			return $data["seq"];
		}
	}else{
		$data = db_fetch_row("select $field from $table_name where $key_field = id");
		return $data[$field];
	}
}


/**
 * find out, if this is a pure STACKed graph
 * @param int $_local_graph_id	- graph to be examined
 * @return bool					- true, if pure STACKed graph
 */
function aggregate_is_pure_stacked_graph($_local_graph_id) {
	aggregate_log(__FUNCTION__ . " local_graph: " . $_local_graph_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	$_pure_stacked_graph = false;

	if (!empty($_local_graph_id)) {
		# fetch all AREA graph items
		$_count = db_fetch_cell("SELECT COUNT(id) " .
					"FROM graph_templates_item " .
					"WHERE graph_templates_item.local_graph_id=$_local_graph_id " .
					"AND graph_templates_item.graph_type_id IN " . 
					"(" . GRAPH_ITEM_TYPE_AREA .
					"," . GRAPH_ITEM_TYPE_LINE1 .
					"," . GRAPH_ITEM_TYPE_LINE2 .
					"," . GRAPH_ITEM_TYPE_LINE3 .
					")");
		aggregate_log(__FUNCTION__ . " #AREA/LINEx items: " . $_count, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
		/* if there's at least one AREA item, this is NOT a pure LINEx graph
		 * if there's NO AREA item, this IS a pure LINEx graph
		 * in case we find STACKs, there must be at least one AREA as well, or the graph itself is malformed
		 * this would fail on a PURE GPRINT/HRULE/VRULE graph as well, but that is malformed, too
		 */
		$_pure_stacked_graph = ($_count == 0);

	}

	return $_pure_stacked_graph;
}


/**
 * find out, if graph has a STACK
 * @param int $_local_graph_id	- graph to be examined
 * @return bool					- true, if pure STACKed graph
 */
function aggregate_is_stacked_graph($_local_graph_id) {
	aggregate_log(__FUNCTION__ . " local_graph: " . $_local_graph_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	$_pure_stacked_graph = false;

	if (!empty($_local_graph_id)) {
		# fetch all AREA graph items
		$_count = db_fetch_cell("SELECT COUNT(id) " .
					"FROM graph_templates_item " .
					"WHERE graph_templates_item.local_graph_id=$_local_graph_id " .
					"AND graph_templates_item.graph_type_id =" . GRAPH_ITEM_TYPE_STACK);
		aggregate_log(__FUNCTION__ . " #AREA/LINEx items: " . $_count, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
		/* if there's at least one AREA item, this is NOT a pure LINEx graph
		 * if there's NO AREA item, this IS a pure LINEx graph
		 * in case we find STACKs, there must be at least one AREA as well, or the graph itself is malformed
		 * this would fail on a PURE GPRINT/HRULE/VRULE graph as well, but that is malformed, too
		 */
		$_stacked_graph = ($_count > 0);

	}

	return $_stacked_graph;
}


/**
 * aggregate_convert_to_stack	- Convert graph_type to STACK, if appropriate
 *
 * @param int $_graph_item_type	- current graph_item_type
 * @param int $_old_graph_id		- local graph id of graph to be inserted
 * @param int $_graph_no			- no of graph
 * @param int $_graph_item_sequence	- no of next item
 */
function aggregate_convert_to_stack($_graph_item_type, $_old_graph_id, $_graph_no, $_graph_item_sequence) {
	# globals used
	global $graph_item_types;
	aggregate_log(__FUNCTION__ . "  called: Item type:" . $graph_item_types{$_graph_item_type} . " graph: " . $_graph_no . " item: " . $_graph_item_sequence, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	if ($_graph_no === 0) {
		/* this one converts the first graph only
		 * we must make sure to have at least one AREA, else AREA/STACK would be malformed
		 * so we have to detect, whether an AREA already exists or not
		 * doing so for each call of this function is an overhead, once per whole graph would be ok
		 * but this way it's easier to read
		 */
		$_pure_linex_graph = aggregate_detect_linex_graph_type($_old_graph_id);
	}

	if (ereg("(LINE[123])", $graph_item_types{$_graph_item_type})) {
		if ($_graph_no === 0) {
			/* convert LINEx statements to AREA on the first graph */
			$_graph_item_type = GRAPH_ITEM_TYPE_AREA;
		} else {
			/* convert LINEx statements to STACK */
			$_graph_item_type = GRAPH_ITEM_TYPE_STACK;
		}
	} elseif (ereg("(AREA)", $graph_item_types{$_graph_item_type}) && !($_graph_no === 0)) {
		/* convert AREA statements, but not for the first graph
		 * this is required, when graphing a "negative" AREA */
		$_graph_item_type = GRAPH_ITEM_TYPE_STACK;
	}


	aggregate_log(__FUNCTION__ . "  return: Item type:" . $graph_item_types{$_graph_item_type} . " graph: " . $_graph_no, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	return $_graph_item_type;

}

/**
 * Convert AREA/STACK graph_type to the one provided
 *
 * @param int $_graph_item_type	- current graph_item_type
 * @param int $_graph_type		- new graph_item_type
 * @return int					- new graph_item_type
 */
function aggregate_convert_graph_type($_graph_item_type, $_graph_type) {
	# globals used
	global $graph_item_types;
	aggregate_log(__FUNCTION__ . "  called: Item Type: " . $graph_item_types{$_graph_item_type} . " new item type: " . $_graph_type, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	if (ereg("(AREA|STACK)", $graph_item_types{$_graph_item_type})) {
		/* change AREA|STACK statements only */
		aggregate_log(__FUNCTION__ . "  return: Item type:" . $graph_item_types{$_graph_type} . " graph: " . $_graph_no, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
		return $_graph_type;
	} else {
		aggregate_log(__FUNCTION__ . "  return: Item type:" . $graph_item_types{$_graph_item_type} . " graph: " . $_graph_no, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
		return $_graph_item_type;
	}
}



function aggregate_conditional_convert_graph_type($_graph_id, $_old_type, $_new_type) {
	aggregate_log(__FUNCTION__ . "  called: graph: " . $_graph_id . " old item type: " . $_old_type . " new item type: " . $_new_type, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	if (!empty($_graph_id) && !empty($_old_type)) {
		/* fetch the first item of requested graph_type */
		$_graph_item_id = db_fetch_cell("SELECT id " .
					"FROM graph_templates_item " .
					"WHERE graph_templates_item.local_graph_id=$_graph_id " .
					"AND graph_templates_item.graph_type_id=" . $_old_type .
					" ORDER BY sequence LIMIT 0,1");
		/* and update it to the new graph_type */
		db_execute("UPDATE graph_templates_item SET graph_templates_item.graph_type_id=" . $_new_type . " WHERE graph_templates_item.id=" . $_graph_item_id);
	}
}


function aggregate_change_graph_type($graph_index, $old_graph_type, $new_graph_type) {
	aggregate_log(__FUNCTION__ . " called. Index " . $graph_index . " old type " . $old_graph_type . " Graph Type: " . $new_graph_type, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* LEGEND entries and xRULEs stay unchanged
	 * xRULEs honestly do not make much sense on an aggregated graph, though */
	switch ($old_graph_type) {
		case GRAPH_ITEM_TYPE_GPRINT:
		case GRAPH_ITEM_TYPE_COMMENT:
		case GRAPH_ITEM_TYPE_HRULE:
		case GRAPH_ITEM_TYPE_VRULE:
			return $old_graph_type;
			break;
	}

	/* this item is eligible to a type change */
	switch ($new_graph_type) {
		case AGGREGATE_GRAPH_TYPE_KEEP:
			/* keep entry as defined by the Graph */
			return $old_graph_type;
			break;
		case GRAPH_ITEM_TYPE_STACK:
			/* create an AREA/STACK graph
			 * pay attention to AREA handling!
			 * any AREA/STACKed graph needs a base AREA entry
			 * but e.g. a graph that prints on both negative and positive y-axis may hold two AREAs
			 * so it's a good idea to keep all AREA entries of the first aggregated elementary graph (index 0)*/
			if ($graph_index == 0 && $old_graph_type == GRAPH_ITEM_TYPE_AREA) {
				/* don't change (multi-)AREAs on the first graph */
				return $old_graph_type;
			} else {
				/* this is either
				 * - not the first graph, any item type:	convert to STACK
				 * - the first graph and a LINEx item: 		convert to STACK
				 */
				return GRAPH_ITEM_TYPE_STACK;
			}
			/* for a pure LINEx graph,
			 * this will result in a pure STACKed graph, without any AREA
			 * we will take care of this at the very end, after adding the last graph to the aggregate, during post-processing
			 */
			break;
		case GRAPH_ITEM_TYPE_LINE1:
		case GRAPH_ITEM_TYPE_LINE2:
		case GRAPH_ITEM_TYPE_LINE3:
			/* create a LINEx graph */
			return $new_graph_type;
			break;
	}
}


/**
 * draw_aggregate_graph_items_list	- draw graph item list
 *
 * @param int $_graph_id			- id of the graph for which the items shall be listed
 */
function draw_aggregate_graph_items_list($_graph_id) {
	global $colors, $config;
	aggregate_log(__FUNCTION__ . "  called. Id: " . $_graph_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	if (file_exists($config["include_path"] . "/global_arrays.php")) {
		include($config["include_path"] . "/global_arrays.php");
	} else {
		include($config["include_path"] . "/config_arrays.php");
	}

	#if (read_config_option("log_verbosity", TRUE) == POLLER_VERBOSITY_LOW) {
	#	aggregate_log("function: draw_aggregate_graph_items_list called. Id:" . $_graph_id, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
	#}

	# fetch graph items
	$item_list = db_fetch_assoc("SELECT " .
			"graph_templates_item.id, " .
			"graph_templates_item.text_format, " .
			"graph_templates_item.value, " .
			"graph_templates_item.hard_return, " .
			"graph_templates_item.graph_type_id, " .
			"graph_templates_item.consolidation_function_id, " .
			"cdef.name as cdef_name, " .
			"colors.hex " .
		"FROM graph_templates_item " .
		"LEFT JOIN cdef ON (cdef_id=cdef.id) " .
		"LEFT JOIN colors ON (color_id=colors.id) " .
		"WHERE graph_templates_item.local_graph_id=$_graph_id " .
		"ORDER BY graph_templates_item.sequence");

	# fetch color templates
	$color_templates = db_fetch_assoc("SELECT " .
		"color_template_id, " .
		"name " .
		"FROM plugin_aggregate_color_templates " .
		"ORDER BY name");

	# draw list of graph items
	html_start_box("<strong>Graph Template Items</strong>", "100%", $colors["header"], "3", "center", "");

	# print column header
	print "<tr bgcolor='#" . $colors["header_panel"] . "'>";
	DrawMatrixHeaderItem("Graph Item",$colors["header_text"],1);
	DrawMatrixHeaderItem("Data Source",$colors["header_text"],1);
	DrawMatrixHeaderItem("Graph Item Type",$colors["header_text"],1);
	DrawMatrixHeaderItem("CF Type",$colors["header_text"],1);
	DrawMatrixHeaderItem("Item Color",$colors["header_text"],2);
	DrawMatrixHeaderItem("Color Template",$colors["header_text"],1);
	DrawMatrixHeaderItem("Skip",$colors["header_text"],1);
	DrawMatrixHeaderItem("Total",$colors["header_text"],1);
	print "</tr>";

	$group_counter = 0; $_graph_type_name = ""; $i = 0;
	$alternate_color_1 = $colors["alternate"]; $alternate_color_2 = $colors["alternate"];

	if (sizeof($item_list) > 0) {
		foreach ($item_list as $item) {
			/* graph grouping display logic */
			$this_row_style = ""; $use_custom_row_color = false; $hard_return = "";

			if ($graph_item_types{$item["graph_type_id"]} != "GPRINT") {
				$this_row_style = "font-weight: bold;"; $use_custom_row_color = true;

				if ($group_counter % 2 == 0) {
					$alternate_color_1 = "EEEEEE";
					$alternate_color_2 = "EEEEEE";
					$custom_row_color = "D5D5D5";
				}else{
					$alternate_color_1 = $colors["alternate"];
					$alternate_color_2 = $colors["alternate"];
					$custom_row_color = "D2D6E7";
				}

				$group_counter++;
			}

			$_graph_type_name = $graph_item_types{$item["graph_type_id"]};

			/* alternating row color */
			if ($use_custom_row_color == false) {
				form_alternate_row_color($alternate_color_1,$alternate_color_2,$i);
			}else{
				print "<tr bgcolor='#$custom_row_color'>";
			}

			print "<td>";
			print "<strong>Item # " . ($i+1) . "</strong>";
			print "</td>\n";

			#if (empty($item["data_source_name"])) { $item["data_source_name"] = "No Task"; }

			switch (true) {
				case ereg("(AREA|STACK|GPRINT|LINE[123])", $_graph_type_name):
					$matrix_title = $item["text_format"];
					break;
				case ereg("(HRULE|VRULE)", $_graph_type_name):
					$matrix_title = "HRULE: " . $item["value"];
					break;
				case ereg("(COMMENT)", $_graph_type_name):
					$matrix_title = "COMMENT: " . $item["text_format"];
					break;
			}

			if ($item["hard_return"] == "on") {
				$hard_return = "<strong><font color=\"#FF0000\">&lt;HR&gt;</font></strong>";
			}

			# agg_# will denote the graph item to be skipped. Index starts at 1
			print "<td style='$this_row_style'>" . htmlspecialchars($matrix_title) . $hard_return . "</td>\n";
			print "<td style='$this_row_style'>" . $graph_item_types{$item["graph_type_id"]} . "</td>\n";
			print "<td style='$this_row_style'>" . $consolidation_functions{$item["consolidation_function_id"]} . "</td>\n";
			print "<td" . ((!empty($item["hex"])) ? " bgcolor='#" . $item["hex"] . "'" : "") . " width='1%'>&nbsp;</td>\n";
			print "<td style='$this_row_style'>" . $item["hex"] . "</td>\n";
			# dropdown for color templates
			print "<td>";
			if (!empty($item["hex"])) {
				print "<select name='agg_color_" . ($i+1) ."'>";
				print "<option value='0' selected>None</option>\n";
				html_create_list($color_templates, "name", "color_template_id", "None");
				print "</select>\n";
			}
			print "</td>";
			# select boxes for skipping unwanted items
			print "<td style='" . get_checkbox_style() ."' width='1%' align='center'>" .
			  "<input type='checkbox' style='margin: 0px;' name='agg_skip_" . ($i+1) . "' title=" . $item["text_format"] .">" .
			  "</td>";
			# select boxes for totalling items
			print "<td style='" . get_checkbox_style() ."' width='1%' align='center'>" .
			  "<input type='checkbox' style='margin: 0px;' name='agg_total_" . ($i+1) . "' title=" . $item["text_format"] .">" .
			  "</td>";

			print "</tr>";

			$i++;
		}
	}else{
		print "<tr bgcolor='#" . $colors["form_alternate2"] . "'><td colspan='7'><em>No Items</em></td></tr>";
	}

	html_end_box();
	form_hidden_box("item_no", sizeof($item_list), sizeof($item_list));

}

/**
 * duplicate_color_template				- duplicate color template
 *
 * @param int $_color_template_id		- id of the base color template
 * @param string $color_template_title	- title of the duplicated color template
 */
function duplicate_color_template($_color_template_id, $color_template_title) {
	aggregate_log(__FUNCTION__ . "  called. Color Template Id: " . $_color_template_id . " Title: " . $color_template_title, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* fetch data from table plugin_aggregate_color_templates */
	$color_template = db_fetch_row("select *
									from plugin_aggregate_color_templates
									where color_template_id=$_color_template_id");
	/* fetch data from table plugin_aggregate_color_template_items */
	$color_template_items = db_fetch_assoc("select *
									from plugin_aggregate_color_template_items
									where color_template_id=$_color_template_id");

	/* create new entry: plugin_aggregate_color_templates */
	$save["color_template_id"] = 0;
	/* substitute the title variable */
	$save["name"] = str_replace("<template_title>", $color_template["name"], $color_template_title);
	aggregate_log("function: duplicate_color_template called. Id:" . $_color_template_id . " Title: " . $color_template_title . " Replaced: " . $save["name"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
	$new_color_template_id = sql_save($save, "plugin_aggregate_color_templates", "color_template_id");
	unset($save);

	/* create new entry(s): plugin_aggregate_color_template_items */
	if (sizeof($color_template_items) > 0) {
		foreach ($color_template_items as $color_template_item) {
			$save["color_template_item_id"] = 0;
			$save["color_template_id"] = $new_color_template_id;
			$save["color_id"] = $color_template_item["color_id"];
			$save["sequence"] = $color_template_item["sequence"];
			aggregate_log("function: duplicate_color_template called. Id:" . $new_color_template_id . " Color: " . $save["color_id"] . " sequence: " . $save["sequence"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			$new_color_template_item_id = sql_save($save, "plugin_aggregate_color_template_items", "color_template_item_id");
			unset($save);
		}
	}
}


/**
 * aggregate_cdef_make0			- return the id of a "Make 0" cdef, create that cdef if necessary
 */
function aggregate_cdef_make0() {
	global $config;
	include_once($config['base_path'] . "/lib/cdef.php");

	aggregate_log(__FUNCTION__ . " called", true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	# magic name of new cdef
	$magic = "_MAKE 0";

	# search the "magic" cdef
	$cdef_id = db_fetch_cell("SELECT id FROM cdef WHERE name = '" . $magic . "'");
	if (isset($cdef_id) && $cdef_id > 0) {
		return $cdef_id;	# hoping, that nobody changed the cdef_items!
	}

	# create a new cdef entry
	$save["id"] = 0;
	$save["hash"] = get_hash_cdef(0);
	$save["name"] = $magic;
	# save the cdef itself
	$new_cdef_id = sql_save($save, "cdef");
	aggregate_log(__FUNCTION__ . " created new cdef: " . $new_cdef_id . " name: " . $new_cdef_name . " value: " . $new_cdef_text, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

	# create a new cdef item entry
	unset($save);
	$save["id"] = 0;
	$save["hash"] = get_hash_cdef(0, "cdef_item");
	$save["cdef_id"] = $new_cdef_id;
	$save["sequence"] = 1;
	$save["type"] = 6;		# this will be replaced by a define as soon as it exists for a pure text field
	$save["value"] = "CURRENT_DATA_SOURCE,0,*";
	# save the cdef item, there's only one!
	$cdef_item_id = sql_save($save, "cdef_items");
	aggregate_log(__FUNCTION__ . " created new cdef item: " . $cdef_item_id, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

	return $new_cdef_id;
}


/**
 * aggregate_cdef_totalling			- create a totalling CDEF, if need be
 * @param int $_new_graph_id		- id of new graph
 * @param int $_graph_item_sequence	- current graph item sequence
 * @param int $_total_type			- what type of totalling is required?
 */
function aggregate_cdef_totalling($_new_graph_id, $_graph_item_sequence, $_total_type) {
	global $config;
	include_once($config['base_path'] . "/lib/cdef.php");

	aggregate_log(__FUNCTION__ . " called. Working on Graph: " . $_new_graph_id . " sequence: " .  $_graph_item_sequence  . " totalling: " . $_total_type, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	# take graph item data for the totalling items
	if (!empty($_new_graph_id)) {
		$sql = "SELECT id, cdef_id " .
			"FROM graph_templates_item " .
			"WHERE local_graph_id=$_new_graph_id " .
			"AND sequence >= $_graph_item_sequence " .
			"ORDER BY sequence";
		aggregate_log("sql: " . $sql, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
		$graph_template_items = db_fetch_assoc($sql);
	}

	# now get the list of cdefs
	$sql = "SELECT id, name " .
				"FROM cdef " .
				"ORDER BY id";
	aggregate_log(__FUNCTION__ . " sql: " . $sql, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
	$_cdefs = db_fetch_assoc($sql); # index the cdefs by their id's
	$cdefs = array();
	# build cdefs array to allow for indexing on cdef_id
	foreach ($_cdefs as $_cdef) {
		$cdefs[$_cdef["id"]]["id"] = $_cdef["id"];
		$cdefs[$_cdef["id"]]["name"] = $_cdef["name"];
		$cdefs[$_cdef["id"]]["cdef_text"] = get_cdef($_cdef["id"]);
	}
	# add pseudo CDEF for CURRENT_DATA_SOURCE, in case CDEF=NONE
	# we then may apply the standard CDEF procedure to create a new CDEF
	$cdefs[0]["id"] = 0;
	$cdefs[0]["name"] = "Items";
	$cdefs[0]["cdef_text"] = "CURRENT_DATA_SOURCE";

	/* new CDEF(s) are required! */
	$num_items = sizeof($graph_template_items);
	if ($num_items > 0) {
		$i = 0;
		foreach ($graph_template_items as $graph_template_item) {
			# current cdef
			$cdef_id = $graph_template_item["cdef_id"];
			$cdef_name = $cdefs[$cdef_id]["name"];
			$cdef_text = $cdefs[$cdef_id]["cdef_text"];
			aggregate_log(__FUNCTION__ . " cdef id: " . $cdef_id . " name: " . $cdef_name . " value: " . $cdef_text, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			# new cdef
			$new_cdef_text = "INVALID";	# in case sth goes wrong
			switch ($_total_type) {
				case AGGREGATE_TOTAL_TYPE_SIMILAR:
					$new_cdef_text = str_replace("CURRENT_DATA_SOURCE", "SIMILAR_DATA_SOURCES_NODUPS", $cdef_text);
					break;
				case AGGREGATE_TOTAL_TYPE_ALL:
					$new_cdef_text = str_replace("CURRENT_DATA_SOURCE", "ALL_DATA_SOURCES_NODUPS", $cdef_text);
					break;
			}
			# is the new cdef already present?
			$new_cdef_id = "";
			reset($cdefs);
			foreach ($cdefs as $cdef) {
				aggregate_log(__FUNCTION__ . " verify matching cdef: " . $cdef["id"] . " on: " . $cdef["cdef_text"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				if ($cdef["cdef_text"] == $new_cdef_text) {
					$new_cdef_id = $cdef["id"];
					aggregate_log(__FUNCTION__ . " matching cdef: " . $new_cdef_id, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
					# leave on first match
					break;
				}
			}
			# in case, we have NO match
			if (empty($new_cdef_id)) {
				# create a new cdef entry
				$save["id"] = 0;
				$save["hash"] = get_hash_cdef(0);
				$new_cdef_name = "INVALID " . $cdef_name; # in case anything goes wrong
				switch ($_total_type) {
					case AGGREGATE_TOTAL_TYPE_SIMILAR:
						$new_cdef_name = "_AGGREGATE SIMILAR " . $cdef_name;
						break;
					case AGGREGATE_TOTAL_TYPE_ALL:
						$new_cdef_name = "_AGGREGATE ALL " . $cdef_name;
						break;
				}
				$save["name"] = $new_cdef_name;
				# save the cdef itself
				$new_cdef_id = sql_save($save, "cdef");
				aggregate_log(__FUNCTION__ . " created new cdef: " . $new_cdef_id . " name: " . $new_cdef_name . " value: " . $new_cdef_text, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				unset($save);

				# create a new cdef item entry
				$save["id"] = 0;
				$save["hash"] = get_hash_cdef(0, "cdef_item");
				$save["cdef_id"] = $new_cdef_id;
				$save["sequence"] = 1;
				$save["type"] = 6;		# this will be replaced by a define as soon as it exists for a pure text field
				$save["value"] = $new_cdef_text;
				# save the cdef item, there's only one!
				$cdef_item_id = sql_save($save, "cdef_items");
				aggregate_log(__FUNCTION__ . " created new cdef item: " . $cdef_item_id, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				# now extend the cdef array to learn the newly entered cdef for the next loop
				$cdefs[$new_cdef_id]["id"] = $new_cdef_id;
				$cdefs[$new_cdef_id]["name"] = $new_cdef_name;
				$cdefs[$new_cdef_id]["cdef_text"] = $new_cdef_text;
			}
			# now that we have a new cdef id, update record accordingly
			$sql = "UPDATE graph_templates_item SET cdef_id=$new_cdef_id WHERE id=" . $graph_template_item["id"];
			aggregate_log(__FUNCTION__ . " sql: " . $sql, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			$ok = db_execute($sql);
			aggregate_log(__FUNCTION__ . " updated new cdef id: " . $new_cdef_id . " for item: " . $graph_template_item["id"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
		}
	}
}


/** auto_hr			- set a new hr when items are skipped
 * @param array $s	- array of skipped items
 * @param array $h	- array of items with HR
 * returns array	- array with new HR markers
 */
function auto_hr($s, $h) {
	aggregate_log(__FUNCTION__ . "  called", true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	# start at end of array, both arrays are from 1 .. count(array)
	$i = count($h);
	# make sure, that last item always has a HR, even if template does not have any
	$h[$i] = true;
	do {
		# if skipped item has a HR
		if (isset($s[$i]) && ($s[$i] > 0) && $h[$i]) {
			# set previous item (if any) to HR
			if (isset($h[$i-1])) $h[$i-1] = $h[$i];
		}
	} while($i-- > 0);
	return $h;
}

/** auto_title					- generate a title suggested to the user
 * @param int $_local_graph_id	- the id of the graph stanza
 * returns string				- the title
 */
function auto_title($_local_graph_id) {
	aggregate_log(__FUNCTION__ . "  called. Local Graph Id: " . $_local_graph_id, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	# apply given graph title, but drop host and query variables
	$graph_title = "Aggregate ";
	$graph_title .= db_fetch_cell("select title from graph_templates_graph where local_graph_id=$_local_graph_id");
	aggregate_log("title:" . $graph_title, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
	# remove all "- |query_*|" occurences
	$pattern = "/-?\s+\|query_\w+\|/";
	$graph_title = preg_replace($pattern, "", $graph_title);
	aggregate_log("title:" . $graph_title, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
	# remove all "- |host_*|" occurences
	$pattern = "/-?\s+\|host_\w+\|/";
	$graph_title = preg_replace($pattern, "", $graph_title);
	aggregate_log("title:" . $graph_title, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
	return $graph_title;
}


/* aggregate_log - logs a string to Cacti's log file or optionally to the browser
 @arg $string - the string to append to the log file
 @arg $output - (bool) whether to output the log line to the browser using pring() or not
 @arg $environ - (string) tell's from where the script was called from */
function aggregate_log($string, $output = false, $environ="AGGREGATE", $level=POLLER_VERBOSITY_NONE) {
	# if current verbosity >= level of current message, print it
	if (AGGREGATE_DEBUG >= $level) {
		cacti_log($string, $output, $environ);
	}
}

?>

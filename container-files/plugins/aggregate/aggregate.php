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


/** aggregate_graphs_create_graph	- create a new graph based on an existing one
 * 									  take all basic graph definitions from this one, but omit graph items
 * 									  wipe out host_id and graph_template_id
 * @param int $_local_graph_id		- id of an already existing graph
 * @param string $_graph_title		- title for new graph
 * @return int						- id of the new graph
 *  */
function aggregate_graphs_create_graph($_local_graph_id, $_graph_title) {
	global $config, $struct_graph, $struct_graph_item;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	aggregate_log(__FUNCTION__ . " local_graph: " . $_local_graph_id . " graph title: " . $_graph_title, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	if (!empty($_local_graph_id)) {
		$local_graph = db_fetch_row("select * from graph_local where id=$_local_graph_id");
		$graph_template_graph = db_fetch_row("select * from graph_templates_graph where local_graph_id=$_local_graph_id");

		/* create new entry: graph_local */
		$local_graph["id"] 					= 0;
		$local_graph["graph_template_id"] 	= 0; 	# no templating
		$local_graph["host_id"] 			= 0;  	# no host to be referred to
		$local_graph["snmp_query_id"] 		= 0;	# no templating
		$local_graph["snmp_index"] 			= '';	# no templating, may hold string data
		$local_graph_id 					= sql_save($local_graph, "graph_local");

		/* create new entry: graph_templates_graph */
		$graph_template_graph["id"] 							= 0;
		$graph_template_graph["local_graph_id"] 				= (isset($local_graph_id) ? $local_graph_id : 0);
		$graph_template_graph["local_graph_template_graph_id"] 	= 0; 	# no templating
		$graph_template_graph["graph_template_id"] 				= 0; 	# no templating
		$graph_template_graph["title"] 							= $_graph_title;
		$graph_templates_graph_id 								= sql_save($graph_template_graph, "graph_templates_graph");

		# update title cache
		if (!empty($_local_graph_id)) {
			update_graph_title_cache($local_graph_id);
		}
	}

	/* restore original error handler */
	restore_error_handler();

	# return the id of the newly inserted graph
	return $local_graph_id;

}

/** aggregate_graphs_insert_graph_items	- inserts all graph items of an existing graph
 * @param int $_new_graph_id			- id of the new graph
 * @param int $_old_graph_id			- id of the old graph
 * @param int $_skip					- graph items to be skipped, array starts at 1
 * @param bool $_hr						- graph items that should have a <HR>
 * @param int $_graph_item_sequence		- sequence number of the next graph item to be inserted
 * @param int $_selected_graph_index	- index of current graph to be inserted
 * @param array $_color_templates		- the color templates to be used
 * @param int $_graph_type				- conversion to AREA/STACK or LINE required?
 * @param int $_gprint_prefix			- prefix for the legend line
 * @param int $_total					- Totalling: graph items AND/OR legend
 * @param int $_total_type				- Totalling: SIMILAR/ALL data sources
 * @return int							- id of the next graph item to be inserted
 *  */
function aggregate_graphs_insert_graph_items($_new_graph_id, $_old_graph_id, $_skip,
$_graph_item_sequence, $_selected_graph_index, $_color_templates,
$_graph_type, $_gprint_prefix, $_total, $_total_type) {

	global $struct_graph_item, $graph_item_types, $config;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	aggregate_log(__FUNCTION__ . " called. Insert old graph " . $_old_graph_id . " into new graph " . $_new_graph_id
	. " at sequence: " . $_graph_item_sequence  . " Graph_No: " . $_selected_graph_index
	. " Type Action: " . $_graph_type, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);
	aggregate_log(__FUNCTION__ . " skipping: " . serialize($_skip), true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

	# take graph item data from old one
	if (!empty($_old_graph_id)) {
		$graph_items = db_fetch_assoc("select * from graph_templates_item where local_graph_id=$_old_graph_id ORDER BY SEQUENCE");
		$graph_local = db_fetch_row("select host_id, graph_template_id, snmp_query_id, snmp_index from graph_local where id=" . $_old_graph_id);
	}

	/* create new entry(s): graph_templates_item */
	$num_items = sizeof($graph_items);
	if ($num_items > 0) {

		# take care of items having a HR that shall be skipped
		$i = 0;
		for ($i; $i < $num_items; $i++) {
			# remember existing hard returns (array must start at 1 to match $skipped_items
			$_hr[$i+1] = ($graph_items[$i]["hard_return"] != "");
		}
		# move "skipped hard returns" to previous graph item
		$_hr = auto_hr($_skip, $_hr);

		# next entry will have to have a prepended text format
		$prepend = true;
		$skip_graph = false;
		$make0_cdef = aggregate_cdef_make0();
		$i = 0;

		foreach ($graph_items as $graph_item) {
			# loop starts at 0, but $_skip starts at 1, so increment before comparing
			$i++;
			# go ahead, if this graph item has to be skipped
			if (isset($_skip[$i])) {
				continue;
			}

			if ($_total == AGGREGATE_TOTAL_ONLY) {
				# if we only need the totalling legend, ...
				if (($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_GPRINT) || ($graph_item["graph_type_id"] == GRAPH_ITEM_TYPE_COMMENT)) {
					# and this is a legend entry (GPRINT, COMMENT), skip
					continue;
				} else {
					# this is a graph entry, remove text to make it disappear
					# do NOT skip!
					# we need this entry as a DEF
					# and as long as cacti does not provide for a "pure DEF" graph item type
					# we need this workaround
					$graph_item["text_format"] = "";
					# make sure, that this entry does not have a HR,
					# else a single colored mark will be drawn
					$graph_item["hard_return"] = "";
					$_hr[$i] = "";
					# make sure, that data of this item will be suppressed: make 0!
					$graph_item["cdef_id"] = $make0_cdef;
				}
			}
			
			# use all data from "old" graph ...
			$save = $graph_item;

			# now it's time for some "special purpose" processing
			# selected fields will need special treatment

			# take care of color changes only if not set to None
			if (isset($_color_templates[$i])) {
				if ($_color_templates[$i] > 0) {
					# get the size of the color templates array
					# if number of colored items exceed array size, use round robin
					$num_colors = db_fetch_cell("SELECT count(color_id) " .
							"FROM plugin_aggregate_color_template_items " .
							"WHERE color_template_id=" . $_color_templates[$i]);

					# templating required, get color for current graph item
					$sql = "SELECT color_id " .
							"FROM plugin_aggregate_color_template_items " .
							"WHERE color_template_id = " . $_color_templates[$i] .
							" LIMIT " . ($_selected_graph_index % $num_colors) . ",1";
					$save["color_id"] = db_fetch_cell($sql);
				} else {
					/* set a color even if no color templating is required */
					$save["color_id"] = $graph_item["color_id"];
				}
			} /* else: no color templating defined, e.g. GPRINT entry */

			# take care of the graph_item_type
			
			/* change graph types, if requested */
			$save["graph_type_id"] = aggregate_change_graph_type(
										$_selected_graph_index,
										$graph_item["graph_type_id"],
										$_graph_type);
			
			#			switch ($_graph_type) {
			#				case AGGREGATE_GRAPH_TYPE_KEEP: /* keep entry as defined by the Graph */
			#					break;
			#				case GRAPH_ITEM_TYPE_STACK: /* Change graph type to create an AREA/STACK graph */
			#					$save["graph_type_id"] = aggregate_convert_to_stack($save["graph_type_id"], $_old_graph_id, $_selected_graph_index, $_graph_item_sequence);
			#					break;
			#				case GRAPH_ITEM_TYPE_LINE1: /* Change AREA/STACK graph type to create a LINE1 graph */
			#					$save["graph_type_id"] = aggregate_convert_graph_type($save["graph_type_id"], GRAPH_ITEM_TYPE_LINE1);
			#				case GRAPH_ITEM_TYPE_LINE2: /* Change AREA/STACK graph type to create a LINE2 graph */
			#					$save["graph_type_id"] = aggregate_convert_graph_type($save["graph_type_id"], GRAPH_ITEM_TYPE_LINE2);
			#				case GRAPH_ITEM_TYPE_LINE3: /* Change AREA/STACK graph type to create a LINE3 graph */
			#					$save["graph_type_id"] = aggregate_convert_graph_type($save["graph_type_id"], GRAPH_ITEM_TYPE_LINE3);
			#					break;
			#			}


			# new item text format required?
			if ($prepend && ($_total_type == AGGREGATE_TOTAL_TYPE_ALL)) {
				# pointless to add any data source item name here, cause ALL are totaled
				$save["text_format"] = $_gprint_prefix;
				# no more prepending until next line break is encountered
				$prepend = false;
			} elseif ($prepend && (strlen($save["text_format"]) > 0) && (strlen($_gprint_prefix) > 0)) {
			#if ($prepend && (strlen($save["text_format"]) > 0) && (strlen($_gprint_prefix) > 0)) {
				$save["text_format"] = substitute_host_data($_gprint_prefix . " " . $save["text_format"], "|", "|", $graph_local["host_id"]);
				aggregate_log(__FUNCTION__ . " substituted:" . $save["text_format"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

				/* if this is a data query graph type, try to substitute */
				if ($graph_local["snmp_query_id"] > 0 && strlen($graph_local["snmp_index"]) > 0) {
					$save["text_format"] = substitute_snmp_query_data($save["text_format"], $graph_local["host_id"], $graph_local["snmp_query_id"], $graph_local["snmp_index"], read_config_option("max_data_query_field_length"));
					aggregate_log(__FUNCTION__ . " substituted:" . $save["text_format"] . " for " . $graph_local["host_id"] . "," . $graph_local["snmp_query_id"] . "," . $graph_local["snmp_index"], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				}

				# no more prepending until next line break is encountered
				$prepend = false;
			}
			
			# <HR> wanted?
			if (isset($_hr[$i]) && $_hr[$i] > 0) {
				$save["hard_return"] = "on";
			}
			# if this item defines a line break, remember to prepend next line
			$prepend = ($save["hard_return"] == "on");

			# provide new sequence number
			$save["sequence"] = $_graph_item_sequence;
			aggregate_log(__FUNCTION__ . "  hard return: " . $save["hard_return"] . " sequence: " . $_graph_item_sequence, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

			$save["id"] 							= 0;
			$save["local_graph_template_item_id"]	= 0;	# disconnect this graph item from the graph template item
			$save["local_graph_id"] 				= (isset($_new_graph_id) ? $_new_graph_id : 0);
			$save["graph_template_id"] 				= 0;	# disconnect this graph item from the graph template

			$graph_item_mappings{$graph_item["id"]} = sql_save($save, "graph_templates_item");

			$_graph_item_sequence++;
		}
	}

	/* restore original error handler */
	restore_error_handler();

	# return with next sequence number to be filled
	return $_graph_item_sequence;
}


/**
 * cleanup of graph items of the new graph
 * @param int $base			- base graph id
 * @param int $aggregate	- graph id of aggregate
 * @param int $reorder		- type of reordering
 */
function aggregate_graphs_cleanup($base, $aggregate, $reorder) {
	global $config;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
	aggregate_log(__FUNCTION__ . " called. Base " . $base . " Aggregate " . $aggregate . " Reorder: " . $reorder, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	/* restore original error handler */
	restore_error_handler();
}


/**
 * reorder graph items
 * @param int $base			- base graph id
 * @param int $aggregate	- graph id of aggregate
 * @param int $reorder		- type of reordering
 */
function aggregate_reorder_ds_graph($base, $aggregate, $reorder) {
	global $config;
	include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
	aggregate_log(__FUNCTION__ . " called. Base " . $base . " Aggregate " . $aggregate . " Reorder: " . $reorder, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

	/* suppress warnings */
	error_reporting(0);

	/* install own error handler */
	set_error_handler("aggregate_error_handler");

	if ($reorder == AGGREGATE_ORDER_DS_GRAPH) {

		$new_seq = 1;
		/* get all different local_data_template_rrd_id's
		 * respecting the order that the aggregated graph has
		 */
		$sql = "SELECT DISTINCT " .
					"data_template_rrd.local_data_template_rrd_id " .
					"FROM data_template_rrd " .
					"LEFT JOIN graph_templates_item ON (graph_templates_item.task_item_id=data_template_rrd.id) " .
					"WHERE graph_templates_item.local_graph_id=" . $base .
					" ORDER BY graph_templates_item.sequence";
		#print $sql ."\n";
		$ds_ids = db_fetch_assoc($sql);

		foreach($ds_ids as $ds_id) {
			aggregate_log( "local_data_template_rrd_id: " . $ds_id["local_data_template_rrd_id"], false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			/* get all different task_item_id's
			 * respecting the order that the aggregated graph has
			 */
			$sql = "SELECT " .
				"graph_templates_item.id, " .
				"graph_templates_item.task_item_id " .
				"FROM graph_templates_item " .
				"LEFT JOIN data_template_rrd ON (task_item_id=data_template_rrd.id) " .
				"WHERE local_graph_id=" . $aggregate .
				" AND data_template_rrd.local_data_template_rrd_id=" . $ds_id["local_data_template_rrd_id"] .
				" ORDER BY sequence";
			aggregate_log(__FUNCTION__ .  " sql: " . $sql, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
			$items = db_fetch_assoc($sql);

			foreach($items as $item) {
				# accumulate the updates to avoid interfering the next loops
				$updates[] = "UPDATE graph_templates_item SET sequence=" . $new_seq++ . " WHERE id=" . $item["id"];
			}
		}

		# now get all "empty" local_data_template_rrd_id's
		# = those graph items without associated data source (e.g. COMMENT)
		$sql = "SELECT id " .
			"FROM graph_templates_item " .
			"WHERE graph_templates_item.local_graph_id=" . $aggregate .
			" AND graph_templates_item.task_item_id=0" .
			" ORDER BY sequence";
		aggregate_log($sql, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
		$empty_task_items = db_fetch_assoc($sql);
		# now add those "empty" one's to the end
		foreach($empty_task_items as $item) {
			# accumulate the updates to avoid interfering the next loops
			$updates[] = "UPDATE graph_templates_item SET sequence=" . $new_seq++ . " WHERE id=" . $item["id"];
		}
		# now run all updates
		if (sizeof($updates)) {
			foreach($updates as $update) {
				aggregate_log(__FUNCTION__ .  " update: " . $update, false, "AGGREGATE", AGGREGATE_LOG_DEBUG);
				db_execute($update);
			}
		}

	}

	/* restore original error handler */
	restore_error_handler();
}


/**
 * aggregate_graphs_action_execute	- perform aggregate_graph execute action
 * @param string $action			- action to be performed
 * @return $action					- action has to be passed top next plugin in chain
 *  */
function aggregate_graphs_action_execute($action) {
	global $config;

	if ($action == "plugin_aggregate") { /* aggregate */
		include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
		aggregate_log(__FUNCTION__ . "  called. Action: " . $action, true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

		/* suppress warnings */
		error_reporting(0);

		/* install own error handler */
		set_error_handler("aggregate_error_handler");

		/* loop through each of the graph_items selected on the previous page for skipped items */
		$skipped_items   = array();
		$hr_items        = array();
		$color_templates = array();
		while (list($var,$val) = each($_POST)) {
			# work on color_templates
			if (preg_match("/^agg_color_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */
				# create an array element with index of graph item to be skipped
				# index starts at 1
				$color_templates[$matches[1]] = $val;
			}
			# work on checkboxed for adding <HR> to a graph item
			if (preg_match("/^agg_hr_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */
				# create an array element with index of graph item to be skipped
				# index starts at 1
				$hr_items[$matches[1]] = $matches[1];
			}
			# work on checkboxed for skipping items
			if (preg_match("/^agg_skip_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */
				# create an array element with index of graph item to be skipped
				# index starts at 1
				$skipped_items[$matches[1]] = $matches[1];
			}
			# work on checkboxed for totalling items
			if (preg_match("/^agg_total_([0-9]+)$/", $var, $matches)) {
				/* ================= input validation ================= */
				input_validate_input_number($matches[1]);
				/* ==================================================== */
				# create an array element with index of graph item to be totalled
				# index starts at 1
				$total_items[$matches[1]] = $matches[1];
			}

		}

		if (isset($_POST["selected_items"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));

			/* =============================================================================================================================== */
			input_validate_input_number($selected_items[0]);
			$graph_title 	= sql_sanitize(form_input_validate(htmlspecialchars($_POST["title_format"]), "title_format", "", true, 3));
			$gprint_prefix 	= sql_sanitize(form_input_validate(htmlspecialchars($_POST["gprint_prefix"]), "gprint_prefix", "", true, 3));
			$_graph_type 	= form_input_validate(htmlspecialchars($_POST["aggregate_graph_type"]), "aggregate_graph_type", "^[0-9]+$", true, 3);
			$_total 		= form_input_validate(htmlspecialchars($_POST["aggregate_total"]), "aggregate_total", "^[0-9]+$", true, 3);
			$_total_type	= form_input_validate(htmlspecialchars($_POST["aggregate_total_type"]), "aggregate_total_type", "^[0-9]+$", true, 3);
			$_total_prefix 	= form_input_validate(htmlspecialchars($_POST["aggregate_total_prefix"]), "aggregate_total_prefix", "", true, 3);
			$_reorder 		= form_input_validate(htmlspecialchars($_POST["aggregate_order_type"]), "aggregate_order_type", "^[0-9]+$", true, 3);
			$item_no 		= form_input_validate(htmlspecialchars($_POST["item_no"]), "item_no", "^[0-9]+$", true, 3);
			/* =============================================================================================================================== */
			# on error exit
			if (is_error_message()) {
				/* restore original error handler */
				restore_error_handler();
				return;
			}

			# create new graph based on first graph selected
			$new_graph_id = aggregate_graphs_create_graph($selected_items[0], $graph_title);
			# sequence number of next graph item to be added, index starts at 1
			$next_item_sequence = 1;

			/* now add the graphs one by one to the newly created graph
			 * program flow is governed by
			 * - totalling
			 * - new graph type: convert graph to e.g. AREA
			 */
			# loop for all selected graphs
			for ($selected_graph_index=0;($selected_graph_index<count($selected_items));$selected_graph_index++) {
				/* ================= input validation ================= */
				input_validate_input_number($selected_items[$selected_graph_index]);
				/* ==================================================== */
				# insert all graph items of selected graph
				# next items to be inserted have to be in sequence
				$next_item_sequence = aggregate_graphs_insert_graph_items(
										$new_graph_id,
										$selected_items[$selected_graph_index],
										$skipped_items,
										$next_item_sequence,
										$selected_graph_index,
										$color_templates,
										$_graph_type,
										$gprint_prefix,
										$_total,
										"");
			}
			aggregate_log(__FUNCTION__ . "  all items inserted, next item seq: " . $next_item_sequence . " selGraph: " . $selected_graph_index, true, "AGGREGATE", AGGREGATE_LOG_DEBUG);

			/* post processing for pure LINEx graphs
			 * if we convert to AREA/STACK, the function aggregate_graphs_insert_graph_items
			 * created a pure STACK graph (see comments in that function)
			 * so let's find out, if we have a pure STACK now ...
			 */
			if (aggregate_is_pure_stacked_graph($new_graph_id)) {
				/* ... and convert to AREA */
				aggregate_conditional_convert_graph_type($new_graph_id, GRAPH_ITEM_TYPE_STACK, GRAPH_ITEM_TYPE_AREA);
			}

			if (aggregate_is_stacked_graph($new_graph_id)) {				
				/* reorder graph items, if requested 
				 * for STACKed graphs, reorder before adding totals */
				aggregate_reorder_ds_graph(
					$selected_items[0],
					$new_graph_id,
					$_reorder);
			}

			/* special code to add totalling graph items */
			switch ($_total) {
				case AGGREGATE_TOTAL_NONE: # no totalling
					# do NOT add any totalling items
					break;
				case AGGREGATE_TOTAL_ALL: # any totalling option was selected ...
					$_graph_type = GRAPH_ITEM_TYPE_LINE1;
				case AGGREGATE_TOTAL_ONLY:
					# use the prefix for totalling GPRINTs as given by the user
					switch ($_total_type) {
						case AGGREGATE_TOTAL_TYPE_SIMILAR:
						case AGGREGATE_TOTAL_TYPE_ALL:
							$gprint_prefix = $_total_prefix;
						break;
					}

					# now skip all items, that are
						# - explicitely marked as skipped (based on $skipped_items)
						# - OR NOT marked as "totalling" items
					for ($i=1; $i<=$item_no; $i++) {
							aggregate_log(__FUNCTION__ . " old skip: " . $skipped_items[$i], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
						# skip all items, that shall not be totalled
						if (!isset($total_items[$i])) $skipped_items[$i] = $i;
							aggregate_log(__FUNCTION__ . " new skip: " . $skipped_items[$i], true, "AGGREGATE", AGGREGATE_LOG_DEBUG);
					}

					# add the "templating" graph to the new graph, honoring skipped, hr and color
					$foo = aggregate_graphs_insert_graph_items(
						$new_graph_id,
						$selected_items[0],
						$skipped_items,
						$next_item_sequence,
						$selected_graph_index,
						$color_templates,
						$_graph_type,			#TODO: user may choose LINEx instead of assuming LINE1
						$gprint_prefix,
						AGGREGATE_TOTAL_ALL,	# now add the totalling line(s)
						$_total_type);

					# now pay attention to CDEFs
					# next_item_sequence still points to the first totalling graph item
					aggregate_cdef_totalling(
						$new_graph_id,
						$next_item_sequence,
						$_total_type);
			}

			/* post processing for pure LINEx graphs
			 * if we convert to AREA/STACK, the function aggregate_graphs_insert_graph_items
			 * created a pure STACK graph (see comments in that function)
			 * so let's find out, if we have a pure STACK now ...
			 */
			if (aggregate_is_pure_stacked_graph($new_graph_id)) {
				/* ... and convert to AREA */
				aggregate_conditional_convert_graph_type($new_graph_id, GRAPH_ITEM_TYPE_STACK, GRAPH_ITEM_TYPE_AREA);
			}

			if (!aggregate_is_stacked_graph($new_graph_id)) {			
				/* reorder graph items, if requested 
				 * for non-STACKed graphs, we want to reorder the totals as well */
				aggregate_reorder_ds_graph(
					$selected_items[0],
					$new_graph_id,
					$_reorder);
			}

			header("Location:" . $config["url_path"] . "graphs.php?action=graph_edit&id=$new_graph_id");
			exit;
		}
		/* restore original error handler */
		restore_error_handler();
	}else{
		/* pass action code to next plugin in chain */
		return $action;
	}
}


/**
 * aggregate_graphs_action_prepare	- perform aggregate_graph 				prepare action
 * @param array $save				- drp_action:	selected action from dropdown
 *									  graph_array:	graphs titles selected from graph management's list
 *									  graph_list:	graphs selected from graph management's list
 * @return array $save				- pass $save to next plugin in chain
 *  */
function aggregate_graphs_action_prepare($save) {
	# globals used
	global $colors, $config, $struct_aggregate, $help_file;

	if ($save["drp_action"] == "plugin_aggregate") { /* aggregate */
		include_once($config['base_path'] . "/plugins/aggregate/aggregate_functions.php");
		aggregate_log(__FUNCTION__ . "  called. Parameters: " . serialize($save), true, "AGGREGATE", AGGREGATE_LOG_FUNCTIONS);

		/* suppress warnings */
		error_reporting(0);

		/* install own error handler */
		set_error_handler("aggregate_error_handler");

		/* initialize return code and graphs array */
		$return_code = false;
		$graphs      = array();

		/* find out which (if any) data sources are being used by this graph, so we can tell the user */
		if (isset($save["graph_array"])) {
			//print "<pre>";print_r($save["graph_array"]);print "</pre>";
			# close the html_start_box, because it is too small
			print "<td align='right' class='textHeaderDark' bgcolor='#6d88ad'><a class='linkOverDark' href='$help_file' target='_blank'><strong>[Click here for Help]</strong></a></td>";
			html_end_box();

			# provide a new prefix for GPRINT lines
			$gprint_prefix = "|query_ifName|";

			# fetch all data sources for all selected graphs
			$data_sources = db_fetch_assoc("select
				data_template_data.local_data_id,
				data_template_data.name_cache
				from (data_template_rrd,data_template_data,graph_templates_item)
				where graph_templates_item.task_item_id=data_template_rrd.id
				and data_template_rrd.local_data_id=data_template_data.local_data_id
				and " . array_to_sql_or($save["graph_array"], "graph_templates_item.local_graph_id") . "
				and data_template_data.local_data_id > 0
				group by data_template_data.local_data_id
				order by data_template_data.name_cache");

			# open a new html_start_box ...
			html_start_box("", "100%", $colors["header_panel"], "3", "center", "");

			# verify, that only a single graph template is used, else
			# aggregate will look funny
			$sql = "SELECT DISTINCT graph_templates.name " .
				"FROM graph_local " .
				"LEFT JOIN graph_templates ON (graph_local.graph_template_id=graph_templates.id) " .
				"WHERE " . array_to_sql_or($save["graph_array"], "graph_local.id");
			$used_graph_templates = db_fetch_assoc($sql);

			if (sizeof($used_graph_templates) > 1) {
				# this is invalid! STOP
				print "<tr><td class='textArea'>" .
				"<p class='textError'>The Graphs chosen for AGGREGATE refer to different Graph Templates. This will break AGGREGATE</p>";
				print "<ul>";
				foreach ($used_graph_templates as $graph_template) {
					print "<li>" . $graph_template["name"] . "</li>\n";
				}
				print "</ul>";
				print "<p class='textError'>Please click CANCEL</p><p> and choose different Graphs</p>";
				form_hidden_box("title_format", $graph_prefix . " ERROR", $graph_prefix . " ERROR");
			} else {
				/* list affected graphs */
				print "<tr>";
				print "<td class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
				"<p>Are you sure you want to aggregate the following graphs?</p><ul>" .
				$save["graph_list"] . "</ul></td>";

				/* list affected data sources */
				if (sizeof($data_sources) > 0) {
					print "<td class='textArea' bgcolor='#" . $colors["form_alternate1"] . "'>" .
					"<p>The following data sources are in use by these graphs:</p><ul>";
					foreach ($data_sources as $data_source) {
						print "<li>" . $data_source["name_cache"] . "</li>\n";
					}
					print "</ul></td>";
				}
				print "</tr>";

				/* aggregate form */
				$_aggregate_defaults = array(
					"title_format" 	=> auto_title($save["graph_array"][0]),
					"gprint_prefix"	=> $gprint_prefix
				);

				draw_edit_form(array(
					"config" => array("no_form_tag" => true),
					"fields" => inject_form_variables($struct_aggregate, $_aggregate_defaults)
				));

				html_end_box();

				# draw all graph items of first graph, including a html_start_box
				draw_aggregate_graph_items_list($save["graph_array"][0]);

				# again, a new html_start_box. Using the one from above would yield ugly formatted NO and YES buttons
				html_start_box("<strong>Please confirm</strong>", "100%", $colors["header"], "3", "center", "");

				# now everything is fine
				$return_code = true;
			}
		}

		?>
<script type="text/javascript">

		//TODO: we should do this in jQuery!
		function changeTotals() {
			switch (document.getElementById('aggregate_total').value) {
				case '<?php print AGGREGATE_TOTAL_NONE;?>':
				document.getElementById('aggregate_total_type').disabled  = "disabled";
				document.getElementById('aggregate_total_prefix').disabled  = "disabled";
					document.getElementById('aggregate_order_type').disabled  = "";
					break;
				case '<?php print AGGREGATE_TOTAL_ALL;?>': 
				document.getElementById('aggregate_total_type').disabled  = "";
				document.getElementById('aggregate_total_prefix').disabled  = "";
					document.getElementById('aggregate_order_type').disabled  = "";
				changeTotalsType();
					break;
				case '<?php print AGGREGATE_TOTAL_ONLY;?>':
					document.getElementById('aggregate_total_type').disabled  = "";
					document.getElementById('aggregate_total_prefix').disabled  = "";
					document.getElementById('aggregate_order_type').disabled  = "disabled";
					changeTotalsType();
					break;
			}
		}

		function changeTotalsType() {
			if ((document.getElementById('aggregate_total_type').value == <?php print AGGREGATE_TOTAL_TYPE_SIMILAR;?>)) {
				document.getElementById('aggregate_total_prefix').value  = "Total";
			} else if ((document.getElementById('aggregate_total_type').value == <?php print AGGREGATE_TOTAL_TYPE_ALL;?>)) {
				document.getElementById('aggregate_total_prefix').value  = "All Items";
			}
		}

		changeTotals();
		</script>
		<?php

		/* restore original error handler */
		restore_error_handler();

		return $return_code;
	}else{
		/* pass action to next plugin in chain */
		return $save;
	}
}
?>
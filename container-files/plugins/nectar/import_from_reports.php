<?php
chdir('../../');

include('./include/global.php');
include_once($config["base_path"] . "/plugins/nectar/setup.php");

$sql = 'SHOW COLUMNS FROM reports';
$result = db_fetch_assoc($sql);
$found = false;
foreach ($result as $row) {
	if ($row['Field'] == 'from_email') {
		$found = true;
	}
}

$reports = db_fetch_assoc("select * from reports order by id") or die("Could not connect to database");
foreach ($reports as $report) {
	$nectar["id"] = 0;
	$nectar["name"] = $report["name"];
	$time = time();
	$hour = $report["hour"];
	$minute = $report["minute"] * 15;
	$sec = 0;
	$month = date("m", $time);
	$day = date("d", $time) + 1;
	$year = date("Y", $time);
	$nectar["mailtime"] = mktime($hour, $minute, $sec, $month, $day, $year);
	$nectar["intrvl"] = NECTAR_SCHED_INTVL_DAY;
	$nectar["count"] = 1;
	$nectar["offset"] = 0;
	if ($found) {
		$nectar["from_email"] = $report["from_email"];
	}
	$nectar["email"] = $report["email"];
	if ($report["rtype"] == "attach" ) {
		$nectar["attachment_type"] = NECTAR_TYPE_INLINE_PNG;
	} elseif ($report["rtype"] == "jpeg") {
		$nectar["attachment_type"] = NECTAR_TYPE_INLINE_JPG;
	} elseif ($report["rtype"] == "pdf") {
		$nectar["attachment_type"] = NECTAR_TYPE_ATTACH_PDF;
	}
	$nectar["lastsent"] = $report["lastsent"];
	$nectar["enabled"] = '';

	$nectar_id = sql_save($nectar, "plugin_nectar");
	print("New Nectar created, id: " . $nectar_id . " from report: " . $report["id"] . "\n");

	$reports_data = db_fetch_assoc("select * from reports_data where reportid=" . $report["id"] . " order by gorder") or die("Could not connect to database");
	/* create new rule items */
	$save = array();
	if (sizeof($reports_data) > 0) {
		foreach ($reports_data as $reports_item) {
			$save["id"] = 0;
			$save["report_id"] = $nectar_id;
			if($reports_item["item"] == "graph") {
				$save["item_type"] = NECTAR_ITEM_GRAPH;
			} else {
				$save["item_type"] = NECTAR_ITEM_TEXT;
			}
			$save["host_id"] = $reports_item["hostid"];
			$save["local_graph_id"] = $reports_item["local_graph_id"];
			switch ($reports_item["type"]) {
				case 1:
					$save["timespan"] = GT_LAST_DAY;
					break;
				case 2:
					$save["timespan"] = GT_LAST_WEEK;
					break;
				case 3:
					$save["timespan"] = GT_LAST_MONTH;
					break;
				case 4:
					$save["timespan"] = GT_LAST_YEAR;
					break;
				default:
					$save["timespan"] = GT_LAST_DAY;
					break;
			}
			$align = substr($reports_item["data"], 0, 1);
			switch ($align) {
				case "L":
					$save["align"] = NECTAR_ALIGN_LEFT;
					break;
				case "C":
					$save["align"] = NECTAR_ALIGN_CENTER;
					break;
				case "R":
					$save["align"] = NECTAR_ALIGN_RIGHT;
					break;
				default:
					$save["align"] = NECTAR_ALIGN_CENTER;
					break;
			}
			$save["font_size"] = substr($reports_item["data"], 1, 2);
			$save["item_text"] = substr($reports_item["data"], 3);
			$save["sequence"] = $reports_item["gorder"];
			$nectar_item_id = sql_save($save, "plugin_nectar_items");
			print("New Nectar Item created, id: " . $nectar_item_id . " from report item: " . $reports_item["id"] . "\n");
		}
	}
}


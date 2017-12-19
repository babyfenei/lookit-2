<?php
/*******************************************************************************

Author ......... Reinhard Scheck
based on work of Jimmy Conner
Contact ........ gandalf@cacti.net
Home Site ......
Program ........ Send Graphs by email
Version ........ 0.35a
Purpose ........ Allows you to send graphs via Email

*******************************************************************************/

define("NECTAR_SEND_NOW", 1);
define("NECTAR_DUPLICATE", 2);
define("NECTAR_ENABLE", 3);
define("NECTAR_DISABLE", 4);
define("NECTAR_DELETE", 99);
define("NECTAR_OWN", 100);

define("NECTAR_TYPE_INLINE_PNG", 1);
define("NECTAR_TYPE_INLINE_JPG", 2);
define("NECTAR_TYPE_INLINE_GIF", 3);
define("NECTAR_TYPE_ATTACH_PNG", 11);
define("NECTAR_TYPE_ATTACH_JPG", 12);
define("NECTAR_TYPE_ATTACH_GIF", 13);
define("NECTAR_TYPE_ATTACH_PDF", 14);
define("NECTAR_TYPE_INLINE_PNG_LN", 91);
define("NECTAR_TYPE_INLINE_JPG_LN", 92);
define("NECTAR_TYPE_INLINE_GIF_LN", 93);

define("NECTAR_ITEM_GRAPH", 1);
define("NECTAR_ITEM_TEXT", 2);
define("NECTAR_ITEM_TREE", 3);
define("NECTAR_ITEM_HR", 4);

define("NECTAR_ALIGN_LEFT", 1);
define("NECTAR_ALIGN_CENTER", 2);
define("NECTAR_ALIGN_RIGHT", 3);

define("NECTAR_SCHED_INTVL_DAY", 1);
define("NECTAR_SCHED_INTVL_WEEK", 2);
define("NECTAR_SCHED_INTVL_MONTH_DAY", 3);
define("NECTAR_SCHED_INTVL_MONTH_WEEKDAY", 4);
define("NECTAR_SCHED_INTVL_YEAR", 5);

define("NECTAR_SCHED_COUNT", 1);
define("NECTAR_SCHED_OFFSET", 0);

define("NECTAR_GRAPH_LINK", 0);

define("NECTAR_FONT_SIZE", 10);
define("NECTAR_HOST_NONE", 0);
define("NECTAR_TREE_NONE", 0);
define("NECTAR_TIMESPAN_DEFAULT", GT_LAST_DAY);
define("NECTAR_EXTENSION_GD", "gd");	# php-gd extension required for png2jpeg, png2gif
# define a debugging level specific to NECTAR
define('NECTAR_DEBUG', read_config_option("nectar_log_verbosity"), true);

define("NECTAR_PERM_ADMIN", 0);
define("NECTAR_PERM_USER",  1);
define("NECTAR_PERM_NONE",  2);

define("NECTAR_OUTPUT_STDOUT", 1);
define("NECTAR_OUTPUT_EMAIL",  2);

define("NECTAR_DEFAULT_MAX_SIZE", 10485760);

function plugin_nectar_install () {
	# graph setup all arrays needed for automation
	api_plugin_register_hook('nectar', 'config_arrays',         'nectar_config_arrays',         'setup.php');
	api_plugin_register_hook('nectar', 'config_form',           'nectar_config_form',           'setup.php');
	api_plugin_register_hook('nectar', 'config_settings',       'nectar_config_settings',       'setup.php');
	api_plugin_register_hook('nectar', 'draw_navigation_text',  'nectar_draw_navigation_text',  'setup.php');
	api_plugin_register_hook('nectar', 'poller_bottom',         'nectar_poller_bottom',         'setup.php');
	api_plugin_register_hook('nectar', 'console_after',         'nectar_console_after',         'setup.php');
	api_plugin_register_hook('nectar', 'top_header_tabs',       'nectar_show_tab',              'setup.php');
	api_plugin_register_hook('nectar', 'top_graph_header_tabs', 'nectar_show_tab',              'setup.php');

	api_plugin_register_hook('nectar', 'graphs_action_array',   'nectar_graphs_action_array',   'setup.php');
	api_plugin_register_hook('nectar', 'graphs_action_prepare', 'nectar_graphs_action_prepare', 'setup.php');
	api_plugin_register_hook('nectar', 'graphs_action_execute', 'nectar_graphs_action_execute', 'setup.php');

	api_plugin_register_realm('nectar', 'nectar.php', 'Plugin -> Nectar Reports Admin', 1);
	api_plugin_register_realm('nectar', 'nectar_user.php', 'Plugin -> Nectar Reports User', 1);

	nectar_setup_table ();
}

function plugin_nectar_uninstall () {
	// Do any extra Uninstall stuff here
}

function plugin_nectar_check_config () {
	// Here we will check to ensure everything is configured
	nectar_check_upgrade ();
	return true;
}

function plugin_nectar_upgrade () {
	// Here we will upgrade to the newest version
	nectar_check_upgrade ();
	return true;
}

function plugin_nectar_version () {
	return nectar_version();
}

function nectar_check_upgrade () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");
	include_once($config["library_path"] . "/functions.php");

	// Let's only run this check if we are on a page that actually needs the data
	$files = array('plugins.php', 'nectar.php', 'nectar_edit.php');
	if (isset($_SERVER['PHP_SELF']) && !in_array(basename($_SERVER['PHP_SELF']), $files)) {
		return;
	}

	$version = nectar_version ();
	$current = $version['version'];
	$old     = db_fetch_cell("SELECT version FROM plugin_config WHERE directory='nectar'");
	if ($current != $old) {
		/* plugin_nectar upgrade */
		$nectar_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM plugin_nectar"), "Field", "Field");

		if (!in_array("bcc", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN bcc TEXT AFTER email");
		}

		if (!in_array("from_name", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN from_name VARCHAR(40) NOT NULL DEFAULT '' AFTER mailtime");
		}

		if (!in_array("user_id", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN user_id mediumint(8) unsigned NOT NULL DEFAULT '0' AFTER id");
		}

		if (!in_array("graph_width", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN graph_width smallint(2) unsigned NOT NULL DEFAULT '0' AFTER attachment_type");
		}

		if (!in_array("graph_height", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN graph_height smallint(2) unsigned NOT NULL DEFAULT '0' AFTER graph_width");
		}

		if (!in_array("graph_columns", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN graph_columns smallint(2) unsigned NOT NULL DEFAULT '0' AFTER graph_height");
		}

		if (!in_array("thumbnails", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN thumbnails char(2) NOT NULL DEFAULT '' AFTER graph_columns");
		}

		if (!in_array("font_size", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN font_size smallint(2) NOT NULL DEFAULT 16 AFTER name");
		}

		if (!in_array("alignment", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN alignment smallint(2) NOT NULL DEFAULT 0 AFTER font_size");
		}

		if (!in_array("cformat", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN cformat char(2) NOT NULL DEFAULT '' AFTER name");
		}

		if (!in_array("format_file", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN format_file varchar(255) NOT NULL DEFAULT '' AFTER cformat");
		}

		if (!in_array("graph_linked", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN graph_linked char(2) NOT NULL DEFAULT '' AFTER alignment");
		}

		if (!in_array("subject", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar ADD COLUMN subject varchar(64) NOT NULL DEFAULT '' AFTER mailtime");
		}

		/* plugin_nectar_items upgrade */
		$nectar_columns = array_rekey(db_fetch_assoc("SHOW COLUMNS FROM plugin_nectar_items"), "Field", "Field");
		if (!in_array("host_template_id", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar_items ADD COLUMN host_template_id int(10) unsigned NOT NULL DEFAULT '0' AFTER item_type");
		}

		if (!in_array("graph_template_id", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar_items ADD COLUMN graph_template_id int(10) unsigned NOT NULL DEFAULT '0' AFTER host_id");
		}

		if (!in_array("tree_id", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar_items ADD COLUMN tree_id int(10) unsigned NOT NULL DEFAULT '0' AFTER item_type");
		}

		if (!in_array("branch_id", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar_items ADD COLUMN branch_id int(10) unsigned NOT NULL DEFAULT '0' AFTER tree_id");
		}

		if (!in_array("tree_cascade", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar_items ADD COLUMN tree_cascade char(2) NOT NULL DEFAULT '' AFTER branch_id");
		}

		if (!in_array("graph_name_regexp", $nectar_columns)) {
			db_execute("ALTER TABLE plugin_nectar_items ADD COLUMN graph_name_regexp varchar(128) NOT NULL DEFAULT '' AFTER tree_cascade");
		}

		/* fix host templates and graph template ids */
		$items = db_fetch_assoc("SELECT * FROM plugin_nectar_items WHERE item_type=1");
		if (sizeof($items)) {
		foreach ($items as $row) {
				$host = db_fetch_row("SELECT host.* FROM graph_local LEFT JOIN host ON (graph_local.host_id=host.id) WHERE graph_local.id=" . $row["local_graph_id"]);
				$graph_template = db_fetch_cell("SELECT graph_template_id FROM graph_local WHERE id=" . $row["local_graph_id"]);

				db_execute("UPDATE plugin_nectar_items SET " .
						" host_id='" . $host["id"] . "', " .
						" host_template_id='" . $host["host_template_id"] . "', " .
						" graph_template_id='" . $graph_template . "' " .
						" WHERE id=" . $row["id"]);
		}
		}
		api_plugin_register_hook('nectar', 'top_header_tabs',       'nectar_show_tab',              'setup.php');
		api_plugin_register_hook('nectar', 'top_graph_header_tabs', 'nectar_show_tab',              'setup.php');
		api_plugin_register_hook('nectar', 'config_settings',       'nectar_config_settings',       'setup.php');
		api_plugin_register_hook('nectar', 'graphs_action_array',   'nectar_graphs_action_array',   'setup.php');
		api_plugin_register_hook('nectar', 'graphs_action_prepare', 'nectar_graphs_action_prepare', 'setup.php');
		api_plugin_register_hook('nectar', 'graphs_action_execute', 'nectar_graphs_action_execute', 'setup.php');

		if (api_plugin_is_enabled('nectar')) {
			# may sound ridiculous, but enables new hooks
			api_plugin_enable_hooks('nectar');
		}
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='nectar'");
		db_execute("UPDATE plugin_config SET " .
				"version='" . $version["version"] . "', " .
				"name='" . $version["longname"] . "', " .
				"author='" . $version["author"] . "', " .
				"webpage='" . $version["url"] . "' " .
				"WHERE directory='" . $version["name"] . "' ");
	}
}

function nectar_check_dependencies() {
	global $plugins, $config;

	/* perform a test */
	$fields_test = array(
		'test1' => array(
			'sql' => "|arg1:test1||arg1:test2|"
		)
	);

	$items["test1"] = "0";
	$items["test2"] = "0";

	$test1 = inject_form_variables($fields_test, $items);

	if ($test1["test1"]["sql"] != "00") {
		cacti_log("FATAL: Cacti Dependency NOT met for NECTAR.  Please patch the file './lib/html_utility.php' from the Cacti Groups SVN repository or from the 0.8.7e directory", false, "NECTAR");
		return false;
	}else{
		return true;
	}
}

function nectar_show_tab() {
	global $config;

	if (!isset($_SESSION["sess_nectar_level"])) {
		$_SESSION["sess_nectar_level"] = nectar_permissions();
	}

	if ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN || $_SESSION["sess_nectar_level"] == NECTAR_PERM_USER) {
		if (substr_count($_SERVER["REQUEST_URI"], "nectar")) {
			if ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN) {
				print '<a href="' . $config['url_path'] . 'plugins/nectar/nectar.php"><img src="' . $config['url_path'] . 'plugins/nectar/images/tab_nectar_down.gif" alt="Nectar" align="absmiddle" border="0"></a>';
			}else{
				print '<a href="' . $config['url_path'] . 'plugins/nectar/nectar_user.php"><img src="' . $config['url_path'] . 'plugins/nectar/images/tab_nectar_down.gif" alt="Nectar" align="absmiddle" border="0"></a>';
			}
		}else{
			if ($_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN) {
				print '<a href="' . $config['url_path'] . 'plugins/nectar/nectar.php"><img src="' . $config['url_path'] . 'plugins/nectar/images/tab_nectar.gif" alt="Nectar" align="absmiddle" border="0"></a>';
			}else{
				print '<a href="' . $config['url_path'] . 'plugins/nectar/nectar_user.php"><img src="' . $config['url_path'] . 'plugins/nectar/images/tab_nectar.gif" alt="Nectar" align="absmiddle" border="0"></a>';
			}
		}
	}
}

/**
 * Version information (used by update plugin)
 */
function nectar_version () {
	return array(
		'name' 		=> 'nectar',
		'version' 	=> '0.35a',
		'longname'	=> 'Send Graphs via EMail',
		'author'	=> 'Reinhard Scheck',
		'homepage'	=> 'http://docs.cacti.net/plugin:nectar',
		'email'		=> 'gandalf@cacti.net',
		'url'		=> 'http://docs.cacti.net/plugin:nectar'
		);
}

function nectar_console_after () {
	nectar_setup_table ();
}

function nectar_permissions() {
	$admin_realm = db_fetch_cell("SELECT id FROM plugin_realms WHERE file LIKE '%nectar.php%'") + 100;
	$user_realm  = db_fetch_cell("SELECT id FROM plugin_realms WHERE file LIKE '%nectar_user.php%'") + 100;

	if (isset($_SESSION["sess_user_id"])) {
		if (sizeof(db_fetch_assoc("SELECT realm_id
			FROM user_auth_realm
			WHERE user_id=" . $_SESSION["sess_user_id"] . "
			AND realm_id=$admin_realm"))) {
			return NECTAR_PERM_ADMIN;
		}elseif (sizeof(db_fetch_assoc("SELECT realm_id
			FROM user_auth_realm
			WHERE user_id=" . $_SESSION["sess_user_id"] . "
			AND realm_id=$user_realm"))) {
			return NECTAR_PERM_USER;
		}else{
			return NECTAR_PERM_NONE;
		}
	}else{
		return NECTAR_PERM_NONE;
	}
}

function nectar_config_arrays () {
	global $menu, $messages, $attachment_sizes;
	global $nectar_actions, $attach_types, $item_types, $alignment, $nectar_interval;

	nectar_check_upgrade ();

	if (isset($_SESSION['nectar_message']) && $_SESSION['nectar_message'] != '') {
		$messages['nectar_message'] = array('message' => "<i>" . $_SESSION['nectar_message'] . "</i>", 'type' => 'info');
	}

	if (isset($_SESSION['nectar_error']) && $_SESSION['nectar_error'] != '') {
		$messages['nectar_error'] = array('message' => "<span style='color:red;'><i>" . $_SESSION['nectar_error'] . "</i></span>", 'type' => 'info');
	}

	$messages['nectar_save']        = array('message' => '<i>Report Saved</i>', 'type' => 'info');
	$messages['nectar_save_failed'] = array('message' => '<font style="color:red;"><i>Report Save Failed</i></font>', 'type' => 'info');

	$attachment_sizes = array(
		1048576 => "1 Megabyte",
		2097152 => "2 Megabytes",
		4194304 => "4 Megabytes",
		10485760 => "10 Megabytes",
		20971520 => "20 Megabytes",
		52428800 => "50 Megabytes",
		104857600 => "100 Megabytes"
	);

	$nectar_actions = array(
		NECTAR_SEND_NOW  => "Send Now",
		NECTAR_DUPLICATE => "Duplicate",
		NECTAR_ENABLE    => "Enable",
		NECTAR_DISABLE   => "Disable",
		NECTAR_DELETE    => "Delete",
	);

	if (isset($_SESSION) &&
		array_key_exists("sess_nectar_level", $_SESSION) &&
		$_SESSION["sess_nectar_level"] == NECTAR_PERM_ADMIN) {
		$nectar_actions[NECTAR_OWN] = "Take Ownership";
	}

	$attach_types = array(
		NECTAR_TYPE_INLINE_PNG => 'Inline PNG Image',
		#NECTAR_TYPE_INLINE_JPG => 'Inline JPEG Image',
		#NECTAR_TYPE_ATTACH_PDF => 'PDF Attachment',
	);
	if (extension_loaded(NECTAR_EXTENSION_GD)) {
		$attach_types[NECTAR_TYPE_INLINE_JPG] = 'Inline JPEG Image';
		$attach_types[NECTAR_TYPE_INLINE_GIF] = 'Inline GIF Image';
	}
	$attach_types[NECTAR_TYPE_ATTACH_PNG] = 'Attached PNG Image';
	if (extension_loaded(NECTAR_EXTENSION_GD)) {
		$attach_types[NECTAR_TYPE_ATTACH_JPG] = 'Attached JPEG Image';
		$attach_types[NECTAR_TYPE_ATTACH_GIF] = 'Attached GIF Image';
	}
	if (read_config_option("nectar_allow_ln") != '') {
		$attach_types[NECTAR_TYPE_INLINE_PNG_LN] = 'Inline PNG Image, LN Style';
		if (extension_loaded(NECTAR_EXTENSION_GD)) {
			$attach_types[NECTAR_TYPE_INLINE_JPG_LN] = 'Inline JPEG Image, LN Style';
			$attach_types[NECTAR_TYPE_INLINE_GIF_LN] = 'Inline GIF Image, LN Style';
		}
	}

	$item_types = array(
		NECTAR_ITEM_TEXT  => 'Text',
		NECTAR_ITEM_TREE => 'Tree',
		NECTAR_ITEM_GRAPH => 'Graph',
		NECTAR_ITEM_HR => 'Horizontal Rule'
	);

	$alignment = array(
		NECTAR_ALIGN_LEFT   => 'left',
		NECTAR_ALIGN_CENTER => 'center',
		NECTAR_ALIGN_RIGHT  => 'right'
	);

	$nectar_interval = array(
		NECTAR_SCHED_INTVL_DAY           => 'Day(s)',
		NECTAR_SCHED_INTVL_WEEK          => 'Week(s)',
		NECTAR_SCHED_INTVL_MONTH_DAY     => 'Month(s), Day of Month',
		NECTAR_SCHED_INTVL_MONTH_WEEKDAY => 'Month(s), Day of Week',
		NECTAR_SCHED_INTVL_YEAR          => 'Year(s)',
	);

	$messages["mg_mailtime_invalid"]["message"] = "Invalid Timestamp. Select timestamp in the future.";
	$messages["mg_mailtime_invalid"]["type"]    = "error";
}


/**
 * get available format files for nectar
 * @return array	- available format files
 */
function nectar_get_format_files() {
	global $config;

	$formats = array();
	$dir     = $config["base_path"] . "/plugins/nectar/formats";

	if (is_dir($dir)) {
		if (function_exists("scandir")) {
			$files = scandir($dir);
		}elseif ($dh = opendir($dir)) {
			while (($file = readdir($dh)) !== false) {
				$files[] = $file;
			}
			closedir($dh);
		}

		if (sizeof($files)) {
		foreach($files as $file) {
			if (substr_count($file, ".format")) {
				$contents = file($dir . "/" . $file);

				if (sizeof($contents)) {
				foreach($contents as $line) {
					$line = trim($line);
					if (substr_count($line, "Description:") && substr($line, 0, 1) == "#") {
						$arr = explode(":", $line);
						$formats[$file] = trim($arr[1]) . " (" . $file . ")";
					}
				}
				}
			}
		}
		}
	}

	return $formats;
}


/**
 * define configuration settings for nectar
 */
function nectar_config_settings () {
	global $tabs, $settings, $attach_types, $attachment_sizes, $logfile_verbosity;

	$tabs["misc"] = "Misc";

	$temp = array(
		"nectar_header" => array(
			"friendly_name" => "Nectar Report Presets",
			"method" => "spacer",
			),
		"nectar_default_image_format" => array(
			"friendly_name" => "Default Graph Image Format",
			"description" => "When creating a new report, what image type should be used for the inline graphs.",
			"method" => "drop_array",
			"default" => NECTAR_TYPE_INLINE_PNG,
			"array" => $attach_types
			),
		"nectar_max_attach" => array(
			"friendly_name" => "Maximum E-Mail Size",
			"description" => "The maximum size of the E-Mail message including all attachements.",
			"method" => "drop_array",
			"default" => NECTAR_DEFAULT_MAX_SIZE,
			"array" => $attachment_sizes
			),
		"nectar_log_verbosity" => array(
			"friendly_name" => "Poller Logging Level for NECTAR",
			"description" => "What level of detail do you want sent to the log file. WARNING: Leaving in any other status than NONE or LOW can exaust your disk space rapidly.",
			"method" => "drop_array",
			"default" => POLLER_VERBOSITY_LOW,
			"array" => $logfile_verbosity,
			),
		"nectar_allow_ln" => array(
			"friendly_name" => "Enable Lotus Notus (R) tweak",
			"description" => "Enable code tweak for specific handling of Lotus Notes Mail Clients.",
			"method" => "checkbox",
			"default" => '',
			)
		);

	if (isset($settings["misc"])) {
		$settings["misc"] = array_merge($settings["misc"], $temp);
	}else {
		$settings["misc"] = $temp;
	}
}


/**
 * define configuration forms for nectar
 */
function nectar_config_form () {
	global $config, $graph_timespans, $nectar_interval;
	global $attach_types, $item_types, $alignment;
	global $fields_nectar_edit, $fields_nectar_item_edit;

	/* don't load up this form unless we are using nectar */
	if (!isset($_SERVER["REQUEST_URI"]) || !substr_count($_SERVER["REQUEST_URI"], "nectar")) return;

	/* don't load up this form unless we are logged in */
	if (!isset($_SESSION["sess_user_id"])) return;

	include_once($config["base_path"] . "/lib/auth.php");

	/* get the format files */
	$formats = nectar_get_format_files();

	$fields_nectar_edit = array(
		'genhead' => array(
			'friendly_name' => 'General Settings',
			'method' => 'spacer'),
		'name' => array(
			'friendly_name' => 'Report Name',
			'method' => 'textbox',
			'default' => 'New Report',
			'description' => 'Give this Report a descriptive Name',
			'max_length' => 99,
			'value' => '|arg1:name|' ),
		'enabled' => array(
			'friendly_name' => 'Enable Report',
			'method' => 'checkbox',
			'default' => '',
			'description' => 'Check this box to enable this Report.',
			'value' => '|arg1:enabled|',
			'form_id' => false),
		'formathead' => array(
			'friendly_name' => 'Output Formatting',
			'method' => 'spacer'),
		'cformat' => array(
			'friendly_name' => 'Use Custom Format HTML',
			'method' => 'checkbox',
			'default' => '',
			'description' => 'Check this box if you want to use custom html and CSS for the report.',
			'value' => '|arg1:cformat|',
			'form_id' => false),
		'format_file' => array(
			'friendly_name' => 'Format File to Use',
			'method' => 'drop_array',
			'default' => 'default.format',
			'description' => htmlspecialchars('Choose the custom html wrapper and CSS file to use.  This file contains both html and CSS to wrap around your report.
			If it contains more than simply CSS, you need to place a special <REPORT> tag inside of the file.  This format tag will be replaced by the report content.
			These files are located in the \'formats\' directory.'),
			'value' => '|arg1:format_file|',
			'array' => $formats),
		'font_size' => array(
			'friendly_name' => 'Default Text Font Size',
			'description' => 'Defines the default font size for all text in the report including the Report Title.',
			'default' => 16,
			'method' => 'drop_array',
			'array' => array(7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32),
			'value' => '|arg1:font_size|' ),
		'alignment' => array(
			'friendly_name' => 'Default Object Alignment',
			'description' => 'Defines the default Alignment for Text and Graphs.',
			'default' => 0,
			'method' => 'drop_array',
			'array' => $alignment,
			'value' => '|arg1:alignment|' ),
		'graph_linked' => array(
			'friendly_name' => 'Graph Linked',
			'method' => 'checkbox',
			'default' => '',
			'description' => 'Should the Graphs be linked back to the Cacti site?',
			'value' => '|arg1:graph_linked|' ),
		'graphhead' => array(
			'friendly_name' => 'Graph Settings',
			'method' => 'spacer'),
		'graph_columns' => array(
			'friendly_name' => 'Graph Columns',
			'method' => 'drop_array',
			'default' => '1',
			'array' => array(1 => 1, 2, 3, 4, 5),
			'description' => 'The number of Graph columns.',
			'value' => '|arg1:graph_columns|'),
		'graph_width' => array(
			'friendly_name' => 'Graph Width',
			'method' => 'drop_array',
			'default' => '300',
			'array' => array(100 => 100, 150 => 150, 200 => 200, 250 => 250, 300 => 300, 350 => 350, 400 => 400, 500 => 500, 600 => 600, 700 => 700, 800 => 800, 900 => 900, 1000 => 1000),
			'description' => 'The Graph width in pixels.',
			'value' => '|arg1:graph_width|' ),
		'graph_height' => array(
			'friendly_name' => 'Graph Height',
			'method' => 'drop_array',
			'default' => '125',
			'array' => array(75 => 75, 100 => 100, 125 => 125, 150 => 150, 175 => 175, 200 => 200, 250 => 250, 300 => 300),
			'description' => 'The Graph height in pixels.',
			'value' => '|arg1:graph_height|' ),
		'thumbnails' => array(
			'friendly_name' => 'Thumbnails',
			'method' => 'checkbox',
			'default' => '',
			'description' => 'Should the Graphs be rendered as Thumbnails?',
			'value' => '|arg1:thumbnails|' ),
		'freqhead' => array(
			'friendly_name' => 'Email Frequency',
			'method' => 'spacer'),
		'mailtime' => array(
			'friendly_name' => 'Next Timestamp for Sending Mail Report',
			'description' => 'Start time for [first|next] mail to take place.  All future
				mailing times will be based upon this start time. A good example would be 2:00am. The time
				must be in the future.  If a fractional time is used, say 2:00am, it is assumed to be in the future.',
			'default' => 0,
			'method' => 'textbox',
			'size' => 20,
			'max_length' => 20,
			'value' => '|arg1:mailtime|' ),
		'intrvl' => array(
			'friendly_name' => 'Report Interval',
			'description' => 'Defines a Report Frequency relative to the given Mailtime above.' . '<br>' .
				'e.g. "Week(s)" represents a weekly Reporting Interval.',
			'default' => NECTAR_SCHED_INTVL_DAY,
			'method' => 'drop_array',
			'array' => $nectar_interval,
			'value' => '|arg1:intrvl|' ),
		'count' => array(
			'friendly_name' => 'Interval Frequency',
			'description' => 'Based upon the Timespan of the Report Interval above, defines the Frequency within that Interval.<br>' .
				'e.g. If the Report Interval is "Month(s)", then "2" indicates Every "2 Month(s) from the next Mailtime."' .
				'Lastly, if using the Month(s) Report Intervals, the "Day of Week" and the "Day of Month" are both calculated based upon the Mailtime you specify above.',
			'default' => NECTAR_SCHED_COUNT,
			'method' => 'textbox',
			'size' => 10,
			'max_length' => 10,
			'value' => '|arg1:count|' ),
		'emailhead' => array(
			'friendly_name' => 'Email Sender/Receiver Details',
			'method' => 'spacer'),
		'subject' => array(
			'friendly_name' => 'Subject',
			'method' => 'textbox',
			'default' => 'Nectar Report',
			'description' => 'This value will be used as the default Email subject.  The report name will be used if left blank.',
			'max_length' => 255,
			'value' => '|arg1:subject|' ),
		'from_name' => array(
			'friendly_name' => 'From Name',
			'method' => 'textbox',
			'default' => read_config_option("settings_from_name"),
			'description' => 'This Name will be used as the default E-mail Sender',
			'max_length' => 255,
			'value' => '|arg1:from_name|' ),
		'from_email' => array(
			'friendly_name' => 'From Email Address',
			'method' => 'textbox',
			'default' => read_config_option("settings_from_email"),
			'description' => 'This Adress will be used as the E-mail Senders address',
			'max_length' => 255,
			'value' => '|arg1:from_email|' ),
		'email' => array(
			'friendly_name' => 'To Email Address(es)',
			'method' => 'textarea',
			'textarea_rows' => '5',
			'textarea_cols' => '60',
			'class' => 'textAreaNotes',
			'default' => '',
			'description' => 'Please seperate multiple adresses by comma (,)',
			'max_length' => 255,
			'value' => '|arg1:email|' ),
		'bcc' => array(
			'friendly_name' => 'BCC Address(es)',
			'method' => 'textarea',
			'textarea_rows' => '5',
			'textarea_cols' => '60',
			'class' => 'textAreaNotes',
			'default' => '',
			'description' => 'Blind carbon copy. Please seperate multiple adresses by comma (,)',
			'max_length' => 255,
			'value' => '|arg1:bcc|' ),
		'attachment_type' => array(
			'friendly_name' => 'Image attach type',
			'method' => 'drop_array',
			'default' => read_config_option("nectar_default_image_format"),
			'description' => 'Select one of the given Types for the Image Attachments',
			'value' => '|arg1:attachment_type|',
			'array' => $attach_types),
	);

	/* get the hosts sql first */
	if (read_config_option("auth_method") != 0) {
		/* get policy information for the sql where clause */
		$current_user = db_fetch_row("select * from user_auth where id=" . $_SESSION["sess_user_id"]);
		$sql_where    = get_graph_permissions_sql($current_user["policy_graphs"], $current_user["policy_hosts"], $current_user["policy_graph_templates"]);

		$hosts_sql = "SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
			FROM (graph_templates_graph,host)
			LEFT JOIN graph_local ON (graph_local.host_id=host.id)
			LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
			WHERE graph_templates_graph.local_graph_id=graph_local.id
			AND host_template_id=|arg1:host_template_id|
			" . (empty($sql_where) ? "" : "AND $sql_where") . "
			ORDER BY name";
	}else{
		$hosts_sql = "SELECT DISTINCT host.id, CONCAT_WS('',host.description,' (',host.hostname,')') as name
			FROM host
			WHERE host_template_id=|arg1:host_template_id|
			ORDER BY name";
	}

	/* next do the templates sql */
	if (read_config_option("auth_method") != 0) {
		$templates_sql = "SELECT DISTINCT graph_templates.id, graph_templates.name
			FROM (graph_templates_graph,graph_local)
			LEFT JOIN host ON (host.id=graph_local.host_id)
			LEFT JOIN graph_templates ON (graph_templates.id=graph_local.graph_template_id)
			LEFT JOIN user_auth_perms ON ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . ") OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=" . $_SESSION["sess_user_id"] . "))
			WHERE graph_templates_graph.local_graph_id=graph_local.id
			AND graph_templates.id IS NOT NULL
			AND host_id=|arg1:host_id|
			" . (empty($sql_where) ? "" : "AND $sql_where") . "
			ORDER BY name";
	}else{
		$templates_sql = "SELECT DISTINCT graph_templates.id, graph_templates.name
			FROM graph_templates
			WHERE host_id=|arg1:host_id|
			ORDER BY name";
	}

	/* last do the tree permissions */
	if (read_config_option("auth_method") != 0) {
		/* all allowed by default */
		$sql_in = "";
		if ($current_user["policy_trees"] == 1) {
			$exclude_trees = db_fetch_assoc("SELECT item_id
				FROM user_auth_perms
				WHERE user_id=" . $_SESSION["sess_user_id"] . "
				AND type=2");

			if (sizeof($exclude_trees)) {
			foreach($exclude_trees as $tree) {
				$sql_in .= (strlen($sql_in) ? ", ":"") . $tree["item_id"];
			}
			}

			$sql_where = (strlen($sql_in) ? "WHERE id NOT IN ($sql_in)":"");
		}else{
			$include_trees = db_fetch_assoc("SELECT item_id
				FROM user_auth_perms
				WHERE user_id=" . $_SESSION["sess_user_id"] . "
				AND type=2");

			if (sizeof($include_trees)) {
			foreach($include_trees as $tree) {
				$sql_in .= (strlen($sql_in) ? ", ":"") . $tree["item_id"];
			}
			}

			$sql_where = (strlen($sql_in) ? "WHERE id IN ($sql_in)":"");
		}

		$trees_sql = "SELECT id, name FROM graph_tree $sql_where ORDER BY name";
	}else{
		$trees_sql = "SELECT id, name FROM graph_tree ORDER BY name";
	}

	$fields_nectar_item_edit = array(
		'item_type' => array(
			'friendly_name' => 'Type',
			'method' => 'drop_array',
			'default' => NECTAR_ITEM_GRAPH,
			'description' => 'Item Type to be added.',
			'value' => '|arg1:item_type|',
			'on_change' => 'toggle_item_type()',
			'array' => $item_types),
		'tree_id' => array(
			'friendly_name' => 'Graph Tree',
			'method' => 'drop_sql',
			'default' => NECTAR_TREE_NONE,
			'none_value' => "None",
			'description' => 'Select a Tree to use.',
			'value' => '|arg1:tree_id|',
			'on_change' => 'applyChange(document.nectar_item_edit)',
			'sql' => $trees_sql),
		'branch_id' => array(
			'friendly_name' => 'Graph Tree Branch',
			'method' => 'drop_sql',
			'default' => NECTAR_TREE_NONE,
			'none_value' => "All",
			'description' => 'Select a Tree Branch to use.',
			'value' => '|arg1:branch_id|',
			'sql' => "(SELECT id, CONCAT_WS('', title, ' (Branch)') AS name
				FROM graph_tree_items
				WHERE graph_tree_id=|arg1:tree_id| AND host_id=0 AND local_graph_id=0
				ORDER BY order_key)
				UNION
				(SELECT graph_tree_items.id, CONCAT_WS('', description, ' (Host)') AS name
				FROM graph_tree_items
				INNER JOIN host
				ON host.id=graph_tree_items.host_id
				WHERE graph_tree_id=|arg1:tree_id|)
				ORDER BY name"),
		'tree_cascade' => array(
			'friendly_name' => 'Cascade to Branches',
			'method' => 'checkbox',
			'default' => '',
			'description' => 'Should all children branch Graphs be rendered?',
			'value' => '|arg1:tree_cascade|' ),
		'graph_name_regexp' => array(
			'friendly_name' => 'Graph Name Regular Expression',
			'method' => 'textbox',
			'default' => '',
			'description' => 'A Perl compatible regular expression (REGEXP) used to select graphs to include from the tree.',
			'max_length' => 255,
			'size' => 80,
			'value' => '|arg1:graph_name_regexp|'),
		'host_template_id' => array(
			'friendly_name' => 'Host Template',
			'method' => 'drop_sql',
			'default' => NECTAR_HOST_NONE,
			'none_value' => "None",
			'description' => 'Select a Host Template to use.',
			'value' => '|arg1:host_template_id|',
			'on_change' => 'applyChange(document.nectar_item_edit)',
			'sql' => "SELECT DISTINCT ht.id, ht.name FROM host_template AS ht INNER JOIN host AS h ON h.host_template_id=ht.id ORDER BY name"),
		'host_id' => array(
			'friendly_name' => 'Host',
			'method' => 'drop_sql',
			'default' => NECTAR_HOST_NONE,
			'description' => 'Select a Host to specify a Graph',
			'value' => '|arg1:host_id|',
			'none_value' => "None",
			'on_change' => 'applyChange(document.nectar_item_edit)',
			'sql' => $hosts_sql),
		'graph_template_id' => array(
			'friendly_name' => 'Graph Template',
			'method' => 'drop_sql',
			'default' => '0',
			'description' => 'Select a Graph Template for the host',
			'none_value' => "None",
			'on_change' => 'applyChange(document.nectar_item_edit)',
			'value' => '|arg1:graph_template_id|',
			'sql' => $templates_sql),
		'local_graph_id' => array(
			'friendly_name' => 'Graph Name',
			'method' => 'drop_sql',
			'default' => '0',
			'description' => 'The Graph to use for this report item.',
			'none_value' => "None",
			'on_change' => 'graphImage(this.value)',
			'value' => '|arg1:local_graph_id|',
			'sql' => "SELECT graph_templates_graph.local_graph_id as id, graph_templates_graph.title_cache as name
				FROM graph_local LEFT JOIN graph_templates_graph ON (graph_local.id=graph_templates_graph.local_graph_id)
				WHERE graph_local.host_id=|arg1:host_id| AND graph_templates_graph.graph_template_id=|arg1:graph_template_id|
				ORDER BY name"),
		'timespan' => array(
			'friendly_name' => 'Graph Timespan',
			'method' => 'drop_array',
			'default' => GT_LAST_DAY,
			'description' => "Graph End Time is always set to the Nectar's schedule." . '<br>' .
				'Graph Start Time equals Graph End Time minus given timespan',
			'array' => $graph_timespans,
			'value' => '|arg1:timespan|'),
		'align' => array(
			'friendly_name' => 'Alignment',
			'method' => 'drop_array',
			'default' => NECTAR_ALIGN_LEFT,
			'description' => 'Alignment of the Item',
			'value' => '|arg1:align|',
			'array' => $alignment),
		'item_text' => array(
			'friendly_name' => 'Fixed Text',
			'method' => 'textbox',
			'default' => '',
			'description' => 'Enter descriptive Text',
			'max_length' => 255,
			'value' => '|arg1:item_text|'),
		'font_size' => array(
			'friendly_name' => 'Font Size',
			'method' => 'drop_array',
			'default' => NECTAR_FONT_SIZE,
			'array' => array(7 => 7, 8 => 8, 10 => 10, 12 => 12, 14 => 14, 16 => 16, 18 => 18, 20 => 20, 24 => 24, 28 => 28, 32 => 32),
			'description' => 'Font Size of the Item',
			'value' => '|arg1:font_size|'),
		'sequence' => array(
			'method' => 'view',
			'friendly_name' => 'Sequence',
			'description' => 'Sequence of Item.',
			'value' => '|arg1:sequence|'),
	);
}


/**
 * draw navigation texts for nectar
 * @param $nav
 */
function nectar_draw_navigation_text ($nav) {
	$nav["nectar.php:"]               = array("title" => "Nectar", "mapping" => "", "url" => "nectar.php", "level" => "0");
	$nav["nectar.php:actions"]        = array("title" => "Report Add", "mapping" => "nectar.php:", "url" => "nectar.php", "level" => "1");
	$nav["nectar.php:delete"]         = array("title" => "Report Delete", "mapping" => "nectar.php:", "url" => "nectar.php", "level" => "1");
	$nav["nectar.php:edit"]           = array("title" => "Report Edit", "mapping" => "nectar.php:", "url" => "nectar.php?action=edit", "level" => "1");
	$nav["nectar.php:item_edit"]      = array("title" => "Report Edit Item", "mapping" => "nectar.php:,nectar.php:edit", "url" => "", "level" => "2");
	$nav["nectar_user.php:"]          = array("title" => "Nectar", "mapping" => "", "url" => "nectar_user.php", "level" => "0");
	$nav["nectar_user.php:actions"]   = array("title" => "Report Add", "mapping" => "nectar_user.php:", "url" => "nectar_user.php", "level" => "1");
	$nav["nectar_user.php:delete"]    = array("title" => "Report Delete", "mapping" => "nectar_user.php:", "url" => "nectar_user.php", "level" => "1");
	$nav["nectar_user.php:edit"]      = array("title" => "Report Edit", "mapping" => "nectar_user.php:", "url" => "nectar_user.php?action=edit", "level" => "1");
	$nav["nectar_user.php:item_edit"] = array("title" => "Report Edit Item", "mapping" => "nectar_user.php:,nectar_user.php:edit", "url" => "", "level" => "2");
	return $nav;
}


/**
 * define the nectar code that will be processed at the end of each polling event
 */
function nectar_poller_bottom () {
	global $config;
	include_once($config["base_path"] . "/lib/poller.php");

	$command_string = read_config_option("path_php_binary");
	$extra_args = "-q " . $config["base_path"] . "/plugins/nectar/poller_nectar.php";
	exec_background($command_string, "$extra_args");
}


/**
 * create all nectar tables
 */
function nectar_setup_table () {
	global $config, $database_default;
	include_once($config["library_path"] . "/database.php");

	/* are my tables already present? */
	$sql	= "show tables from `" . $database_default . "`";
	$result = db_fetch_assoc($sql) or die (mysql_error());
	$tables = array();
	$sql 	= array();

	foreach($result as $index => $arr) {
		foreach($arr as $tbl) {
			$tables[] = $tbl;
		}
	}

	if (!in_array('plugin_nectar', $tables)) {
		$data = array();
		$data['columns'][] = array('name' => 'id',
			'type' => 'mediumint(8)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'auto_increment' => true);

		$data['columns'][] = array('name' => 'user_id',
			'type' => 'mediumint(8)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'name',
			'type' => 'varchar(100)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'cformat',
			'type' => 'char(2)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'format_file',
			'type' => 'varchar(255)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'font_size',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'alignment',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'graph_linked',
			'type' => 'char(2)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'intrvl',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'count',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'offset',
			'type' => 'int(12)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'mailtime',
			'type' => 'bigint',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'subject',
			'type' => 'varchar(64)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'from_name',
			'type' => 'varchar(40)',
			'NULL' => false);

		$data['columns'][] = array('name' => 'from_email',
			'type' => 'text',
			'NULL' => false);

		$data['columns'][] = array('name' => 'email',
			'type' => 'text',
			'NULL' => false);

		$data['columns'][] = array('name' => 'bcc',
			'type' => 'text',
			'NULL' => false);

		$data['columns'][] = array('name' => 'attachment_type',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => NECTAR_TYPE_INLINE_PNG);

		$data['columns'][] = array('name' => 'graph_height',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'graph_width',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'graph_columns',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'thumbnails',
			'type' => 'char(2)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'lastsent',
			'type' => 'bigint',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'enabled',
			'type' => 'char(2)',
			'NULL' => true,
			'default' => '');

		$data['primary'] = 'id';

		$data['keys'][] = array('name' => 'mailtime',
			'columns' => 'mailtime');

		$data['type'] = 'MyISAM';

		$data['comment'] = 'Nectar Reports';

		api_plugin_db_table_create ('nectar', 'plugin_nectar', $data);
	}

	if (!in_array('plugin_nectar_items', $tables)) {
		$data = array();

		$data['columns'][] = array('name' => 'id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'auto_increment' => true);

		$data['columns'][] = array('name' => 'report_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'item_type',
			'type' => 'tinyint(1)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => NECTAR_ITEM_GRAPH);

		$data['columns'][] = array('name' => 'tree_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'branch_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'tree_cascade',
			'type' => 'char(2)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'graph_name_regexp',
			'type' => 'varchar(128)',
			'NULL' => false,
			'default' => '');

		$data['columns'][] = array('name' => 'host_template_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'host_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'graph_template_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'local_graph_id',
			'type' => 'int(10)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'timespan',
			'type' => 'int',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['columns'][] = array('name' => 'align',
			'type' => 'tinyint(1)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => NECTAR_ALIGN_LEFT);

		$data['columns'][] = array('name' => 'item_text',
			'type' => 'text',
			'NULL' => false,);

		$data['columns'][] = array('name' => 'font_size',
			'type' => 'smallint(2)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => NECTAR_FONT_SIZE);

		$data['columns'][] = array('name' => 'sequence',
			'type' => 'smallint(5)',
			'unsigned' => 'unsigned',
			'NULL' => false,
			'default' => 0);

		$data['primary'] = 'id';

		$data['keys'][]  = array('name' => 'report_id',
			'columns' => 'report_id');

		$data['type']    = 'MyISAM';

		$data['comment'] = 'Nectar Report Items';

		api_plugin_db_table_create ('nectar', 'plugin_nectar_items', $data);
	}
}


/**
 * PHP error handler
 * @arg $errno
 * @arg $errmsg
 * @arg $filename
 * @arg $linenum
 * @arg $vars
 */
function nectar_error_handler($errno, $errmsg, $filename, $linenum, $vars) {
	$errno = $errno & error_reporting();

	# return if error handling disabled by @
	if ($errno == 0) return;

	# define constants not available with PHP 4
	if(!defined('E_STRICT'))            define('E_STRICT', 2048);
	if(!defined('E_RECOVERABLE_ERROR')) define('E_RECOVERABLE_ERROR', 4096);

	if (read_config_option("log_verbosity") >= POLLER_VERBOSITY_HIGH) {
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
		print("PROGERR: " . $err . "<br><pre>");

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
 * Setup the new dropdown action for Graph Management
 * @arg $action		actions to be performed from dropdown
 */
function nectar_graphs_action_array($action) {
	$action['plugin_nectar'] = 'Add to Nectar Report';
	return $action;
}


/**
 * nectar_graphs_action_prepare - perform nectar_graph prepare action
 * @param array $save - drp_action: selected action from dropdown
 *              graph_array: graphs titles selected from graph management's list
 *              graph_list: graphs selected from graph management's list
 * returns array $save				-
 *  */
function nectar_graphs_action_prepare($save) {
	global $colors, $config, $graph_timespans, $alignment;

	if ($save["drp_action"] == "plugin_nectar") { /* nectar */
		print "	<tr>
				<td class='textArea' bgcolor='#" . $colors["form_alternate1"]. "'>
					<p>Choose the Nectar Report to associate these graphs with.  The defaults for alignment will be used
					for each graph in the list below.</p>
					<p>" . $save['graph_list'] . "</p>
					<p><strong>Nectar Report:</strong><br>";
					form_dropdown("nectar_id",db_fetch_assoc("SELECT plugin_nectar.id, plugin_nectar.name
						FROM plugin_nectar
						WHERE user_id=" . $_SESSION["sess_user_id"] . "
						ORDER by name"),"name","id","","","0");
					echo "<br><p><strong>Graph Timespan:</strong><br>";
					form_dropdown("timespan", $graph_timespans, "", "", "0", "", "", "");
					echo "<br><p><strong>Graph Alignment:</strong><br>";
					form_dropdown("alignment", $alignment, "", "", "0", "", "", "");
					print "</p>
				</td>
			</tr>\n
			";
	}else{
		return $save;
	}
}


/**
 * nectar_graphs_action_execute - perform nectar_graph execute action
 * @param string $action - action to be performed
 * return -
 *  */
function nectar_graphs_action_execute($action) {
	global $config;

	if ($action == "plugin_nectar") { /* nectar */
		$message = '';

		/* loop through each of the graph_items selected on the previous page for skipped items */
		if (isset($_POST["selected_items"])) {
			$selected_items = unserialize(stripslashes($_POST["selected_items"]));
			$nectar_id      = $_POST["nectar_id"];

			input_validate_input_number($nectar_id);
			input_validate_input_number($_POST["timespan"]);
			input_validate_input_number($_POST["alignment"]);

			$nectar = db_fetch_row("SELECT * FROM plugin_nectar WHERE id=" . $nectar_id);

			if (sizeof($selected_items)) {
			foreach($selected_items as $local_graph_id) {
				/* ================= input validation ================= */
				input_validate_input_number($local_graph_id);
				/* ==================================================== */

				/* see if the graph is already added */
				$existing = db_fetch_cell("SELECT id
					FROM plugin_nectar_items
					WHERE local_graph_id=" . $local_graph_id . "
					AND report_id=" . $nectar_id . "
					AND timespan=" . $_POST["timespan"]);

				if (!$existing) {
					$sequence = db_fetch_cell("SELECT max(sequence)
						FROM plugin_nectar_items
						WHERE report_id=" . $nectar_id);
					$sequence++;

					$graph_data = db_fetch_row("SELECT *
						FROM graph_local
						WHERE id=" . $local_graph_id);

					if ($graph_data["host_id"]) {
						$host_template = db_fetch_cell("SELECT host_template_id
							FROM host
							WHERE id=" . $graph_data["host_id"]);
					}else{
						$host_template = 0;
					}

					$save["id"]                = 0;
					$save["report_id"]         = $nectar_id;
					$save["item_type"]         = NECTAR_ITEM_GRAPH;
					$save["tree_id"]           = 0;
					$save["branch_id"]         = 0;
					$save["tree_cascade"]      = '';
					$save["graph_name_regexp"] = '';
					$save["host_template_id"]  = $host_template;
					$save["host_id"]           = $graph_data["host_id"];
					$save["graph_template_id"] = $graph_data["graph_template_id"];
					$save["local_graph_id"]    = $local_graph_id;
					$save["timespan"]          = $_POST["timespan"];
					$save["align"]             = $_POST["alignment"];
					$save["item_text"]         = '';
					$save["font_size"]         = $nectar["font_size"];
					$save["sequence"]          = $sequence;

					$id = sql_save($save, 'plugin_nectar_items');
					if ($id) {
						$message .= "Created Nectar Graph Report Item '<i>" . get_graph_title($local_graph_id) . "</i>'<br>";
					}else{
						$message .= "Failed Adding Nectar Graph Report Item '<i>" . get_graph_title($local_graph_id) . "</i>' Already Exists<br>";
					}
				}else{
					$message .= "Skipped Nectar Graph Report Item '<i>" . get_graph_title($local_graph_id) . "</i>' Already Exists<br>";
				}
			}
			}
		}

		if (strlen($message)) {
			$_SESSION['nectar_message'] = "$message";
		}
		raise_message('nectar_message');
	}else{
		return $action;
	}
}

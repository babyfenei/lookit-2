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

$guest_account = true;
chdir('../../');
include("./include/auth.php");
include($config["base_path"] . "/plugins/nectar/nectar_webapi.php");
define("MAX_DISPLAY_PAGES", 21);

/* set default action */
if (!isset($_REQUEST["action"])) { $_REQUEST["action"] = ""; }

/* check edit/alter permissions */
if (!isset($_SESSION["sess_nectar_level"])) {
	$_SESSION["sess_nectar_level"] = nectar_permissions();
}

if (isset($_GET["id"]) && $_SESSION["sess_nectar_level"] != NECTAR_PERM_ADMIN) {
	$user_id = db_fetch_cell("SELECT user_id FROM plugin_nectar WHERE id=" . $_GET["id"]);

	if (($user_id != '') && ($user_id != $_SESSION["sess_user_id"])) {
		echo "FATAL: YOU DO NOT HAVE PERMISSION TO EDIT THIS TEMPLATE";
		exit;
	}
}

switch ($_REQUEST["action"]) {
	case 'save':
		nectar_form_save();

		break;
	case 'send':
		nectar_send($_GET["id"]);

		header("Location: nectar.php?action=edit&tab=" . $_GET["tab"] . "&id=" . $_GET["id"]);
		break;
	case 'actions':
		nectar_form_actions();

		break;
	case 'item_movedown':
		nectar_item_movedown();

		header("Location: nectar.php?action=edit&tab=items&id=" . $_GET["id"]);
		break;
	case 'item_moveup':
		nectar_item_moveup();

		header("Location: nectar.php?action=edit&tab=items&id=" . $_GET["id"]);
		break;
	case 'item_remove':
		nectar_item_remove();

		header("Location: nectar.php?action=edit&tab=items&id=" . $_GET["id"]);
		break;
	case 'item_edit':
		include_once($config['base_path'] . "/plugins/nectar/top_general_header.php");

		display_output_messages();

		nectar_item_edit();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
	case 'remove':
		nectar_remove();

		header ("Location: nectar.php");
		break;
	case 'edit':
		include_once($config['base_path'] . "/plugins/nectar/top_general_header.php");

		display_output_messages();

		nectar_edit();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
	default:
		include_once($config['base_path'] . "/plugins/nectar/top_general_header.php");

		display_output_messages();

		nectar();

		include_once($config['include_path'] . "/bottom_footer.php");
		break;
}

<?php
/**
 * ispCP ω (OMEGA) a Virtual Hosting Control System
 *
 * @copyright 	2001-2006 by moleSoftware GmbH
 * @copyright 	2006-2010 by ispCP | http://isp-control.net
 * @version 	SVN: $Id$
 * @link 		http://isp-control.net
 * @author 		ispCP Team
 *
 * @license
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * The Original Code is "VHCS - Virtual Hosting Control System".
 *
 * The Initial Developer of the Original Code is moleSoftware GmbH.
 * Portions created by Initial Developer are Copyright (C) 2001-2006
 * by moleSoftware GmbH. All Rights Reserved.
 * Portions created by the ispCP Team are Copyright (C) 2006-2010 by
 * isp Control Panel. All Rights Reserved.
 */

require '../include/ispcp-lib.php';

check_login(__FILE__);

$cfg = ispCP_Registry::get('Config');

$tpl = new ispCP_pTemplate();
$tpl->define_dynamic('page', $cfg->RESELLER_TEMPLATE_PATH . '/alias_add.tpl');
$tpl->define_dynamic('page_message', 'page');
$tpl->define_dynamic('logged_from', 'page');
$tpl->define_dynamic('user_entry', 'page');
$tpl->define_dynamic('ip_entry', 'page');

$tpl->assign(
	array(
		'THEME_COLOR_PATH' => "../themes/{$cfg->USER_INITIAL_THEME}",
		'THEME_CHARSET' => tr('encoding'),
		'ISP_LOGO' => get_logo($_SESSION['user_id'])
	)
);

$reseller_id = $_SESSION['user_id'];

/**
 * static page messages.
 */

gen_reseller_mainmenu($tpl, $cfg->RESELLER_TEMPLATE_PATH . '/main_menu_users_manage.tpl');
gen_reseller_menu($tpl, $cfg->RESELLER_TEMPLATE_PATH . '/menu_users_manage.tpl');

gen_logged_from($tpl);

$tpl->assign(
	array(
		'TR_CLIENT_ADD_ALIAS_PAGE_TITLE' => tr('ispCP Reseller: Add Alias'),
		'TR_MANAGE_DOMAIN_ALIAS' => tr('Manage domain alias'),
		'TR_ADD_ALIAS' => tr('Add domain alias'),
		'TR_DOMAIN_NAME' => tr('Domain name'),
		'TR_DOMAIN_ACCOUNT' => tr('User account'),
		'TR_MOUNT_POINT' => tr('Directory mount point'),
		'TR_DOMAIN_IP' => tr('Domain IP'),
		'TR_FORWARD' => tr('Forward to URL'),
		'TR_ADD' => tr('Add alias'),
		'TR_DMN_HELP' => tr("You do not need 'www.' ispCP will add it on its own."),
		'TR_JS_EMPTYDATA' => tr("Empty data or wrong field!"),
		'TR_JS_WDNAME' => tr("Wrong domain name!"),
		'TR_JS_MPOINTERROR' => tr("Please write mount point!"),
		'TR_ENABLE_FWD' => tr("Enable Forward"),
		'TR_ENABLE' => tr("Enable"),
		'TR_DISABLE' => tr("Disable"),
		'TR_PREFIX_HTTP' => 'http://',
		'TR_PREFIX_HTTPS' => 'https://',
		'TR_PREFIX_FTP' => 'ftp://'
	)
);

list($rdmn_current, $rdmn_max,
	$rsub_current, $rsub_max,
 	$rals_current, $rals_max,
 	$rmail_current, $rmail_max,
 	$rftp_current, $rftp_max,
 	$rsql_db_current, $rsql_db_max,
 	$rsql_user_current, $rsql_user_max,
 	$rtraff_current, $rtraff_max,
 	$rdisk_current, $rdisk_max
 	) = get_reseller_default_props($sql, $_SESSION['user_id']);

if ($rals_max != 0 && $rals_current >= $rals_max) {
	$_SESSION['almax'] = '_yes_';
}

if (!check_reseller_permissions($reseller_id, 'alias') ||
	isset($_SESSION['almax'])) {
	user_goto('alias.php');
}

$err_txt = '_off_';
if (isset($_POST['uaction']) && $_POST['uaction'] === 'add_alias') {
	add_domain_alias($sql, $err_txt);
} else {
	// Init fields
	init_empty_data();
	$tpl->assign("PAGE_MESSAGE", "");
}

gen_al_page($tpl, $_SESSION['user_id']);
gen_page_msg($tpl, $err_txt);
// gen_page_message($tpl);
$tpl->parse('PAGE', 'page');

$tpl->prnt();

if ($cfg->DUMP_GUI_DEBUG) {
	dump_gui_debug();
}
// Begin function declaration lines

function init_empty_data() {
	global $cr_user_id, $alias_name, $domain_ip, $forward, $mount_point;

	$cr_user_id = $alias_name = $domain_ip = $forward = $mount_point = '';
} // End of init_empty_data()

/**
 * Show data fields
 */
function gen_al_page(&$tpl, $reseller_id) {
	// NXW Some unused variables so...
	/*
	global $cr_user_id, $alias_name, $domain_ip, $forward, $forward_prefix,
	$mount_point;
	*/

	global $alias_name, $forward, $forward_prefix, $mount_point;

	$sql = ispCP_Registry::get('Db');
	$cfg = ispCP_Registry::get('Config');

	// NXW: Unused variables so...
	/*
	list($udmn_current, $udmn_max, $udmn_uf,
		$usub_current, $usub_max, $usub_uf,
		$uals_current, $uals_max, $uals_uf,
		$umail_current, $umail_max, $umail_uf,
		$uftp_current, $uftp_max, $uftp_uf,
		$usql_db_current, $usql_db_max, $usql_db_uf,
		$usql_user_current, $usql_user_max, $usql_user_uf,
		$utraff_current, $utraff_max, $utraff_uf,
		$udisk_current, $udisk_max, $udisk_uf
	) = generate_reseller_user_props($reseller_id);
	*/
	list(,,,,,,$uals_current) = generate_reseller_user_props($reseller_id);

	// NXW: Unused variables so ...
	/*
	list($rdmn_current, $rdmn_max,
		$rsub_current, $rsub_max,
		$rals_current, $rals_max,
		$rmail_current, $rmail_max,
		$rftp_current, $rftp_max,
		$rsql_db_current, $rsql_db_max,
		$rsql_user_current, $rsql_user_max,
		$rtraff_current, $rtraff_max,
		$rdisk_current, $rdisk_max
	) = get_reseller_default_props($sql, $reseller_id);
	*/
	list(,,,,,$rals_max) = get_reseller_default_props($sql, $reseller_id);

	if ($uals_current >= $rals_max && $rals_max != "0") {
		$_SESSION['almax'] = '_yes_';
		user_goto('alias.php');
	}
	if (isset($_POST['status']) && $_POST['status'] == 1) {
		$forward_prefix = clean_input($_POST['forward_prefix']);
		if ($_POST['status'] == 1) {
			$check_en = $cfg->HTML_CHECKED;
			$check_dis = '';
			$forward = strtolower(clean_input($_POST['forward']));
			$tpl->assign(
				array(
					'READONLY_FORWARD'	=> '',
					'DISABLE_FORWARD'	=> ''
				)
			);
		} else {
			$check_en = '';
			$check_dis = $cfg->HTML_CHECKED;
			$forward = '';
			$tpl->assign(
				array(
					'READONLY_FORWARD'	=> $cfg->HTML_READONLY,
					'DISABLE_FORWARD'	=> $cfg->HTML_DISABLED
				)
			);
		}
		$tpl->assign(
			array(
				'HTTP_YES'	=> ($forward_prefix === 'http://') ? $cfg->HTML_SELECTED : '',
				'HTTPS_YES'	=> ($forward_prefix === 'https://') ? $cfg->HTML_SELECTED : '',
				'FTP_YES'	=> ($forward_prefix === 'ftp://') ? $cfg->HTML_SELECTED : ''
			)
		);
	} else {
		$check_en = '';
		$check_dis = $cfg->HTML_CHECKED;
		$forward = '';
		$tpl->assign(
			array(
				'READONLY_FORWARD'	=> $cfg->HTML_READONLY,
				'DISABLE_FORWARD'	=> $cfg->HTML_DISABLED,
				'HTTP_YES'			=>	'',
				'HTTPS_YES'			=>	'',
				'FTP_YES'			=>	''
			)
		);
	}

	$tpl->assign(
		array(
			'DOMAIN' => tohtml(decode_idna($alias_name)),
			'MP' => tohtml(decode_idna($mount_point)),
			'FORWARD' => tohtml($forward),
			'CHECK_EN' => $check_en,
			'CHECK_DIS' => $check_dis,
		)
	);

	generate_ip_list($tpl, $reseller_id);
	gen_users_list($tpl, $reseller_id);
} // End of gen_al_page()

function add_domain_alias(&$sql, &$err_al) {

	global $cr_user_id, $alias_name, $domain_ip, $forward, $forward_prefix,
		$mount_point, $validation_err_msg;
	$cfg = ispCP_Registry::get('Config');

	// NXW: Unused variable so...
	// $cr_user_id = $dmn_id = $_POST['usraccounts'];
	$cr_user_id = $_POST['usraccounts'];

	// Should be perfomed after domain names syntax validation now
	//$alias_name = encode_idna(strtolower($_POST['ndomain_name']));

	$alias_name = strtolower($_POST['ndomain_name']);
	$mount_point = array_encode_idna(strtolower($_POST['ndomain_mpoint']), true);

	if ($_POST['status'] == 1) {
		$forward = strtolower(clean_input($_POST['forward']));
		$forward_prefix = clean_input($_POST['forward_prefix']);
	} else {
		$forward = 'no';
		$forward_prefix = '';
	}

	$query = "
		SELECT
			`domain_ip_id`
		FROM
			`domain`
		WHERE
			`domain_id` = ?
	";

	$rs = exec_query($sql, $query, $cr_user_id);
	$domain_ip = $rs->fields['domain_ip_id'];

	// $mount_point = "/".$mount_point;

	// First check if input string is a valid domain names
	if (!validates_dname($alias_name)) {
		$err_al = $validation_err_msg;
		return;
	}

	// Should be perfomed after domain names syntax validation now
	$alias_name = encode_idna($alias_name);

	if (ispcp_domain_exists($alias_name, $_SESSION['user_id'])) {
		$err_al = tr('Domain with that name already exists on the system!');
	} else if (!validates_mpoint($mount_point) && $mount_point != '/') {
		$err_al = tr("Incorrect mount point syntax");
	} else if ($alias_name == $cfg->BASE_SERVER_VHOST) {
		$err_al = tr('Master domain cannot be used!');
	} else if ($_POST['status'] == 1) {
		if (substr_count($forward, '.') <= 2) {
			$ret = validates_dname($forward);
		} else {
			$ret = validates_dname($forward, true);
		}
		if (!$ret) {
			$err_al = tr("Wrong domain part in forward URL!");
		} else {
			$forward = encode_idna($forward_prefix.$forward);
		}
	} else {
		// now let's fix the mountpoint
		$mount_point = array_decode_idna($mount_point, true);

		$res = exec_query($sql, "SELECT `domain_id` FROM `domain_aliasses` WHERE `alias_name` = ?", $alias_name);
		$res2 = exec_query($sql, "SELECT `domain_id` FROM `domain` WHERE `domain_name` = ?", $alias_name);
		if ($res->rowCount() > 0 || $res2->rowCount() > 0) {
			// we already have domain with this name
			$err_al = tr("Domain with this name already exist");
		}

		$query = "SELECT COUNT(`subdomain_id`) AS cnt FROM `subdomain` WHERE `domain_id` = ? AND `subdomain_mount` = ?";
		$subdomres = exec_query($sql, $query, array($cr_user_id, $mount_point));
		$subdomdata = $subdomres->fetchRow();
		$query = "SELECT COUNT(`subdomain_alias_id`) AS alscnt FROM `subdomain_alias` WHERE `alias_id` IN (SELECT `alias_id` FROM `domain_aliasses` WHERE `domain_id` = ?) AND `subdomain_alias_mount` = ?";
		$alssubdomres = exec_query($sql, $query, array($cr_user_id, $mount_point));
		$alssubdomdata = $alssubdomres->fetchRow();
		if ($subdomdata['cnt'] > 0 || $alssubdomdata['alscnt'] > 0) {
			$err_al = tr("There is a subdomain with the same mount point!");
		}
	}

	if ('_off_' !== $err_al) {
		return;
	}

	// Begin add new alias domain
	$alias_name = htmlspecialchars($alias_name, ENT_QUOTES, "UTF-8");
	check_for_lock_file();

	exec_query($sql,
		"INSERT INTO `domain_aliasses` (`domain_id`, `alias_name`, `alias_mount`, ".
		 "`alias_status`, `alias_ip_id`, `url_forward`) VALUES (?, ?, ?, ?, ?, ?)",
		array($cr_user_id, $alias_name, $mount_point, $cfg->ITEM_ADD_STATUS, $domain_ip, $forward));

	$als_id = $sql->insertId();

	update_reseller_c_props(get_reseller_id($cr_user_id));

	$query = 'SELECT `email` FROM `admin` WHERE `admin_id` = ? LIMIT 1';
	$rs = exec_query($sql, $query, who_owns_this($cr_user_id, 'dmn_id'));
	$user_email = $rs->fields['email'];

	// Create the 3 default addresses if wanted
	if ($cfg->CREATE_DEFAULT_EMAIL_ADDRESSES)
		client_mail_add_default_accounts($cr_user_id, $user_email, $alias_name, 'alias', $als_id);

	send_request();
	$admin_login = $_SESSION['user_logged'];
	write_log("$admin_login: add domain alias: $alias_name");

	$_SESSION["aladd"] = '_yes_';
	user_goto('alias.php');
} // End of add_domain_alias();

function gen_users_list(&$tpl, $reseller_id) {
	global $cr_user_id;
	$sql = ispCP_Registry::get('Db');
	$cfg = ispCP_Registry::get('Config');

	$query = "
		SELECT
			`admin_id`
		FROM
			`admin`
		WHERE
			`admin_type` = 'user'
		AND
			`created_by` = ?
		ORDER BY
			`admin_name`
	";

	$ar = exec_query($sql, $query, $reseller_id);

	if ($ar->rowCount() == 0) {
		set_page_message(tr('There is no user records for this reseller to add an alias for.'));
		user_goto('alias.php');
		$tpl->assign('USER_ENTRY', '');
		return false;
	}

	$i = 1;
	while ($ad = $ar->fetchRow()) { // Process all founded users
		$admin_id = $ad['admin_id'];
		$selected = '';
		// Get domain data
		$query = "
			SELECT
				`domain_id`,
				IFNULL(`domain_name`, '') AS domain_name
			FROM
				`domain`
			WHERE
				`domain_admin_id` = ?
		";

		$dr = exec_query($sql, $query, $admin_id);
		$dd = $dr->fetchRow();

		$domain_id = $dd['domain_id'];
		$domain_name = $dd['domain_name'];

		if ((('' == $cr_user_id) && ($i == 1))
			|| ($cr_user_id == $domain_id)) {
			$selected = $cfg->HTML_SELECTED;
		}

		$domain_name = decode_idna($domain_name);

		$tpl->assign(
			array(
				'USER' => $domain_id,
				'USER_DOMAIN_ACCOUN' => tohtml($domain_name),
				'SELECTED' => $selected
			)
		);
		$i++;
		$tpl->parse('USER_ENTRY', '.user_entry');
	} // end of loop
	return true;
} // End of gen_users_list()

function gen_page_msg(&$tpl, $error_txt) {
	if ($error_txt != '_off_') {
		$tpl->assign('MESSAGE', $error_txt);
		$tpl->parse('PAGE_MESSAGE', 'page_message');
	} else {
		$tpl->assign('PAGE_MESSAGE', '');
	}
} // End of gen_page_msg()

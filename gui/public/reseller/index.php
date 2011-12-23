<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 *
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
 *
 * Portions created by the ispCP Team are Copyright (C) 2006-2010 by
 * isp Control Panel. All Rights Reserved.
 *
 * Portions created by the i-MSCP Team are Copyright (C) 2010-2011 by
 * i-MSCP a internet Multi Server Control Panel. All Rights Reserved.
 *
 * @category	iMSCP
 * @package		iMSCP_Core
 * @subpackage	Reseller
 * @copyright   2001-2006 by moleSoftware GmbH
 * @copyright   2006-2010 by ispCP | http://isp-control.net
 * @copyright   2010-2011 by i-msCP | http://i-mscp.net
 * @link		http://i-mscp.net
 * @author	  ispCP Team
 * @author	  i-MSCP Team
 */

/************************************************************************************
 * Script functions
 */

/**
 * Generates support questions notice for reseller.
 *
 * Notice reseller about any new support questions and answers.
 *
 * @return void
 */
function reseller_generateSupportQuestionsMessage()
{
	$query = "
        SELECT
            count(`ticket_id`) `nbQuestions`
        FROM
            `tickets`
        WHERE
            `ticket_to` = ?
        AND
            `ticket_status` IN (1, 4)
        AND
            `ticket_reply` = 0
    ";
	$stmt = exec_query($query, $_SESSION['user_id']);

	$nbQuestions = $stmt->fields['nbQuestions'];

	if ($nbQuestions != 0) {
		set_page_message(tr('You have received <b>%d</b> new support questions.', $nbQuestions));
	}
}

/**
 * Generates message for new orders.
 *
 * @return void
 */
function reseller_generateOrdersMessage()
{
	/** @var $cfg iMSCP_Config_Handler_File */
	$cfg = iMSCP_Registry::get('config');

	$query = "SELECT COUNT(`id`) `nbAccountOrders` FROM `orders` WHERE `user_id` = ? AND `status` = ?";
	$stmt = exec_query($query, array($_SESSION['user_id'], $cfg->ITEM_ORDER_CONFIRMED_STATUS));

	$nbAccountOrders = $stmt->fields['nbAccountOrders'];

	if ($nbAccountOrders) {
		set_page_message(tr('You have %d new accounts %s.', $nbAccountOrders, ($nbAccountOrders > 1) ? tr('orders') : tr('order')));
	}
}

/**
 * Generates message for new domain aliases orders.
 *
 * @return void
 */
function reseller_generateOrdersAliasesMessage()
{
	/** @var $cfg iMSCP_Config_Handler_File */
	$cfg = iMSCP_Registry::get('config');

	$query = "
		SELECT
			COUNT(`alias_id`) `nbOrdersAliases`
		FROM
			`domain_aliasses` `t1`
		INNER JOIN
			`domain` `t2` ON(`t2`.`domain_created_id` = ?)
		WHERE
			`t1`.`domain_id` = `t2`.`domain_id`
		AND
			`t1`.`alias_status` = ?
	";
	$stmt = exec_query($query, array($_SESSION['user_id'], $cfg->ITEM_ORDERED_STATUS));

	$nbOrdersAliases = $stmt->fields['nbOrdersAliases'];

	if ($nbOrdersAliases) {
		set_page_message(tr('You have %d new domain alias %s.', $nbOrdersAliases, ($nbOrdersAliases > 1) ? tr('orders') : tr('order')));
	}
}

/**
 * Generates traffic usage bar.
 *
 * @param iMSCP_pTemplate $tpl Template engine
 * @param int $usage Current traffic usage
 * @param int $maxUsage Traffic max usage
 * @param int $barMax Bar max
 * @return void
 */
function reseller_generateTrafficUsageBar($tpl, $usage, $maxUsage, $barMax)
{
	// Is limited traffic usage for reseller ?
	if ($maxUsage != 0) {
		list($percent, $bar) = calc_bars($usage, $maxUsage, $barMax);
		$trafficUsageData = tr('%1$s%% [%2$s of %3$s]', $percent, numberBytesHuman($usage), numberBytesHuman($maxUsage));
	} else {
		$percent = $bar = 0;
		$trafficUsageData = tr('%1$s%% [%2$s of unlimited]', $percent, numberBytesHuman($usage), numberBytesHuman($maxUsage));
	}

	$tpl->assign(
		array(
			 'TRAFFIC_USAGE_DATA' => $trafficUsageData,
			 'TRAFFIC_BARS' => $bar,
			 'TRAFFIC_PERCENT' => $percent > 100 ? 100 : $percent));
}

/**
 * Generates disk usage bar.
 *
 * @param iMSCP_pTemplate $tpl Template engine
 * @param $usage Disk usage
 * @param $maxUsage Max disk usage
 * @param $barMax Bar max
 * @return void
 */
function reseller_generateDiskUsageBar($tpl, $usage, $maxUsage, $barMax)
{
	// is Limited disk usage for reseller ?
	if ($maxUsage != 0) {
		list($percent, $bar) = calc_bars($usage, $maxUsage, $barMax);
		$diskUsageData = tr('%1$s%% [%2$s of %3$s]', $percent, numberBytesHuman($usage), numberBytesHuman($maxUsage));
	} else {
		$percent = $bar = 0;
		$diskUsageData = tr('%1$s%% [%2$s of unlimited]', $percent, numberBytesHuman($usage));
	}

	$tpl->assign(
		array(
			 'DISK_USAGE_DATA' => $diskUsageData,
			 'DISK_BARS' => $bar,
			 'DISK_PERCENT' => $percent > 100 ? 100 : $percent));
}

/**
 * Generates page data.
 *
 * @param iMSCP_pTemplate $tpl Template engine
 * @param $resellerId Reseller unique identifier
 * @param $resellerName Reseller name
 * @return void
 */
function reseller_generatePageData($tpl, $resellerId, $resellerName)
{
	$resellerProperties = get_reseller_default_props($resellerId);

	list(
		$udmnCurrent, , , $usubCurrent, , , $ualsCurrent, , , $umailCurrent, , ,
		$uftpCurrent, , , $usqlDbCurrent, , , $usqlUserCurrent, , , $utraffCurrent, , ,
		$udiskCurrent
		) = generate_reseller_user_props($resellerId);

	// Convert into Mib values
	$rtraffMax = $resellerProperties['max_traff_amnt'] * 1024 * 1024;
	$rdiskMax = $resellerProperties['max_disk_amnt'] * 1024 * 1024;

	reseller_generateTrafficUsageBar($tpl, $utraffCurrent, $rtraffMax, 400);
	reseller_generateDiskUsageBar($tpl, $udiskCurrent, $rdiskMax, 400);

	if ($rtraffMax > 0 && $utraffCurrent > $rtraffMax) {
		$tpl->assign('TR_TRAFFIC_WARNING', tr('You are exceeding your traffic limit.'));
	} else {
		$tpl->assign('TRAFFIC_WARNING_MESSAGE', '');
	}

	if ($rdiskMax > 0 && $udiskCurrent > $rdiskMax) {
		$tpl->assign('TR_DISK_WARNING', tr('You are exceeding your disk limit.'));
	} else {
		$tpl->assign('DISK_WARNING_MESSAGE', '');
	}

	$tpl->assign(
		array(
			 'TR_ACCOUNT_OVERVIEW' => tr('Account overview'),
			 'TR_ACCOUNT_LIMITS' => tr('Account limits'),
			 'TR_FEATURES' => tr('Features'),
			 'ACCOUNT_NAME' => tr('Account name'),
			 'GENERAL_INFO' => tr('General information'),
			 'DOMAINS' => tr('Domain accounts'),
			 'SUBDOMAINS' => tr('Subdomains'),
			 'ALIASES' => tr('Aliases'),
			 'MAIL_ACCOUNTS' => tr('Mail accounts'),
			 'TR_FTP_ACCOUNTS' => tr('FTP accounts'),
			 'SQL_DATABASES' => tr('SQL databases'),
			 'SQL_USERS' => tr('SQL users'),
			 'TRAFFIC' => tr("Traffic"),
			 'DISK' => tr('Disk'),
			 'RESELLER_NAME' => tohtml($resellerName),
			 'DMN_MSG' => ($resellerProperties['max_dmn_cnt'])
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $udmnCurrent, $resellerProperties['current_dmn_cnt'], $resellerProperties['max_dmn_cnt'])
				 : tr('%1$d / %2$d of <b>unlimited</b>', $udmnCurrent, $resellerProperties['current_dmn_cnt']),
			 'SUB_MSG' => ($resellerProperties['max_sub_cnt'] > 0)
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $usubCurrent, $resellerProperties['current_sub_cnt'], $resellerProperties['max_sub_cnt'])
				 : (($resellerProperties['max_sub_cnt'] === '-1') ? tr('<b>disabled</b>')
					 : tr('%1$d / %2$d of <b>unlimited</b>', $usubCurrent, $resellerProperties['current_sub_cnt'])),
			 'ALS_MSG' => ($resellerProperties['max_als_cnt'] > 0)
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $ualsCurrent, $resellerProperties['current_als_cnt'], $resellerProperties['max_als_cnt'])
				 : (($resellerProperties['max_als_cnt'] === '-1') ? tr('<b>disabled</b>')
					 : tr('%1$d / %2$d of <b>unlimited</b>', $ualsCurrent, $resellerProperties['current_als_cnt'])),
			 'MAIL_MSG' => ($resellerProperties['max_mail_cnt'] > 0)
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $umailCurrent, $resellerProperties['current_mail_cnt'], $resellerProperties['max_mail_cnt'])
				 : (($resellerProperties['max_mail_cnt'] === '-1') ? tr('<b>disabled</b>')
					 : tr('%1$d / %2$d of <b>unlimited</b>', $umailCurrent, $resellerProperties['current_mail_cnt'])),
			 'FTP_MSG' => ($resellerProperties['max_ftp_cnt'] > 0)
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $uftpCurrent, $resellerProperties['current_ftp_cnt'], $resellerProperties['max_ftp_cnt'])
				 : (($resellerProperties['max_ftp_cnt'] === '-1') ? tr('<b>disabled</b>')
					 : tr('%1$d / %2$d of <b>unlimited</b>', $uftpCurrent, $resellerProperties['current_ftp_cnt'])),
			 'SQL_DB_MSG' => ($resellerProperties['max_sql_db_cnt'] > 0)
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $usqlDbCurrent, $resellerProperties['current_sql_db_cnt'], $resellerProperties['max_sql_db_cnt'])
				 : (($resellerProperties['max_sql_db_cnt'] === '-1') ? tr('<b>disabled</b>')
					 : tr('%1$d / %2$d of <b>unlimited</b>', $usqlDbCurrent, $resellerProperties['current_sql_db_cnt'])),
			 'SQL_USER_MSG' => ($resellerProperties['max_sql_db_cnt'] > 0)
				 ? tr('%1$d / %2$d of <b>%3$d</b>', $usqlUserCurrent, $resellerProperties['current_sql_user_cnt'], $resellerProperties['max_sql_user_cnt'])
				 : (($resellerProperties['max_sql_user_cnt'] === '-1') ? tr('<b>disabled</b>')
					 : tr('%1$d / %2$d of <b>unlimited</b>', $usqlUserCurrent, $resellerProperties['current_sql_user_cnt'])),
			 'TR_SUPPORT' => tr('Support system'),
			 'SUPPORT_STATUS' => ($resellerProperties['support_system'] == 'yes')
				 ? '<span style="color:green;">' . tr('Enabled') . '</span>'
				 : '<span style="color:red;">' . tr('Disabled') . '</span>',
			 'TR_PHP_EDITOR' => tr('PHP Editor'),
			 'PHP_EDITOR_STATUS' => ($resellerProperties['php_ini_system'] == 'yes')
				 ? '<span style="color:green;">' . tr('Enabled') . '</span>'
				 : '<span style="color:red;">' . tr('Disabled') . '</span>',
			 'TR_APS' => tr('Softwares installer'),
			 'APS_STATUS' => ($resellerProperties['software_allowed'] == 'yes')
				 ? '<span style="color:green;">' . tr('Enabled') . '</span>'
				 : '<span style="color:red;">' . tr('Disabled') . '</span>'));
}

/************************************************************************************
 * Main script
 */

// Include core library
require 'imscp-lib.php';

iMSCP_Events_Manager::getInstance()->dispatch(iMSCP_Events::onResellerScriptStart);

/** @var $cfg iMSCP_Config_Handler_File */
$cfg = iMSCP_Registry::get('config');

check_login(__FILE__, $cfg->PREVENT_EXTERNAL_LOGIN_RESELLER);

$tpl = new iMSCP_pTemplate();
$tpl->define_dynamic(
	array(
		'layout' => $cfg->RESELLER_TEMPLATE_PATH . '/../shared/layouts/ui.tpl',
		'page' => $cfg->RESELLER_TEMPLATE_PATH . '/index.tpl',
		'page_message' => 'page',
		'traffic_warning_message' => 'page',
		'disk_warning_message' => 'page'));

$tpl->assign(
	array(
		 'THEME_CHARSET' => tr('encoding'),
		 'TR_PAGE_TITLE' => tr('i-MSCP - Reseller / General information'),
		 'THEME_COLOR_PATH' => "../themes/{$cfg->USER_INITIAL_THEME}",
		 'ISP_LOGO' => layout_getUserLogo(),
		 'TR_SAVE' => tr('Save'),
		 'TR_TRAFFIC_USAGE' => tr('Traffic usage'),
		 'TR_DISK_USAGE' => tr('Disk usage')));

generateNavigation($tpl);
reseller_generateSupportQuestionsMessage();
reseller_generateOrdersMessage();
reseller_generateOrdersAliasesMessage();
reseller_generatePageData($tpl, $_SESSION['user_id'], $_SESSION['user_logged']);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');

iMSCP_Events_Manager::getInstance()->dispatch(iMSCP_Events::onResellerScriptEnd, new iMSCP_Events_Response($tpl));

$tpl->prnt();

unsetMessages();

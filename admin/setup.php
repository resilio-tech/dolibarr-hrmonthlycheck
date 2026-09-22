<?php
/* Copyright (C) 2026 Resilio SA
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    hrmonthlycheck/admin/setup.php
 * \ingroup hrmonthlycheck
 * \brief   HrMonthlyCheck setup page.
 */

if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1');
}

$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
require_once '../lib/hrmonthlycheck.lib.php';

$langs->loadLangs(array('admin', 'holiday', 'hrmonthlycheck@hrmonthlycheck'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');


/*
 * Actions
 */

if ($action == 'update') {
	$channel = GETPOST('channel', 'aZ09');
	if (!in_array($channel, array('email', 'zulip', 'both'))) {
		$channel = 'email';
	}
	dolibarr_set_const($db, 'HRMONTHLYCHECK_CHANNEL', $channel, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_WORKRATE_FIELD', GETPOST('workrate_field', 'aZ09'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_BYOD_FIELD', GETPOST('byod_field', 'aZ09'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_UNPAID_LEAVE_TYPES', implode(',', array_map('intval', GETPOST('unpaid_leave_types', 'array:int'))), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_ZULIP_SITE', GETPOST('zulip_site', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_ZULIP_BOT_EMAIL', GETPOST('zulip_email', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_ZULIP_HR_STREAM', GETPOST('zulip_stream', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'HRMONTHLYCHECK_ZULIP_HR_TOPIC', GETPOST('zulip_topic', 'alphanohtml'), 'chaine', 0, '', $conf->entity);
	$apikey = trim((string) GETPOST('zulip_apikey', 'none'));
	if ($apikey !== '') {
		dolibarr_set_const($db, 'HRMONTHLYCHECK_ZULIP_BOT_APIKEY', dolEncrypt($apikey), 'chaine', 0, '', $conf->entity);
	}
	setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
}


/*
 * View
 */

$form = new Form($db);

$extrafields = new ExtraFields($db);
$userFields = $extrafields->fetch_name_optionals_label('user');

$holiday = new Holiday($db);
$leaveTypes = array();
foreach ($holiday->getTypes(1) as $type) {
	$leaveTypes[$type['rowid']] = $langs->trans($type['code']) != $type['code'] ? $langs->trans($type['code']) : $type['label'];
}

$page_name = "HrMonthlyCheckSetup";

llxHeader('', $langs->trans($page_name), '', '', 0, 0, '', '', '', 'mod-hrmonthlycheck page-admin');

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

$head = hrmonthlycheckAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, 'hrmonthlycheck@hrmonthlycheck');

print '<span class="opacitymedium">'.$langs->trans("HrMonthlyCheckSetupPage").'</span><br><br>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

$channels = array(
	'email' => $langs->trans('Email'),
	'zulip' => 'Zulip',
	'both' => $langs->trans('HrMonthlyCheckEmailAndZulip'),
);
print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckChannel").'</td>';
print '<td>'.$form->selectarray('channel', $channels, getDolGlobalString('HRMONTHLYCHECK_CHANNEL', 'email')).'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckWorkRateField").'</td>';
print '<td>'.$form->selectarray('workrate_field', $userFields, getDolGlobalString('HRMONTHLYCHECK_WORKRATE_FIELD'), 1, 0, 0, '', 1).'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckByodField").'</td>';
print '<td>'.$form->selectarray('byod_field', $userFields, getDolGlobalString('HRMONTHLYCHECK_BYOD_FIELD'), 1, 0, 0, '', 1).'</td></tr>';

$selectedLeaveTypes = array_filter(explode(',', getDolGlobalString('HRMONTHLYCHECK_UNPAID_LEAVE_TYPES')));
print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckUnpaidLeaveTypes").'</td>';
print '<td>'.$form->multiselectarray('unpaid_leave_types', $leaveTypes, $selectedLeaveTypes, 0, 0, 'minwidth300').'</td></tr>';

print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("HrMonthlyCheckZulipSetup").'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckZulipSite").'</td>';
print '<td><input type="text" name="zulip_site" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('HRMONTHLYCHECK_ZULIP_SITE')).'" placeholder="https://your-org.zulipchat.com"></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckZulipBotEmail").'</td>';
print '<td><input type="text" name="zulip_email" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('HRMONTHLYCHECK_ZULIP_BOT_EMAIL')).'" placeholder="hr-bot@your-org.zulipchat.com"></td></tr>';

$apikeyPlaceholder = getDolGlobalString('HRMONTHLYCHECK_ZULIP_BOT_APIKEY') !== '' ? $langs->trans("HrMonthlyCheckKeepCurrentSecret") : '';
print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckZulipApiKey").'</td>';
print '<td><input type="password" name="zulip_apikey" autocomplete="new-password" class="minwidth300" value="" placeholder="'.dol_escape_htmltag($apikeyPlaceholder).'"></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckZulipHrStream").'</td>';
print '<td><input type="text" name="zulip_stream" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('HRMONTHLYCHECK_ZULIP_HR_STREAM')).'"></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("HrMonthlyCheckZulipHrTopic").'</td>';
print '<td><input type="text" name="zulip_topic" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('HRMONTHLYCHECK_ZULIP_HR_TOPIC')).'" placeholder="HR monthly check"></td></tr>';

print '</table>';
print '<div class="center" style="margin-top:10px">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();

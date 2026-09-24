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
 * \file    hrmonthlycheck/answers.php
 * \ingroup hrmonthlycheck
 * \brief   Page where HR reviews the answers of the employees for a month.
 */

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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once __DIR__.'/class/HrMonthlyCheckAnswer.class.php';
require_once __DIR__.'/class/HrMonthlyCheckRecap.class.php';
require_once __DIR__.'/lib/hrmonthlycheck.lib.php';

$langs->loadLangs(array('hrm', 'hrmonthlycheck@hrmonthlycheck'));

$month = GETPOSTINT('month');
$year = GETPOSTINT('year');
$period = ($month > 0 && $year > 0) ? sprintf('%04d%02d', $year, $month) : GETPOST('period', 'aZ09');
if (!hrmonthlycheckIsValidPeriod($period)) {
	$period = hrmonthlycheckCurrentPeriod();
}

if (!isModEnabled('hrmonthlycheck') || !$user->hasRight('hrmonthlycheck', 'read')) {
	accessforbidden();
}

$loader = new HrMonthlyCheckRecap($db);
$recaps = $loader->fetchAll($period);
if ($recaps === null) {
	dol_print_error($db, $loader->error);
	exit;
}


/*
 * View
 */

$formother = new FormOther($db);
$title = $langs->trans('HrMonthlyCheckAnswersTitle', hrmonthlycheckPeriodLabel($period, $langs));

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-hrmonthlycheck page-answers');

print load_fiche_titre($title, '', 'user');

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
print $formother->select_month((int) substr($period, 4, 2), 'month', 0, 1);
print $formother->selectyear((int) substr($period, 0, 4), 'year', 0, 5, 1);
print ' <input type="submit" class="button small" value="'.$langs->trans('Refresh').'">';
print '</form>';
print '<br>';

$changeRequests = array_filter($recaps, function ($recap) {
	return (int) $recap->answer_status === HrMonthlyCheckAnswer::STATUS_CHANGE_REQUESTED;
});
$arrivalsAndDepartures = array_filter($recaps, function ($recap) {
	return $recap->starts_in_period || $recap->ends_in_period;
});

hrmonthlycheckPrintRecapTable($loader, $changeRequests, $langs->trans('HrMonthlyCheckChangeRequests'));
hrmonthlycheckPrintRecapTable($loader, $arrivalsAndDepartures, $langs->trans('HrMonthlyCheckArrivalsAndDepartures'));
hrmonthlycheckPrintRecapTable($loader, $recaps, $langs->trans('HrMonthlyCheckAllEmployees'));

llxFooter();
$db->close();

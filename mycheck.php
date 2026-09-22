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
 * \file    hrmonthlycheck/mycheck.php
 * \ingroup hrmonthlycheck
 * \brief   Page where an employee checks their HR information of the month and answers.
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
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once __DIR__.'/class/HrMonthlyCheckAnswer.class.php';
require_once __DIR__.'/class/HrMonthlyCheckRecap.class.php';
require_once __DIR__.'/lib/hrmonthlycheck.lib.php';

$langs->loadLangs(array('hrmonthlycheck@hrmonthlycheck'));

$action = GETPOST('action', 'aZ09');
$answer = GETPOST('answer', 'aZ09');
$period = GETPOST('period', 'aZ09');
if (!hrmonthlycheckIsValidPeriod($period)) {
	$period = hrmonthlycheckCurrentPeriod();
}

if (!isModEnabled('hrmonthlycheck') || $user->socid > 0) {
	accessforbidden();
}

$loader = new HrMonthlyCheckRecap($db);
$recaps = $loader->fetchAll($period, (int) $user->id);
if ($recaps === null) {
	dol_print_error($db, $loader->error);
	exit;
}
$recap = isset($recaps[(int) $user->id]) ? $recaps[(int) $user->id] : null;
$pageUrl = $_SERVER['PHP_SELF'].'?period='.urlencode($period);


/*
 * Actions
 */

if ($recap && $action == 'confirm_confirm' && GETPOST('confirm', 'alpha') == 'yes') {
	$answerObject = new HrMonthlyCheckAnswer($db);
	if ($answerObject->save((int) $user->id, $period, HrMonthlyCheckAnswer::STATUS_CONFIRMED, '') > 0) {
		setEventMessages($langs->trans('HrMonthlyCheckThanks'), null, 'mesgs');
		header('Location: '.$pageUrl);
		exit;
	}
	setEventMessages($answerObject->error, null, 'errors');
}

if ($recap && $action == 'savechange') {
	$note = GETPOST('note', 'alphanohtml');
	if ($note === '') {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentities('HrMonthlyCheckChangeDescription')), null, 'errors');
		$answer = 'change';
	} else {
		$answerObject = new HrMonthlyCheckAnswer($db);
		if ($answerObject->save((int) $user->id, $period, HrMonthlyCheckAnswer::STATUS_CHANGE_REQUESTED, $note) > 0) {
			setEventMessages($langs->trans('HrMonthlyCheckThanks'), null, 'mesgs');
			header('Location: '.$pageUrl);
			exit;
		}
		setEventMessages($answerObject->error, null, 'errors');
		$answer = 'change';
	}
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('HrMonthlyCheckMyCheckTitle', hrmonthlycheckPeriodLabel($period, $langs));

llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-hrmonthlycheck page-mycheck');

print load_fiche_titre($title, '', 'user');

if (!$recap) {
	print '<div class="opacitymedium">'.$langs->trans('HrMonthlyCheckNotConcerned').'</div>';
	llxFooter();
	$db->close();
	exit;
}

if ($answer == 'confirm') {
	print $form->formconfirm($pageUrl, $langs->trans('HrMonthlyCheckConfirmButton'), $langs->trans('HrMonthlyCheckConfirmQuestion'), 'confirm_confirm', '', 0, 1);
}

print '<span class="opacitymedium">'.$langs->trans('HrMonthlyCheckIntro', hrmonthlycheckPeriodLabel($period, $langs)).'</span><br><br>';

print '<table class="border centpercent tableforfield">';
foreach ($loader->lines($recap, $langs) as $label => $value) {
	print '<tr><td class="titlefield">'.dol_escape_htmltag($label).'</td><td>'.dol_escape_htmltag($value).'</td></tr>';
}
print '<tr><td class="titlefield">'.$langs->trans('HrMonthlyCheckYourAnswer').'</td><td>';
print HrMonthlyCheckAnswer::statusBadge($recap->answer_status, $langs);
if ($recap->date_answer) {
	print ' <span class="opacitymedium">'.dol_print_date($recap->date_answer, 'dayhour', 'tzuser').'</span>';
}
if ((string) $recap->answer_note !== '') {
	print '<br>'.dol_nl2br(dol_escape_htmltag($recap->answer_note, 0, 1));
}
print '</td></tr>';
print '</table>';

if ($answer == 'change') {
	print '<br>';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="savechange">';
	print '<input type="hidden" name="period" value="'.dol_escape_htmltag($period).'">';
	print load_fiche_titre($langs->trans('HrMonthlyCheckChangeButton'), '', '');
	print '<textarea name="note" class="quatrevingtpercent" rows="5" placeholder="'.dol_escape_htmltag($langs->trans('HrMonthlyCheckChangeDescription')).'">';
	print dol_escape_htmltag(GETPOSTISSET('note') ? GETPOST('note', 'alphanohtml') : ((int) $recap->answer_status === HrMonthlyCheckAnswer::STATUS_CHANGE_REQUESTED ? $recap->answer_note : ''), 0, 1);
	print '</textarea>';
	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
	print ' <a class="button button-cancel" href="'.$pageUrl.'">'.$langs->trans('Cancel').'</a>';
	print '</div>';
	print '</form>';
} else {
	print '<div class="tabsAction">';
	print dolGetButtonAction($langs->trans('HrMonthlyCheckConfirmButton'), '', 'default', $pageUrl.'&answer=confirm');
	print dolGetButtonAction($langs->trans('HrMonthlyCheckChangeButton'), '', 'default', $pageUrl.'&answer=change');
	print '</div>';
}

llxFooter();
$db->close();

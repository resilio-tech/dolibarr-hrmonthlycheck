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
 * \file    hrmonthlycheck/lib/hrmonthlycheck.lib.php
 * \ingroup hrmonthlycheck
 * \brief   Library files with common functions for HrMonthlyCheck
 */

/**
 * Prepare admin pages header
 *
 * @return array<int,array<int,string>>
 */
function hrmonthlycheckAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("hrmonthlycheck@hrmonthlycheck");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/hrmonthlycheck/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/hrmonthlycheck/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'hrmonthlycheck@hrmonthlycheck');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'hrmonthlycheck@hrmonthlycheck', 'remove');

	return $head;
}

/**
 * Print a table of employee recaps with their answer.
 *
 * @param HrMonthlyCheckRecap $loader Recap loader
 * @param array<int,stdClass> $recaps Recaps to show
 * @param string              $title  Title of the table
 * @return void
 */
function hrmonthlycheckPrintRecapTable($loader, $recaps, $title)
{
	global $db, $langs;

	$fields = $loader->fields($langs);

	print load_fiche_titre($title.' ('.count($recaps).')', '', '');
	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('Employee').'</th>';
	foreach ($fields as $label) {
		print '<th>'.dol_escape_htmltag($label).'</th>';
	}
	print '<th>'.$langs->trans('HrMonthlyCheckAnswer').'</th>';
	print '</tr>';

	if (empty($recaps)) {
		print '<tr class="oddeven"><td colspan="'.(count($fields) + 2).'"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
	}

	$employee = new User($db);
	foreach ($recaps as $recap) {
		$employee->id = $recap->id;
		$employee->firstname = $recap->firstname;
		$employee->lastname = $recap->lastname;
		$employee->email = $recap->email;
		$employee->status = 1;
		$employee->statut = 1;

		$values = $loader->values($recap, $langs);
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.$employee->getNomUrl(1).'</td>';
		foreach (array_keys($fields) as $key) {
			print '<td>'.dol_escape_htmltag($values[$key]).'</td>';
		}
		print '<td>'.HrMonthlyCheckAnswer::statusBadge($recap->answer_status, $langs);
		if ((string) $recap->answer_note !== '') {
			print '<br>'.dol_nl2br(dol_escape_htmltag($recap->answer_note, 0, 1));
		}
		print '</td>';
		print '</tr>';
	}

	print '</table>';
	print '</div>';
	print '<br>';
}

/**
 * Whether a string is a period in the YYYYMM format.
 *
 * @param string $period Period to check
 * @return bool
 */
function hrmonthlycheckIsValidPeriod($period)
{
	return (bool) preg_match('/^\d{4}(0[1-9]|1[0-2])$/', (string) $period);
}

/**
 * Period of the current month.
 *
 * @return string Period in the YYYYMM format
 */
function hrmonthlycheckCurrentPeriod()
{
	return dol_print_date(dol_now(), '%Y%m', 'tzserver');
}

/**
 * First and last day of a period, at midnight GMT.
 *
 * @param string $period Period in the YYYYMM format
 * @return array{0:int,1:int}
 */
function hrmonthlycheckPeriodBounds($period)
{
	$year = (int) substr($period, 0, 4);
	$month = (int) substr($period, 4, 2);
	$next = dol_get_next_month($month, $year);

	return array(
		(int) dol_mktime(0, 0, 0, $month, 1, $year, 'gmt'),
		(int) dol_mktime(0, 0, 0, $next['month'], 1, $next['year'], 'gmt') - 86400,
	);
}

/**
 * Month and year of a period, e.g. "September 2026".
 *
 * @param string    $period      Period in the YYYYMM format
 * @param Translate $outputlangs Language
 * @return string
 */
function hrmonthlycheckPeriodLabel($period, $outputlangs)
{
	$bounds = hrmonthlycheckPeriodBounds($period);

	return dol_print_date($bounds[0], '%B %Y', 'gmt', $outputlangs);
}

/**
 * Restrict a leave to a period.
 *
 * @param int $start       First day of the leave (midnight GMT)
 * @param int $end         Last day of the leave (midnight GMT)
 * @param int $halfday     Dolibarr half day flag: 0 full days, -1 starts in the afternoon, 1 ends in the morning, 2 both
 * @param int $periodStart First day of the period (midnight GMT)
 * @param int $periodEnd   Last day of the period (midnight GMT)
 * @return array{start:int,end:int,halfday:int}
 */
function hrmonthlycheckClipLeave($start, $end, $halfday, $periodStart, $periodEnd)
{
	$startsInAfternoon = ($halfday == -1 || $halfday == 2) && $start >= $periodStart;
	$endsInMorning = ($halfday == 1 || $halfday == 2) && $end <= $periodEnd;

	if ($startsInAfternoon && $endsInMorning) {
		$clippedHalfday = 2;
	} elseif ($startsInAfternoon) {
		$clippedHalfday = -1;
	} elseif ($endsInMorning) {
		$clippedHalfday = 1;
	} else {
		$clippedHalfday = 0;
	}

	return array(
		'start' => max($start, $periodStart),
		'end' => min($end, $periodEnd),
		'halfday' => $clippedHalfday,
	);
}

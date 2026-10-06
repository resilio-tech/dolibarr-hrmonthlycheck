<?php
/* Copyright (C) 2026 Resilio SA
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file       class/HrMonthlyCheckRecap.class.php
 * \ingroup    hrmonthlycheck
 * \brief      HR information of the employees for a month.
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
require_once __DIR__.'/../lib/hrmonthlycheck.lib.php';

/**
 * Class HrMonthlyCheckRecap
 */
class HrMonthlyCheckRecap
{
	/** @var DoliDB Database connection */
	public $db;

	/** @var string Last error message */
	public $error = '';

	/** @var ExtraFields User extrafields */
	private $extrafields;

	/** @var string Code of the user extrafield holding the work rate */
	private $workrateField;

	/** @var string Code of the user extrafield holding BYOD */
	private $byodField;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database connection
	 */
	public function __construct($db)
	{
		$this->db = $db;

		$this->extrafields = new ExtraFields($db);
		$labels = $this->extrafields->fetch_name_optionals_label('user');

		$this->workrateField = $this->configuredField('HRMONTHLYCHECK_WORKRATE_FIELD', $labels);
		$this->byodField = $this->configuredField('HRMONTHLYCHECK_BYOD_FIELD', $labels);
	}

	/**
	 * Extrafield code stored in a setting, if it still exists.
	 *
	 * @param string               $const  Setting name
	 * @param array<string,string> $labels Existing user extrafields
	 * @return string Empty when not configured
	 */
	private function configuredField(string $const, array $labels): string
	{
		$code = getDolGlobalString($const);

		return ($code !== '' && isset($labels[$code]) && preg_match('/^[a-z0-9_]+$/i', $code)) ? $code : '';
	}

	/**
	 * Load the recap of the employees working during a period.
	 *
	 * @param string $period Period in the YYYYMM format
	 * @param int    $userId Restrict to one employee (0 = all)
	 * @return array<int,stdClass>|null Keyed by user id, null on error
	 */
	public function fetchAll(string $period, int $userId = 0): ?array
	{
		global $conf;

		list($periodStart, $periodEnd) = hrmonthlycheckPeriodBounds($period);
		$firstDay = dol_print_date($periodStart, '%Y-%m-%d', 'gmt');
		$lastDay = dol_print_date($periodEnd, '%Y-%m-%d', 'gmt');

		$sql = "SELECT u.rowid, u.firstname, u.lastname, u.email, u.salary, u.weeklyhours, u.dateemployment, u.dateemploymentend,";
		$sql .= " u.address, u.zip, u.town, d.nom as state, d.code_departement as state_code, c.code as country_code, c.label as country,";
		$sql .= " rib.iban_prefix as iban, a.status as answer_status, a.note as answer_note, a.date_answer";
		if ($this->workrateField !== '') {
			$sql .= ", ue.".$this->workrateField." as workrate";
		}
		if ($this->byodField !== '') {
			$sql .= ", ue.".$this->byodField." as byod";
		}
		$sql .= " FROM ".$this->db->prefix()."user as u";
		$sql .= " LEFT JOIN ".$this->db->prefix()."c_departements as d ON d.rowid = u.fk_state";
		$sql .= " LEFT JOIN ".$this->db->prefix()."c_country as c ON c.rowid = u.fk_country";
		$sql .= " LEFT JOIN ".$this->db->prefix()."user_extrafields as ue ON ue.fk_object = u.rowid";
		$sql .= " LEFT JOIN ".$this->db->prefix()."user_rib as rib ON rib.fk_user = u.rowid";
		$sql .= " LEFT JOIN ".$this->db->prefix()."hrmonthlycheck_answer as a ON a.fk_user = u.rowid";
		$sql .= " AND a.entity = ".((int) $conf->entity)." AND a.yearmonth = '".$this->db->escape($period)."'";
		$sql .= " WHERE u.entity IN (".getEntity('user').")";
		$sql .= " AND u.employee = 1 AND u.statut = 1";
		$sql .= " AND (u.dateemployment IS NULL OR u.dateemployment <= '".$this->db->escape($lastDay)."')";
		$sql .= " AND (u.dateemploymentend IS NULL OR u.dateemploymentend >= '".$this->db->escape($firstDay)."')";
		if ($userId > 0) {
			$sql .= " AND u.rowid = ".((int) $userId);
		}
		$sql .= " ORDER BY u.lastname, u.firstname, u.rowid, rib.rowid";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return null;
		}

		$recaps = array();
		while ($obj = $this->db->fetch_object($resql)) {
			if (isset($recaps[(int) $obj->rowid])) {
				continue;
			}
			$obj->id = (int) $obj->rowid;
			$obj->datestart = $this->db->jdate($obj->dateemployment, 1);
			$obj->dateend = $this->db->jdate($obj->dateemploymentend, 1);
			$obj->starts_in_period = $obj->datestart && $obj->datestart >= $periodStart && $obj->datestart <= $periodEnd;
			$obj->ends_in_period = $obj->dateend && $obj->dateend >= $periodStart && $obj->dateend <= $periodEnd;
			$obj->date_answer = $this->db->jdate($obj->date_answer);
			$obj->leaves = array();
			$recaps[$obj->id] = $obj;
		}
		$this->db->free($resql);

		if (!$this->loadUnpaidLeaves($recaps, $firstDay, $lastDay, $periodStart, $periodEnd)) {
			return null;
		}

		return $recaps;
	}

	/**
	 * Add to each recap the approved unpaid leaves overlapping the period.
	 *
	 * @param array<int,stdClass> $recaps      Recaps keyed by user id
	 * @param string              $firstDay    First day of the period (YYYY-MM-DD)
	 * @param string              $lastDay     Last day of the period (YYYY-MM-DD)
	 * @param int                 $periodStart First day of the period (midnight GMT)
	 * @param int                 $periodEnd   Last day of the period (midnight GMT)
	 * @return bool False on error
	 */
	private function loadUnpaidLeaves(array $recaps, string $firstDay, string $lastDay, int $periodStart, int $periodEnd): bool
	{
		$typeIds = array_filter(array_map('intval', explode(',', getDolGlobalString('HRMONTHLYCHECK_UNPAID_LEAVE_TYPES'))));
		if (empty($typeIds) || empty($recaps)) {
			return true;
		}

		$sql = "SELECT h.fk_user, h.date_debut, h.date_fin, h.halfday";
		$sql .= " FROM ".$this->db->prefix()."holiday as h";
		$sql .= " WHERE h.entity IN (".getEntity('holiday').")";
		$sql .= " AND h.statut = ".((int) Holiday::STATUS_APPROVED);
		$sql .= " AND h.fk_type IN (".$this->db->sanitize(implode(',', $typeIds)).")";
		$sql .= " AND h.fk_user IN (".$this->db->sanitize(implode(',', array_keys($recaps))).")";
		$sql .= " AND h.date_debut <= '".$this->db->escape($lastDay)."'";
		$sql .= " AND h.date_fin >= '".$this->db->escape($firstDay)."'";
		$sql .= " ORDER BY h.date_debut";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return false;
		}

		while ($obj = $this->db->fetch_object($resql)) {
			$leave = hrmonthlycheckClipLeave(
				(int) $this->db->jdate($obj->date_debut, 1),
				(int) $this->db->jdate($obj->date_fin, 1),
				(int) $obj->halfday,
				$periodStart,
				$periodEnd
			);
			$leave['days'] = num_open_day($leave['start'], $leave['end'], 0, 1, $leave['halfday'], '', (int) $obj->fk_user);
			$recaps[(int) $obj->fk_user]->leaves[] = $leave;
		}
		$this->db->free($resql);

		return true;
	}

	/**
	 * Labels of the recap fields, in display order.
	 *
	 * @param Translate $outputlangs Language
	 * @return array<string,string>
	 */
	public function fields($outputlangs): array
	{
		$outputlangs->loadLangs(array('users', 'salaries', 'companies', 'bills'));

		$fields = array();
		if ($this->workrateField !== '') {
			$fields['workrate'] = $outputlangs->transnoentities('HrMonthlyCheckWorkRate');
		}
		$fields['weeklyhours'] = $outputlangs->transnoentities('WeeklyHours');
		$fields['salary'] = $outputlangs->transnoentities('Salary');
		if ($this->byodField !== '') {
			$fields['byod'] = $outputlangs->transnoentities('HrMonthlyCheckByod');
		}
		$fields['address'] = $outputlangs->transnoentities('Address');
		$fields['unpaidleave'] = $outputlangs->transnoentities('HrMonthlyCheckUnpaidLeave');
		$fields['datestart'] = $outputlangs->transnoentities('HrMonthlyCheckStartDate');
		$fields['dateend'] = $outputlangs->transnoentities('HrMonthlyCheckEndDate');
		$fields['iban'] = $outputlangs->transnoentities('IBAN');

		return $fields;
	}

	/**
	 * Values of the recap fields for one employee, as plain text.
	 * The employment dates are empty when they fall outside the period.
	 *
	 * @param stdClass  $recap       Recap loaded by fetchAll()
	 * @param Translate $outputlangs Language
	 * @return array<string,string>
	 */
	public function values(stdClass $recap, $outputlangs): array
	{
		global $conf;

		$values = array();
		if ($this->workrateField !== '') {
			$values['workrate'] = $this->extraFieldText($this->workrateField, $recap->workrate, $outputlangs);
		}
		$values['weeklyhours'] = ((string) $recap->weeklyhours !== '') ? price($recap->weeklyhours, 0, $outputlangs, 0, 0) : '';
		$values['salary'] = ((string) $recap->salary !== '') ? price($recap->salary, 0, $outputlangs, 1, -1, -1, $conf->currency) : '';
		if ($this->byodField !== '') {
			$values['byod'] = $this->extraFieldText($this->byodField, $recap->byod, $outputlangs);
		}
		$values['address'] = dol_format_address($recap, 1, ', ', $outputlangs);
		$values['unpaidleave'] = $this->leavesText($recap->leaves, $outputlangs);
		$values['datestart'] = $recap->starts_in_period ? dol_print_date($recap->datestart, 'day', 'gmt', $outputlangs) : '';
		$values['dateend'] = $recap->ends_in_period ? dol_print_date($recap->dateend, 'day', 'gmt', $outputlangs) : '';
		$values['iban'] = (string) $recap->iban;

		return array_map('dol_string_nohtmltag', $values);
	}

	/**
	 * Recap of one employee as label => value, as shown to the employee.
	 * The employment dates are left out when they fall outside the period.
	 *
	 * @param stdClass  $recap       Recap loaded by fetchAll()
	 * @param Translate $outputlangs Language
	 * @return array<string,string>
	 */
	public function lines(stdClass $recap, $outputlangs): array
	{
		$values = $this->values($recap, $outputlangs);

		$lines = array();
		foreach ($this->fields($outputlangs) as $key => $label) {
			if (in_array($key, array('datestart', 'dateend')) && $values[$key] === '') {
				continue;
			}
			$lines[$label] = $values[$key] !== '' ? $values[$key] : '-';
		}

		return $lines;
	}

	/**
	 * Plain text value of a user extrafield.
	 *
	 * @param string    $code        Extrafield code
	 * @param mixed     $value       Raw value
	 * @param Translate $outputlangs Language
	 * @return string
	 */
	private function extraFieldText(string $code, $value, $outputlangs): string
	{
		if ($this->extrafields->attributes['user']['type'][$code] == 'boolean') {
			return yn($value ? 1 : 0, 1, 0);
		}

		return (string) $this->extrafields->showOutputField($code, $value, '', 'user', $outputlangs);
	}

	/**
	 * Unpaid leaves of the period, e.g. "03/09/2026 - 05/09/2026 (3)".
	 *
	 * @param array<int,array{start:int,end:int,halfday:int,days:float}> $leaves      Leaves
	 * @param Translate                                                   $outputlangs Language
	 * @return string
	 */
	private function leavesText(array $leaves, $outputlangs): string
	{
		if (empty($leaves)) {
			return $outputlangs->transnoentities('None');
		}

		$texts = array();
		foreach ($leaves as $leave) {
			$texts[] = dol_print_date($leave['start'], 'day', 'gmt', $outputlangs).' - '.dol_print_date($leave['end'], 'day', 'gmt', $outputlangs)
				.' ('.$outputlangs->transnoentities('HrMonthlyCheckNbDays', price($leave['days'], 0, $outputlangs, 0, 0)).')';
		}

		return implode(', ', $texts);
	}
}

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
 * \file       class/HrMonthlyCheckAnswer.class.php
 * \ingroup    hrmonthlycheck
 * \brief      Answer of an employee to the monthly check of their HR information.
 */

/**
 * Class HrMonthlyCheckAnswer
 */
class HrMonthlyCheckAnswer
{
	const STATUS_CONFIRMED = 1;
	const STATUS_CHANGE_REQUESTED = 2;

	/** @var DoliDB Database connection */
	public $db;

	/** @var string Last error message */
	public $error = '';

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database connection
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Record the answer of an employee for a period, replacing the previous one.
	 *
	 * @param int    $userId Employee
	 * @param string $period Period in the YYYYMM format
	 * @param int    $status STATUS_CONFIRMED or STATUS_CHANGE_REQUESTED
	 * @param string $note   Requested change
	 * @return int 1 on success, -1 on error
	 */
	public function save(int $userId, string $period, int $status, string $note): int
	{
		global $conf;

		$this->db->begin();

		$sql = "SELECT rowid FROM ".$this->db->prefix()."hrmonthlycheck_answer";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " AND fk_user = ".((int) $userId);
		$sql .= " AND yearmonth = '".$this->db->escape($period)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$existing = $this->db->fetch_object($resql);

		if ($existing) {
			$sql = "UPDATE ".$this->db->prefix()."hrmonthlycheck_answer SET";
			$sql .= " status = ".((int) $status);
			$sql .= ", note = ".($note !== '' ? "'".$this->db->escape($note)."'" : "NULL");
			$sql .= ", date_answer = '".$this->db->idate(dol_now())."'";
			$sql .= " WHERE rowid = ".((int) $existing->rowid);
		} else {
			$sql = "INSERT INTO ".$this->db->prefix()."hrmonthlycheck_answer (entity, fk_user, yearmonth, status, note, date_answer)";
			$sql .= " VALUES (".((int) $conf->entity).", ".((int) $userId).", '".$this->db->escape($period)."', ".((int) $status);
			$sql .= ", ".($note !== '' ? "'".$this->db->escape($note)."'" : "NULL");
			$sql .= ", '".$this->db->idate(dol_now())."')";
		}

		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		return 1;
	}

	/**
	 * Number of change requests for a period.
	 *
	 * @param string $period Period in the YYYYMM format
	 * @return int Negative on error
	 */
	public function countChangeRequests(string $period): int
	{
		global $conf;

		$sql = "SELECT COUNT(rowid) as nb FROM ".$this->db->prefix()."hrmonthlycheck_answer";
		$sql .= " WHERE entity = ".((int) $conf->entity);
		$sql .= " AND yearmonth = '".$this->db->escape($period)."'";
		$sql .= " AND status = ".self::STATUS_CHANGE_REQUESTED;
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);

		return $obj ? (int) $obj->nb : 0;
	}

	/**
	 * Status badge of an answer.
	 *
	 * @param int|null  $status      Answer status, null when the employee did not answer
	 * @param Translate $outputlangs Language
	 * @return string HTML
	 */
	public static function statusBadge($status, $outputlangs): string
	{
		if ((int) $status === self::STATUS_CONFIRMED) {
			$label = $outputlangs->trans('HrMonthlyCheckConfirmed');
			return dolGetStatus($label, $label, '', 'status4', 5);
		}
		if ((int) $status === self::STATUS_CHANGE_REQUESTED) {
			$label = $outputlangs->trans('HrMonthlyCheckChangeRequested');
			return dolGetStatus($label, $label, '', 'status1', 5);
		}

		$label = $outputlangs->trans('HrMonthlyCheckNoAnswer');
		return dolGetStatus($label, $label, '', 'status0', 5);
	}
}

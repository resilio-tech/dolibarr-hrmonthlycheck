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
 * \file       class/HrMonthlyCheckCron.class.php
 * \ingroup    hrmonthlycheck
 * \brief      Scheduled jobs: send the monthly recap to the employees, then notify HR.
 */

require_once __DIR__.'/HrMonthlyCheckAnswer.class.php';
require_once __DIR__.'/HrMonthlyCheckRecap.class.php';
require_once __DIR__.'/ZulipNotifier.class.php';
require_once __DIR__.'/../lib/hrmonthlycheck.lib.php';

/**
 * Class HrMonthlyCheckCron
 */
class HrMonthlyCheckCron
{
	/** @var DoliDB Database connection */
	public $db;

	/** @var string Human readable output (shown in the cron job result) */
	public $output = '';

	/** @var string Error message (non-empty means failure) */
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
	 * Cron entry point: send every employee the recap of their HR information for the current month.
	 *
	 * @param string $params Optional parameters (unused)
	 * @return int 0 on success, negative on error
	 */
	public function sendEmployeeRecaps($params = ''): int
	{
		global $langs;

		$langs->loadLangs(array('main', 'hrmonthlycheck@hrmonthlycheck'));
		$this->output = '';
		$this->error = '';

		$notifier = ZulipNotifier::fromConf();
		if ($notifier === null) {
			$this->error = 'Zulip is not configured';
			return -1;
		}

		$period = hrmonthlycheckCurrentPeriod();
		$loader = new HrMonthlyCheckRecap($this->db);
		$recaps = $loader->fetchAll($period);
		if ($recaps === null) {
			$this->error = $loader->error;
			return -1;
		}

		$periodLabel = hrmonthlycheckPeriodLabel($period, $langs);
		$url = dol_buildpath('/hrmonthlycheck/mycheck.php', 3).'?period='.$period;

		$sent = 0;
		$failures = array();
		foreach ($recaps as $recap) {
			$name = trim($recap->firstname.' '.$recap->lastname);
			if ((string) $recap->email === '') {
				$failures[] = $name.': no email';
				continue;
			}

			if (!$notifier->sendDirect($recap->email, $this->zulipMessage($recap, $periodLabel, $url))) {
				$failures[] = $name.': '.$notifier->getError();
				continue;
			}
			$sent++;
		}

		$this->output = $sent.' employee(s) notified for '.$period;
		if (!empty($failures)) {
			$this->error = implode("\n", $failures);
			return -1;
		}

		return 0;
	}

	/**
	 * Cron entry point: tell HR on Zulip where to find the answers of the current month.
	 *
	 * @param string $params Optional parameters (unused)
	 * @return int 0 on success, negative on error
	 */
	public function notifyHr($params = ''): int
	{
		global $langs;

		$langs->loadLangs(array('main', 'hrmonthlycheck@hrmonthlycheck'));
		$this->output = '';
		$this->error = '';

		$notifier = ZulipNotifier::fromConf();
		if ($notifier === null) {
			$this->error = 'Zulip is not configured';
			return -1;
		}

		$period = hrmonthlycheckCurrentPeriod();
		$answer = new HrMonthlyCheckAnswer($this->db);
		$nbChanges = $answer->countChangeRequests($period);
		if ($nbChanges < 0) {
			$this->error = $answer->error;
			return -1;
		}

		$url = dol_buildpath('/hrmonthlycheck/answers.php', 3).'?period='.$period;
		$this->output = $langs->transnoentities('HrMonthlyCheckHrNotification', hrmonthlycheckPeriodLabel($period, $langs), $nbChanges);
		$this->output .= "\n\n[".$langs->transnoentities('HrMonthlyCheckSeeAnswers').']('.$url.')';

		$topic = getDolGlobalString('HRMONTHLYCHECK_ZULIP_HR_TOPIC') ?: 'HR monthly check';
		if (!$notifier->sendStream(getDolGlobalString('HRMONTHLYCHECK_ZULIP_HR_STREAM'), $topic, $this->output)) {
			$this->error = $notifier->getError();
			return -1;
		}

		return 0;
	}

	/**
	 * Zulip message sent to an employee.
	 *
	 * @param stdClass $recap       Recap of the employee
	 * @param string   $periodLabel Month and year
	 * @param string   $url         Page where the employee answers
	 * @return string Zulip Markdown
	 */
	private function zulipMessage(stdClass $recap, string $periodLabel, string $url): string
	{
		global $langs;

		$content = $langs->transnoentities('HrMonthlyCheckGreeting', $recap->firstname)."\n\n";
		$content .= $langs->transnoentities('HrMonthlyCheckMessageIntro', $periodLabel)."\n\n";
		$content .= '['.$langs->transnoentities('HrMonthlyCheckConfirmButton').']('.$url.'&answer=confirm)';
		$content .= ' | ['.$langs->transnoentities('HrMonthlyCheckChangeButton').']('.$url.'&answer=change)';

		return $content;
	}
}

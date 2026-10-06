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
 * 	\defgroup   hrmonthlycheck     Module HrMonthlyCheck
 *  \brief      HrMonthlyCheck module descriptor.
 *
 *  \file       htdocs/hrmonthlycheck/core/modules/modHrMonthlyCheck.class.php
 *  \ingroup    hrmonthlycheck
 *  \brief      Description and activation file for module HrMonthlyCheck
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';

/**
 *  Description and activation class for module HrMonthlyCheck
 */
class modHrMonthlyCheck extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;

		$this->numero = 570000;

		$this->rights_class = 'hrmonthlycheck';
		$this->family = 'hr';
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = 'Monthly check of their HR information by the employees';
		$this->descriptionlong = 'HrMonthlyCheckDescription';

		$this->editor_name = 'Slordef';
		$this->editor_url = '';
		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'fa-user-check';

		$this->module_parts = array(
			'triggers' => 0,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(),
			'js' => array(),
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		$this->dirs = array();

		$this->config_page_url = array("setup.php@hrmonthlycheck");

		$this->hidden = false;
		$this->depends = array('modHoliday');
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("hrmonthlycheck@hrmonthlycheck");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(17, 0);
		$this->need_javascript_ajax = 0;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		$this->const = array();

		if (!isModEnabled("hrmonthlycheck")) {
			$conf->hrmonthlycheck = new stdClass();
			$conf->hrmonthlycheck->enabled = 0;
		}

		$this->tabs = array();

		$this->dictionaries = array();

		$this->boxes = array();

		$employeeStart = $this->nextMonthlyRun(15);
		$hrStart = $this->nextMonthlyRun(21);
		$this->cronjobs = array(
			0 => array(
				'label' => 'HR monthly check: send the recap to employees',
				'jobtype' => 'method',
				'class' => '/hrmonthlycheck/class/HrMonthlyCheckCron.class.php',
				'objectname' => 'HrMonthlyCheckCron',
				'method' => 'sendEmployeeRecaps',
				'parameters' => '',
				'comment' => 'On the 15th, send every employee the recap of their HR information for the month, with a link to confirm it or ask for a change',
				'frequency' => 1,
				'unitfrequency' => 2678400,
				'datestart' => $employeeStart,
				'datenextrun' => $employeeStart,
				'status' => 0,
				'test' => 'isModEnabled("hrmonthlycheck")',
				'priority' => 50,
			),
			1 => array(
				'label' => 'HR monthly check: notify HR',
				'jobtype' => 'method',
				'class' => '/hrmonthlycheck/class/HrMonthlyCheckCron.class.php',
				'objectname' => 'HrMonthlyCheckCron',
				'method' => 'notifyHr',
				'parameters' => '',
				'comment' => 'On the 21st, post to the HR Zulip stream a link to the answers of the employees for the month',
				'frequency' => 1,
				'unitfrequency' => 2678400,
				'datestart' => $hrStart,
				'datenextrun' => $hrStart,
				'status' => 0,
				'test' => 'isModEnabled("hrmonthlycheck")',
				'priority' => 50,
			),
		);

		$this->rights = array();
		$this->rights[0][0] = $this->numero.'01';
		$this->rights[0][1] = 'See the monthly check answers of all employees';
		$this->rights[0][2] = 'r';
		$this->rights[0][3] = 0;
		$this->rights[0][4] = 'read';
		$this->rights[0][5] = '';

		$this->menu = array();
		$this->menu[0] = array(
			'fk_menu' => 'fk_mainmenu=hrm',
			'type' => 'left',
			'titre' => 'HrMonthlyCheckMyCheck',
			'mainmenu' => 'hrm',
			'leftmenu' => 'hrmonthlycheck_mycheck',
			'url' => '/hrmonthlycheck/mycheck.php',
			'langs' => 'hrmonthlycheck@hrmonthlycheck',
			'position' => 1000,
			'enabled' => 'isModEnabled("hrmonthlycheck")',
			'perms' => '1',
			'target' => '',
			'user' => 0,
		);
		$this->menu[1] = array(
			'fk_menu' => 'fk_mainmenu=hrm',
			'type' => 'left',
			'titre' => 'HrMonthlyCheckAnswers',
			'mainmenu' => 'hrm',
			'leftmenu' => 'hrmonthlycheck_answers',
			'url' => '/hrmonthlycheck/answers.php',
			'langs' => 'hrmonthlycheck@hrmonthlycheck',
			'position' => 1001,
			'enabled' => 'isModEnabled("hrmonthlycheck")',
			'perms' => '$user->hasRight("hrmonthlycheck", "read")',
			'target' => '',
			'user' => 0,
		);
	}

	/**
	 * Next occurrence of a day of the month at 08:00.
	 *
	 * @param int $day Day of the month
	 * @return int Timestamp
	 */
	private function nextMonthlyRun(int $day): int
	{
		$now = dol_now();
		$today = dol_getdate($now);
		$run = dol_mktime(8, 0, 0, $today['mon'], $day, $today['year']);
		if ($run <= $now) {
			$next = dol_get_next_month($today['mon'], $today['year']);
			$run = dol_mktime(8, 0, 0, $next['month'], $day, $next['year']);
		}

		return (int) $run;
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int             	1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/hrmonthlycheck/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		return $this->_init(array(), $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		return $this->_remove(array(), $options);
	}
}

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
 * \file       test/phpunit/HrMonthlyCheckLibTest.php
 * \ingroup    hrmonthlycheck
 * \brief      PHPUnit tests of the period validation and of the leave clipping.
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__FILE__).'/../../lib/hrmonthlycheck.lib.php';

/**
 * Class HrMonthlyCheckLibTest
 */
class HrMonthlyCheckLibTest extends TestCase
{
	const SEP_01 = 1788220800;
	const SEP_30 = 1790726400;
	const DAY = 86400;

	/**
	 * @return void
	 */
	public function testValidPeriods()
	{
		$this->assertTrue(hrmonthlycheckIsValidPeriod('202609'));
		$this->assertTrue(hrmonthlycheckIsValidPeriod('202612'));
		$this->assertFalse(hrmonthlycheckIsValidPeriod('202613'));
		$this->assertFalse(hrmonthlycheckIsValidPeriod('202600'));
		$this->assertFalse(hrmonthlycheckIsValidPeriod('2026-09'));
		$this->assertFalse(hrmonthlycheckIsValidPeriod("202609' OR 1=1"));
		$this->assertFalse(hrmonthlycheckIsValidPeriod(''));
	}

	/**
	 * @return void
	 */
	public function testLeaveInsideThePeriodKeepsItsHalfDays()
	{
		$leave = hrmonthlycheckClipLeave(self::SEP_01 + 2 * self::DAY, self::SEP_01 + 4 * self::DAY, 2, self::SEP_01, self::SEP_30);

		$this->assertSame(array('start' => self::SEP_01 + 2 * self::DAY, 'end' => self::SEP_01 + 4 * self::DAY, 'halfday' => 2), $leave);
	}

	/**
	 * @return void
	 */
	public function testLeaveStartingBeforeThePeriodDropsTheStartHalfDay()
	{
		$leave = hrmonthlycheckClipLeave(self::SEP_01 - 3 * self::DAY, self::SEP_01 + 1 * self::DAY, 2, self::SEP_01, self::SEP_30);

		$this->assertSame(array('start' => self::SEP_01, 'end' => self::SEP_01 + 1 * self::DAY, 'halfday' => 1), $leave);
	}

	/**
	 * @return void
	 */
	public function testLeaveEndingAfterThePeriodDropsTheEndHalfDay()
	{
		$leave = hrmonthlycheckClipLeave(self::SEP_30 - 1 * self::DAY, self::SEP_30 + 5 * self::DAY, 2, self::SEP_01, self::SEP_30);

		$this->assertSame(array('start' => self::SEP_30 - 1 * self::DAY, 'end' => self::SEP_30, 'halfday' => -1), $leave);
	}

	/**
	 * @return void
	 */
	public function testLeaveCoveringThePeriodIsFullDays()
	{
		$leave = hrmonthlycheckClipLeave(self::SEP_01 - self::DAY, self::SEP_30 + self::DAY, 2, self::SEP_01, self::SEP_30);

		$this->assertSame(array('start' => self::SEP_01, 'end' => self::SEP_30, 'halfday' => 0), $leave);
	}
}

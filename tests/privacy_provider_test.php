<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy provider tests for assignsubmission_responsetemplate.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_responsetemplate;

use core_privacy\local\metadata\null_provider;
use core_privacy\tests\provider_testcase;

/**
 * Privacy provider tests.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \assignsubmission_responsetemplate\privacy\provider
 */
final class privacy_provider_test extends provider_testcase {
    /**
     * The provider implements the null_provider interface.
     */
    public function test_implements_null_provider(): void {
        $this->assertTrue(
            is_subclass_of(\assignsubmission_responsetemplate\privacy\provider::class, null_provider::class)
        );
    }

    /**
     * get_reason() returns a string key that resolves to a non-empty language string.
     */
    public function test_get_reason_resolves_to_lang_string(): void {
        $reason = \assignsubmission_responsetemplate\privacy\provider::get_reason();
        $this->assertSame('privacy:metadata', $reason);
        $this->assertNotEmpty(get_string($reason, 'assignsubmission_responsetemplate'));
    }
}

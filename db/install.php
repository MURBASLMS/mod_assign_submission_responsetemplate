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
 * Post-install hook for the response template submission plugin.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs once after the plugin's database schema is installed.
 *
 * @return void
 */
function xmldb_assignsubmission_responsetemplate_install() {
    // Sets the sortorder as the highest expected index, this guarantees that it applies after onlinetext.
    // This method of setting the sortorder complies with {@see assign_plugin::is_last()} logic.
    $submissionplugins = core_component::get_plugin_list('assignsubmission');
    set_config('sortorder', count($submissionplugins) - 1, 'assignsubmission_responsetemplate');
}

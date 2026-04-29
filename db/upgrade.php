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
 * Upgrade steps for the response template submission plugin.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Run incremental schema upgrades for assignsubmission_responsetemplate.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool Always true on success.
 */
function xmldb_assignsubmission_responsetemplate_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026042900) {
        // Promote the existing foreign key on `assignment` to foreign-unique.
        // Existing installs may have duplicate rows from earlier development;
        // collapse them to the most recent before adding the unique constraint.
        $duplicates = $DB->get_records_sql(
            "SELECT assignment, COUNT(*) AS c
               FROM {assign_submission_resptemp}
           GROUP BY assignment
             HAVING COUNT(*) > 1"
        );
        foreach ($duplicates as $row) {
            $records = $DB->get_records(
                'assign_submission_resptemp',
                ['assignment' => $row->assignment],
                'id DESC'
            );
            // Keep the highest id (most recent), drop the rest.
            array_shift($records);
            foreach ($records as $stale) {
                $DB->delete_records('assign_submission_resptemp', ['id' => $stale->id]);
            }
        }

        $table = new xmldb_table('assign_submission_resptemp');
        $oldkey = new xmldb_key('assignment', XMLDB_KEY_FOREIGN, ['assignment'], 'assign', ['id']);
        $newkey = new xmldb_key('assignment', XMLDB_KEY_FOREIGN_UNIQUE, ['assignment'], 'assign', ['id']);
        $dbman->drop_key($table, $oldkey);
        $dbman->add_key($table, $newkey);

        upgrade_plugin_savepoint(true, 2026042900, 'assignsubmission', 'responsetemplate');
    }

    return true;
}

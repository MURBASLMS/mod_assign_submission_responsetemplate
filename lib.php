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
 * File serving for the response template submission plugin.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Serve files for the response template plugin.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The module context.
 * @param string $filearea The file area.
 * @param array $args Extra arguments.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if the file is not found.
 */
function assignsubmission_responsetemplate_pluginfile(
    $course,
    $cm,
    $context,
    $filearea,
    $args,
    $forcedownload,
    array $options = []
) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    if ($cm->modname !== 'assign') {
        return false;
    }

    // Those permissions should cover roles that expected to view/edit the template, but are not students.
    $capabilities = [
        'mod/assign:grade',
        'mod/assign:viewgrades',
        'mod/assign:addinstance',
    ];
    if (!has_any_capability($capabilities, $context)) {
        return false;
    }

    if ($filearea !== assign_submission_responsetemplate::FILEAREA) {
        return false;
    }

    $itemid = (int) array_shift($args);
    if ($itemid !== 0) {
        return false;
    }

    if (empty($args)) {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $forcedownload = true;

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        assign_submission_responsetemplate::COMPONENT,
        assign_submission_responsetemplate::FILEAREA,
        $itemid,
        $filepath,
        $filename
    );
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

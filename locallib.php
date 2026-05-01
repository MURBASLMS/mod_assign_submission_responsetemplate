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
 * Library class for the response template submission plugin.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

/**
 * Submission plugin that allows teachers to define a response template
 * which is pre-filled into the online text editor for students.
 *
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 MURBA S.L.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_submission_responsetemplate extends assign_submission_plugin {
    /** @var string Database table name. */
    const TABLE = 'assignsubmission_responsetemplate';

    /** @var string Frankenstyle component name. */
    const COMPONENT = 'assignsubmission_responsetemplate';

    /** @var string File area for template files. */
    const FILEAREA = 'template';

    /**
     * Get the name of the plugin.
     *
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', self::COMPONENT);
    }

    /**
     * Add the template editor to the assignment settings form.
     *
     * @param MoodleQuickForm $mform The form to add elements to.
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        $editoroptions = [
            'subdirs'  => 1,
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'context'  => $this->assignment->get_context(),
        ];

        $mform->addElement(
            'editor',
            'assignsubmission_responsetemplate_template',
            get_string('responsetemplate', self::COMPONENT),
            ['rows' => 10],
            $editoroptions
        );

        $mform->hideIf(
            'assignsubmission_responsetemplate_template',
            'assignsubmission_responsetemplate_enabled',
            'notchecked'
        );
        $mform->hideIf(
            'assignsubmission_responsetemplate_template',
            'assignsubmission_onlinetext_enabled',
            'notchecked'
        );
    }

    /**
     * Populate the assignment settings form with existing template data.
     *
     * @param array|stdClass $defaultvalues The default values for the form.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        $cm = $this->assignment->get_course_module();
        if (!$cm || empty($cm->instance)) {
            // New assignment — no saved template to load.
            return;
        }

        $instance = $this->assignment->get_instance();
        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);

        $draftdata = (object) [
            'template'       => $record ? $record->template : '',
            'templateformat' => $record ? $record->templateformat : editors_get_preferred_format(),
        ];

        $context = $this->assignment->get_context();
        file_prepare_standard_editor(
            $draftdata,
            'template',
            ['context' => $context],
            $context,
            self::COMPONENT,
            self::FILEAREA,
            0
        );

        $fieldname = 'assignsubmission_responsetemplate_template';
        if (is_array($defaultvalues)) {
            $defaultvalues[$fieldname] = $draftdata->template_editor;
        } else {
            $defaultvalues->$fieldname = $draftdata->template_editor;
        }
    }

    /**
     * Save the template from the assignment settings form.
     *
     * @param stdClass $data The form data.
     * @return bool
     */
    public function save_settings(stdClass $data) {
        global $DB;

        $instance = $this->assignment->get_instance();
        if (!$instance) {
            return true;
        }

        // Moodle may strip the plugin prefix from field names.
        $fullname  = 'assignsubmission_responsetemplate_template';
        $shortname = 'template';
        $editordata = $data->$fullname ?? $data->$shortname ?? null;

        if (!$editordata || !is_array($editordata)) {
            return true;
        }

        $contextid = $this->assignment->get_context()->id;
        // Persist embedded files into the template area AND rewrite the editor's
        // draftfile.php URLs to @@PLUGINFILE@@ tokens so the stored text remains
        // portable across contexts.
        $savedtext = file_save_draft_area_files(
            $editordata['itemid'],
            $contextid,
            self::COMPONENT,
            self::FILEAREA,
            0,
            null,
            $editordata['text']
        );

        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);
        $newrecord = (object) [
            'assignment'     => $instance->id,
            'template'       => $savedtext,
            'templateformat' => $editordata['format'],
        ];

        if ($record) {
            $newrecord->id = $record->id;
            $DB->update_record(self::TABLE, $newrecord);
        } else {
            $DB->insert_record(self::TABLE, $newrecord);
        }

        return true;
    }

    /**
     * Inject the response template into the online text editor for students.
     *
     * This runs after the onlinetext plugin (via sort order) and sets
     * $data->onlinetext only when onlinetext has left it empty, so the editor is
     * pre-filled on first attempt and students can restore the template by
     * clearing the editor. Embedded template images and attachments are served
     * from the template file area via the plugin's pluginfile callback — no
     * copying into the student's draft area is required.
     *
     * @param stdClass|null $submission The existing submission, or null.
     * @param MoodleQuickForm $mform The submission form.
     * @param stdClass $data Shared form data object.
     * @param int $userid The student user ID.
     * @return bool Always false — this plugin adds no form elements.
     */
    public function get_form_elements_for_user($submission, MoodleQuickForm $mform, stdClass $data, $userid) {
        global $DB;

        // We must not apply the template if content was already entered.
        if (!empty(trim($data->onlinetext ?? ''))) {
            return false;
        }

        $assignmentid = $this->assignment->get_instance()->id;
        $template = $DB->get_record(self::TABLE, ['assignment' => $assignmentid]);
        if (!$template || empty(trim($template->template))) {
            return false;
        }

        // Rewrite our plugin files as final files, then prepare the editor without moving those files
        // into the draft area of the student. We could not do this if the student had already entered content.
        $context = $this->assignment->get_context();
        $data->onlinetext = file_rewrite_pluginfile_urls(
            $template->template,
            'pluginfile.php',
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            0
        );
        $data->onlinetextformat = $template->templateformat;
        $data = file_prepare_standard_editor($data, 'onlinetext', ['context' => $context], $context);

        return false;
    }

    /**
     * This plugin participates in the submission form loop but does not
     * accept submissions itself — student data is stored by onlinetext.
     *
     * @return bool
     */
    public function allow_submissions() {
        return true;
    }

    /**
     * The plugin never stores student submission content.
     *
     * @param stdClass $submission The submission object.
     * @return bool Always true.
     */
    public function is_empty(stdClass $submission) {
        return true;
    }

    /**
     * Remove all template data when the assignment is deleted.
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;

        $cm = $this->assignment->get_course_module();
        if ($cm && !empty($cm->instance)) {
            $DB->delete_records(self::TABLE, ['assignment' => $cm->instance]);
        }

        return true;
    }
}

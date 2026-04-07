<?php
/**
 * @package   assignsubmission_responsetemplate
 * @copyright 2026 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

class assign_submission_responsetemplate extends assign_submission_plugin {

    const TABLE = 'assign_submission_resptemp';
    const COMPONENT = 'assignsubmission_responsetemplate';
    const FILEAREA = 'template';

    /**
     * Get the name of the plugin.
     */
    public function get_name() {
        return get_string('pluginname', self::COMPONENT);
    }

    /**
     * Helper to safely get the assignment ID without triggering core crashes.
     * 
     * @return int|null
     */
    private function get_assignment_id_safe() {
        $cm = $this->assignment->get_course_module();
        return ($cm && !empty($cm->instance)) ? (int)$cm->instance : null;
    }

    /**
     * Define elements in the teacher assignment settings.
     */
    public function get_settings(MoodleQuickForm $mform) {
        $editoroptions = [
            'subdirs' => 1, 
            'maxfiles' => EDITOR_UNLIMITED_FILES, 
            'context' => $this->assignment->get_context()
        ];

        $mform->addElement('editor', 'assignsubmission_responsetemplate_template',
            get_string('responsetemplate', self::COMPONENT), ['rows' => 10], $editoroptions);
        $mform->setType('assignsubmission_responsetemplate_template', PARAM_RAW);

        // Force Fullscreen Checkbox.
        $mform->addElement('checkbox', 'assignsubmission_responsetemplate_fullscreen', 
            get_string('forcefullscreen', self::COMPONENT));

        $mform->hideIf('assignsubmission_responsetemplate_template', 'assignsubmission_responsetemplate_enabled', 'notchecked');
        $mform->hideIf('assignsubmission_responsetemplate_fullscreen', 'assignsubmission_responsetemplate_enabled', 'notchecked');
    }

    /**
     * Populate the settings form.
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        $assignmentid = $this->get_assignment_id_safe();
        if (!$assignmentid) {
            return;
        }

        $record = $DB->get_record(self::TABLE, ['assignment' => $assignmentid]);
        
        $draftdata = new stdClass();
        $draftdata->template = $record ? $record->template : '';
        $draftdata->templateformat = $record ? $record->templateformat : editors_get_preferred_format();

        file_prepare_standard_editor(
            $draftdata, 
            'template', 
            ['context' => $this->assignment->get_context()], 
            $this->assignment->get_context(), 
            self::COMPONENT, 
            self::FILEAREA, 
            0
        );

        $fullname = 'assignsubmission_responsetemplate_template';
        $fsname = 'assignsubmission_responsetemplate_fullscreen';
        
        // Defensive check: handle cases where the DB column might not exist yet.
        $fullscreenval = ($record && isset($record->fullscreen)) ? $record->fullscreen : 0;

        if (is_array($defaultvalues)) {
            $defaultvalues[$fullname] = $draftdata->template_editor;
            $defaultvalues[$fsname] = $fullscreenval;
        } else {
            $defaultvalues->$fullname = $draftdata->template_editor;
            $defaultvalues->$fsname = $fullscreenval;
        }
    }

    /**
     * Save settings to the custom table.
     */
    public function save_settings(stdClass $data) {
        global $DB;

        // In save_settings, the core record is guaranteed to exist.
        $instance = $this->assignment->get_instance();
        if (!$instance) {
            return true;
        }

        $fieldname = 'assignsubmission_responsetemplate_template';
        $fsname = 'assignsubmission_responsetemplate_fullscreen';
        
        if (!isset($data->$fieldname)) {
            return true;
        }

        $editordata = $data->$fieldname;
        file_save_draft_area_files($editordata['itemid'], $this->assignment->get_context()->id, self::COMPONENT, self::FILEAREA, 0);

        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);
        
        $newrecord = new stdClass();
        $newrecord->assignment = $instance->id;
        $newrecord->template = $editordata['text'];
        $newrecord->templateformat = $editordata['format'];
        $newrecord->fullscreen = !empty($data->$fsname) ? 1 : 0;

        if ($record) {
            $newrecord->id = $record->id;
            $DB->update_record(self::TABLE, $newrecord);
        } else {
            $DB->insert_record(self::TABLE, $newrecord);
        }
        return true;
    }

    /**
     * Standard Moodle compliance.
     */
    public function allow_submissions() {
        return false; 
    }

    public function is_empty(stdClass $submission) {
        return true;
    }

    public function delete_instance() {
        global $DB;
        $id = $this->get_assignment_id_safe();
        if ($id) {
            $DB->delete_records(self::TABLE, ['assignment' => $id]);
        }
        return true;
    }
}
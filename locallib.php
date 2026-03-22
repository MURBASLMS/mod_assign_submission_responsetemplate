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

    public function get_name() {
        return get_string('pluginname', self::COMPONENT);
    }

    /**
     * Define the element in the teacher's assignment settings.
     */
    public function get_settings(MoodleQuickForm $mform) {
        $editoroptions = [
            'subdirs' => 1, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'context' => $this->assignment->get_context()
        ];

        $mform->addElement('editor', 'assignsubmission_responsetemplate_template',
            get_string('responsetemplate', self::COMPONENT), ['rows' => 10], $editoroptions);
        $mform->setType('assignsubmission_responsetemplate_template', PARAM_RAW);
        
        $mform->hideIf('assignsubmission_responsetemplate_template', 'assignsubmission_responsetemplate_enabled', 'notchecked');
        $mform->hideIf('assignsubmission_responsetemplate_template', 'assignsubmission_onlinetext_enabled', 'notchecked');
    }

    /**
     * Populate the teacher settings form.
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        // SAFE GUARD: Check if the assignment record exists before calling get_instance().
        // If we are adding a new assignment, $cm->instance will be 0 or empty.
        $cm = $this->assignment->get_course_module();
        if (!$cm || empty($cm->instance)) {
            return; // Exit early: we are in "Add Mode".
        }

        // It is now safe to fetch the instance.
        $instance = $this->assignment->get_instance();
        
        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);
        $draftdata = (object)[
            'template' => $record ? $record->template : '',
            'templateformat' => $record ? $record->templateformat : editors_get_preferred_format()
        ];

        file_prepare_standard_editor($draftdata, 'template', 
            ['context' => $this->assignment->get_context()], 
            $this->assignment->get_context(), self::COMPONENT, self::FILEAREA, 0);

        // Map resulting editor object back to the form field.
        $fieldname = 'assignsubmission_responsetemplate_template';
        if (is_array($defaultvalues)) {
            $defaultvalues[$fieldname] = $draftdata->template_editor;
        } else {
            $defaultvalues->$fieldname = $draftdata->template_editor;
        }
    }

    /**
     * Save the settings.
     */
    public function save_settings(stdClass $data) {
        global $DB;

        // During save, Moodle has already created the assign record.
        $instance = $this->assignment->get_instance();
        if (!$instance) {
            return true;
        }

        // mod_assign strips prefixes. Check both full and stripped names for safety.
        $fullname = 'assignsubmission_responsetemplate_template';
        $shortname = 'template';
        $editordata = isset($data->$fullname) ? $data->$fullname : (isset($data->$shortname) ? $data->$shortname : null);

        if (!$editordata || !is_array($editordata)) {
            return true;
        }

        // Persist files from the draft area.
        file_save_draft_area_files($editordata['itemid'], $this->assignment->get_context()->id, self::COMPONENT, self::FILEAREA, 0);

        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);
        $newrecord = (object)[
            'assignment' => $instance->id,
            'template' => $editordata['text'],
            'templateformat' => $editordata['format']
        ];

        if ($record) {
            $newrecord->id = $record->id;
            $DB->update_record(self::TABLE, $newrecord);
        } else {
            $DB->insert_record(self::TABLE, $newrecord);
        }
        return true;
    }

    public function allow_submissions() { return false; }
    public function is_empty(stdClass $submission) { return true; }
    
    public function delete_instance() {
        global $DB;
        $cm = $this->assignment->get_course_module();
        if ($cm && !empty($cm->instance)) {
            $DB->delete_records(self::TABLE, ['assignment' => $cm->instance]);
        }
        return true;
    }
}

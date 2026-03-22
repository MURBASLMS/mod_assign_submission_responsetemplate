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

    public function data_preprocessing(&$defaultvalues) {
        global $DB;
        $instance = $this->assignment->get_instance();
        if (!$instance) return;

        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);
        $draftdata = (object)[
            'template' => $record ? $record->template : '',
            'templateformat' => $record ? $record->templateformat : editors_get_preferred_format()
        ];

        file_prepare_standard_editor($draftdata, 'template', 
            ['context' => $this->assignment->get_context()], 
            $this->assignment->get_context(), self::COMPONENT, self::FILEAREA, 0);

        $fieldname = 'assignsubmission_responsetemplate_template';
        if (is_array($defaultvalues)) { $defaultvalues[$fieldname] = $draftdata->template_editor; }
        else { $defaultvalues->$fieldname = $draftdata->template_editor; }
    }

    public function save_settings(stdClass $data) {
        global $DB;
        $instance = $this->assignment->get_instance();
        if (!$instance) return true;

        $fieldname = 'assignsubmission_responsetemplate_template';
        if (!isset($data->$fieldname)) return true;

        file_save_draft_area_files($data->{$fieldname}['itemid'], $this->assignment->get_context()->id, self::COMPONENT, self::FILEAREA, 0);

        $record = $DB->get_record(self::TABLE, ['assignment' => $instance->id]);
        $newrecord = (object)[
            'assignment' => $instance->id,
            'template' => $data->{$fieldname}['text'],
            'templateformat' => $data->{$fieldname}['format']
        ];

        if ($record) {
            $newrecord->id = $record->id;
            $DB->update_record(self::TABLE, $newrecord);
        } else {
            $DB->insert_record(self::TABLE, $newrecord);
        }
        return true;
    }

    // This plugin is now just a settings provider.
    public function allow_submissions() { return false; }
    public function is_empty(stdClass $submission) { return true; }
}
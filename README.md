Basically adds an element to online text where a teacher can add a "response template" to act a little like quiz essay. Saves to a new table defined 
in this plugin. On submission it should load up in the student's submission in tinymce for them to fill in the answers.
Why? grading is generally better in assignment, several use cases popped up for "exam" type scenarios where quiz grading and feedback did not fit the bill.

in mod/assign/submission/onlinetext/locallib.php
core hack around L168 (MOODLE_404_STABLE)
Could not get it to work outside this in a separate plugin, not that sure why but it seemed very hard to get the assignment and submission id passed through.

 ```
 if ($submission) {
            $onlinetextsubmission = $this->get_onlinetext_submission($submission->id);
            if ($onlinetextsubmission) {
                $data->onlinetext = $onlinetextsubmission->onlinetext;
                $data->onlinetextformat = $onlinetextsubmission->onlineformat;
            } else {
                // --- START RESPONSE TEMPLATE CORE HACK ---
                // If there is no existing student submission, check for a teacher template.
                global $DB;
                $resptemp = $DB->get_record('assign_submission_resptemp', [
                    'assignment' => $this->assignment->get_instance()->id
                ]);

                if ($resptemp && !empty(trim($resptemp->template))) {
                    // Inject the template into the data object.
                    $data->onlinetext = file_rewrite_pluginfile_urls(
                        $resptemp->template,
                        'pluginfile.php',
                        $this->assignment->get_context()->id,
                        'assignsubmission_responsetemplate',
                        'template',
                        0
                    );
                    $data->onlinetextformat = $resptemp->templateformat;
                    
                    // Log to PHP error log.
                    error_log("[CORE_HACK] Template injected for Assignment: " . $this->assignment->get_instance()->id);
                }
                // --- END RESPONSE TEMPLATE CORE HACK ---
            }
        }
        

        $data = file_prepare_standard_editor($data,
                                             'onlinetext',
                                             $editoroptions,
                                             $this->assignment->get_context(),
                                             'assignsubmission_onlinetext',
                                             ASSIGNSUBMISSION_ONLINETEXT_FILEAREA,
                                             $submissionid);
        $mform->addElement('editor', 'onlinetext_editor', $this->get_name(), null, $editoroptions);

        return true;
    }```


further core hack for full screen with sidebar - WIP
```
 /**
     * Add form elements for settings
     *
     * @param mixed $submission can be null
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return true if elements were added to the form
     */
    public function get_form_elements($submission, MoodleQuickForm $mform, stdClass $data) {
        // --- START RESPONSE TEMPLATE CORE HACK ---
        global $DB, $PAGE; 
        // --- END RESPONSE TEMPLATE CORE HACK ---

        $elements = array();
        $editoroptions = $this->get_edit_options(); // This MUST be defined here.
        $submissionid = $submission ? $submission->id : 0;

        if (!isset($data->onlinetext)) {
            $data->onlinetext = '';
        }
        if (!isset($data->onlinetextformat)) {
            $data->onlinetextformat = editors_get_preferred_format();
        }

        if ($submission) {
            $onlinetextsubmission = $this->get_onlinetext_submission($submission->id);
            if ($onlinetextsubmission) {
                $data->onlinetext = $onlinetextsubmission->onlinetext;
                $data->onlinetextformat = $onlinetextsubmission->onlineformat;
            } else {
                // --- START RESPONSE TEMPLATE CORE HACK ---
                global $DB, $PAGE;
                $assigninstance = $this->assignment; // This is the 'assign' class instance.
                $resptemp = $DB->get_record('assign_submission_resptemp', [
                    'assignment' => $assigninstance->get_instance()->id
                ]);

                if ($resptemp && !empty(trim($resptemp->template))) {
                    $data->onlinetext = file_rewrite_pluginfile_urls(
                        $resptemp->template, 'pluginfile.php',
                        $this->assignment->get_context()->id,
                        'assignsubmission_responsetemplate', 'template', 0
                    );
                    $data->onlinetextformat = $resptemp->templateformat;

                    if (!empty($resptemp->fullscreen)) {
                        $context = $this->assignment->get_context();

                        // Use the official Assignment API methods to get the content.
                        // This handles all permissions, filters, and lazy-loading.
                        $desc = format_text(
                            $assigninstance->get_instance()->intro, 
                            $assigninstance->get_instance()->introformat, 
                            ['context' => $context]
                        );

                        // Try to get activity instructions using the specific getter.
                        $instructions = '';
                        $instance = $assigninstance->get_instance();
                        if (method_exists($assigninstance, 'get_activity_instructions')) {
                            $instructions = $assigninstance->get_activity_instructions();
                        } else if (isset($instance->activityinstructions)) {
                            // Fallback for versions where getter isn't public.
                            $instructions = format_text(
                                $instance->activityinstructions, 
                                $instance->activityinstructionsformat, 
                                ['context' => $context]
                            );
                        }

                        $sidebarinfo = [
                            'description' => $desc,
                            'instructions' => $instructions
                        ];

                        $PAGE->requires->js_call_amd('assignsubmission_responsetemplate/fullscreen', 'init', [$sidebarinfo]);
                    }
                }
                // --- END RESPONSE TEMPLATE CORE HACK ---
            }
        }

        // Arguments: 3rd arg is $editoroptions, which is now guaranteed to be an array.
        $data = file_prepare_standard_editor($data,
                                             'onlinetext',
                                             $editoroptions,
                                             $this->assignment->get_context(),
                                             'assignsubmission_onlinetext',
                                             ASSIGNSUBMISSION_ONLINETEXT_FILEAREA,
                                             $submissionid);
        $mform->addElement('editor', 'onlinetext_editor', $this->get_name(), null, $editoroptions);

        return true;
    }```

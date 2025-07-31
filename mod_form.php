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
 * The avatar course module configuration form.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 */
class mod_avatar_mod_form extends moodleform_mod {

    /**
     * Defines forms elements.
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // General settings header.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Name of the avatar.
        $mform->addElement('text', 'name', get_string('avatarname', 'avatar'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Module standard elements.
        $this->standard_intro_elements();

        // Appearance settings.
        $mform->addElement('header', 'appearancehdr', get_string('appearance'));

        // Avatar selection.
        $avatarselectionoptions = [
            \mod_avatar\avatar::SELECTION_ALL => get_string('avatarselectionall', 'mod_avatar'),
            \mod_avatar\avatar::SELECTION_SPECIFIC => get_string('avatarselectionspecific', 'mod_avatar'),
        ];
        $mform->addElement('select', 'avatarselection', get_string('avatarselection', 'mod_avatar'), $avatarselectionoptions);
        $mform->addHelpButton('avatarselection', 'avatarselection', 'mod_avatar');

        // Specific tags for avatar selection.
        $mform->addElement('text', 'specifictags', get_string('specifictags', 'mod_avatar'));
        $mform->setType('specifictags', PARAM_TEXT);
        $mform->disabledIf('specifictags', 'avatarselection', 'neq', 1);
        $mform->addHelpButton('specifictags', 'specifictags', 'mod_avatar');

        // Display mode.
        $mform->addElement('select', 'displaymode', get_string('displaymode', 'mod_avatar'), [
            0 => get_string('displaymodepage', 'mod_avatar'),
            1 => get_string('displaymodeinline', 'mod_avatar'),
        ]);
        $mform->addHelpButton('displaymode', 'displaymode', 'mod_avatar');
        // Collection limit.
        if ($displaymode = get_config('mod_avatar', 'displaymode')) {
            $mform->setDefault('displaymode', $displaymode);
        }

        $editoroptions = ['maxfiles' => EDITOR_UNLIMITED_FILES, 'noclean' => true, 'context' => $this->context, 'subdirs' => true];

        // Header content.
        $mform->addElement('editor', 'headercontent_editor',
            get_string('headercontent', 'mod_avatar'), ['rows' => 10], $editoroptions);
        $mform->setType('headercontent_editor', PARAM_RAW);
        $mform->addHelpButton('headercontent_editor', 'headercontent', 'mod_avatar');

        // Footer content.
        $mform->addElement('editor', 'footercontent_editor',
            get_string('footercontent', 'mod_avatar'), ['rows' => 10], $editoroptions);
        $mform->setType('footercontent_editor', PARAM_RAW);
        $mform->addHelpButton('footercontent_editor', 'footercontent', 'mod_avatar');

        // Empty state content.
        $mform->addElement('editor', 'emptystate_editor',
            get_string('emptystate', 'mod_avatar'), ['rows' => 10], $editoroptions);
        $mform->setType('emptystate_editor', PARAM_RAW);

        // Standard module elements.
        $this->standard_coursemodule_elements();

        // Action buttons.
        $this->add_action_buttons();
    }

    /**
     * Add elements for setting the custom completion rules.
     *
     * @return array List of added element names, or names of wrapping group elements.
     */
    public function add_completion_rules() {

        $mform = $this->_form;
        $suffix = $this->get_suffix();
        $mform->addElement('checkbox', 'completioncollect' . $suffix, '', get_string('completioncollect', 'mod_avatar'));

        return ['completionview' . $suffix, 'completioncollect' . $suffix];
    }

    /**
     * Enabled completion rules.
     *
     * @param array $data Input data not yet validated.
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completionview' . $suffix]) || !empty($data['completioncollect' . $suffix]);
    }

    /**
     * Get the suffixed name for a compeltion element
     *
     * @param string $fieldname The base field name
     * @return string The field name with the appropriate suffix
     */
    protected function get_suffixed_name(string $fieldname): string {
        return $fieldname . $this->get_suffix();
    }

    /**
     * Process the data before it is saved.
     *
     * @return array
     */
    public function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return $data;
        }
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completioncollect) || !$autocompletion) {
                $data->completioncollectcount = 0;
            }
        }
        return $data;
    }

    /**
     * Post-process the data before it is saved.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completionview) || !$autocompletion) {
                $data->completionview = 0;
            }
            if (empty($data->completioncollect) || !$autocompletion) {
                $data->completioncollect = 0;
            }
        }
    }

    /**
     * Prepares the data for the form. Prepare the file areas.
     *
     * @param array $defaultvalues The default values for the form.
     */
    public function data_preprocessing(&$defaultvalues) {

        $fileareas = ['headercontent', 'footercontent', 'emptystate'];

        foreach ($fileareas as $area) {
            if ($this->current->instance) {

                $draftitemid = file_get_submitted_draft_itemid($area);
                $defaultvalues[$area . '_editor']['text'] = file_prepare_draft_area(
                    $draftitemid, $this->context->id, 'mod_avatar', $area, 0, avatar_get_editor_options(), $defaultvalues[$area]);

                $defaultvalues[$area . '_editor']['format'] = $defaultvalues[$area . 'format'] ?? 0;
                $defaultvalues[$area . '_editor']['itemid'] = $draftitemid;
            } else {
                $draftitemid = file_get_submitted_draft_itemid($area . '_editor');
                file_prepare_draft_area($draftitemid, null, 'mod_avatar', $area, false);
                $defaultvalues[$area . '_editor']['format'] = editors_get_preferred_format();
                $defaultvalues[$area . '_editor']['itemid'] = $draftitemid;
            }
        }
    }
}

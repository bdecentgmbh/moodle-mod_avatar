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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

use mod_avatar\hook\extend_mod_form;
use mod_avatar\hook\mod_form_validation;

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
        $mform->setDefault('displaymode', (int) get_config('mod_avatar', 'displaymode'));

        $editoroptions = avatar_get_editor_options($this->context);

        // Header content.
        $mform->addElement(
            'editor',
            'headercontent_editor',
            get_string('headercontent', 'mod_avatar'),
            ['rows' => 10],
            $editoroptions
        );
        $mform->setType('headercontent_editor', PARAM_RAW);
        $mform->addHelpButton('headercontent_editor', 'headercontent', 'mod_avatar');

        // Footer content.
        $mform->addElement(
            'editor',
            'footercontent_editor',
            get_string('footercontent', 'mod_avatar'),
            ['rows' => 10],
            $editoroptions
        );
        $mform->setType('footercontent_editor', PARAM_RAW);
        $mform->addHelpButton('footercontent_editor', 'footercontent', 'mod_avatar');

        // Empty state content.
        $mform->addElement(
            'editor',
            'emptystate_editor',
            get_string('emptystate', 'mod_avatar'),
            ['rows' => 10],
            $editoroptions
        );
        $mform->setType('emptystate_editor', PARAM_RAW);
        $mform->addHelpButton('emptystate_editor', 'emptystate', 'mod_avatar');

        // Settings of the addons.
        \core\di::get(\core\hook\manager::class)->dispatch(
            new extend_mod_form($mform, (int) ($this->current->instance ?? 0), $this->context)
        );

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

        return ['completioncollect' . $suffix];
    }

    /**
     * Enabled completion rules.
     *
     * @param array $data Input data not yet validated.
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();
        return !empty($data['completioncollect' . $suffix]);
    }

    /**
     * Validation on the settings form.
     *
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (($data['avatarselection'] ?? 0) == \mod_avatar\avatar::SELECTION_SPECIFIC && trim($data['specifictags'] ?? '') === '') {
            $errors['specifictags'] = get_string('required');
        }

        $hook = new mod_form_validation($data, $files);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return array_merge($errors, $hook->get_errors());
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
     * Post-process the data before it is saved.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion == COMPLETION_TRACKING_AUTOMATIC;
            $suffix = $this->get_suffix();
            if (empty($data->{'completioncollect' . $suffix}) || !$autocompletion) {
                $data->{'completioncollect' . $suffix} = 0;
            }
        }
    }

    /**
     * Prepares the editors of the form.
     *
     * @param array $defaultvalues The default values for the form.
     */
    public function data_preprocessing(&$defaultvalues) {
        $options = avatar_get_editor_options($this->context);
        foreach (['headercontent', 'footercontent', 'emptystate'] as $area) {
            $draftitemid = file_get_submitted_draft_itemid($area . '_editor');
            if ($this->current->instance) {
                $defaultvalues[$area . '_editor'] = [
                    'text' => file_prepare_draft_area(
                        $draftitemid,
                        $this->context->id,
                        'mod_avatar',
                        $area,
                        $defaultvalues['id'],
                        $options,
                        $defaultvalues[$area] ?? ''
                    ),
                    'format' => $defaultvalues[$area . 'format'] ?? FORMAT_HTML,
                    'itemid' => $draftitemid,
                ];
            } else {
                file_prepare_draft_area($draftitemid, null, 'mod_avatar', $area, false);
                $defaultvalues[$area . '_editor'] = ['text' => '', 'format' => editors_get_preferred_format(),
                    'itemid' => $draftitemid];
            }
        }
    }
}

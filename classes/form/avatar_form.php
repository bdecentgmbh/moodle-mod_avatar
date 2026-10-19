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
 * Avatar creation and editing form
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\form;

defined('MOODLE_INTERNAL') || die();

use mod_avatar\avatar;
use mod_avatar\hook\avatar_form_validation;
use mod_avatar\hook\extend_avatar_form;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create and edit an avatar of the avatar library.
 *
 * Custom data: avatarid (0 for a new avatar), context, maxbytes.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class avatar_form extends \moodleform {
    /**
     * Form definition.
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        // The avatar is given by the page, never by the submitted data.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->setConstants(['id' => (int) ($this->_customdata['avatarid'] ?? 0)]);

        // General settings.
        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('text', 'name', get_string('avatarname', 'mod_avatar'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('text', 'idnumber', get_string('idnumber'), ['size' => '64']);
        $mform->setType('idnumber', PARAM_RAW_TRIMMED);
        $mform->addRule('idnumber', get_string('maximumchars', '', 100), 'maxlength', 100, 'client');

        $mform->addElement('editor', 'description_editor', get_string('description'), null, avatar::get_editor_options());
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('textarea', 'internalnotes', get_string('internalnotes', 'mod_avatar'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('internalnotes', PARAM_TEXT);

        $mform->addElement(
            'editor',
            'secretinfo_editor',
            get_string('secretinfo', 'mod_avatar'),
            ['rows' => 3],
            avatar::get_editor_options(0)
        );
        $mform->setType('secretinfo_editor', PARAM_RAW);
        $mform->addHelpButton('secretinfo_editor', 'secretinfo', 'mod_avatar');

        // Gallery.
        $mform->addElement('header', 'gallery', get_string('gallery', 'mod_avatar'));

        $mform->addElement(
            'filemanager',
            'previewimage',
            get_string('previewimage', 'mod_avatar'),
            null,
            avatar::get_filemanager_options('previewimage')
        );
        $mform->addRule('previewimage', null, 'required', null, 'client');

        $mform->addElement(
            'filemanager',
            'additionalmedia',
            get_string('additionalmedia', 'mod_avatar'),
            null,
            avatar::get_filemanager_options('additionalmedia')
        );

        // Media.
        $mform->addElement('header', 'media', get_string('media', 'mod_avatar'));

        $variantoptions = range(1, avatar::MAX_VARIANTS);
        $mform->addElement(
            'select',
            'variants',
            get_string('variants', 'mod_avatar'),
            array_combine($variantoptions, $variantoptions)
        );
        $mform->setDefault('variants', 1);

        // Changing the number of variants reloads the form with the matching image fields.
        $mform->registerNoSubmitButton('updatevariants');
        $mform->addElement('submit', 'updatevariants', get_string('updatevariants', 'mod_avatar'), ['class' => 'd-none']);
        $PAGE->requires->js_amd_inline("
            const variants = document.querySelector('#id_variants');
            if (variants) {
                variants.addEventListener('change', () => document.querySelector('#id_updatevariants').click());
            }");

        // Tags.
        $mform->addElement('tags', 'tags', get_string('tags'), ['component' => 'mod_avatar', 'itemtype' => 'avatar']);
        $mform->addHelpButton('tags', 'tags', 'mod_avatar');
    }

    /**
     * Adds the fields of the variants (for the selected number of variants) and the fields of the addons.
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform = $this->_form;
        $variants = (int) ($mform->getElementValue('variants')[0] ?? 1);

        for ($i = 1; $i <= min($variants, avatar::MAX_VARIANTS); $i++) {
            $fields = [
                "avatarimage{$i}" => get_string('avatarimage', 'mod_avatar'),
                "avatarthumbnail{$i}" => get_string('avatarthumbnail', 'mod_avatar'),
                "animationstates{$i}" => get_string('animationstates', 'mod_avatar'),
            ];
            foreach ($fields as $name => $label) {
                // Added, then moved before the tags (insertElementBefore() needs an element of the form).
                $mform->addElement('filemanager', $name, "{$label} {$i}", null, avatar::get_filemanager_options($name));
                $mform->insertElementBefore($mform->removeElement($name, false), 'tags');
            }
            $mform->addRule("avatarimage{$i}", get_string('required'), 'required', null, 'client');
        }

        \core\di::get(\core\hook\manager::class)->dispatch(
            new extend_avatar_form($mform, (int) ($this->_customdata['avatarid'] ?? 0))
        );

        $this->add_action_buttons();
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $DB, $USER;

        $errors = parent::validation($data, $files);

        // The image fields must contain a file, not just a draft area.
        $usercontext = \core\context\user::instance($USER->id);
        $fs = get_file_storage();
        $required = ['previewimage'];
        for ($i = 1; $i <= min((int) $data['variants'], avatar::MAX_VARIANTS); $i++) {
            $required[] = "avatarimage{$i}";
        }
        foreach ($required as $field) {
            if (empty($data[$field]) || $fs->is_area_empty($usercontext->id, 'user', 'draft', $data[$field])) {
                $errors[$field] = get_string('required');
            }
        }

        if (
            $data['idnumber'] !== '' && $DB->record_exists_select(
                'avatar_list',
                'idnumber = :idnumber AND id <> :id',
                ['idnumber' => $data['idnumber'], 'id' => (int) ($this->_customdata['avatarid'] ?? 0)]
            )
        ) {
            $errors['idnumber'] = get_string('idnumberexists', 'mod_avatar');
        }

        $hook = new avatar_form_validation($data, $files);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);

        return array_merge($errors, $hook->get_errors());
    }
}

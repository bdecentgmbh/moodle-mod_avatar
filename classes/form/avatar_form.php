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

use context_system;
use mod_avatar\plugininfo\avataraddon;

require_once("$CFG->libdir/formslib.php");

/**
 * Avatar creation form.
 */
class avatar_form extends \moodleform {

    /**
     * @var int Maximum file size for uploaded files.
     */
    protected $maxbytes;

    /**
     * @var int Maximum number of variants.
     */
    protected $maxvariants = 10;

    /**
     * Constructor
     *
     * @param string $action Form action URL.
     * @param array $customdata Custom data for the form.
     * @param string $method Form submission method (default: 'post').
     * @param string $target Target for form submission.
     * @param array $attributes Additional attributes for the form.
     * @param bool $editable Whether the form is editable (default: true).
     * @param array $ajaxformdata Data for AJAX form submission.
     * @return void
     */
    public function __construct($action = null, $customdata = null, $method = 'post', $target = '',
        $attributes = null, $editable = true, $ajaxformdata = null) {

        $this->maxbytes = $customdata['maxbytes'] ?? 0;
        parent::__construct($action, $customdata, $method, $target, $attributes, $editable, $ajaxformdata);
    }

    /**
     * Form definition
     */
    public function definition() {
        global $CFG, $PAGE;

        $mform = $this->_form;

        // Add hidden id field.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // General settings.
        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('text', 'name', get_string('avatarname', 'mod_avatar'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('idnumber'), ['size' => '64']);
        $mform->setType('idnumber', PARAM_RAW);

        $mform->addElement('editor', 'description_editor',
            get_string('description'), null, $this->get_description_editor_options());
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('textarea', 'internalnotes', get_string('internalnotes', 'mod_avatar'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('internalnotes', PARAM_TEXT);

        $mform->addElement('editor', 'secretinfo_editor', get_string('secretinfo', 'mod_avatar'),
            ['rows' => 3, 'cols' => 60], $this->get_description_editor_options(0));
        $mform->setType('secretinfo', PARAM_TEXT);

        // Gallery.
        $mform->addElement('header', 'gallery', get_string('gallery', 'mod_avatar'));

        $mform->addElement('filemanager', 'previewimage', get_string('previewimage', 'mod_avatar'), null,
            ['maxbytes' => $this->maxbytes, 'accepted_types' => ['image']]);
        $mform->addRule('previewimage', null, 'required', null, 'client');

        $mform->addElement('filemanager', 'additionalmedia', get_string('additionalmedia', 'mod_avatar'), null,
            ['maxbytes' => $this->maxbytes, 'accepted_types' => ['image', 'video']]);

        // Media.
        $mform->addElement('header', 'media', get_string('media', 'mod_avatar'));

        $variantoptions = range(1, $this->maxvariants);
        $mform->addElement('select', 'variants',
            get_string('variants', 'mod_avatar'), array_combine($variantoptions, $variantoptions));
        $mform->setDefault('variants', 1);

        $mform->registerNoSubmitButton('updatevariants');
        $mform->addElement('submit', 'updatevariants', get_string('updatevariants', 'mod_avatar'), ['class' => 'd-none']);

        // Tags.
        $mform->addElement('tags', 'tags', get_string('tags'), ['component' => 'mod_avatar', 'itemtype' => 'avatar']);
        $mform->addHelpButton('tags', 'tags', 'mod_avatar');

        // Add capability check for managing avatars.
        if (!has_capability('mod/avatar:manage', context_system::instance())) {
            $mform->disabledIf('coursecategories', 'id', 'eq', 0); // Disable for new avatars.
            $mform->disabledIf('coursecategories', 'id', 'neq', 0); // Disable for existing avatars.
        }

        // Add custom javascript to handle variant length.
        $PAGE->requires->js_amd_inline("
            document.querySelector('select[name=\"variants\"]') !== null ? document.querySelector('select[name=\"variants\"]')
                .onchange = (e) => document.querySelector('input[name=updatevariants]').click() : ''; "
        );
    }

    /**
     * Get description editor options.
     *
     * @param int $files Maximum number of files.
     * @return array
     */
    public function get_description_editor_options($files = EDITOR_UNLIMITED_FILES) {
        global $CFG;
        return [
            'maxfiles' => $files,
            'maxbytes' => $this->maxbytes,
            'trusttext' => false,
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL,
            'context' => $this->_customdata['context'],
        ];
    }

    /**
     * Form validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate preview image.
        if (empty($data['previewimage'])) {
            $errors['previewimage'] = get_string('required');
        }

        // Validate avatar images for selected variants.
        for ($i = 1; $i <= $data['variants']; $i++) {
            $avatarimagekey = "avatarimage{$i}";
            if (empty($data[$avatarimagekey])) {
                $errors[$avatarimagekey] = get_string('required');
            }
        }

        return $errors;
    }

    /**
     * Include variant related fields after form data is set.
     *
     * @return void
     */
    public function definition_after_data() {
        parent::definition_after_data();

        $mform = $this->_form;
        $variants = $mform->getElementValue('variants');

        if ($variants) {
            $variants = $variants[0];
            $list = [];

            $fileoptions = ['maxbytes' => $this->maxbytes, 'accepted_types' => ['image'], 'maxfiles' => -1];
            for ($i = 1; $i <= $this->maxvariants; $i++) {
                if ($i <= $variants) {
                    $list[] = &$mform->addElement('filemanager', "avatarimage{$i}",
                        get_string('avatarimage', 'mod_avatar') . " {$i}", null, $fileoptions + ['maxfiles' => 1]);

                    $mform->addRule("avatarimage{$i}", get_string('required'), 'required', null, 'client');

                    $list[] = &$mform->addElement('filemanager', "avatarthumbnail{$i}",
                        get_string('avatarthumbnail', 'mod_avatar') . " {$i}", null, $fileoptions + ['maxfiles' => 1]);

                    $list[] = &$mform->addElement('filemanager', "animationstates{$i}",
                        get_string('animationstates', 'mod_avatar') . " {$i}", null, $fileoptions );
                }
            }

            foreach ($list as &$element) {
                $mform->insertElementBefore($mform->removeElement($element->getName(), false), 'tags');
            }
        }

        // Extend the avatar form fields from addon.
        avataraddon::pro_util()?->avatar_form_definition_after_data($mform);

        $this->add_action_buttons();
    }
}

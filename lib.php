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
 * Avatar lib.php - Defines the libarary methods.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_avatar\avatar;

/**
 * Returns the information on whether the module supports a feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function avatar_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the avatar into the database.
 *
 * @param stdClass $avatar An object from the form in mod_form.php
 * @param mod_avatar_mod_form|null $mform
 * @return int The id of the newly inserted avatar record
 */
function avatar_add_instance(stdClass $avatar, ?mod_avatar_mod_form $mform = null) {
    global $DB;

    $avatar->timecreated = time();

    // Handle header content files.
    $avatar = file_postupdate_standard_editor($avatar, 'headercontent', avatar_get_editor_options(),
        context_module::instance($avatar->coursemodule), 'mod_avatar', 'headercontent', 0);
    $avatar->headercontentformat = $avatar->headercontentformat ?? FORMAT_HTML;

    // Handle footer content files.
    $avatar = file_postupdate_standard_editor($avatar, 'footercontent', avatar_get_editor_options(),
        context_module::instance($avatar->coursemodule), 'mod_avatar', 'footercontent', 0);
    $avatar->footercontentformat = $avatar->footercontentformat ?? FORMAT_HTML;

    $avatar = file_postupdate_standard_editor($avatar, 'emptystate', avatar_get_editor_options(),
        context_module::instance($avatar->coursemodule), 'mod_avatar', 'emptystate', 0);
    $avatar->emptystateformat = $avatar->emptystateformat ?? FORMAT_HTML;

    // Avatar selection type.
    if ($avatar->avatarselection == avatar::SELECTION_SPECIFIC) {
        $avatar->specifictags = trim($avatar->specifictags);
    } else {
        $avatar->specifictags = '';
    }

    $avatar->id = $DB->insert_record('avatar', $avatar);

    $completionexpected = (!empty($avatar->completionexpected)) ? $avatar->completionexpected : null;
    \core_completion\api::update_completion_date_event($avatar->coursemodule, 'avatar', $avatar->id, $completionexpected);

    return $avatar->id;
}

/**
 * Updates an instance of the avatar in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $avatar An object from the form in mod_form.php
 * @param mod_avatar_mod_form|null $mform
 * @return boolean Success/Fail
 */
function avatar_update_instance(stdClass $avatar, ?mod_avatar_mod_form $mform = null) {
    global $DB;

    $avatar->timemodified = time();
    $avatar->id = $avatar->instance;

    // Handle header content files.
    $avatar = file_postupdate_standard_editor($avatar, 'headercontent',
        avatar_get_editor_options(), context_module::instance($avatar->coursemodule), 'mod_avatar', 'headercontent', $avatar->id);
    $avatar->headercontentformat = $avatar->headercontentformat ?? FORMAT_HTML;

    // Handle footer content files.
    $avatar = file_postupdate_standard_editor($avatar, 'footercontent',
        avatar_get_editor_options(), context_module::instance($avatar->coursemodule), 'mod_avatar', 'footercontent', $avatar->id);
    $avatar->footercontentformat = $avatar->footercontentformat ?? FORMAT_HTML;

    $avatar = file_postupdate_standard_editor($avatar, 'emptystate',
        avatar_get_editor_options(), context_module::instance($avatar->coursemodule), 'mod_avatar', 'emptystate', $avatar->id);
    $avatar->emptystateformat = $avatar->emptystateformat ?? FORMAT_HTML;

    // Handle specific tags.
    if ($avatar->avatarselection == avatar::SELECTION_SPECIFIC) {
        $avatar->specifictags = trim($avatar->specifictags);
    } else {
        $avatar->specifictags = '';
    }

    $completionexpected = (!empty($avatar->completionexpected)) ? $avatar->completionexpected : null;
    \core_completion\api::update_completion_date_event($avatar->coursemodule, 'avatar', $avatar->id, $completionexpected);

    return $DB->update_record('avatar', $avatar);
}

/**
 * Removes an instance of the avatar from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function avatar_delete_instance($id) {
    global $DB;

    if (!$avatar = $DB->get_record('avatar', ['id' => $id])) {
        return false;
    }

    // Delete files associated with this avatar.
    $cm = get_coursemodule_from_instance('avatar', $id);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_avatar');

    // Delete the instance from db.
    $DB->delete_records('avatar', ['id' => $avatar->id]);

    return true;
}

/**
 * Serves the files from the avatar file areas
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the avatar's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file not found, just send the file otherwise
 */
function avatar_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=[]) {

    $fileareas = ['headercontent', 'footercontent', 'emptystate'];
    $systemfiles = ['previewimage', 'additionalmedia', 'tagimages', 'description'];
    for ($i = 1; $i <= 10; $i++) {
        $systemfiles[] = 'avatarimage' . $i;
        $systemfiles[] = 'avatarthumbnail' .  $i;
        $systemfiles[] = 'animationstates' .  $i;
    }

    if (!in_array($filearea, array_merge($fileareas, $systemfiles))) {
        return false;
    }

    if (!in_array($filearea, $fileareas) && $context->contextlevel == CONTEXT_MODULE) {
        return false;
    }

    if ($context->contextlevel == CONTEXT_SYSTEM && !in_array($filearea, $systemfiles)) {
        return false;
    }

    $itemid = array_shift($args);
    $filename = array_pop($args);

    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' . implode('/', $args) . '/';
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_avatar', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }

    // Send the file.
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}

/**
 * Get editor options for avatar module.
 *
 * @return array
 */
function avatar_get_editor_options() {
    global $CFG;

    require_once("$CFG->libdir/formslib.php");

    return [
        'subdirs' => 0,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'changeformat' => 1,
        'context' => null,
        'noclean' => 0,
        'trusttext' => 0,
    ];
}

/**
 * Callback function to search for avatars with a specific tag.
 *
 * @param core_tag_tag $tag Tag object
 * @param bool $exclusivemode If set to true, items that are tagged with other tags will be excluded
 * @param int $fromctx Context id where the link was displayed
 * @param int $ctx Context id where to search for records
 * @param bool $rec Search in subcontexts?
 * @param int $page Page number
 * @return \core_tag\output\tagindex
 */
function mod_avatar_get_tagged_avatars($tag, $exclusivemode = false, $fromctx = 0, $ctx = 0, $rec = true, $page = 0) {
    global $OUTPUT;
    $perpage = 20;

    // Build the SQL query.
    $avatarlist = new \mod_avatar\tag\avatar_tag_search($tag, $exclusivemode, $fromctx, $ctx, $rec, $page, $perpage);

    $content = $OUTPUT->render_from_template('mod_avatar/tagindex', $avatarlist->get_content());

    return new \core_tag\output\tagindex($tag, 'mod_avatar', 'avatar', $content,
        $exclusivemode, $fromctx, $ctx, $rec, $page, $avatarlist->get_count());
}

/**
 * Obtains the automatic completion state for this avatar based on any conditions
 * in avatar settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function avatar_get_completion_state($course, $cm, $userid, $type) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/avatar/classes/completion/custom_completion.php');

    $completion = new \mod_avatar\completion\custom_completion($cm, $userid);

    if ($completion->get_state('completioncollect') == COMPLETION_INCOMPLETE) {
        return COMPLETION_INCOMPLETE;
    }

    return COMPLETION_COMPLETE;
}

/**
 * Add a get_coursemodule_info function in case any avatar type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function avatar_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, displaymode, completioncollect';
    if (!$avatar = $DB->get_record('avatar', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $avatar->name;

    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('avatar', $avatar, $coursemodule->id, false);
    }

    // Add display mode to the result.
    $result->customdata['displaymode'] = $avatar->displaymode;

    // Populate the custom completion rules.
    $result->customdata['customcompletionrules']['completioncollect'] = $avatar->completioncollect;

    return $result;
}

/**
 * Extend the course module info for avatar module.
 * Include the avatar activity content for inline display mode.
 *
 * @param cm_info $cm The course module info instance.
 */
function mod_avatar_cm_info_view(cm_info $cm) {
    global $DB, $USER, $PAGE;

    $avatar = $DB->get_record('avatar', ['id' => $cm->instance]);

    if (isset($avatar->displaymode) && $avatar->displaymode == 1) {
        // Inline display mode.
        $content = $cm->get_formatted_content(['overflowdiv' => true, 'noclean' => true]);

        // Get the avatar activity content.
        $renderer = $PAGE->get_renderer('mod_avatar');
        $avatarobj = new \mod_avatar\output\avatar_activity($avatar, $cm, $cm->get_course());
        $activitycontent = $renderer->render($avatarobj);

        // Combine the formatted content with the avatar activity content.
        $content .= $activitycontent;

        $cm->set_content($content);

        $PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);
    }

}

/**
 * Mark the activity completed and trigger the course_module_viewed event.
 *
 * @param  stdClass $avatar       avatar object
 * @param  stdClass $course     course object
 * @param  stdClass $cm         course module object
 * @param  stdClass $context    context object
 * @since Moodle 3.1
 */
function avatar_view($avatar, $course, $cm, $context) {

    $event = \mod_avatar\event\course_module_viewed::create([
        'objectid' => $cm->instance,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot($cm->modname, $avatar);
    $event->trigger();

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Returns the HTML for the avatar usage table.
 *
 * @param array $args The arguments passed to the function.
 * @return string HTML for the avatar usage table.
 */
function mod_avatar_output_fragment_avatar_usage($args) {
    global $OUTPUT;

    $avatarid = $args['avatarid'];
    $type = $args['type'];

    $filterset = new \mod_avatar\table\avatar_usage_table_filterset();

    $filterset->add_filter(new \core_table\local\filter\integer_filter(
        'avatarid', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [(int) $avatarid]));
    $filterset->add_filter(new \core_table\local\filter\string_filter(
        'type', \core_table\local\filter\filter::JOINTYPE_DEFAULT, [(string) $type]));

    $table = new \mod_avatar\table\avatar_usage_table(
        'avatar_usage_' . $type,
        $avatarid
    );

    $table->set_filterset($filterset);
    ob_start();
    $table->out(25, true);
    $tablecontent = ob_get_clean();

    return $tablecontent;
}

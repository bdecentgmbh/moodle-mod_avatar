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
 * Library of functions of the avatar activity.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_avatar\avatar;
use mod_avatar\hook\after_instance_saved;
use mod_avatar\hook\before_instance_deleted;
use mod_avatar\hook\extend_activity_navigation;

/** Display mode: the avatars are shown on the page of the activity. */
define('AVATAR_DISPLAY_PAGE', 0);

/** Display mode: the avatars are shown on the course page. */
define('AVATAR_DISPLAY_INLINE', 1);

/**
 * Returns the information on whether the module supports a feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function avatar_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPS:
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Saves the editor fields of the activity form into the file areas of the activity.
 *
 * @param stdClass $avatar The data of the activity form (with id and coursemodule).
 * @return stdClass The data with the rewritten texts and formats.
 */
function avatar_save_editors(stdClass $avatar): stdClass {
    $context = \core\context\module::instance($avatar->coursemodule);
    foreach (['headercontent', 'footercontent', 'emptystate'] as $field) {
        if (!isset($avatar->{$field . '_editor'})) {
            continue;
        }
        $avatar = file_postupdate_standard_editor(
            $avatar,
            $field,
            avatar_get_editor_options($context),
            $context,
            'mod_avatar',
            $field,
            $avatar->id
        );
    }
    return $avatar;
}

/**
 * Normalises the avatar selection of the activity form.
 *
 * @param stdClass $avatar The data of the activity form.
 * @return stdClass
 */
function avatar_prepare_selection(stdClass $avatar): stdClass {
    if (isset($avatar->avatarselection) && $avatar->avatarselection == avatar::SELECTION_SPECIFIC) {
        $avatar->specifictags = trim((string) ($avatar->specifictags ?? ''));
    } else {
        $avatar->avatarselection = avatar::SELECTION_ALL;
        $avatar->specifictags = '';
    }
    return $avatar;
}

/**
 * Saves a new instance of the avatar activity into the database.
 *
 * @param stdClass $avatar An object from the form in mod_form.php
 * @param mod_avatar_mod_form|null $mform
 * @return int The id of the newly inserted avatar record
 */
function avatar_add_instance(stdClass $avatar, ?mod_avatar_mod_form $mform = null) {
    global $DB;

    $avatar->timecreated = time();
    $avatar->timemodified = $avatar->timecreated;
    $avatar = avatar_prepare_selection($avatar);
    $avatar->id = $DB->insert_record('avatar', $avatar);

    $avatar = avatar_save_editors($avatar);
    $DB->update_record('avatar', $avatar);

    \core\di::get(\core\hook\manager::class)->dispatch(new after_instance_saved($avatar, true));

    $completionexpected = !empty($avatar->completionexpected) ? $avatar->completionexpected : null;
    \core_completion\api::update_completion_date_event($avatar->coursemodule, 'avatar', $avatar->id, $completionexpected);

    return $avatar->id;
}

/**
 * Updates an instance of the avatar activity in the database.
 *
 * @param stdClass $avatar An object from the form in mod_form.php
 * @param mod_avatar_mod_form|null $mform
 * @return bool
 */
function avatar_update_instance(stdClass $avatar, ?mod_avatar_mod_form $mform = null) {
    global $DB;

    $avatar->timemodified = time();
    $avatar->id = $avatar->instance;
    $avatar = avatar_prepare_selection($avatar);
    $avatar = avatar_save_editors($avatar);
    $DB->update_record('avatar', $avatar);

    \core\di::get(\core\hook\manager::class)->dispatch(new after_instance_saved($avatar, false));

    $completionexpected = !empty($avatar->completionexpected) ? $avatar->completionexpected : null;
    \core_completion\api::update_completion_date_event($avatar->coursemodule, 'avatar', $avatar->id, $completionexpected);

    return true;
}

/**
 * Removes an instance of the avatar activity from the database.
 *
 * The avatars collected in the activity stay in the collections of the users.
 *
 * @param int $id Id of the module instance
 * @return bool
 */
function avatar_delete_instance($id) {
    global $DB;

    if (!$avatar = $DB->get_record('avatar', ['id' => $id])) {
        return false;
    }

    \core\di::get(\core\hook\manager::class)->dispatch(new before_instance_deleted($avatar));

    if ($cm = get_coursemodule_from_instance('avatar', $id, 0, false, IGNORE_MISSING)) {
        // Keep the collected avatars, but no longer link them to the deleted activity.
        $DB->set_field('avatar_user', 'cmid', 0, ['cmid' => $cm->id]);
        $context = \core\context\module::instance($cm->id, IGNORE_MISSING);
        if ($context) {
            get_file_storage()->delete_area_files($context->id, 'mod_avatar');
        }
    }

    $DB->delete_records('avatar', ['id' => $avatar->id]);

    return true;
}

/**
 * The file areas of the avatar library (system context): images and descriptions of the avatars.
 *
 * @return string[]
 */
function avatar_get_library_fileareas(): array {
    $areas = ['previewimage', 'additionalmedia', 'description', 'tagimages'];
    for ($i = 1; $i <= avatar::MAX_VARIANTS; $i++) {
        $areas[] = 'avatarimage' . $i;
        $areas[] = 'avatarthumbnail' . $i;
        $areas[] = 'animationstates' . $i;
    }
    return $areas;
}

/**
 * Serves the files of the avatar activity and of the avatar library.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param \core\context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file is not found, otherwise the file is sent
 */
function avatar_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_MODULE) {
        // Texts of the activity: header, footer and empty state.
        if (!in_array($filearea, ['headercontent', 'footercontent', 'emptystate'])) {
            return false;
        }
        require_course_login($course, true, $cm);
        require_capability('mod/avatar:view', $context);
    } else if ($context->contextlevel == CONTEXT_SYSTEM) {
        // Images and descriptions of the avatar library.
        if (!in_array($filearea, avatar_get_library_fileareas())) {
            return false;
        }
        require_login(null, true);
        require_capability('mod/avatar:view', $context);
    } else {
        return false;
    }

    $itemid = (int) array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $file = get_file_storage()->get_file($context->id, 'mod_avatar', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, DAYSECS, 0, $forcedownload, $options);
}

/**
 * Options of the editors of the activity form.
 *
 * @param \core\context|null $context The context of the activity.
 * @return array
 */
function avatar_get_editor_options(?\core\context $context = null) {
    global $CFG;

    require_once($CFG->libdir . '/formslib.php');

    return [
        'subdirs' => 0,
        'maxbytes' => $CFG->maxbytes,
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'changeformat' => 1,
        'context' => $context,
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

    $avatarlist = new \mod_avatar\tag\avatar_tag_search($tag, $exclusivemode, $fromctx, $ctx, $rec, $page, $perpage);
    $content = $OUTPUT->render_from_template('mod_avatar/tagindex', $avatarlist->get_content());

    return new \core_tag\output\tagindex(
        $tag,
        'mod_avatar',
        'avatar',
        $content,
        $exclusivemode,
        $fromctx,
        $ctx,
        $rec,
        $page,
        $avatarlist->get_count()
    );
}

/**
 * Information about the activity for the course page (and the custom completion rules).
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info|false
 */
function avatar_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, displaymode, completioncollect';
    if (!$avatar = $DB->get_record('avatar', ['id' => $coursemodule->instance], $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $avatar->name;

    if ($coursemodule->showdescription) {
        $result->content = format_module_intro('avatar', $avatar, $coursemodule->id, false);
    }

    $result->customdata['displaymode'] = (int) $avatar->displaymode;

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completioncollect'] = $avatar->completioncollect;
    }

    return $result;
}

/**
 * Shows the avatars of the activity on the course page when its display mode is inline.
 *
 * @param cm_info $cm The course module info instance.
 */
function mod_avatar_cm_info_view(cm_info $cm) {
    global $DB, $PAGE;

    if (($cm->customdata['displaymode'] ?? AVATAR_DISPLAY_PAGE) != AVATAR_DISPLAY_INLINE || !$cm->uservisible) {
        return;
    }
    if (!has_capability('mod/avatar:view', $cm->context)) {
        return;
    }

    $avatar = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);
    $renderer = $PAGE->get_renderer('mod_avatar');
    $activity = new \mod_avatar\output\avatar_activity($avatar, $cm, $cm->get_course());

    $cm->set_content($cm->get_formatted_content(['overflowdiv' => true]) . $renderer->render($activity), true);
    $PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);
}

/**
 * Marks the activity viewed (completion) and triggers the course_module_viewed event.
 *
 * @param stdClass $avatar The activity instance.
 * @param stdClass $course The course.
 * @param stdClass|cm_info $cm The course module.
 * @param \core\context $context The module context.
 */
function avatar_view($avatar, $course, $cm, $context) {
    global $CFG;
    require_once($CFG->libdir . '/completionlib.php');

    $event = \mod_avatar\event\course_module_viewed::create([
        'objectid' => $cm->instance,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('avatar', $avatar);
    $event->trigger();

    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Fragment: where an avatar of the library is used (activities, courses or users), for the Manage avatars page.
 *
 * @param array $args The arguments: context, avatarid, type (activities, courses, users).
 * @return string HTML of the usage table.
 */
function mod_avatar_output_fragment_avatar_usage($args) {
    $context = \core\context\system::instance();
    require_capability('mod/avatar:overview', $context);

    $avatarid = clean_param($args['avatarid'] ?? 0, PARAM_INT);
    $type = clean_param($args['type'] ?? '', PARAM_ALPHA);
    if (!in_array($type, ['activities', 'courses', 'users'])) {
        throw new \moodle_exception('invalidparameter', 'debug');
    }

    $filterset = new \mod_avatar\table\avatar_usage_table_filterset();
    $filterset->add_filter(new \core_table\local\filter\integer_filter(
        'avatarid',
        \core_table\local\filter\filter::JOINTYPE_DEFAULT,
        [$avatarid]
    ));
    $filterset->add_filter(new \core_table\local\filter\string_filter(
        'type',
        \core_table\local\filter\filter::JOINTYPE_DEFAULT,
        [$type]
    ));

    $table = new \mod_avatar\table\avatar_usage_table('avatar_usage_' . $type);
    $table->set_filterset($filterset);

    ob_start();
    $table->out(25, true);
    return ob_get_clean();
}

/**
 * Adds the pages of the addons to the navigation of the activity.
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $node The node to add module settings to
 */
function avatar_extend_settings_navigation(settings_navigation $settings, navigation_node $node) {
    $cm = $settings->get_page()->cm;
    if ($cm) {
        \core\di::get(\core\hook\manager::class)->dispatch(new extend_activity_navigation($node, $cm));
    }
}

/**
 * Adds "My avatars" to the profile of the current user.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser is the user viewing profile, current user ?
 * @param stdClass $course course object
 */
function mod_avatar_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (
        !$iscurrentuser || !isloggedin() || isguestuser()
            || !has_capability('mod/avatar:view', \core\context\system::instance())
    ) {
        return;
    }

    $category = 'miscellaneous';
    if (!array_key_exists($category, $tree->__get('categories'))) {
        $category = 'avatar';
        $tree->add_category(new core_user\output\myprofile\category(
            $category,
            get_string('pluginname', 'mod_avatar'),
            'privacyandpolicies'
        ));
    }

    $tree->add_node(new core_user\output\myprofile\node(
        $category,
        'myavatars',
        get_string('myavatars', 'mod_avatar'),
        null,
        new \moodle_url('/mod/avatar/myavatars.php')
    ));
}

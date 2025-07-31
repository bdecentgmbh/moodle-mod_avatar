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
 * Avatar module - Behat step definitions
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Gherkin\Node\TableNode;

/**
 * Avatar module - Behat step definitions
 */
class behat_mod_avatar extends behat_base {

    /**
     * Navigate to avatar management page.
     *
     * @Given /^I navigate to avatar management$/
     */
    public function i_navigate_to_avatar_management() {
        $this->execute('behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Avatar > Manage avatars']);
    }

    /**
     * Navigate to avatar settings page.
     *
     * @Given /^I navigate to avatar settings$/
     */
    public function i_navigate_to_avatar_settings() {
        $this->execute('behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Avatar > General settings']);
    }

    /**
     * Navigate to archived avatars page.
     *
     * @Given /^I navigate to archived avatars$/
     */
    public function i_navigate_to_archived_avatars() {
        $this->execute('behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Avatar > Manage avatars']);
        $this->execute('behat_general::i_click_on', ['Archived avatars', 'link']);
    }

    /**
     * Create a new avatar with the given fields.
     *
     * @Given /^I create avatar with the following fields to these values:$/
     * @param TableNode $data
     */
    public function i_create_avatar_with_the_following_fields_to_these_values(TableNode $data) {
        $this->execute('behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Avatar > Manage avatars']);
        $this->execute('behat_general::i_click_on', ['Create avatar', 'button']);
        $this->execute('behat_forms::i_expand_all_fieldsets');
        $this->execute('behat_forms::i_set_the_following_fields_to_these_values', [$data]);
        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar.png', 'Preview image']);
        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

        // Get the number of variants from the data.
        $variants = 1; // Default.
        foreach ($data->getRows() as $row) {
            if ($row[0] == 'Number of variants') {
                $variants = (int)$row[1];
                break;
            }
        }

        // Upload avatar images for each variant.
        for ($i = 1; $i <= $variants; $i++) {
            $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
                ['mod/avatar/tests/fixtures/test_avatar_'.$i.'.png', "Avatar image {$i}"]);
            $this->execute('behat_general::i_click_on', ['Save changes', 'button']);
            if ($i == $variants) {
                break; // Not add thumbnail for the last variant.
            }
            $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
                ["mod/avatar/tests/fixtures/test_avatar_thumbnail_{$i}.png", "Avatar thumbnail {$i}"]);
            $this->execute('behat_general::i_click_on', ['Save changes', 'button']);
        }
    }

    /**
     * Create a new avatar with secret info and additional media.
     *
     * @Given /^I create avatar "(?P<avatarname>(?:[^"]|\\")*)" with secret info and additional media$/
     * @param string $avatarname
     */
    public function i_create_avatar_with_secret_info_and_additional_media($avatarname) {
        $this->execute('behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Avatar > Manage avatars']);
        $this->execute('behat_general::i_click_on', ['Create avatar', 'button']);
        $this->execute('behat_forms::i_expand_all_fieldsets');

        // Set basic fields.
        $this->execute('behat_forms::i_set_the_field_to', ['Avatar name', $avatarname]);
        $this->execute('behat_forms::i_set_the_field_to', [
            'Description', "This is a detailed description of {$avatarname} with <strong>HTML formatting</strong>"]);
        $this->execute('behat_forms::i_set_the_field_to', [
            'Secret info', "This is secret information about {$avatarname} that is only revealed when fully upgraded"]);
        $this->execute('behat_forms::i_set_the_field_to', ['Tags', 'secret,media,test']);
        $this->execute('behat_forms::i_set_the_field_to', ['Number of variants', '2']);

        // Upload preview image.
        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar.png', 'Preview image']);

        // Save to update form with variant fields.
        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

        // Upload variant images.
        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar.png', 'Avatar image 1']);
        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar_thumbnail.png', 'Avatar thumbnail 1']);
        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar.png', 'Avatar image 2']);
        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

        // Upload additional media.
        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar.png', 'Additional media']);
        $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
            ['mod/avatar/tests/fixtures/test_avatar.png', 'Additional media']);

        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);
    }

    /**
     * Create a multi variant avatar.
     *
     * @Given /^I create a multi variant avatar "(?P<avatarname>(?:[^"]|\\")*)" with (?P<variants>\d+) variants$/
     * @param string $avatarname
     * @param int $variants
     */
    public function i_create_a_multi_variant_avatar_with_variants($avatarname, $variants) {
        $this->execute('behat_navigation::i_navigate_to_in_site_administration',
            ['Plugins > Activity modules > Avatar > Manage avatars']);
        $this->execute('behat_general::i_click_on', ['Create avatar', 'button']);
        $this->execute('behat_forms::i_expand_all_fieldsets');

        // Set basic fields.
        $this->execute('behat_forms::i_set_the_field_to', ['Avatar name', $avatarname]);
        $this->execute('behat_forms::i_set_the_field_to', ['Description', "multi variant avatar with {$variants} variants"]);
        $this->execute('behat_forms::i_set_the_field_to', ['Tags', 'multi,variant,test']);
        $this->execute('behat_repository_upload::i_upload_file_to_filemanager', [
            'mod/avatar/tests/fixtures/test_avatar.png', 'Preview image']);

        // Set variants and upload images.
        $this->execute('behat_forms::i_set_the_field_to', ['Number of variants', $variants]);
        $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

        // Upload avatar images for each variant.
        for ($i = 1; $i <= $variants; $i++) {
            $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
                ["mod/avatar/tests/fixtures/test_avatar_{$i}.png", "Avatar image {$i}"]);
            $this->execute('behat_general::i_click_on', ['Save changes', 'button']);

            if ($i == $variants) {
                break; // Not add thumbnail for the last variant.
            }
            $this->execute('behat_repository_upload::i_upload_file_to_filemanager',
                ["mod/avatar/tests/fixtures/test_avatar_thumbnail_{$i}.png", "Avatar thumbnail {$i}"]);
            $this->execute('behat_general::i_click_on', ['Save changes', 'button']);
        }
    }

    /**
     * Add an avatar activity to a course.
     *
     * @Given /^I add an avatar activity to course "(?P<coursename>(?:[^"]|\\")*)" with:$/
     * @param string $coursename
     * @param TableNode $data
     */
    public function i_add_an_avatar_activity_to_course_with($coursename, TableNode $data) {
        $this->execute('behat_navigation::i_am_on_course_homepage_with_editing_mode_on', [$coursename]);
        $this->execute('behat_course::i_add_to_course_section_and_i_fill_the_form_with', ['Avatar', $coursename, 1, $data]);
    }

    /**
     * Check if an avatar is visible in the avatar list.
     *
     * @Then /^I should see avatar "(?P<avatarname>(?:[^"]|\\")*)" in the avatar list$/
     * @param string $avatarname
     */
    public function i_should_see_avatar_in_the_avatar_list($avatarname) {
        $this->execute('behat_general::assert_element_contains_text',
            [$avatarname, 'table.avatars', 'css_element']);
    }

    /**
     * Check if an avatar is not visible in the avatar list.
     *
     * @Then /^I should not see avatar "(?P<avatarname>(?:[^"]|\\")*)" in the avatar list$/
     * @param string $avatarname
     */
    public function i_should_not_see_avatar_in_the_avatar_list($avatarname) {
        $this->execute('behat_general::assert_element_not_contains_text',
            [$avatarname, 'table.avatars', 'css_element']);
    }

    /**
     * Check if an avatar is visible in the archived avatars list.
     *
     * @Then /^I should see avatar "(?P<avatarname>(?:[^"]|\\")*)" in the archived avatars list$/
     * @param string $avatarname
     */
    public function i_should_see_avatar_in_the_archived_avatars_list($avatarname) {
        $this->execute('behat_general::assert_element_contains_text',
            [$avatarname, 'table.archived-avatars', 'css_element']);
    }

    /**
     * Check if an avatar is not visible in the archived avatars list.
     *
     * @Then /^I should not see avatar "(?P<avatarname>(?:[^"]|\\")*)" in the archived avatars list$/
     * @param string $avatarname
     */
    public function i_should_not_see_avatar_in_the_archived_avatars_list($avatarname) {
        $this->execute('behat_general::assert_element_not_contains_text',
            [$avatarname, 'table.archived-avatars', 'css_element']);
    }

    /**
     * Delete an archived avatar.
     *
     * @Given /^I delete archived avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_delete_archived_avatar($avatarname) {
        $this->execute('behat_general::i_click_on_in_the',
            ['.action-delete', 'css_element', $avatarname, 'table_row']);
        $this->execute('behat_general::i_click_on', ['Yes', 'button']);
    }

    /**
     * Collect an avatar.
     *
     * @Given /^I collect avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_collect_avatar($avatarname) {
        $this->execute('behat_general::i_click_on_in_the',
            ['.action-collect', 'css_element', ".avatar-details[data-title=$avatarname]", 'css_element']);
    }

    /**
     * Pick an avatar.
     *
     * @Given /^I pick avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_pick_avatar($avatarname) {
        $this->execute('behat_general::i_click_on_in_the',
            ['Pick', 'button', ".avatar-details[data-title=\"$avatarname\"]", 'css_element']);
    }

    /**
     * Upgrade an avatar to the next variant.
     *
     * @Given /^I upgrade avatar "(?P<avatarname>(?:[^"]|\\")*)" to the next variant$/
     * @param string $avatarname
     */
    public function i_upgrade_avatar_to_the_next_variant($avatarname) {
        $this->execute('behat_general::i_click_on_in_the',
            ['.action-upgrade', 'css_element', $avatarname, 'table_row']);
        $this->execute('behat_general::i_click_on', ['Upgrade', 'button']);
    }

    /**
     * Upgrade an avatar.
     *
     * @Given /^I upgrade avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_upgrade_avatar($avatarname) {
        $this->execute('behat_general::i_click_on', ['Upgrade', 'button']);
    }

    /**
     * View my avatars.
     *
     * @Given /^I view my avatars$/
     */
    public function i_view_my_avatars() {
        $this->execute('behat_navigation::i_follow_in_the_user_menu', ['My avatars']);
    }

    /**
     * Check if I have collected an avatar.
     *
     * @Then /^I should see avatar "(?P<avatarname>(?:[^"]|\\")*)" in my collection$/
     * @param string $avatarname
     */
    public function i_should_see_avatar_in_my_collection($avatarname) {
        $this->execute('behat_general::assert_element_contains_text',
            [$avatarname, '#page-mod-avatar-myavatars .avatar-gallery .avatar-card', 'css_element']);
    }

    /**
     * Check if I have not collected an avatar.
     *
     * @Then /^I should not see avatar "(?P<avatarname>(?:[^"]|\\")*)" in my collection$/
     * @param string $avatarname
     */
    public function i_should_not_see_avatar_in_my_collection($avatarname) {
        $this->execute('behat_general::assert_element_not_contains_text',
            [$avatarname, '#page-mod-avatar-myavatars .avatar-gallery .avatar-card', 'css_element']);
    }

    /**
     * Check if an avatar has a specific variant level.
     *
     * @Then /^I should see avatar "(?P<avatarname>(?:[^"]|\\")*)" with variant level (?P<level>\d+)$/
     * @param string $avatarname
     * @param int $level
     */
    public function i_should_see_avatar_with_variant_level($avatarname, $level) {

        $imagename = 'test_avatar_thumbnail_'.$level.'.png';

        $this->execute('behat_general::should_exist',
            ["//div[contains(@class, 'avatar-card') or contains(@class, 'avatar-block')]
            //img[contains(@src, '{$imagename}') or contains(@src, 'test_avatar_{$level}.png')
            and contains(@alt, '{$avatarname}')]",
            'xpath_element']);
    }

    /**
     * Check if tag images are displayed for an avatar.
     *
     * @Then /^I should see tag images for avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_should_see_tag_images_for_avatar($avatarname) {
        $this->execute('behat_general::should_exist',
            ["//div[contains(@class, 'avatar-card') or contains(@class, 'avatar-block')]
            [contains(., '{$avatarname}')]//div[contains(@class, 'avatar-tags')]//img",
            'xpath_element']);
    }

    /**
     * Check if secret info is displayed for an avatar.
     *
     * @Then /^I should see secret info for avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_should_see_secret_info_for_avatar($avatarname) {
        $this->execute('behat_general::should_exist',
            ['.avatar-secret', 'css_element']);
    }

    /**
     * Check if secret info is not displayed for an avatar.
     *
     * @Then /^I should not see secret info for avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_should_not_see_secret_info_for_avatar($avatarname) {
        $this->execute('behat_general::should_not_exist',
            ['.avatar-secret', 'css_element']);
    }

    /**
     * Check if additional media is displayed for an avatar.
     *
     * @Then /^I should see additional media for avatar "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_should_see_additional_media_for_avatar($avatarname) {
        $this->execute('behat_general::should_exist',
            ['.avatar-media', 'css_element']);
    }

    /**
     * Navigate to avatar report.
     *
     * @Given /^I navigate to avatar report for "(?P<activityname>(?:[^"]|\\")*)"$/
     * @param string $activityname
     */
    public function i_navigate_to_avatar_report_for($activityname) {
        $this->execute('behat_navigation::i_am_on_page_instance', [$activityname]);
        $this->execute('behat_navigation::i_navigate_to_in_current_page_administration', ['Report']);
    }

    /**
     * View avatar info page.
     *
     * @Given /^I view avatar info for "(?P<avatarname>(?:[^"]|\\")*)"$/
     * @param string $avatarname
     */
    public function i_view_avatar_info_for($avatarname) {
        $this->execute('behat_general::i_click_on_in_the',
            ['View', 'link', ".avatar-card[data-title=\"$avatarname\"]", 'css_element']);
    }
}

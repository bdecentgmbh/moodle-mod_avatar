@mod @mod_avatar @mod_avatar_variations @_file_upload
Feature: Managing avatar variations and upgrades
  In order to provide different avatar variations
  As an admin
  I need to be able to create and manage avatar variations

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  @javascript
  Scenario: Create avatar with multiple variants
    When I log in as "admin"
    And I navigate to avatar management
    And I click on "Create avatar" "button"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Avatar name | multi variant Avatar               |
      | Description | An avatar with multiple variations |
      | Tags        | multi, variant, test               |
    And I upload "mod/avatar/tests/fixtures/test_avatar.png" file to "Preview image" filemanager
    And I set the field "Number of variants" to "3"
    And I press "Save changes"
    And I upload "mod/avatar/tests/fixtures/test_avatar.png" file to "Avatar image 1" filemanager
    And I press "Save changes"
    And I upload "mod/avatar/tests/fixtures/test_avatar_thumbnail_1.png" file to "Avatar thumbnail 1" filemanager
    And I press "Save changes"
    And I upload "mod/avatar/tests/fixtures/test_avatar_2.png" file to "Avatar image 2" filemanager
    And I press "Save changes"
    And I upload "mod/avatar/tests/fixtures/test_avatar_thumbnail_2.png" file to "Avatar thumbnail 2" filemanager
    And I press "Save changes"
    And I upload "mod/avatar/tests/fixtures/test_avatar_3.png" file to "Avatar image 3" filemanager
    And I press "Save changes"
    Then I should see "multi variant Avatar" in the "avatars" "table"
    And I add an avatar activity to course "Course 1" with:
      | Avatar name | Multi variant avatar             |
      | Description | Multiple variant avatar activity |
    And I click on "Multi variant avatar" "link" in the ".activity" "css_element"
    And I should see "multi variant Avatar" in the "#page-mod-avatar-view .mod_avatar" "css_element"
    And I click on "multi variant Avatar" "link" in the ".mod_avatar .avatar-gallery" "css_element"
    And I should see "multi variant Avatar" in the "#page-mod-avatar-view_avatar .avatar-title" "css_element"
    And ".avatar-progress-bar .progress-indicator:nth-child(3)" "css_element" should exist

  @javascript
  Scenario: Student collects and upgrades a multi variant avatar
    Given I log in as "admin"
    And I create a multi variant avatar "Upgradable Avatar" with 3 variants
    And I log out
    And I log in as "teacher1"
    And I add an avatar activity to course "Course 1" with:
      | Avatar name | Collect and Upgrade Avatars                  |
      | Description | Collect and upgrade avatars in this activity |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Collect and Upgrade Avatars"
    When I pick avatar "Upgradable Avatar"
    Then I should see "Avatar collected successfully"
    And I view my avatars
    And I should see avatar "Upgradable Avatar" in my collection
    And I should see avatar "Upgradable Avatar" with variant level 1
    And I am on "Course 1" course homepage
    And I follow "Collect and Upgrade Avatars"
    Then I click on "Upgradable Avatar" "link" in the ".mod_avatar .avatar-gallery" "css_element"
    And "button.upgrade-avatar" "css_element" should exist in the "#page-mod-avatar-view_avatar .avatar-buttons" "css_element"
    When I click on "Upgrade" "button" in the ".avatar-info .avatar-buttons" "css_element"
    Then I should see "Avatar upgraded successfully"
    And I should see avatar "Upgradable Avatar" with variant level 2
    And "button.upgrade-avatar" "css_element" should exist in the "#page-mod-avatar-view_avatar .avatar-buttons" "css_element"
    When I click on "Upgrade" "button" in the ".avatar-info .avatar-buttons" "css_element"
    Then I should see "Avatar upgraded successfully"
    And I should see avatar "Upgradable Avatar" with variant level 3
    And "button.upgrade-avatar" "css_element" should not exist in the "#page-mod-avatar-view_avatar .avatar-buttons" "css_element"

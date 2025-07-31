@mod @mod_avatar @mod_avatar_management @_file_upload
Feature: Managing avatars in the avatar module
  In order to use the avatar module
  As an admin
  I need to be able to create, edit, and delete avatars

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

  Scenario: Avatar management - When the module is installed, no avatars exist
    When I log in as "admin"
    And I navigate to avatar management
    Then I should see "Manage avatars" in the "#page-header h1" "css_element"
    And I should see "There aren't any avatars created yet. Please create your first avatar to get things going."
    And "table.avatars" "css_element" should not exist in the "#region-main" "css_element"
    And "Create avatar" "button" should exist in the "#region-main" "css_element"

  @javascript @_file_upload
  Scenario: Avatar management - Create a new avatar
    When I log in as "admin"
    And I navigate to avatar management
    And I click on "Create avatar" "button"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Avatar name | Test Avatar             |
      | Description | Test avatar description |
      | Tags        | test, avatar            |
    And I upload "mod/avatar/tests/fixtures/test_avatar.png" file to "Preview image" filemanager
    And I click on "Save changes" "button"
    And I upload "mod/avatar/tests/fixtures/test_avatar.png" file to "Avatar image 1" filemanager
    And I click on "Save changes" "button"
    Then I should see "Test Avatar" in the "avatars" "table"
    And I should see "Test avatar description" in the "avatars" "table"

  @javascript @_file_upload
  Scenario: Avatar management - Edit an existing avatar
    When I log in as "admin"
    And I create avatar with the following fields to these values:
      | Avatar name | Test Avatar             |
      | Description | Test avatar description |
      | Tags        | test, avatar            |
    And I should see "Test Avatar" in the "avatars" "table"
    And I click on ".action-edit" "css_element" in the "Test Avatar" "table_row"
    And I expand all fieldsets
    And I set the following fields to these values:
      | Avatar name | Updated Avatar             |
      | Description | Updated avatar description |
    And I click on "Save changes" "button"
    Then I should not see "Test Avatar" in the "avatars" "table"
    And I should see "Updated Avatar" in the "avatars" "table"
    And I should see "Updated avatar description" in the "avatars" "table"

  @javascript
  Scenario: Avatar management - Archive an avatar
    When I log in as "admin"
    And I create avatar with the following fields to these values:
      | Avatar name | Test Avatar             |
      | Description | Test avatar description |
      | Tags        | test, avatar            |
    And I create avatar with the following fields to these values:
      | Avatar name | Cool Avatar             |
      | Description | Test avatar description |
      | Tags        | test, avatar            |
    And I should see "Test Avatar" in the "avatars" "table"
    # And I archive avatar "Test Avatar"
    And I click on ".action-archive" "css_element" in the "Test Avatar" "table_row"
    And I click on "Yes" "button" in the ".modal" "css_element"
    Then I should not see avatar "Test Avatar" in the avatar list
    And I navigate to archived avatars
    And I should see avatar "Test Avatar" in the archived avatars list

  @javascript
  Scenario: Avatar management - Restore an archived avatar
    When I log in as "admin"
    And I create avatar with the following fields to these values:
      | Avatar name | Test Avatar             |
      | Description | Test avatar description |
      | Tags        | test, avatar            |
    And I click on ".action-archive" "css_element" in the "Test Avatar" "table_row"
    And I click on "Yes" "button" in the ".modal" "css_element"
    And I navigate to archived avatars
    And I should see avatar "Test Avatar" in the archived avatars list
    And I click on ".action-restore" "css_element" in the "Test Avatar" "table_row"
    Then "table.archived-avatars" "css_element" should not exist
    And I navigate to avatar management
    And I should see avatar "Test Avatar" in the avatar list

  @javascript
  Scenario: Avatar management - Delete an archived avatar
    When I log in as "admin"
    And I create avatar with the following fields to these values:
      | Avatar name | Test Avatar             |
      | Description | Test avatar description |
      | Tags        | test, avatar            |
    And I click on ".action-archive" "css_element" in the "Test Avatar" "table_row"
    And I click on "Yes" "button" in the ".modal" "css_element"
    And I navigate to archived avatars
    And I should see avatar "Test Avatar" in the archived avatars list
    And I delete archived avatar "Test Avatar"
    Then "table.archived-avatars" "css_element" should not exist
    And I navigate to avatar management
    And "table.active-avatars" "css_element" should not exist

@mod @mod_avatar @mod_avatar_activity @_file_upload
Feature: Using the avatar activity in a course
  In order to use avatars in my course
  As a teacher
  I need to be able to add avatar activities and students need to collect avatars

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
    And I log in as "admin"
    And I create avatar with the following fields to these values:
      | Avatar name | Cool Avatar               |
      | Description | A cool avatar for testing |
      | Tags        | cool, test                |
    And I create avatar with the following fields to these values:
      | Avatar name | Fantasy Avatar               |
      | Description | A fantasy avatar for testing |
      | Tags        | fantasy, medieval            |
    And I create avatar with the following fields to these values:
      | Avatar name | Hero Avatar                     |
      | Description | A super hero avatar for testing |
      | Tags        | hero, cartoon                   |
    And I create avatar with the following fields to these values:
      | Avatar name | Sports Avatar               |
      | Description | A sports avatar for testing |
      | Tags        | sports, athletic            |

  @javascript
  Scenario: Add an avatar activity to a course
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "Avatar" activity to course "Course 1" section "1" and I fill the form with:
      | Avatar name     | Collect Avatars                       |
      | Description     | Collect cool avatars in this activity |
      | showdescription | 1                                     |
    Then I should see "Collect Avatars"
    And I should see "Collect cool avatars in this activity"
    Then I click on "Collect Avatars" "link" in the ".activityname" "css_element"
    And I should see "Pick your avatar" in the ".mod_avatar.display-page " "css_element"
    And I should see "Cool Avatar" in the " .mod_avatar .avatar-gallery" "css_element"
    And I should see "Pick" in the ".mod_avatar .avatar-gallery" "css_element"

  @javascript
  Scenario: Student collects an avatar
    Given I log in as "teacher1"
    And I add an avatar activity to course "Course 1" with:
      | Avatar name | Collect Avatars                       |
      | Description | Collect cool avatars in this activity |
    When I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Collect Avatars"
    Then I should see "Cool Avatar"
    And I click on "Pick" "button" in the ".avatar-gallery" "css_element"
    And I follow "My avatars" in the user menu
    And I should see "My avatars" in the "#page-header" "css_element"
    And I should see "Cool Avatar" in the ".avatar-gallery" "css_element"

  @javascript
  Scenario Outline: Avatar activity: Avatar selection based on tags
    When I log in as "teacher1"
    And I add an avatar activity to course "Course 1" with:
      | Avatar name      | Avatar selection           |
      | Description      | Avatars list based on tags |
      | Avatar selection | Specific tags              |
      | Specific tags    | <tags>                     |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Avatar selection"
    Then I <fantasyshouldornot> see "Fantasy Avatar"
    And I <heroshouldornot> see "Hero Avatar"
    And I <sportsshouldornot> see "Sports Avatar"

    Examples:
      | tags                       | fantasyshouldornot | heroshouldornot | sportsshouldornot |
      | fantasy                    | should             | should not      | should not        |
      | fantasy, sports            | should             | should not      | should            |
      | sports, athletic           | should not         | should not      | should            |
      | fantasy, athletic, cartoon | should             | should          | should            |
      | cool                       | should not         | should not      | should not        |

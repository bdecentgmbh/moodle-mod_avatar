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
    And the following "mod_avatar > avatars" exist:
      | name           | description                     | tags              |
      | Cool Avatar    | A cool avatar for testing       | cool, test        |
      | Fantasy Avatar | A fantasy avatar for testing    | fantasy, medieval |
      | Hero Avatar    | A super hero avatar for testing | hero, cartoon     |
      | Sports Avatar  | A sports avatar for testing     | sports, athletic  |

  @javascript
  Scenario: Add an avatar activity to a course
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "avatar" activity to course "Course 1" section "1" and I fill the form with:
      | Avatar name      | Collect Avatars                       |
      | Description      | Collect cool avatars in this activity |
      | showdescription  | 1                                     |
      | Avatar selection | Specific tags                         |
      | Specific tags    | cool, sports                          |
    Then I should see "Collect Avatars"
    And I should see "Collect cool avatars in this activity"
    Then I click on "Collect Avatars" "link" in the ".activityname" "css_element"
    And I should see "Pick your avatar" in the ".mod_avatar.display-page " "css_element"
    And I should see "Cool Avatar" in the " .mod_avatar .avatar-gallery" "css_element"
    And I should see "Sports Avatar" in the " .mod_avatar .avatar-gallery" "css_element"
    And I should not see "Fantasy Avatar" in the " .mod_avatar .avatar-gallery" "css_element"
    # Teachers cannot collect avatars, so they see no Pick buttons.
    And "Pick" "button" should not exist in the ".mod_avatar .avatar-gallery" "css_element"

  @javascript
  Scenario: Student collects an avatar
    Given the following "activities" exist:
      | activity | name            | intro                                 | course | section |
      | avatar   | Collect Avatars | Collect cool avatars in this activity | C1     | 1       |
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Collect Avatars"
    Then I should see "Cool Avatar"
    And I click on "Pick" "button" in the ".avatar-gallery" "css_element"
    And I view my avatars
    And I should see "My avatars" in the "#page-header" "css_element"
    And I should see "Cool Avatar" in the ".avatar-gallery" "css_element"

  @javascript
  Scenario Outline: Avatar activity: Avatar selection based on tags
    # avatarselection 1: specific tags.
    Given the following "activities" exist:
      | activity | name             | intro                      | course | section | avatarselection | specifictags |
      | avatar   | Avatar selection | Avatars list based on tags | C1     | 1       | 1               | <tags>       |
    When I log in as "student1"
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

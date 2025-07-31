@mod @mod_avatar @mod_avatar_info @_file_upload
Feature: Avatar information display
  In order to understand and interact with avatars
  As a user
  I need to see complete and accurate information about avatars

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And I log in as "admin"
    And I create a multi variant avatar "Test Avatar" with 3 variants
    And I create avatar with the following fields to these values:
      | Avatar name | Basic Avatar               |
      | Description | A basic avatar for testing |
      | Tags        | basic, test, example       |
    And I add an avatar activity to course "Course 1" with:
      | Avatar name | Avatar Information |
      | Description | Test avatar info   |
    And I log out

  @javascript
  Scenario Outline: Verify basic avatar information display
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Avatar Information"
    Then I should see "Test Avatar" in the ".avatar-gallery" "css_element"
    And I should see "Basic Avatar" in the ".avatar-gallery" "css_element"
    # Check avatar details
    When I click on "<avatar_name>" "link" in the ".avatar-gallery" "css_element"
    Then I should see "<avatar_name>" in the "h2.avatar-title" "css_element"
    And I should see "<description>" in the ".avatar-description" "css_element"
    # Check tags
    And I should see "<tag1>" in the ".avatar-text-tags" "css_element"
    And I should see "<tag2>" in the ".avatar-text-tags" "css_element"
    And I should see "<tag3>" in the ".avatar-text-tags" "css_element"
    # Check variant progress
    And ".progress-indicator:nth-child(<variant>)" "css_element" should exist in the ".avatar-progress-bar" "css_element"
    And ".progress-indicator.completed" "css_element" should not exist in the ".avatar-progress-bar" "css_element"
    # Check buttons
    And "Pick" "button" should exist in the ".avatar-buttons" "css_element"
    And "Upgrade" "button" should not exist in the ".avatar-buttons" "css_element"
    # Pick an avatar and check for upgrade button
    When I pick avatar "<avatar_name>"
    Then I should see "Avatar collected successfully"
    And "Pick" "button" should not exist in the ".avatar-actions" "css_element"
    And "Upgrade" "button" <upgradeshouldornot> exist in the ".avatar-actions" "css_element"
    And ".progress-indicator:nth-child(1).completed" "css_element" should exist in the ".avatar-progress-bar" "css_element"

    Examples:
      | avatar_name  | description                          | tag1  | tag2    | tag3    | variant | upgradeshouldornot |
      | Test Avatar  | multi variant avatar with 3 variants | multi | variant | test    | 3       | should             |
      | Basic Avatar | A basic avatar for testing           | basic | test    | example | 1       | should not         |

  @javascript
  Scenario Outline: Avatar - Verify different tag display formats
    When I log in as "admin"
    And I navigate to "Plugins > Avatar > General settings" in site administration
    And I should see "Tag images"
    And I upload "/mod/avatar/tests/fixtures/sports.png" file to "Tag images" filemanager
    And I press "Save changes"
    And I upload "/mod/avatar/tests/fixtures/fantasy.png" file to "Tag images" filemanager
    And I press "Save changes"
    And I create avatar with the following fields to these values:
      | Avatar name | <avatar_name> |
      | Description | <description> |
      | Tags        | <tags>        |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Avatar Information"
    When I click on "<avatar_name>" "link" in the ".avatar-gallery" "css_element"
    Then I should see "<avatar_name>" in the "h2.avatar-title" "css_element"
    # Check tag display
    And ".avatar-text-tags" "css_element" <textshouldornot> exist
    And "//a[contains(text(), '#<texttags>')]" "xpath_element" <textshouldornot> exist in the "<texttagelement>" "css_element"
    And "<imagetags>" "css_element" <imageshouldornot> exist

    Examples:
      | avatar_name       | description     | tags                | texttags | textshouldornot | imagetags                                 | imageshouldornot | texttagelement                                    | imagetagelement                                  |
      | Text Tags Avatar  | Text tags only  | simple, plain, text | simple   | should          | .avatar-image-tags                        | should not       | .avatar-text-tags                                 | .avatar-details[data-title=\"Text Tags Avatar\"] |
      | Image Tags Avatar | Image tags only | sports, fantasy     | sports   | should not      | img[alt=\"sports\"], img[alt=\"fantasy\"] | should           | .avatar-details[data-title=\"Image Tags Avatar\"] | .avatar-image-tags                               |
      | Mixed Tags Avatar | Mixed tags      | simple, sports      | simple   | should          | img[alt=\"sports\"]                       | should           | .avatar-text-tags                                 | .avatar-image-tags                               |

  @javascript
  Scenario: Avatar - Profile image synchronization setting enable and disabled
    Given I log in as "admin"
    And I navigate to "Plugins > Avatar > General settings" in site administration
    And I set the field "Profile image synchronization" to "0"
    And I press "Save changes"
    And I create avatar with the following fields to these values:
      | Avatar name        | Profile Sync Avatar |
      | Description        | Test profile sync   |
      | Number of variants | 2                   |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Avatar Information"
    # Pick avatar without sync enabled
    When I click on "Profile Sync Avatar" "link" in the ".avatar-gallery" "css_element"
    And I pick avatar "Profile Sync Avatar"
    Then I should see "Avatar collected successfully"
    And "//img[contains(@class, 'userpicture') and contains(@src, 'user/icon/boost')]" "xpath_element" should not exist
    # Enable profile sync and upgrade avatar
    When I log in as "admin"
    And I navigate to "Plugins > Avatar > General settings" in site administration
    And I set the field "Profile image sync" to "1"
    And I press "Save changes"
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Avatar Information"
    And I click on "Profile Sync Avatar" "link" in the ".avatar-gallery" "css_element"
    And I upgrade avatar "Profile Sync Avatar"
    Then I should see "Avatar upgraded successfully"
    And "//img[contains(@class, 'userpicture') and contains(@src, 'user/icon/boost')]" "xpath_element" should exist
    # Sync profile image on pick avatar
    And I log in as "student2"
    And I am on "Course 1" course homepage
    And I follow "Avatar Information"
    And I click on "Profile Sync Avatar" "link" in the ".avatar-gallery" "css_element"
    And I pick avatar "Profile Sync Avatar"
    Then I should see "Avatar collected successfully"
    And "//img[contains(@class, 'userpicture') and contains(@src, 'user/icon/boost')]" "xpath_element" should exist

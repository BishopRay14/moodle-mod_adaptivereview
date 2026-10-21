@mod @mod_adaptivereview @javascript
Feature: Repository-backed Adaptive Review lifecycle
  In order to build lifelong mastery from reusable content
  As an instructor and student
  I need set selection and the complete review flow to remain course-integrated

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname      | shortname |
      | Review Course | REVIEW    |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | REVIEW | editingteacher |
      | student1 | REVIEW | student        |
    And the following "activities" exist:
      | activity       | name       | course | idnumber |
      | adaptivereview | Source Set | REVIEW | source   |

  Scenario: An instructor reuses a repository set in another activity
    Given I am on the "Review Course" course page logged in as teacher1
    When I add an adaptivereview activity to course "Review Course" section "1" and I fill the form with:
      | Activity Name    | Consumer Review |
      | Learning set     | Source Set      |
      | Set Mastery goal | 4.5             |
    Then I should see "Consumer Review"
    When I am on the "Consumer Review" "adaptivereview activity" page
    Then I should see "Consumer Review"
    And I should see "Set Mastery"
    And I should see "Goal"
    And I should see "4.5"
    And I should not see "Adaptive Review Repository"

  Scenario: A student completes a new-card review session
    Given I am on the "Source Set" "adaptivereview activity" page logged in as student1
    And I should see "Set Mastery"
    And I should see "Progress Toward Goal"
    And I wait until ".rc-box[role='button'][aria-label^='New:']" "css_element" exists
    When I click on ".rc-box[role='button'][aria-label^='New:']" "css_element"
    And I wait until "I Know It" "button" exists
    And I click on "I Know It" "button"
    And I wait "1" seconds
    And I click on "Continue →" "button"
    And I wait until "I Know It" "button" exists
    And I click on "I Know It" "button"
    And I wait "1" seconds
    And I click on "Continue →" "button"
    And I wait until "I Know It" "button" exists
    And I click on "I Know It" "button"
    And I wait "1" seconds
    And I click on "Continue →" "button"
    And I wait until "I Know It" "button" exists
    And I click on "I Know It" "button"
    And I wait "1" seconds
    And I click on "Continue →" "button"
    And I wait until "I Know It" "button" exists
    And I click on "I Know It" "button"
    And I wait "1" seconds
    And I click on "Continue →" "button"
    Then I should see "Strong Result"
    When I click on "Back to dashboard" "text"
    Then I should see "0.5"
    And I should see "5 Cards"
    And I should not see "Adaptive Review Repository"

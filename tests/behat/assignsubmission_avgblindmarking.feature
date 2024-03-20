@mod @mod_assign @assignsubmission @assignsubmission_avgblindmarking @_file_upload
Feature: In an assignment, teacher can submit feedback files during grading
  In order to provide a feedback file
  As a teacher
  I need to submit a feedback file.

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category | groupmode |
      | Course 1 | C1        | 0        | 1         |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | grader1  | Grader    | 1        | teacher1@example.com |
      | grader2  | Grader    | 2        | teacher2@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | grader1  | C1     | teacher        |
      | grader2  | C1     | teacher        |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activity" exists:
      | activity                                 | assign               |
      | course                                   | C1                   |
      | name                                     | Test assignment name |
      | assignsubmission_avgblindmarking_enabled | 1                    |
      | assignsubmission_onlinetext_enabled      | 1                    |
      | assignsubmission_file_enabled            | 0                    |
      | assignfeedback_comments_enabled          | 1                    |
      | assignfeedback_file_enabled              | 1                    |
      | maxfilessubmission                       | 0                    |
      | markingworkflow                          | 1                    |
      | markingallocation                        | 1                    |

    And I am on the "Test assignment name" Activity page logged in as teacher1
    And I click on "Manage graders" "button" in the ".manageblindgrades" "css_element"
    And I click on "Add grader allocation" "button"
    And I open the autocomplete suggestions list
    And I click on "Student 1" item in the autocomplete list
    And I set the following fields to these values:
      | Allocated graders | Grader 1, Grader 2 |
    And I press "Save changes"

    And I am on the "Test assignment name" Activity page logged in as student1
    When I press "Add submission"
    And I set the following fields to these values:
      | Online text | The submitted text for student1 |
    And I press "Save changes"
    And I press "Submit assignment"
    And I press "Continue"

    And I am on the "Test assignment name" Activity page logged in as student2
    When I press "Add submission"
    And I set the following fields to these values:
      | Online text | The submitted text for student2 |
    And I press "Save changes"
    And I press "Submit assignment"
    And I press "Continue"

  @javascript
  Scenario: A teacher can provide a feedback file when grading an assignment.
    Given I am on the "Test assignment name" Activity page logged in as grader1
    And I follow "View all submissions"
    Then I should see "Student 1"
    And I should not see "Student 2"
    Given I am on the "Test assignment name" Activity page logged in as grader2
    And I follow "View all submissions"
    Then I should not see "Student 1"
    And I should not see "Student 2"

    Given I am on the "Test assignment name" Activity page logged in as grader1
    And I follow "View all submissions"
    And I click on "Grade" "link" in the "Student 1" "table_row"
    And I wait until the page is ready
    And I set the field "Grade out of 100" to "50"
    And I set the field "Feedback comments" to "Grader One Feedback Comments"
    And I upload "mod/assign/submission/avgblindmarking/tests/fixtures/feedback.txt" file to "Feedback files" filemanager
    And I press "Save changes"
    And I click on "View all submissions" "link"
    And I click on "Grade" "link" in the "Student 1" "table_row"
    And I wait until the page is ready
    And I set the field "Marking workflow state" to "Marking completed"
    And I press "Save changes"
    And I am on the "Test assignment name" "assign activity" page
    Then I should not see "Student 1"
    And I click on "Manage my blind grades" "button" in the ".manageblindgrades" "css_element"
    And I click on "View blind grade" "link" in the "Student 1" "table_row"
    Then I should see "The submitted text for student1"
    And I should see "Grader One Feedback Comments"

    Given I am on the "Test assignment name" Activity page logged in as grader2
    And I follow "View all submissions"
    And I click on "Grade" "link" in the "Student 1" "table_row"
    And I wait until the page is ready
    And I set the field "Grade out of 100" to "100"
    And I set the field "Feedback comments" to "Grader Two Feedback Comments"
    And I upload "mod/assign/submission/avgblindmarking/tests/fixtures/feedback2.txt" file to "Feedback files" filemanager
    And I set the field "Marking workflow state" to "Marking completed"
    And I press "Save changes"
    And I am on the "Test assignment name" "assign activity" page
    Then I should not see "Student 1"
    And I click on "Manage my blind grades" "button" in the ".manageblindgrades" "css_element"
    And I click on "View blind grade" "link" in the "Student 1" "table_row"
    Then I should see "The submitted text for student1"
    And I should not see "Grader One Feedback Comments"
    And I should see "Grader Two Feedback Comments"

    Given I am on the "Test assignment name" Activity page logged in as teacher1
    And I follow "View all submissions"
    And I should see "75.00" in the "Student 1" "table_row"
    And I should see "feedback.txt" in the "Student 1" "table_row"
    And I should see "feedback2.txt" in the "Student 1" "table_row"
    And I should see "Grader One Feedback Comments" in the "Student 1" "table_row"
    And I should see "Grader Two Feedback Comments" in the "Student 1" "table_row"
    And I should see "Ready for release" in the "Student 1" "table_row"
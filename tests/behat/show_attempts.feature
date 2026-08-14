@quiz_livequizmonitor @mod @mod_quiz @quiz @report
Feature: View student attempts in Live Quiz Monitor modal
  In order to review recent student activity during an active quiz
  As a teacher
  I need to view student attempts in a popup modal and navigate to a full report

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email             |
      | teacher1 | Terry     | Teacher  | teacher1@test.com |
      | student1 | Sam       | Student  | student1@test.com |
      | student2 | Alex      | Other    | student2@test.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | name   | course | timelimit |
      | quiz     | Quiz 1 | C1     | 600       |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype       | name | questiontext |
      | Test questions   | shortanswer | SA1  | What is 2+2? |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | SA1      | 1    |
    And I log in as "teacher1"

  @javascript @showattempts
  Scenario: Show attempts link opens modal for student with quiz activity
    # Student 1 starts a quiz and creates an attempt.
    Given I am on the "Quiz 1" "quiz activity" page logged in as "student1"
    And I press "Attempt quiz"
    And I press "Start attempt"
    And I log in as "teacher1"

    When I am on the live monitor report for "Quiz 1"

    # Student 1 (Sam Student) has an active attempt -> "Show attempts" link exists
    When I click on ".livequizmonitor-row-actions .dropdown-toggle" "css_element" in the "Sam Student" "table_row"
    Then "Show attempts" "link" should exist in the "Sam Student" "table_row"

    # Click "Show attempts" -> Modal opens with title and list of attempts
    When I click on "Show attempts" "link" in the "Sam Student" "table_row"
    Then I should see "Recent attempts for Sam Student"
    And I should see "Attempt"
    And I should see "Time started"
    And I should see "Time finished"
    And I should see "1"
    And I should see "In progress"
    And "Show more" "button" should exist

    # Click "Show more" -> Opens new window/tab with full log report
    When I click on "Show more" "button"
    And I switch to a second window
    Then "Show report" "button" should exist
    And I should see "What to include in the report"
    #And the field "id" matches value "Course 1"
    #And the field "user" matches value "Sam Student"
    #And the field "modid" matches value "Quiz 1"
    And I should not see "Recent attempts for Sam Student"

  @javascript @showattempts
  Scenario: Show attempts link opens empty modal for student with no quiz activity
    Given I am logged in as "teacher1"

    When I am on the live monitor report for "Quiz 1"
    And I click on ".livequizmonitor-row-actions .dropdown-toggle" "css_element" in the "Alex Other" "table_row"
    Then "Show attempts" "link" should exist in the "Alex Other" "table_row"

    When I click on "Show attempts" "link" in the "Alex Other" "table_row"

    Then I should see "Recent attempts for Alex Other"
    And I should see "No attempts found for this student."
    And "Close" "button" should exist
    And "Show more" "button" should not exist

    When I click on "Close" "button" in the ".modal-footer" "css_element"
    Then I should not see "Recent attempts for Alex Other"

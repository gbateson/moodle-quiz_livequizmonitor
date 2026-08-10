@quiz_livequizmonitor
Feature: Live Quiz Monitor maximum duration settings
  In order to configure the maximum allowable duration of quiz attempts
  As a teacher
  I need to be able to configure the Live Quiz Monitor maximum duration setting

  Background:
    Given the following "courses" exist:
      | fullname    | shortname |
      | Test course | TEST      |
    And I log in as "admin"

  Scenario: Maximum duration setting is available when creating a quiz
    Given I am on "Test course" course homepage
    When I add a "quiz" activity to course "Test course" section "1"
    Then I should see "Maximum duration"
    And the field "Maximum duration" matches value "Default"

  Scenario: Time limit is required when maximum duration uses the time limit
    Given I am on "Test course" course homepage
    When I add a "quiz" activity to course "Test course" section "1"
    And I set the field "Name" to "Test quiz"
    And I set the field "Maximum duration" to "Use time limit"
    And I press "Save and display"
    Then I should see "Please specify a time limit."

  Scenario: Open time is required when maximum duration uses open and close times
    Given I am on "Test course" course homepage
    When I add a "quiz" activity to course "Test course" section "1"
    And I set the field "Name" to "Test quiz"
    And I set the field "Maximum duration" to "Use open/close times"
    And I press "Save and display"
    Then I should see "Please specify a date and time to open the quiz."

  Scenario: Close time is required when maximum duration uses open and close times
    Given I am on "Test course" course homepage
    When I add a "quiz" activity to course "Test course" section "1"
    And I set the field "Name" to "Test quiz"
    And I set the field "Maximum duration" to "Use open/close times"
    And I set the following fields to these values:
      | timeopen[enabled] | 1         |
      | timeopen          | ##today## |
    And I press "Save and display"
    Then I should see "Please specify a date and time to close the quiz."

  Scenario: Maximum duration setting is retained when editing a quiz
    Given I am on "Test course" course homepage
    When I add a "quiz" activity to course "Test course" section "1"
    And I set the field "Name" to "Test quiz"
    And I set the field "Maximum duration" to "Use time limit"
    And I set the following fields to these values:
      | id_timelimit_enabled | 1  |
      | id_timelimit_number  | 10 |
    And I press "Save and display"
    And I follow "Settings"
    Then the field "Maximum duration" matches value "Use time limit"

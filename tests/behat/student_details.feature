@quiz_livequizmonitor @mod @mod_quiz @quiz @report
Feature: Choose the student details and columns shown in the Live Quiz Monitor
  In order to identify students during an exam
  As a site administrator
  I need to choose which student details, such as an ID number or UPI, the monitor shows

  Background:
    Given the following "custom profile fields" exist:
      | datatype | shortname | name |
      | text     | upi       | UPI  |
    And the following "users" exist:
      | username  | firstname | lastname | email             | idnumber | profile_field_upi |
      | teacher1  | Terry     | Teacher  | teacher1@test.com |          |                   |
      | student-a | Name      | A        | email-a@test.com  | S002     | UPIAAA1           |
      | student-b | Name      | B        | email-b@test.com  | S001     | UPIBBB2           |
      | student-c | Name      | C        | email-c@test.com  | S003     |                   |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user      | course | role           |
      | teacher1  | C1     | editingteacher |
      | student-a | C1     | student        |
      | student-b | C1     | student        |
      | student-c | C1     | student        |
    And the following "activities" exist:
      | activity | name   | course | timelimit |
      | quiz     | Quiz 1 | C1     | 600       |

  @javascript
  Scenario: The default columns are the same as before the settings existed
    Given I log in as "teacher1"
    When I am on the live monitor report for "Quiz 1"
    Then "th[data-column='email']" "css_element" should exist
    And "th[data-column='progress']" "css_element" should exist
    And "th[data-column='timeremaining']" "css_element" should exist
    And "th[data-column='idnumber']" "css_element" should not exist
    And I should see "email-a@test.com"

  Scenario: The site admin chooses the columns on the settings page
    Given I log in as "admin"
    When I navigate to "Plugins > Activity modules > Quiz > Live monitor" in site administration
    And I set the following fields to these values:
      | id_s_quiz_livequizmonitor_identityfields_email             | 0 |
      | id_s_quiz_livequizmonitor_identityfields_idnumber          | 1 |
      | id_s_quiz_livequizmonitor_identityfields_profile_field_upi | 1 |
      | id_s_quiz_livequizmonitor_attemptcolumns_timeremaining     | 0 |
    And I press "Save changes"
    Then the following config values are set as admin:
      | config         | value                      | plugin               |
      | identityfields | idnumber,profile_field_upi | quiz_livequizmonitor |
      | attemptcolumns | progress                   | quiz_livequizmonitor |

  @javascript
  Scenario: Teachers see, search and sort by the chosen student details
    Given the following config values are set as admin:
      | config         | value                      | plugin               |
      | identityfields | idnumber,profile_field_upi | quiz_livequizmonitor |
      | attemptcolumns | progress                   | quiz_livequizmonitor |
    And I log in as "teacher1"
    When I am on the live monitor report for "Quiz 1"
    # The chosen columns, after the name, and no Email or Time left.
    Then "th[data-column='idnumber']" "css_element" should exist
    And "th[data-column='profile_field_upi']" "css_element" should exist
    And "th[data-column='email']" "css_element" should not exist
    And "th[data-column='timeremaining']" "css_element" should not exist
    And I should see "S002" in the "Name A" "table_row"
    And I should see "UPIBBB2" in the "Name B" "table_row"
    And I should not see "email-a@test.com"

    # Sort by UPI: students with a UPI first, in order.
    When I click on "th[data-sort-column='profile_field_upi'] [data-action='sort-column']" "css_element"
    Then the following students should appear in order:
      | Name A |
      | Name B |
      | Name C |

    # Sort by ID number.
    When I click on "th[data-sort-column='idnumber'] [data-action='sort-column']" "css_element"
    Then the following students should appear in order:
      | Name B |
      | Name A |
      | Name C |

    # Search by UPI.
    When I set the field "Search students…" to "upibbb2"
    Then I should see "Name B"
    And I should not see "Name A"
    And I should not see "Name C"

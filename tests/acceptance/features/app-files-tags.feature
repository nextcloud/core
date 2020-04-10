@apache
Feature: app-files-tags

#  Scenario: show the input field for tags in the details view
#    Given I am logged in
#    And I open the details view for "welcome.txt"
#    And I see that the details view is open
#    When I open the input field for tags in the details view
#    Then I see that the input field for tags in the details view is shown

#  Scenario: show the input field for tags in the details view after closing and opening the details view again
#    Given I am logged in
#    And I open the details view for "welcome.txt"
#    And I see that the details view is open
#    And I close the details view
#    And I see that the details view is closed
#    And I open the details view for "welcome.txt"
#    And I see that the details view is open
#    When I open the input field for tags in the details view
#    Then I see that the input field for tags in the details view is shown

  Scenario: create tags using the Administration settings
    Given I am logged in as the admin
    And I visit the settings page
    And I open the "Basic settings" section of the "Administration" group
    # The "create" button does nothing before JavaScript was initialized, and
    # the only way to detect that is waiting for the button to select tags to be
    # shown.
    And I see that the button to select tags is shown
    When I create the tag "tag1" in the settings
    Then I see that the dropdown for tags in the settings eventually contains the tag "tag1"

#  Scenario: add tags using the dropdown in the details view
#    Given I am logged in as the admin
#    And I visit the settings page
#    And I open the "Basic settings" section of the "Administration" group
#    # The "create" button does nothing before JavaScript was initialized, and
#    # the only way to detect that is waiting for the button to select tags to be
#    # shown.
#    And I see that the button to select tags is shown
#    And I create the tag "tag1" in the settings
#    And I create the tag "tag2" in the settings
#    And I create the tag "tag3" in the settings
#    And I create the tag "tag4" in the settings
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag1"
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag2"
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag3"
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag4"
#    And I log out
#    And I am logged in
#    And I open the details view for "welcome.txt"
#    And I open the input field for tags in the details view
#    # When the input field is opened the dropdown is also opened automatically.
#    When I check the tag "tag2" in the dropdown for tags in the details view
#    And I check the tag "tag4" in the dropdown for tags in the details view
#    Then I see that the tag "tag2" in the dropdown for tags in the details view is checked
#    And I see that the tag "tag4" in the dropdown for tags in the details view is checked
#    And I see that the input field for tags in the details view contains the tag "tag2"
#    And I see that the input field for tags in the details view contains the tag "tag4"
#
#  Scenario: remove tags using the dropdown in the details view
#    Given I am logged in as the admin
#    And I visit the settings page
#    And I open the "Basic settings" section of the "Administration" group
#    # The "create" button does nothing before JavaScript was initialized, and
#    # the only way to detect that is waiting for the button to select tags to be
#    # shown.
#    And I see that the button to select tags is shown
#    And I create the tag "tag1" in the settings
#    And I create the tag "tag2" in the settings
#    And I create the tag "tag3" in the settings
#    And I create the tag "tag4" in the settings
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag1"
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag2"
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag3"
#    And I see that the dropdown for tags in the settings eventually contains the tag "tag4"
#    And I log out
#    And I am logged in
#    And I open the details view for "welcome.txt"
#    And I open the input field for tags in the details view
#    # When the input field is opened the dropdown is also opened automatically.
#    And I check the tag "tag2" in the dropdown for tags in the details view
#    And I check the tag "tag4" in the dropdown for tags in the details view
#    And I check the tag "tag3" in the dropdown for tags in the details view
#    When I uncheck the tag "tag2" in the dropdown for tags in the details view
#    And I uncheck the tag "tag4" in the dropdown for tags in the details view
#    Then I see that the tag "tag2" in the dropdown for tags in the details view is not checked
#    And I see that the tag "tag4" in the dropdown for tags in the details view is not checked
#    And I see that the tag "tag3" in the dropdown for tags in the details view is checked
#    And I see that the input field for tags in the details view does not contain the tag "tag2"
#    And I see that the input field for tags in the details view does not contain the tag "tag4"
#    And I see that the input field for tags in the details view contains the tag "tag3"

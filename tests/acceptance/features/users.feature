@apache
Feature: users

  Scenario: create a new user
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I click the New user button
    And I see that the new user form is shown
    When I create user unknownUser with password 123456acb
    Then I see that the list of users contains the user unknownUser

  Scenario: create a new user with a custom display name
    Given I am logged in as the admin
    And I open the User settings
    When I click the New user button
    And I see that the new user form is shown
    And I set the user name for the new user to "test"
    And I set the display name for the new user to "Test display name"
    And I set the password for the new user to "123456acb"
    And I create the new user
    Then I see that the list of users contains the user "test"
#    And I see that the display name for the user "test" is "Test display name"

  Scenario: delete a user
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I see that the list of users contains the user user0
    And I open the actions menu for the user user0
#    And I see that the "Delete user" action in the user0 actions menu is shown
#    When I click the "Delete user" action in the user0 actions menu
#    Then I see that the list of users does not contains the user user0

  Scenario: disable a user
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I see that the list of users contains the user user0
    And I open the actions menu for the user user0
#    And I see that the "Disable user" action in the user0 actions menu is shown
#    When I click the "Disable user" action in the user0 actions menu
#    Then I see that the list of users does not contains the user user0
#    When I open the "Disabled users" section
#    Then I see that the list of users contains the user user0

  Scenario: users navigation without disabled users
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I open the "Disabled users" section
    And I see that the list of users contains the user disabledUser
    And I open the actions menu for the user disabledUser
#    And I see that the "Enable user" action in the disabledUser actions menu is shown
#    When I click the "Enable user" action in the disabledUser actions menu
#    Then I see that the section "Disabled users" is not shown
    # check again after reloading the settings
#    When I open the User settings
#    Then I see that the section "Disabled users" is not shown

  Scenario: assign user to a group
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I see that the list of users contains the user user0
    When I toggle the edit mode for the user user0
    Then I see that the edit mode is on for user user0
    # disabled because we need the TAB patch:
    # https://github.com/minkphp/MinkSelenium2Driver/pull/244
    # When I assign the user user0 to the group admin
    # Then I see that the section Admins is shown
    # And I see that the section Admins has a count of 2

  Scenario: create and delete a group
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I see that the list of users contains the user user0
    # disabled because we need the TAB patch:
    # https://github.com/minkphp/MinkSelenium2Driver/pull/244
    # And I assign the user user0 to the group Group1
    # And I see that the section Group1 is shown
    # And I click the "icon-delete" button on the Group1 section
    # And I see that the confirmation dialog is shown
    # When I click the "Yes" button of the confirmation dialog
    # Then I see that the section Group1 is not shown

  Scenario: delete an empty group
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    # disabled because we need the TAB patch:
    # https://github.com/minkphp/MinkSelenium2Driver/pull/244
    # And I assign the user user0 to the group Group1
    # And I see that the section Group1 is shown
    # And I withdraw the user user0 from the group Group1
    # And I see that the section Group1 does not have a count
    # And I click the "icon-delete" button on the Group1 section
    # And I see that the confirmation dialog is shown
    # When I click the "Yes" button of the confirmation dialog
    # Then I see that the section Group1 is not shown

  Scenario: change columns visibility
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I open the settings
    And I see that the settings are opened
    When I toggle the showLanguages checkbox in the settings
    Then I see that the "Language" column is shown
    When I toggle the showLastLogin checkbox in the settings
    Then I see that the "Last login" column is shown
    When I toggle the showStoragePath checkbox in the settings
    Then I see that the "Storage location" column is shown
    When I toggle the showUserBackend checkbox in the settings
    Then I see that the "User backend" column is shown

#  Scenario: change display name
#    Given I act as Jane
#    And I am logged in as the admin
#    And I open the User settings
#    And I see that the list of users contains the user user0
#    And I see that the displayName of user0 is user0
#    When I set the displayName for user0 to user1
#    And I see that the displayName cell for user user0 is done loading
#    Then I see that the displayName of user0 is user1

  Scenario: change password
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I see that the list of users contains the user user0
    When I toggle the edit mode for the user user0
    Then I see that the edit mode is on for user user0
    And I see that the password of user0 is ""
    When I set the password for user0 to 123456
    And I see that the password cell for user user0 is done loading
    # password input is emptied on change
    Then I see that the password of user0 is ""

#  Scenario: change email
#    Given I act as Jane
#    And I am logged in as the admin
#    And I open the User settings
#    And I see that the list of users contains the user user0
#    And I see that the mailAddress of user0 is ""
#    When I set the mailAddress for user0 to "test@nextcloud.com"
#    And I see that the mailAddress cell for user user0 is done loading
#    Then I see that the mailAddress of user0 is "test@nextcloud.com"

  Scenario: change user quota
    Given I act as Jane
    And I am logged in as the admin
    And I open the User settings
    And I see that the list of users contains the user user0
    When I toggle the edit mode for the user user0
    Then I see that the edit mode is on for user user0
    And I see that the user quota of user0 is Unlimited
    # disabled because we need the TAB patch:
    # https://github.com/minkphp/MinkSelenium2Driver/pull/244
    # When I set the user user0 quota to 1GB
    # And I see that the quota cell for user user0 is done loading
    # Then I see that the user quota of user0 is "1 GB"
    # When I set the user user0 quota to Unlimited
    # And I see that the quota cell for user user0 is done loading
    # Then I see that the user quota of user0 is Unlimited
    # When I set the user user0 quota to 0
    # And I see that the quota cell for user user0 is done loading
    # Then I see that the user quota of user0 is "0 B"
    # When I set the user user0 quota to Default
    # And I see that the quota cell for user user0 is done loading
    # Then I see that the user quota of user0 is "Default quota"

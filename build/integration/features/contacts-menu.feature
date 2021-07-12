Feature: contacts-menu

  Scenario: users can be searched by display name
    Given user "user0" exists
    And user "user1" exists
    And As an "admin"
    And sending "PUT" to "/cloud/users/user1" with
      | key | displayname |
      | value | Test name |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "1" contacts
    And searched contact "0" is named "Test name"

  Scenario: users can be searched by email
    Given user "user0" exists
    And user "user1" exists
    And As an "admin"
    And sending "PUT" to "/cloud/users/user1" with
      | key | email |
      | value | test@example.com |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "1" contacts
    And searched contact "0" is named "user1"

  Scenario: users can not be searched by id
    Given user "user0" exists
    And user "user1" exists
    And As an "admin"
    And sending "PUT" to "/cloud/users/user1" with
      | key | displayname |
      | value | Test name |
    When Logging in using web as "user0"
    And searching for contacts matching with "user"
    Then the list of searched contacts has "0" contacts

  Scenario: search several users
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user3" exists
    And user "user4" exists
    And user "user5" exists
    And As an "admin"
    And sending "PUT" to "/cloud/users/user1" with
      | key | displayname |
      | value | Test name |
    And sending "PUT" to "/cloud/users/user2" with
      | key | email |
      | value | test@example.com |
    And sending "PUT" to "/cloud/users/user3" with
      | key | displayname |
      | value | Unmatched name |
    And sending "PUT" to "/cloud/users/user4" with
      | key | email |
      | value | unmatched@example.com |
    And sending "PUT" to "/cloud/users/user5" with
      | key | displayname |
      | value | Another test name |
    And sending "PUT" to "/cloud/users/user5" with
      | key | email |
      | value | another_test@example.com |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "3" contacts
    # Results are sorted alphabetically
    And searched contact "0" is named "Another test name"
    And searched contact "1" is named "Test name"
    And searched contact "2" is named "user2"



  Scenario: users can not be found by display name if visibility is private
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | displayname | Test name |
      | displaynameScope | private |
    And Logging in using web as "user2"
    And Sending a "PUT" to "/settings/users/user2/settings" with requesttoken
      | displayname | Another test name |
      | displaynameScope | contacts |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "1" contacts
    And searched contact "0" is named "Another test name"

  Scenario: users can not be found by email if visibility is private
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | email | test@example.com |
      | emailScope | private |
    And Logging in using web as "user2"
    And Sending a "PUT" to "/settings/users/user2/settings" with requesttoken
      | email | another_test@example.com |
      | emailScope | contacts |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "1" contacts
    And searched contact "0" is named "user2"

  Scenario: users can be found by other properties if the visibility of one is private
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | displayname | Test name |
      | displaynameScope | contacts |
      | email | test@example.com |
      | emailScope | private |
    And Logging in using web as "user2"
    And Sending a "PUT" to "/settings/users/user2/settings" with requesttoken
      | displayname | Another test name |
      | displaynameScope | private |
      | email | another_test@example.com |
      | emailScope | contacts |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "2" contacts
    And searched contact "0" is named ""
    And searched contact "1" is named "Test name"



  Scenario: users can be searched by display name if visibility is increased again
    Given user "user0" exists
    And user "user1" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | displayname | Test name |
      | displaynameScope | private |
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | displaynameScope | contacts |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "1" contacts
    And searched contact "0" is named "Test name"

  Scenario: users can be searched by email if visibility is increased again
    Given user "user0" exists
    And user "user1" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | email | test@example.com |
      | emailScope | private |
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | emailScope | contacts |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "1" contacts
    And searched contact "0" is named "user1"



  Scenario: users can not be searched by display name if visibility is private even if updated with provisioning
    Given user "user0" exists
    And user "user1" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | displaynameScope | private |
    And As an "admin"
    And sending "PUT" to "/cloud/users/user1" with
      | key | displayname |
      | value | Test name |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "0" contacts

  Scenario: users can not be searched by email if visibility is private even if updated with provisioning
    Given user "user0" exists
    And user "user1" exists
    And Logging in using web as "user1"
    And Sending a "PUT" to "/settings/users/user1/settings" with requesttoken
      | emailScope | private |
    And As an "admin"
    And sending "PUT" to "/cloud/users/user1" with
      | key | email |
      | value | test@example.com |
    When Logging in using web as "user0"
    And searching for contacts matching with "test"
    Then the list of searched contacts has "0" contacts

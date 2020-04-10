Feature: app-files-sharing

  Scenario: share a file with another user
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I rename "welcome.txt" to "farewell.txt"
    And I see that the file list contains a file named "farewell.txt"
    When I share "farewell.txt" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    Then I see that the file list contains a file named "farewell.txt"
    And I open the details view for "farewell.txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "admin"

  Scenario: share a file with another user that needs to accept shares
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I visit the settings page
    And I open the "Sharing" section
    And I disable accepting the shares by default
    And I see that shares are not accepted by default
    And I act as John
    And I rename "welcome.txt" to "farewell.txt"
    And I see that the file list contains a file named "farewell.txt"
    When I share "farewell.txt" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    And I open the Files app
    And I see that the file list does not contain a file named "farewell.txt"
    And I accept the share for "/farewell.txt" in the notifications
    # The Files app is open again to reload the file list
    And I open the Files app
    Then I see that the file list contains a file named "farewell.txt"
    And I open the details view for "farewell.txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "admin"

  Scenario: share a file with another user who already has a file with that name
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    When I share "welcome.txt" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    Then I see that the file list contains a file named "welcome (2).txt"
    And I open the details view for "welcome (2).txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "admin"

  Scenario: share a skeleton file with another user before first login
    # If a file is shared with a user before her first login the skeleton would
    # not have been created, so if the shared file has the same name as one from
    # the skeleton the shared file will take its place and the skeleton file
    # will not be added.
    Given I act as John
    And I am logged in as the admin
    When I share "welcome.txt" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    And I am logged in
    Then I see that the file list contains a file named "welcome.txt"
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "admin"

  Scenario: reshare a file with another user
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as Jim
    And I am logged in as "user1"
    And I act as John
    And I rename "welcome.txt" to "farewell.txt"
    And I see that the file list contains a file named "farewell.txt"
    And I share "farewell.txt" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    When I share "farewell.txt" with "user1"
    And I see that the file is shared with "user1"
    And I act as Jim
    # The Files app is open again to reload the file list
    And I open the Files app
    Then I see that the file list contains a file named "farewell.txt"
    And I open the details view for "farewell.txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "user0"

  Scenario: owner sees reshares with other users
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I rename "welcome.txt" to "farewell.txt"
    And I see that the file list contains a file named "farewell.txt"
    And I share "farewell.txt" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    And I share "farewell.txt" with "user1"
    And I see that the file is shared with "user1"
    When I act as John
    # The Files app is open again to reload the file list and the shares
    And I open the Files app
    And I open the details view for "farewell.txt"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    Then I see that the file is shared with "user0"
    And I see that the file is shared with "user1"

  Scenario: share an empty folder with another user
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    When I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    Then I see that the file list contains a file named "Shared folder"
    And I open the details view for "Shared folder"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "admin"

  Scenario: sharee sees a folder created by the owner in a shared folder
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    And I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I enter in the folder named "Shared folder"
    And I create a new folder named "Subfolder"
    And I see that the file list contains a file named "Subfolder"
    When I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    And I enter in the folder named "Shared folder"
    Then I see that the file list contains a file named "Subfolder"

  Scenario: owner sees a folder created by the sharee in a shared folder
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    And I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    And I enter in the folder named "Shared folder"
    And I create a new folder named "Subfolder"
    And I see that the file list contains a file named "Subfolder"
    When I act as John
    And I enter in the folder named "Shared folder"
    Then I see that the file list contains a file named "Subfolder"

  Scenario: resharee sees a folder created by the owner in a shared folder
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as Jim
    And I am logged in as "user1"
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    And I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    And I share "Shared folder" with "user1"
    And I act as John
    And I enter in the folder named "Shared folder"
    And I create a new folder named "Subfolder"
    And I see that the file list contains a file named "Subfolder"
    When I act as Jim
    # The Files app is open again to reload the file list
    And I open the Files app
    And I enter in the folder named "Shared folder"
    Then I see that the file list contains a file named "Subfolder"

  Scenario: owner sees a folder created by the resharee in a shared folder
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as Jim
    And I am logged in as "user1"
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    And I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    And I share "Shared folder" with "user1"
    And I act as Jim
    # The Files app is open again to reload the file list
    And I open the Files app
    And I enter in the folder named "Shared folder"
    And I create a new folder named "Subfolder"
    And I see that the file list contains a file named "Subfolder"
    When I act as John
    And I enter in the folder named "Shared folder"
    Then I see that the file list contains a file named "Subfolder"

  Scenario: sharee can not reshare a folder if the sharer disables it
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    And I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I set the share with "user0" as not reshareable
    And I see that "user0" can not reshare the share
    When I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    Then I see that the file list contains a file named "Shared folder"
    And I open the details view for "Shared folder"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that the file is shared with me by "admin"
    And I see that resharing the file is not allowed

  Scenario: sharee can not reshare a subfolder if the sharer disables it for the parent folder
    Given I act as John
    And I am logged in as the admin
    And I act as Jane
    And I am logged in
    And I act as John
    And I create a new folder named "Shared folder"
    And I see that the file list contains a file named "Shared folder"
    And I share "Shared folder" with "user0"
    And I see that the file is shared with "user0"
    And I set the share with "user0" as not reshareable
    And I see that "user0" can not reshare the share
    And I enter in the folder named "Shared folder"
    And I create a new folder named "Subfolder"
    And I see that the file list contains a file named "Subfolder"
    When I act as Jane
    # The Files app is open again to reload the file list
    And I open the Files app
    And I enter in the folder named "Shared folder"
    Then I see that the file list contains a file named "Subfolder"
    And I open the details view for "Subfolder"
    And I see that the details view is open
    And I open the "Sharing" tab in the details view
    And I see that the "Sharing" tab in the details view is eventually loaded
    And I see that resharing the file is not allowed

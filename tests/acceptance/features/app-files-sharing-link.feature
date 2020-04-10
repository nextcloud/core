Feature: app-files-sharing-link

  Scenario: open the menu in a public shared link
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I open the Share menu
    Then I see that the Share menu is shown

  Scenario: hide download in a public shared link
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I set the download of the shared link as hidden
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    Then I see that the download button is not shown
    And I see that the Share menu button is not shown

  Scenario: show download again in a public shared link
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt"
    And I set the download of the shared link as hidden
    And I set the download of the shared link as shown
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    Then I see that the download button is shown
    And I open the Share menu
    And I see that the Share menu is shown

  Scenario: open a subfolder in a public shared folder
    Given I act as John
    And I am logged in
    And I create a new folder named "Shared folder with subfolders"
    And I enter in the folder named "Shared folder with subfolders"
    And I create a new folder named "Subfolder"
    And I enter in the folder named "Subfolder"
    And I create a new folder named "Subsubfolder"
    And I see that the file list contains a file named "Subsubfolder"
    # The Files app is open again to reload the file list
    And I open the Files app
    And I share the link for "Shared folder with subfolders"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    Then I see that the file list contains a file named "Subfolder"
    And I enter in the folder named "Subfolder"
    And I see that the file list contains a file named "Subsubfolder"

  Scenario: creation is not possible by default in a public shared folder
    Given I act as John
    And I am logged in
    And I create a new folder named "Shared folder"
    # To share the link the "Share" inline action has to be clicked but, as the
    # details view is opened automatically when the folder is created, clicking
    # on the inline action could fail if it is covered by the details view due
    # to its opening animation. Instead of ensuring that the animations of the
    # contents and the details view have both finished it is easier to close the
    # details view and wait until it is closed before continuing.
    And I close the details view
    And I see that the details view is closed
    And I share the link for "Shared folder"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I see that the file list is eventually loaded
    Then I see that it is not possible to create new files

  Scenario: create folder in a public editable shared folder
    Given I act as John
    And I am logged in
    And I create a new folder named "Editable shared folder"
    # To share the link the "Share" inline action has to be clicked but, as the
    # details view is opened automatically when the folder is created, clicking
    # on the inline action could fail if it is covered by the details view due
    # to its opening animation. Instead of ensuring that the animations of the
    # contents and the details view have both finished it is easier to close the
    # details view and wait until it is closed before continuing.
    And I close the details view
    And I see that the details view is closed
    And I share the link for "Editable shared folder"
    And I set the shared link as editable
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I create a new folder named "Subfolder"
    Then I see that the file list contains a file named "Subfolder"

  Scenario: owner sees folder created in the public page of an editable shared folder
    Given I act as John
    And I am logged in
    And I create a new folder named "Editable shared folder"
    # To share the link the "Share" inline action has to be clicked but, as the
    # details view is opened automatically when the folder is created, clicking
    # on the inline action could fail if it is covered by the details view due
    # to its opening animation. Instead of ensuring that the animations of the
    # contents and the details view have both finished it is easier to close the
    # details view and wait until it is closed before continuing.
    And I close the details view
    And I see that the details view is closed
    And I share the link for "Editable shared folder"
    And I set the shared link as editable
    And I write down the shared link
    And I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the shared link I wrote down
    And I create a new folder named "Subfolder"
    And I see that the file list contains a file named "Subfolder"
    When I act as John
    And I enter in the folder named "Editable shared folder"
    Then I see that the file list contains a file named "Subfolder"

  Scenario: set a password to a shared link
    Given I am logged in
    And I share the link for "welcome.txt"
    When I protect the shared link with the password "abcdef"
    Then I see that the password protect is disabled while loading
    And I see that the link share is password protected
    # As Talk is not enabled in the acceptance tests of the server the checkbox
    # is never shown.
    And I see that the checkbox to protect the password of the link share by Talk is not shown

  Scenario: access a shared link protected by password with a valid password
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I see that the current page is the Authenticate page for the shared link I wrote down
    And I authenticate with password "abcdef"
    Then I see that the current page is the shared link I wrote down
    And I see that the shared file preview shows the text "Welcome to your Nextcloud account!"

  Scenario: access a shared link protected by password with an invalid password
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I write down the shared link
    When I act as Jane
    And I visit the shared link I wrote down
    And I authenticate with password "fedcba"
    Then I see that the current page is the Authenticate page for the shared link I wrote down
    And I see that a wrong password for the shared file message is shown

  Scenario: access a direct download shared link protected by password with a valid password
    Given I act as John
    And I am logged in
    And I share the link for "welcome.txt" protected by the password "abcdef"
    And I write down the shared link
    When I act as Jane
    And I visit the direct download shared link I wrote down
    And I see that the current page is the Authenticate page for the direct download shared link I wrote down
    And I authenticate with password "abcdef"
    # download starts no page redirection
    And I see that the current page is the Authenticate page for the direct download shared link I wrote down

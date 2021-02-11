@apache
Feature: app-files

  Scenario: open and close the details view
    Given I am logged in
    When I open the details view for "welcome.txt"
    And I see that the details view is open
    And I close the details view
    Then I see that the details view is closed

  Scenario: open and close the details view twice
    Given I am logged in
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    And I close the details view
    And I see that the details view is closed
    When I open the details view for "welcome.txt"
    And I see that the details view is open
    And I close the details view
    Then I see that the details view is closed

  Scenario: open and close the details view again after coming back from a different section
    Given I am logged in
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    And I close the details view
    And I see that the details view is closed
    And I open the "Recent" section
    And I see that the current section is "Recent"
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    And I close the details view
    And I see that the details view is closed
    When I open the "All files" section
    And I see that the current section is "All files"
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    And I close the details view
    Then I see that the details view is closed

#  Scenario: viewing a favorite file in its folder shows the correct sidebar view
#    Given I am logged in
#    And I create a new folder named "other"
#    And I mark "other" as favorite
#    And I mark "welcome.txt" as favorite
#    And I see that "other" is marked as favorite
#    And I see that "welcome.txt" is marked as favorite
#    And I open the "Favorites" section
#    And I open the details view for "other"
#    And I see that the details view is open
#    And I see that the file name shown in the details view is "other"
#    When I view "welcome.txt" in folder
#    Then I see that the current section is "All files"
#    And I see that the details view is open
#    And I see that the file name shown in the details view is "welcome.txt"
#    When I open the details view for "other"
#    And I see that the file name shown in the details view is "other"


#  Scenario: viewing a favorite file in its folder does not prevent opening the details view in "All files" section
#    Given I am logged in
#    And I mark "welcome.txt" as favorite
#    And I see that "welcome.txt" is marked as favorite
#    And I open the "Favorites" section
#    And I open the details view for "welcome.txt"
#    And I see that the details view is open
#    And I view "welcome.txt" in folder
#    And I see that the current section is "All files"
#    When I open the details view for "welcome.txt"
#    Then I see that the details view is open

#  Scenario: show recent files
#    Given I am logged in
#    And I create a new folder named "Folder just created"
#    When I open the "Recent" section
#    Then I see that the current section is "Recent"
#    Then I see that the file list contains a file named "Folder just created"

#  Scenario: show recent files for a second time
#    Given I am logged in
#    And I open the "Recent" section
#    And I see that the current section is "Recent"
#    And I open the "All files" section
#    And I see that the current section is "All files"
#    And I create a new folder named "Folder just created"
#    When I open the "Recent" section
#    Then I see that the current section is "Recent"
#    Then I see that the file list contains a file named "Folder just created"

#  Scenario: show favorites
#    Given I am logged in
#    And I mark "welcome.txt" as favorite
#    When I open the "Favorites" section
#    Then I see that the current section is "Favorites"
#    Then I see that the file list contains a file named "welcome.txt"

#  Scenario: show favorites for a second time
#    Given I am logged in
#    And I open the "Favorites" section
#    And I see that the current section is "Favorites"
#    And I open the "All files" section
#    And I see that the current section is "All files"
#    And I mark "welcome.txt" as favorite
#    When I open the "Favorites" section
#    Then I see that the current section is "Favorites"
#    Then I see that the file list contains a file named "welcome.txt"

# TODO: disabled unreliable test
#  Scenario: show shares
#    Given I am logged in
#    And I share the link for "welcome.txt"
#    When I open the "Shares" section
#    Then I see that the current section is "Shares"
#    Then I see that the file list contains a file named "welcome.txt"

#  Scenario: show shares for a second time
#    Given I am logged in
#    And I open the "Shares" section
#    And I see that the current section is "Shares"
#    And I open the "All files" section
#    And I see that the current section is "All files"
#    And I share the link for "welcome.txt"
#    When I open the "Shares" section
#    Then I see that the current section is "Shares"
#    Then I see that the file list contains a file named "welcome.txt"

#  Scenario: show deleted files
#    Given I am logged in
#    And I delete "welcome.txt"
#    When I open the "Deleted files" section
#    Then I see that the current section is "Deleted files"
#    Then I see that the file list contains a file named "welcome.txt"

#  Scenario: show deleted files for a second time
#    Given I am logged in
#    And I open the "Deleted files" section
#    And I see that the current section is "Deleted files"
#    And I open the "All files" section
#    And I see that the current section is "All files"
#    And I delete "welcome.txt"
#    When I open the "Deleted files" section
#    Then I see that the current section is "Deleted files"
#    Then I see that the file list contains a file named "welcome.txt"

#  Scenario: move a file to another folder
#    Given I am logged in
#    And I create a new folder named "Destination"
#    When I start the move or copy operation for "welcome.txt"
#    And I select "Destination" in the file picker
#    And I move to the last selected folder in the file picker
#    Then I see that the file list does not contain a file named "welcome.txt"
#    And I enter in the folder named "Destination"
#    And I see that the file list contains a file named "welcome.txt"

#  Scenario: move a selection to another folder
#    Given I am logged in
#    And I create a new folder named "Folder"
#    And I create a new folder named "Not selected folder"
#    And I create a new folder named "Destination"
#    When I select "welcome.txt"
#    And I select "Folder"
#    And I start the move or copy operation for the selected files
#    And I select "Destination" in the file picker
#    And I move to the last selected folder in the file picker
#    Then I see that the file list does not contain a file named "welcome.txt"
#    And I see that the file list does not contain a file named "Folder"
#    And I see that the file list contains a file named "Not selected folder"
#    And I enter in the folder named "Destination"
#    And I see that the file list contains a file named "welcome.txt"
#    And I see that the file list contains a file named "Folder"
#    And I see that the file list does not contain a file named "Not selected folder"

#  Scenario: copy a file to another folder
#    Given I am logged in
#    And I create a new folder named "Destination"
#    When I start the move or copy operation for "welcome.txt"
#    And I select "Destination" in the file picker
#    And I copy to the last selected folder in the file picker
#    Then I enter in the folder named "Destination"
#    # The file will appear in the destination once the copy operation finishes
#    And I see that the file list contains a file named "welcome.txt"
#    # The Files app is open again to reload the file list in the root folder
#    And I open the Files app
#    And I see that the file list contains a file named "welcome.txt"

#  Scenario: copy a selection to another folder
#    Given I am logged in
#    And I create a new folder named "Folder"
#    And I create a new folder named "Not selected folder"
#    And I create a new folder named "Destination"
#    When I select "welcome.txt"
#    And I select "Folder"
#    And I start the move or copy operation for the selected files
#    And I select "Destination" in the file picker
#    And I copy to the last selected folder in the file picker
#    Then I enter in the folder named "Destination"
#    # The files will appear in the destination once the copy operation finishes
#    And I see that the file list contains a file named "welcome.txt"
#    And I see that the file list contains a file named "Folder"
#    And I see that the file list does not contain a file named "Not selected folder"
#    # The Files app is open again to reload the file list in the root folder
#    And I open the Files app
#    And I see that the file list contains a file named "welcome.txt"
#    And I see that the file list contains a file named "Folder"
#    And I see that the file list contains a file named "Not selected folder"

  Scenario: copy a file in its same folder
    Given I am logged in
    When I start the move or copy operation for "welcome.txt"
    # No folder was explicitly selected, so the last selected folder is the
    # current folder.
    And I copy to the last selected folder in the file picker
    Then I see that the file list contains a file named "welcome.txt"
    And I see that the file list contains a file named "welcome (copy).txt"

  Scenario: copy a file twice in its same folder
    Given I am logged in
    And I start the move or copy operation for "welcome.txt"
    # No folder was explicitly selected, so the last selected folder is the
    # current folder.
    And I copy to the last selected folder in the file picker
    When I start the move or copy operation for "welcome.txt"
    And I copy to the last selected folder in the file picker
    Then I see that the file list contains a file named "welcome.txt"
    And I see that the file list contains a file named "welcome (copy).txt"
    And I see that the file list contains a file named "welcome (copy 2).txt"

  Scenario: copy a copy of a file in its same folder
    Given I am logged in
    And I start the move or copy operation for "welcome.txt"
    # No folder was explicitly selected, so the last selected folder is the
    # current folder.
    And I copy to the last selected folder in the file picker
    When I start the move or copy operation for "welcome (copy).txt"
    And I copy to the last selected folder in the file picker
    Then I see that the file list contains a file named "welcome.txt"
    And I see that the file list contains a file named "welcome (copy).txt"
    And I see that the file list contains a file named "welcome (copy 2).txt"

#  Scenario: rename a file with the details view open
#    Given I am logged in
#    And I open the details view for "welcome.txt"
#    When I rename "welcome.txt" to "farewell.txt"
#    Then I see that the file list contains a file named "farewell.txt"
#    And I see that the file name shown in the details view is "farewell.txt"

  Scenario: marking a file as favorite causes the file list to be sorted again
    Given I am logged in
    And I create a new folder named "A name alphabetically lower than welcome.txt"
    And I see that "A name alphabetically lower than welcome.txt" precedes "welcome.txt" in the file list
    # To mark the file as favorite the file actions menu has to be shown but, as
    # the details view is opened automatically when the folder is created,
    # clicking on the menu trigger could fail if it is covered by the details
    # view due to its opening animation. Instead of ensuring that the animations
    # of the contents and the details view have both finished it is easier to
    # close the details view and wait until it is closed before continuing.
    And I close the details view
    And I see that the details view is closed
    When I mark "welcome.txt" as favorite
    Then I see that "welcome.txt" is marked as favorite
    And I see that "welcome.txt" precedes "A name alphabetically lower than welcome.txt" in the file list

  Scenario: unmarking a file as favorite causes the file list to be sorted again
    Given I am logged in
    And I create a new folder named "A name alphabetically lower than welcome.txt"
    And I see that "A name alphabetically lower than welcome.txt" precedes "welcome.txt" in the file list
    # To mark the file as favorite the file actions menu has to be shown but, as
    # the details view is opened automatically when the folder is created,
    # clicking on the menu trigger could fail if it is covered by the details
    # view due to its opening animation. Instead of ensuring that the animations
    # of the contents and the details view have both finished it is easier to
    # close the details view and wait until it is closed before continuing.
    And I close the details view
    And I see that the details view is closed
    And I mark "welcome.txt" as favorite
    And I see that "welcome.txt" is marked as favorite
    And I see that "welcome.txt" precedes "A name alphabetically lower than welcome.txt" in the file list
    When I unmark "welcome.txt" as favorite
    Then I see that "welcome.txt" is not marked as favorite
    And I see that "A name alphabetically lower than welcome.txt" precedes "welcome.txt" in the file list

  Scenario: mark a file as favorite in the details view
    Given I am logged in
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    When I mark the file as favorite in the details view
    Then I see that "welcome.txt" is marked as favorite
    And I see that the file is marked as favorite in the details view

  Scenario: unmark a file as favorite in the details view
    Given I am logged in
    And I open the details view for "welcome.txt"
    And I see that the details view is open
    And I mark the file as favorite in the details view
    And I see that "welcome.txt" is marked as favorite
    And I see that the file is marked as favorite in the details view
    When I unmark the file as favorite in the details view
    Then I see that "welcome.txt" is not marked as favorite
    And I see that the file is not marked as favorite in the details view

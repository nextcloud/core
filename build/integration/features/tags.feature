Feature: tags

  Scenario: Creating a normal tag as regular user should work
    Given user "user0" exists
    When "user0" creates a "normal" tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "201"
    And The following tags should exist for "admin"
      |MySuperAwesomeTagName|true|true|
    And The following tags should exist for "user0"
      |MySuperAwesomeTagName|true|true|

  Scenario: Creating a not user-assignable tag as regular user should fail
    Given user "user0" exists
    When "user0" creates a "not user-assignable" tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "400"
    And "0" tags should exist for "admin"

  Scenario: Creating a not user-visible tag as regular user should fail
    Given user "user0" exists
    When "user0" creates a "not user-visible" tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "400"
    And "0" tags should exist for "admin"

  Scenario: Creating a not user-assignable tag with groups as admin should work
    Given user "user0" exists
    When "admin" creates a "not user-assignable" tag with name "TagWithGroups" and groups "group1|group2"
    Then The response should have a status code "201"
    And The "not user-assignable" tag with name "TagWithGroups" has the groups "group1|group2"

  Scenario: Creating a normal tag with groups as regular user should fail
    Given user "user0" exists
    When "user0" creates a "normal" tag with name "MySuperAwesomeTagName" and groups "group1|group2"
    Then The response should have a status code "400"
    And "0" tags should exist for "user0"

  Scenario: Renaming a normal tag as regular user should work
    Given user "user0" exists
    Given "admin" creates a "normal" tag with name "MySuperAwesomeTagName"
    When "user0" edits the tag with name "MySuperAwesomeTagName" and sets its name to "AnotherTagName"
    Then The response should have a status code "207"
    And The following tags should exist for "admin"
      |AnotherTagName|true|true|

  Scenario: Renaming a not user-assignable tag as regular user should fail
    Given user "user0" exists
    Given "admin" creates a "not user-assignable" tag with name "MySuperAwesomeTagName"
    When "user0" edits the tag with name "MySuperAwesomeTagName" and sets its name to "AnotherTagName"
    Then The response should have a status code "403"
    And The following tags should exist for "admin"
      |MySuperAwesomeTagName|true|false|

  Scenario: Renaming a not user-visible tag as regular user should fail
    Given user "user0" exists
    Given "admin" creates a "not user-visible" tag with name "MySuperAwesomeTagName"
    When "user0" edits the tag with name "MySuperAwesomeTagName" and sets its name to "AnotherTagName"
    Then The response should have a status code "404"
    And The following tags should exist for "admin"
      |MySuperAwesomeTagName|false|true|

  Scenario: Editing tag groups as admin should work
    Given user "user0" exists
    Given "admin" creates a "not user-assignable" tag with name "TagWithGroups" and groups "group1|group2"
    When "admin" edits the tag with name "TagWithGroups" and sets its groups to "group1|group3"
    Then The response should have a status code "207"
    And The "not user-assignable" tag with name "TagWithGroups" has the groups "group1|group3"

  Scenario: Editing tag groups as regular user should fail
    Given user "user0" exists
    Given "admin" creates a "not user-assignable" tag with name "TagWithGroups"
    When "user0" edits the tag with name "TagWithGroups" and sets its groups to "group1|group3"
    Then The response should have a status code "403"

  Scenario: Deleting a normal tag as regular user should fail
    Given user "user0" exists
    Given "admin" creates a "normal" tag with name "MySuperAwesomeTagName"
    When "user0" deletes the tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "403"
    And The following tags should exist for "admin"
      |MySuperAwesomeTagName|true|true|

  Scenario: Deleting a not user-assignable tag as regular user should fail
    Given user "user0" exists
    Given "admin" creates a "not user-assignable" tag with name "MySuperAwesomeTagName"
    When "user0" deletes the tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "403"
    And The following tags should exist for "admin"
      |MySuperAwesomeTagName|true|false|

  Scenario: Deleting a not user-visible tag as regular user should fail
    Given user "user0" exists
    Given "admin" creates a "not user-visible" tag with name "MySuperAwesomeTagName"
    When "user0" deletes the tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "404"
    And The following tags should exist for "admin"
      |MySuperAwesomeTagName|false|true|

  Scenario: Deleting a normal tag as admin should work
    Given "admin" creates a "normal" tag with name "MySuperAwesomeTagName"
    When "admin" deletes the tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "204"
    And "0" tags should exist for "admin"

  Scenario: Deleting a not user-assignable tag as admin should work
    Given "admin" creates a "not user-assignable" tag with name "MySuperAwesomeTagName"
    When "admin" deletes the tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "204"
    And "0" tags should exist for "admin"

  Scenario: Deleting a not user-visible tag as admin should work
    Given "admin" creates a "not user-visible" tag with name "MySuperAwesomeTagName"
    When "admin" deletes the tag with name "MySuperAwesomeTagName"
    Then The response should have a status code "204"
    And "0" tags should exist for "admin"

  Scenario: Assigning a normal tag to a file shared by someone else as regular user should work
    Given user "user0" exists
    Given user "12345" exists
    Given "admin" creates a "normal" tag with name "MySuperAwesomeTagName"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | 12345 |
      | shareType | 0 |
    Given user "12345" accepts last share
    When "12345" adds the tag "MySuperAwesomeTagName" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "201"
    And "/myFileToTag.txt" shared by "user0" has the following tags
      |MySuperAwesomeTagName|

  Scenario: Assigning a normal tag to a file belonging to someone else as regular user should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    When "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "404"
    And "/myFileToTag.txt" shared by "user0" has the following tags
      |MyFirstTag|

  Scenario: Assigning a not user-assignable tag to a file shared by someone else as regular user should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "not user-assignable" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    When "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "403"
    And "/myFileToTag.txt" shared by "user0" has the following tags
      |MyFirstTag|

  Scenario: Assigning a not user-assignable tag to a file shared by someone else as regular user belongs to tag's groups should work
    Given user "user0" exists
    Given user "user1" exists
    Given group "group1" exists
    Given user "user1" belongs to group "group1"
    Given "admin" creates a "not user-assignable" tag with name "MySuperAwesomeTagName" and groups "group1"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    When "user1" adds the tag "MySuperAwesomeTagName" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "201"
    And "/myFileToTag.txt" shared by "user0" has the following tags
      |MySuperAwesomeTagName|


  Scenario: Assigning a not user-visible tag to a file shared by someone else as regular user should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "not user-visible" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    When "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "412"
    And "/myFileToTag.txt" shared by "user0" has the following tags
      |MyFirstTag|

  Scenario: Assigning a not user-visible tag to a file shared by someone else as admin user should work
    Given user "user0" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "not user-visible" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    When "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    When "admin" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "201"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "admin"
      |MyFirstTag|
      |MySecondTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MyFirstTag|

  Scenario: Assigning a not user-assignable tag to a file shared by someone else as admin user should worj
    Given user "user0" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "not user-assignable" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    When "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    When "admin" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "201"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "admin"
      |MyFirstTag|
      |MySecondTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MyFirstTag|
      |MySecondTag|

  Scenario: Unassigning a normal tag from a file shared by someone else as regular user should work
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "204"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MySecondTag|

  Scenario: Unassigning a normal tag from a file unshared by someone else as regular user should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "404"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MyFirstTag|
      |MySecondTag|

  Scenario: Unassigning a not user-visible tag from a file shared by someone else as regular user should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "not user-visible" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    Given "admin" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "404"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MySecondTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "admin"
      |MyFirstTag|
      |MySecondTag|

  Scenario: Unassigning a not user-visible tag from a file shared by someone else as admin should work
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "not user-visible" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    Given "admin" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    When "admin" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "204"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MySecondTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "admin"
      |MySecondTag|

  Scenario: Unassigning a not user-visible tag from a file unshared by someone else should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "not user-visible" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    Given "admin" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Given As "user0" remove all shares from the file named "/myFileToTag.txt"
    When "admin" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "404"

  Scenario: Unassigning a not user-assignable tag from a file shared by someone else as regular user should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "not user-assignable" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    Given "admin" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    When "user1" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "403"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MyFirstTag|
      |MySecondTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "admin"
      |MyFirstTag|
      |MySecondTag|

  Scenario: Unassigning a not user-assignable tag from a file shared by someone else as admin should work
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "not user-assignable" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    Given "admin" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    When "admin" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "204"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MySecondTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "admin"
      |MySecondTag|

  Scenario: Unassigning a not user-assignable tag from a file unshared by someone else should fail
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "not user-assignable" tag with name "MyFirstTag"
    Given "admin" creates a "normal" tag with name "MySecondTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Given user "user1" accepts last share
    Given as "user0" creating a share with
      | path | myFileToTag.txt |
      | shareWith | admin |
      | shareType | 0 |
    Given user "admin" accepts last share
    Given "admin" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    Given "user0" adds the tag "MySecondTag" to "/myFileToTag.txt" shared by "user0"
    Given As "user0" remove all shares from the file named "/myFileToTag.txt"
    When "admin" removes the tag "MyFirstTag" from "/myFileToTag.txt" shared by "user0"
    Then The response should have a status code "404"

  Scenario: Overwriting existing normal tags should fail
    Given user "user0" exists
    Given "user0" creates a "normal" tag with name "MyFirstTag"
    When "user0" creates a "normal" tag with name "MyFirstTag"
    Then The response should have a status code "409"

  Scenario: Overwriting existing not user-assignable tags should fail
    Given "admin" creates a "not user-assignable" tag with name "MyFirstTag"
    When "admin" creates a "not user-assignable" tag with name "MyFirstTag"
    Then The response should have a status code "409"

  Scenario: Overwriting existing not user-visible tags should fail
    Given "admin" creates a "not user-visible" tag with name "MyFirstTag"
    When "admin" creates a "not user-visible" tag with name "MyFirstTag"
    Then The response should have a status code "409"

  Scenario: Getting tags only works with access to the file
    Given user "user0" exists
    Given user "user1" exists
    Given "admin" creates a "normal" tag with name "MyFirstTag"
    Given user "user0" uploads file "data/textfile.txt" to "/myFileToTag.txt"
    When "user0" adds the tag "MyFirstTag" to "/myFileToTag.txt" shared by "user0"
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user0"
      |MyFirstTag|
    And "/myFileToTag.txt" shared by "user0" has the following tags for "user1"
      ||
    And The response should have a status code "404"

  Scenario: User can assign tags when in the tag's groups
    Given user "user0" exists
    Given group "group1" exists
    Given user "user0" belongs to group "group1"
    When "admin" creates a "not user-assignable" tag with name "TagWithGroups" and groups "group1|group2"
    Then The response should have a status code "201"
    And the user "user0" can assign the "not user-assignable" tag with name "TagWithGroups"

  Scenario: User cannot assign tags when not in the tag's groups
    Given user "user0" exists
    When "admin" creates a "not user-assignable" tag with name "TagWithGroups" and groups "group1|group2"
    Then The response should have a status code "201"
    And the user "user0" cannot assign the "not user-assignable" tag with name "TagWithGroups"

  Scenario: Assign a normal tag to a file
    Given user "user0" exists
    And "admin" creates a "normal" tag with name "Etiqueta"
    And As an "user0"
    When "user0" adds the tag "Etiqueta" to "/textfile0.txt" owned by "user0"
    Then The response should have a status code "201"
    And "textfile0.txt" owned by "user0" has the following tags
      | Etiqueta |


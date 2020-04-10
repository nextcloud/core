Feature: sharing
  Background:
    Given using api version "1"
    Given using new dav path

# See sharing-v1-part2.feature

  Scenario: Correct webdav share-permissions for received file with edit and reshare permissions
    Given user "user0" exists
    And user "user1" exists
    And User "user0" uploads file with content "foo" to "/tmp.txt"
    And file "/tmp.txt" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    When as "user1" gets properties of folder "/tmp.txt" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "19"

  Scenario: Correct webdav share-permissions for received file with edit permissions but no reshare permissions
    Given user "user0" exists
    And user "user1" exists
    And User "user0" uploads file with content "foo" to "/tmp.txt"
    And file "tmp.txt" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And As an "user0"
    And Updating last share with
      | permissions | 3 |
    When as "user1" gets properties of folder "/tmp.txt" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "3"

  Scenario: Correct webdav share-permissions for received file with reshare permissions but no edit permissions
    Given user "user0" exists
    And user "user1" exists
    And User "user0" uploads file with content "foo" to "/tmp.txt"
    And file "tmp.txt" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And As an "user0"
    And Updating last share with
      | permissions | 17 |
    When as "user1" gets properties of folder "/tmp.txt" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "17"

  Scenario: Correct webdav share-permissions for owned folder
    Given user "user0" exists
    And user "user0" created a folder "/tmp"
    When as "user0" gets properties of folder "/" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "31"

  Scenario: Correct webdav share-permissions for received folder with all permissions
    Given user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/tmp"
    And file "/tmp" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    When as "user1" gets properties of folder "/tmp" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "31"

  Scenario: Correct webdav share-permissions for received folder with all permissions but edit
    Given user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/tmp"
    And file "/tmp" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And As an "user0"
    And Updating last share with
      | permissions | 29 |
    When as "user1" gets properties of folder "/tmp" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "29"

  Scenario: Correct webdav share-permissions for received folder with all permissions but create
    Given user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/tmp"
    And file "/tmp" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And As an "user0"
    And Updating last share with
      | permissions | 27 |
    When as "user1" gets properties of folder "/tmp" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "27"

  Scenario: Correct webdav share-permissions for received folder with all permissions but delete
    Given user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/tmp"
    And file "/tmp" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And As an "user0"
    And Updating last share with
      | permissions | 23 |
    When as "user1" gets properties of folder "/tmp" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "23"

  Scenario: Correct webdav share-permissions for received folder with all permissions but share
    Given user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/tmp"
    And file "/tmp" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And As an "user0"
    And Updating last share with
      | permissions | 15 |
    When as "user1" gets properties of folder "/tmp" with
      |{http://open-collaboration-services.org/ns}share-permissions |
    Then the single response should contain a property "{http://open-collaboration-services.org/ns}share-permissions" with value "15"

  Scenario: unique target names for incoming shares
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user0" created a folder "/foo"
    And user "user1" created a folder "/foo"
    When file "/foo" of user "user0" is shared with user "user2"
    And user "user2" accepts last share
    And file "/foo" of user "user1" is shared with user "user2"
    And user "user2" accepts last share
    Then user "user2" should see following elements
      | /foo/       |
      | /foo%20(2)/ |

  Scenario: Creating a new share with a disabled user
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And assure user "user0" is disabled
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "997"
    And the HTTP status code should be "401"

  Scenario: Deleting a group share as its owner
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user0" belongs to group "group1"
    And user "user1" belongs to group "group1"
    And As an "user0"
    And creating a share with
      | path | welcome.txt |
      | shareType | 1 |
      | shareWith | group1 |
    When As an "user0"
    And Deleting last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Getting info of last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"
    And As an "user1"
    And Getting info of last share
    And the OCS status code should be "404"
    And the HTTP status code should be "200"

  Scenario: Deleting a group share as user
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user1" belongs to group "group1"
    And As an "user0"
    And creating a share with
      | path | welcome.txt |
      | shareType | 1 |
      | shareWith | group1 |
    When As an "user1"
    And Deleting last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Merging shares for recipient when shared from outside with group and member
    Given using old dav path
    And As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user1" belongs to group "group1"
    And user "user0" created a folder "/merge-test-outside"
    When folder "/merge-test-outside" of user "user0" is shared with group "group1"
    And user "user1" accepts last share
    And folder "/merge-test-outside" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    Then as "user1" the folder "/merge-test-outside" exists
    And as "user1" the folder "/merge-test-outside (2)" does not exist

  Scenario: Merging shares for recipient when shared from outside with group and member with different permissions
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user1" belongs to group "group1"
    And user "user0" created a folder "/merge-test-outside-perms"
    When folder "/merge-test-outside-perms" of user "user0" is shared with group "group1" with permissions 1
    And user "user1" accepts last share
    And folder "/merge-test-outside-perms" of user "user0" is shared with user "user1" with permissions 31
    And user "user1" accepts last share
    Then as "user1" gets properties of folder "/merge-test-outside-perms" with
      |{http://owncloud.org/ns}permissions|
    And the single response should contain a property "{http://owncloud.org/ns}permissions" with value "SRGDNVCK"
    And as "user1" the folder "/merge-test-outside-perms (2)" does not exist

  Scenario: Merging shares for recipient when shared from outside with two groups
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And group "group2" exists
    And user "user1" belongs to group "group1"
    And user "user1" belongs to group "group2"
    And user "user0" created a folder "/merge-test-outside-twogroups"
    When folder "/merge-test-outside-twogroups" of user "user0" is shared with group "group1"
    And user "user1" accepts last share
    And folder "/merge-test-outside-twogroups" of user "user0" is shared with group "group2"
    And user "user1" accepts last share
    Then as "user1" the folder "/merge-test-outside-twogroups" exists
    And as "user1" the folder "/merge-test-outside-twogroups (2)" does not exist

  Scenario: Merging shares for recipient when shared from outside with two groups with different permissions
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And group "group2" exists
    And user "user1" belongs to group "group1"
    And user "user1" belongs to group "group2"
    And user "user0" created a folder "/merge-test-outside-twogroups-perms"
    When folder "/merge-test-outside-twogroups-perms" of user "user0" is shared with group "group1" with permissions 1
    And user "user1" accepts last share
    And folder "/merge-test-outside-twogroups-perms" of user "user0" is shared with group "group2" with permissions 31
    And user "user1" accepts last share
    Then as "user1" gets properties of folder "/merge-test-outside-twogroups-perms" with
      |{http://owncloud.org/ns}permissions|
    And the single response should contain a property "{http://owncloud.org/ns}permissions" with value "SRGDNVCK"
    And as "user1" the folder "/merge-test-outside-twogroups-perms (2)" does not exist

  Scenario: Merging shares for recipient when shared from outside with two groups and member
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And group "group2" exists
    And user "user1" belongs to group "group1"
    And user "user1" belongs to group "group2"
    And user "user0" created a folder "/merge-test-outside-twogroups-member-perms"
    When folder "/merge-test-outside-twogroups-member-perms" of user "user0" is shared with group "group1" with permissions 1
    And user "user1" accepts last share
    And folder "/merge-test-outside-twogroups-member-perms" of user "user0" is shared with group "group2" with permissions 31
    And user "user1" accepts last share
    And folder "/merge-test-outside-twogroups-member-perms" of user "user0" is shared with user "user1" with permissions 1
    And user "user1" accepts last share
    Then as "user1" gets properties of folder "/merge-test-outside-twogroups-member-perms" with
      |{http://owncloud.org/ns}permissions|
    And the single response should contain a property "{http://owncloud.org/ns}permissions" with value "SRGDNVCK"
    And as "user1" the folder "/merge-test-outside-twogroups-member-perms (2)" does not exist

  Scenario: Merging shares for recipient when shared from inside with group
    Given As an "admin"
    And user "user0" exists
    And group "group1" exists
    And user "user0" belongs to group "group1"
    And user "user0" created a folder "/merge-test-inside-group"
    When folder "/merge-test-inside-group" of user "user0" is shared with group "group1"
    Then as "user0" the folder "/merge-test-inside-group" exists
    And as "user0" the folder "/merge-test-inside-group (2)" does not exist

  Scenario: Merging shares for recipient when shared from inside with two groups
    Given As an "admin"
    And user "user0" exists
    And group "group1" exists
    And group "group2" exists
    And user "user0" belongs to group "group1"
    And user "user0" belongs to group "group2"
    And user "user0" created a folder "/merge-test-inside-twogroups"
    When folder "/merge-test-inside-twogroups" of user "user0" is shared with group "group1"
    And folder "/merge-test-inside-twogroups" of user "user0" is shared with group "group2"
    Then as "user0" the folder "/merge-test-inside-twogroups" exists
    And as "user0" the folder "/merge-test-inside-twogroups (2)" does not exist
    And as "user0" the folder "/merge-test-inside-twogroups (3)" does not exist

  Scenario: Merging shares for recipient when shared from inside with group with less permissions
    Given As an "admin"
    And user "user0" exists
    And group "group1" exists
    And group "group2" exists
    And user "user0" belongs to group "group1"
    And user "user0" belongs to group "group2"
    And user "user0" created a folder "/merge-test-inside-twogroups-perms"
    When folder "/merge-test-inside-twogroups-perms" of user "user0" is shared with group "group1"
    And folder "/merge-test-inside-twogroups-perms" of user "user0" is shared with group "group2"
    Then as "user0" gets properties of folder "/merge-test-inside-twogroups-perms" with
      |{http://owncloud.org/ns}permissions|
    And the single response should contain a property "{http://owncloud.org/ns}permissions" with value "RGDNVCK"
    And as "user0" the folder "/merge-test-inside-twogroups-perms (2)" does not exist
    And as "user0" the folder "/merge-test-inside-twogroups-perms (3)" does not exist

  Scenario: Merging shares for recipient when shared from outside with group then user and recipient renames in between
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user1" belongs to group "group1"
    And user "user0" created a folder "/merge-test-outside-groups-renamebeforesecondshare"
    When folder "/merge-test-outside-groups-renamebeforesecondshare" of user "user0" is shared with group "group1"
    And user "user1" accepts last share
    And User "user1" moved folder "/merge-test-outside-groups-renamebeforesecondshare" to "/merge-test-outside-groups-renamebeforesecondshare-renamed"
    And Sleep for "1" seconds
    And folder "/merge-test-outside-groups-renamebeforesecondshare" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    Then as "user1" gets properties of folder "/merge-test-outside-groups-renamebeforesecondshare-renamed" with
      |{http://owncloud.org/ns}permissions|
    And the single response should contain a property "{http://owncloud.org/ns}permissions" with value "SRGDNVCK"
    And as "user1" the folder "/merge-test-outside-groups-renamebeforesecondshare" does not exist

  Scenario: Merging shares for recipient when shared from outside with user then group and recipient renames in between
    Given using old dav path
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user1" belongs to group "group1"
    And user "user0" created a folder "/merge-test-outside-groups-renamebeforesecondshare"
    When folder "/merge-test-outside-groups-renamebeforesecondshare" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And User "user1" moved folder "/merge-test-outside-groups-renamebeforesecondshare" to "/merge-test-outside-groups-renamebeforesecondshare-renamed"
    And Sleep for "1" seconds
    And folder "/merge-test-outside-groups-renamebeforesecondshare" of user "user0" is shared with group "group1"
    And user "user1" accepts last share
    Then as "user1" gets properties of folder "/merge-test-outside-groups-renamebeforesecondshare-renamed" with
      |{http://owncloud.org/ns}permissions|
    And the single response should contain a property "{http://owncloud.org/ns}permissions" with value "SRGDNVCK"
    And as "user1" the folder "/merge-test-outside-groups-renamebeforesecondshare" does not exist

  Scenario: Empting trashbin
    Given As an "admin"
    And user "user0" exists
    And User "user0" deletes file "/textfile0.txt"
    When User "user0" empties trashbin
    Then the HTTP status code should be "204"

  Scenario: orphaned shares
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/common"
    And user "user0" created a folder "/common/sub"
    And file "/common/sub" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And User "user0" deletes folder "/common"
    When User "user0" empties trashbin
    Then as "user1" the folder "/sub" does not exist

  Scenario: sharing again an own file while belonging to a group
    Given As an "admin"
    Given user "user0" exists
    And group "sharing-group" exists
    And user "user0" belongs to group "sharing-group"
    And file "welcome.txt" of user "user0" is shared with group "sharing-group"
    And Deleting last share
    When creating a share with
      | path | welcome.txt |
      | shareWith | sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: unshare from self
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "sharing-group" exists
    And user "user0" belongs to group "sharing-group"
    And user "user1" belongs to group "sharing-group"
    And file "/PARENT/parent.txt" of user "user0" is shared with group "sharing-group"
    And user "user1" accepts last share
    And user "user0" stores etag of element "/PARENT"
    And user "user1" stores etag of element "/"
    And As an "user1"
    When Deleting last share
    Then etag of element "/" of user "user1" has changed
    And etag of element "/PARENT" of user "user0" has not changed

  Scenario: do not allow to increase permissions on received share
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/TMP"
    And As an "user0"
    And creating a share with
      | path | TMP |
      | shareType | 0 |
      | shareWith | user1 |
      | permissions | 17 |
    When As an "user1"
    And Updating last share with
      | permissions | 19 |
    Then the OCS status code should be "403"
    And the HTTP status code should be "401"

  Scenario: do not allow to increase permissions on non received share with user with resharing rights
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user0" created a folder "/TMP"
    And As an "user0"
    And creating a share with
      | path | TMP |
      | shareType | 0 |
      | shareWith | user1 |
      | permissions | 31 |
    And user "user1" accepts last share
    And creating a share with
      | path | TMP |
      | shareType | 0 |
      | shareWith | user2 |
      | permissions | 17 |
    When As an "user1"
    And Updating last share with
      | permissions | 19 |
    Then the OCS status code should be "403"
    And the HTTP status code should be "401"

  Scenario: do not allow to increase link share permissions on reshare
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/TMP"
    And As an "user0"
    And creating a share with
      | path | TMP |
      | shareType | 0 |
      | shareWith | user1 |
      | permissions | 17  |
    When As an "user1"
    And accepting last share
    And creating a share with
      | path | TMP |
      | shareType | 3 |
    And Updating last share with
      | publicUpload | true |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"

  Scenario: do not allow to increase link share permissions on sub reshare
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/TMP"
    And user "user0" created a folder "/TMP/SUB"
    And As an "user0"
    And creating a share with
      | path | TMP |
      | shareType | 0 |
      | shareWith | user1 |
      | permissions | 17  |
    When As an "user1"
    And accepting last share
    And creating a share with
      | path | TMP/SUB |
      | shareType | 3 |
    And Updating last share with
      | publicUpload | true |
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"

  Scenario: deleting file out of a share as recipient creates a backup for the owner
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/shared"
    And User "user0" moved file "/textfile0.txt" to "/shared/shared_file.txt"
    And folder "/shared" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    When User "user1" deletes file "/shared/shared_file.txt"
    Then as "user1" the file "/shared/shared_file.txt" does not exist
    And as "user0" the file "/shared/shared_file.txt" does not exist
    And as "user0" the file "/shared_file.txt" exists in trash
    And as "user1" the file "/shared_file.txt" exists in trash

  Scenario: deleting folder out of a share as recipient creates a backup for the owner
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/shared"
    And user "user0" created a folder "/shared/sub"
    And User "user0" moved file "/textfile0.txt" to "/shared/sub/shared_file.txt"
    And folder "/shared" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    When User "user1" deletes folder "/shared/sub"
    Then as "user1" the folder "/shared/sub" does not exist
    And as "user0" the folder "/shared/sub" does not exist
    And as "user0" the folder "/sub" exists in trash
    And as "user0" the file "/sub/shared_file.txt" exists in trash
    And as "user1" the folder "/sub" exists in trash
    And as "user1" the file "/sub/shared_file.txt" exists in trash

  Scenario: moving a file into a share as recipient
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And user "user0" created a folder "/shared"
    And folder "/shared" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    When User "user1" moved file "/textfile0.txt" to "/shared/shared_file.txt"
    Then as "user1" the file "/shared/shared_file.txt" exists
    And as "user0" the file "/shared/shared_file.txt" exists

  Scenario: Link shares inside of group shares keep their original data when the root share is updated
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "group1" exists
    And user "user1" belongs to group "group1"
    And As an "user0"
    And user "user0" created a folder "/share"
    And folder "/share" of user "user0" is shared with group "group1"
    And user "user1" accepts last share
    And user "user0" created a folder "/share/subfolder"
    And As an "user1"
    And save the last share data as "original"
    And as "user1" creating a share with
      | path | /share/subfolder |
      | shareType | 3 |
      | permissions | 31 |
    And save the last share data as "link"
    And As an "user0"
    And restore the last share data from "original"
    When Updating last share with
      | permissions | 23 |
      | expireDate | +3 days |
    And restore the last share data from "link"
    And Getting info of last share
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_source | A_NUMBER |
      | share_type | 3 |
      | permissions | 23 |
      | file_target | /subfolder |
      | expireDate  |            |

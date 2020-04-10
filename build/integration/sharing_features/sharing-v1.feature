Feature: sharing
  Background:
    Given using api version "1"
    Given using old dav path

  Scenario: Creating a new share with user
    Given user "user0" exists
    And user "user1" exists
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And The following headers should be set
      | Content-Security-Policy | default-src 'none';base-uri 'none';manifest-src 'self' |

  Scenario: Creating a share with a group
    Given user "user0" exists
    And user "user1" exists
    And group "sharing-group" exists
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareWith | sharing-group |
      | shareType | 1 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Creating a new share with user who already received a share through their group
    Given As an "admin"
    And user "user0" exists
    And user "user1" exists
    And group "sharing-group" exists
    And user "user1" belongs to group "sharing-group"
    And file "welcome.txt" of user "user0" is shared with group "sharing-group"
    And user "user1" accepts last share
    And As an "user0"
    Then creating a share with
      | path | welcome.txt |
      | shareWith | user1 |
      | shareType | 0 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"

  Scenario: Creating a new room share when Talk is not enabled
    Given As an "admin"
    And app "spreed" is not enabled
    And user "user0" exists
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareWith | a-room-token |
      | shareType | 10 |
    Then the OCS status code should be "403"
    And the HTTP status code should be "401"

  Scenario: Creating a new public share
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareType | 3 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" can be downloaded

  Scenario: Creating a new public share with password
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareType | 3 |
      | password | publicpw |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" with password "publicpw" can be downloaded

  Scenario: Creating a new public share of a folder
   Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | FOLDER |
      | shareType | 3 |
      | password | publicpw |
      | expireDate | +3 days |
      | publicUpload | true |
      | permissions | 7 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | permissions | 15 |
      | expiration | +3 days |
      | url | AN_URL |
      | token | A_TOKEN |
      | mimetype | httpd/unix-directory |

  Scenario: Creating a new public share with password and adding an expiration date
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | welcome.txt |
      | shareType | 3 |
      | password | publicpw |
    And Updating last share with
      | expireDate | +3 days |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Public shared file "welcome.txt" with password "publicpw" can be downloaded

  Scenario: Creating a new public share, updating its expiration date and getting its info
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | FOLDER |
      | shareType | 3 |
    And Updating last share with
      | expireDate | +3 days |
    And the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Getting info of last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_type | folder |
      | item_source | A_NUMBER |
      | share_type | 3 |
      | file_source | A_NUMBER |
      | file_target | /FOLDER |
      | permissions | 1 |
      | stime | A_NUMBER |
      | expiration | +3 days |
      | token | A_TOKEN |
      | storage | A_NUMBER |
      | mail_send | 0 |
      | uid_owner | user0 |
      | storage_id | home::user0 |
      | file_parent | A_NUMBER |
      | displayname_owner | user0 |
      | url | AN_URL |
      | mimetype | httpd/unix-directory |

  Scenario: Creating a new public share, updating its password and getting its info
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | FOLDER |
      | shareType | 3 |
    And Updating last share with
      | password | publicpw |
    And the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Getting info of last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_type | folder |
      | item_source | A_NUMBER |
      | share_type | 3 |
      | file_source | A_NUMBER |
      | file_target | /FOLDER |
      | permissions | 1 |
      | stime | A_NUMBER |
      | token | A_TOKEN |
      | storage | A_NUMBER |
      | mail_send | 0 |
      | uid_owner | user0 |
      | storage_id | home::user0 |
      | file_parent | A_NUMBER |
      | displayname_owner | user0 |
      | url | AN_URL |
      | mimetype | httpd/unix-directory |

  Scenario: Creating a new public share, updating its permissions and getting its info
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | FOLDER |
      | shareType | 3 |
    And Updating last share with
      | permissions | 7 |
    And the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Getting info of last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_type | folder |
      | item_source | A_NUMBER |
      | share_type | 3 |
      | file_source | A_NUMBER |
      | file_target | /FOLDER |
      | permissions | 15 |
      | stime | A_NUMBER |
      | token | A_TOKEN |
      | storage | A_NUMBER |
      | mail_send | 0 |
      | uid_owner | user0 |
      | storage_id | home::user0 |
      | file_parent | A_NUMBER |
      | displayname_owner | user0 |
      | url | AN_URL |
      | mimetype | httpd/unix-directory |

  Scenario: Creating a new public share, updating its permissions for "hide file list"
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | FOLDER |
      | shareType | 3 |
    And Updating last share with
      | permissions | 4 |
    And the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Getting info of last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_type | folder |
      | item_source | A_NUMBER |
      | share_type | 3 |
      | file_source | A_NUMBER |
      | file_target | /FOLDER |
      | permissions | 4 |
      | stime | A_NUMBER |
      | token | A_TOKEN |
      | storage | A_NUMBER |
      | mail_send | 0 |
      | uid_owner | user0 |
      | storage_id | home::user0 |
      | file_parent | A_NUMBER |
      | displayname_owner | user0 |
      | url | AN_URL |
      | mimetype | httpd/unix-directory |

  Scenario: Creating a new public share, updating publicUpload option and getting its info
    Given user "user0" exists
    And As an "user0"
    When creating a share with
      | path | FOLDER |
      | shareType | 3 |
    And Updating last share with
      | publicUpload | true |
    And the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Getting info of last share
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | item_type | folder |
      | item_source | A_NUMBER |
      | share_type | 3 |
      | file_source | A_NUMBER |
      | file_target | /FOLDER |
      | permissions | 15 |
      | stime | A_NUMBER |
      | token | A_TOKEN |
      | storage | A_NUMBER |
      | mail_send | 0 |
      | uid_owner | user0 |
      | storage_id | home::user0 |
      | file_parent | A_NUMBER |
      | displayname_owner | user0 |
      | url | AN_URL |
      | mimetype | httpd/unix-directory |

  Scenario: getting all shares of a user using that user
    Given user "user0" exists
    And user "user1" exists
    And file "textfile0.txt" of user "user0" is shared with user "user1"
    And As an "user0"
    When sending "GET" to "/apps/files_sharing/api/v1/shares"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And File "textfile0.txt" should be included in the response

  Scenario: getting all shares of a user using another user
    Given user "user0" exists
    And user "user1" exists
    And file "textfile0.txt" of user "user0" is shared with user "user1"
    And As an "admin"
    When sending "GET" to "/apps/files_sharing/api/v1/shares"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And File "textfile0.txt" should not be included in the response

  Scenario: getting all shares of a file
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user3" exists
    And file "textfile0.txt" of user "user0" is shared with user "user1"
    And file "textfile0.txt" of user "user0" is shared with user "user2"
    And As an "user0"
    When sending "GET" to "/apps/files_sharing/api/v1/shares?path=textfile0.txt"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And User "user1" should be included in the response
    And User "user2" should be included in the response
    And User "user3" should not be included in the response

  Scenario: getting all shares of a file with a user with resharing rights but not yourself
    Given user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And user "user3" exists
    And file "textfile0.txt" of user "user0" is shared with user "user1"
    And user "user1" accepts last share
    And file "textfile0.txt" of user "user0" is shared with user "user2"
    And As an "user1"
    When sending "GET" to "/apps/files_sharing/api/v1/shares?path=textfile0 (2).txt&reshares=true"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And User "user1" should not be included in the response
    And User "user2" should be included in the response
    And User "user3" should not be included in the response

  Scenario: getting inherited shares of a file
	Given user "user0" exists
	And user "user1" exists
	And user "user2" exists
	And user "user3" exists
    # will be shared with user1
	And User "user0" created a folder "/first"
	# will be shared with user1, user2
    And User "user0" created a folder "/first/second"
	# will be shared with user1, user3
	And User "user0" uploads file "data/textfile.txt" to "/first/test1.txt"
	# will be shared with user1, user2, user3
	And User "user0" uploads file "data/textfile.txt" to "/first/second/test2.txt"
	And As an "user0"
	And creating a share with
		  | path | /first |
		  | shareType | 0 |
		  | shareWith | user1 |
		  | permissions | 16 |
	And As an "user1"
	And accepting last share
	And folder "first/second" of user "user0" is shared with user "user2"
    And file "first/test1.txt" of user "user0" is shared with user "user3"
	And file "first/second/test2.txt" of user "user0" is shared with user "user3"
    # get inherited shares from the owner PoV
	And As an "user0"
	When sending "GET" to "/apps/files_sharing/api/v1/shares/inherited?path=first/second/test2.txt"
	Then the OCS status code should be "100"
	And the HTTP status code should be "200"
    And User "user0" should not be included in the response
    And User "user1" should be included in the response
    And User "user2" should be included in the response
    And User "user3" should not be included in the response
    When sending "GET" to "/apps/files_sharing/api/v1/shares/inherited?path=first/test1.txt"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And User "user0" should not be included in the response
    And User "user1" should be included in the response
    And User "user2" should not be included in the response
    And User "user3" should not be included in the response
    # get inherited shares from the a user with no shares rights
    And As an "user2"
    When sending "GET" to "/apps/files_sharing/api/v1/shares/inherited?path=first/test1.txt"
    Then the OCS status code should be "404"
    And the HTTP status code should be "200"
    # get inherited shares from the PoV of a user with resharing rights (user1)
    And As an "user1"
    When sending "GET" to "/apps/files_sharing/api/v1/shares/inherited?path=first/second/test2.txt"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And User "user0" should not be included in the response
    And User "user1" should not be included in the response
    And User "user2" should be included in the response
    And User "user3" should not be included in the response
    When sending "GET" to "/apps/files_sharing/api/v1/shares/inherited?path=first/test1.txt"
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And User "user0" should not be included in the response
    And User "user1" should not be included in the response
    And User "user2" should not be included in the response
    And User "user3" should not be included in the response

# See sharing-v1-part2.feature

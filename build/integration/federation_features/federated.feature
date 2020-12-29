Feature: federated
	Background:
		Given using api version "1"

	Scenario: Federate share a file with another server
		Given Using server "REMOTE"
		And user "user1" exists
		And Using server "LOCAL"
		And user "user0" exists
		When User "user0" from server "LOCAL" shares "/textfile0.txt" with user "user1" from server "REMOTE"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| item_type | file |
			| item_source | A_NUMBER |
			| share_type | 6 |
			| file_source | A_NUMBER |
			| path | /textfile0.txt |
			| permissions | 19 |
			| stime | A_NUMBER |
			| storage | A_NUMBER |
			| mail_send | 0 |
			| uid_owner | user0 |
			| storage_id | home::user0 |
			| file_parent | A_NUMBER |
			| displayname_owner | user0 |
			| share_with | user1@REMOTE |
			| share_with_displayname | user1@REMOTE |

	Scenario: Federated group share a file with another server
		Given Using server "REMOTE"
		And parameter "incoming_server2server_group_share_enabled" of app "files_sharing" is set to "yes"
		And user "gs-user1" exists
		And user "gs-user2" exists
		And group "group1" exists
		And As an "admin"
		And Add user "gs-user1" to the group "group1"
		And Add user "gs-user2" to the group "group1"
		And Using server "LOCAL"
		And parameter "outgoing_server2server_group_share_enabled" of app "files_sharing" is set to "yes"
		And user "gs-user0" exists
		When User "gs-user0" from server "LOCAL" shares "/textfile0.txt" with group "group1" from server "REMOTE"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| item_type | file |
			| item_source | A_NUMBER |
			| share_type | 9 |
			| file_source | A_NUMBER |
			| path | /textfile0.txt |
			| permissions | 19 |
			| stime | A_NUMBER |
			| storage | A_NUMBER |
			| mail_send | 0 |
			| uid_owner | gs-user0 |
			| storage_id | home::gs-user0 |
			| file_parent | A_NUMBER |
			| displayname_owner | gs-user0 |
			| share_with | group1@REMOTE |
			| share_with_displayname | group1@REMOTE |


	Scenario: Federate share a file with local server
		Given Using server "LOCAL"
		And user "user0" exists
		And Using server "REMOTE"
		And user "user1" exists
		When User "user1" from server "REMOTE" shares "/textfile0.txt" with user "user0" from server "LOCAL"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| item_type | file |
			| item_source | A_NUMBER |
			| share_type | 6 |
			| file_source | A_NUMBER |
			| path | /textfile0.txt |
			| permissions | 19 |
			| stime | A_NUMBER |
			| storage | A_NUMBER |
			| mail_send | 0 |
			| uid_owner | user1 |
			| storage_id | home::user1 |
			| file_parent | A_NUMBER |
			| displayname_owner | user1 |
			| share_with | user0@LOCAL |
			| share_with_displayname | user0@LOCAL |

	Scenario: Remote sharee can see the pending share
		Given Using server "REMOTE"
		And user "user1" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/textfile0.txt" with user "user1" from server "REMOTE"
		And Using server "REMOTE"
		And As an "user1"
		When sending "GET" to "/apps/files_sharing/api/v1/remote_shares/pending"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| remote | LOCAL |
			| remote_id | A_NUMBER |
			| share_token | A_TOKEN |
			| name | /textfile0.txt |
			| owner | user0 |
			| user | user1 |
			| mountpoint | {{TemporaryMountPointName#/textfile0.txt}} |
			| accepted | 0 |

	Scenario: Remote sharee can see the pending group share
		Given Using server "REMOTE"
		And parameter "incoming_server2server_group_share_enabled" of app "files_sharing" is set to "yes"
		And user "gs-user1" exists
		And user "gs-user2" exists
		And group "group1" exists
		And As an "admin"
		And Add user "gs-user1" to the group "group1"
		And Add user "gs-user2" to the group "group1"
		And Using server "LOCAL"
		And parameter "outgoing_server2server_group_share_enabled" of app "files_sharing" is set to "yes"
		And user "gs-user0" exists
		When User "gs-user0" from server "LOCAL" shares "/textfile0.txt" with group "group1" from server "REMOTE"
		And Using server "REMOTE"
		And As an "gs-user1"
		When sending "GET" to "/apps/files_sharing/api/v1/remote_shares/pending"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| remote | LOCAL |
			| remote_id | A_NUMBER |
			| share_token | A_TOKEN |
			| name | /textfile0.txt |
			| owner | gs-user0 |
			| user | group1 |
			| mountpoint | {{TemporaryMountPointName#/textfile0.txt}} |
			| accepted | 0 |
		And As an "gs-user2"
		When sending "GET" to "/apps/files_sharing/api/v1/remote_shares/pending"
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| remote | LOCAL |
			| remote_id | A_NUMBER |
			| share_token | A_TOKEN |
			| name | /textfile0.txt |
			| owner | gs-user0 |
			| user | group1 |
			| mountpoint | {{TemporaryMountPointName#/textfile0.txt}} |
			| accepted | 0 |

	Scenario: accept a pending remote share
		Given Using server "REMOTE"
		And user "user1" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/textfile0.txt" with user "user1" from server "REMOTE"
		When User "user1" from server "REMOTE" accepts last pending share
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"

	Scenario: accept a pending remote group share
		Given Using server "REMOTE"
		And parameter "incoming_server2server_group_share_enabled" of app "files_sharing" is set to "yes"
		And user "gs-user1" exists
		And user "gs-user2" exists
		And group "group1" exists
		And As an "admin"
		And Add user "gs-user1" to the group "group1"
		And Add user "gs-user2" to the group "group1"
		And Using server "LOCAL"
		And parameter "outgoing_server2server_group_share_enabled" of app "files_sharing" is set to "yes"
		And user "gs-user0" exists
		When User "gs-user0" from server "LOCAL" shares "/textfile0.txt" with group "group1" from server "REMOTE"
		When User "gs-user1" from server "REMOTE" accepts last pending share
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"

	Scenario: Reshare a federated shared file
		Given Using server "REMOTE"
		And user "user1" exists
		And user "user2" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/textfile0.txt" with user "user1" from server "REMOTE"
		And User "user1" from server "REMOTE" accepts last pending share
		And Using server "REMOTE"
		And As an "user1"
    # FIXME this step causes issues in case there is already another incoming share with the (2) suffix
		When creating a share with
			| path | /textfile0 (2).txt |
			| shareType | 0 |
			| shareWith | user2 |
			| permissions | 19 |
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And Share fields of last share match with
			| id | A_NUMBER |
			| item_type | file |
			| item_source | A_NUMBER |
			| share_type | 0 |
			| file_source | A_NUMBER |
			| path | /textfile0 (2).txt |
			| permissions | 19 |
			| stime | A_NUMBER |
			| storage | A_NUMBER |
			| mail_send | 1 |
			| uid_owner | user1 |
			| file_parent | A_NUMBER |
			| displayname_owner | user1 |
			| share_with | user2 |
			| share_with_displayname | user2 |

  Scenario: Share a file to a cloud id on the same instance
    And Using server "LOCAL"
    And user "user0" exists
    And user "user1" exists
    And User "user0" uploads file "data/textfile.txt" to "/sharelocal.txt"
    And User "user0" from server "LOCAL" shares "/sharelocal.txt" with user "user1" from server "LOCAL"
    And User "user1" from server "LOCAL" accepts last pending share
    And Using server "LOCAL"
    And As an "user1"
    When Downloading file "/sharelocal.txt" with range "bytes=0-18"
    Then Downloaded content should be "This is a testfile."

  Scenario: Reshare a federated shared on the same instance to another cloud id on the same instance
    And Using server "LOCAL"
    And user "user0" exists
    And user "user1" exists
    And user "user2" exists
    And User "user0" uploads file "data/textfile.txt" to "/reshare.txt"
    And User "user0" from server "LOCAL" shares "/reshare.txt" with user "user1" from server "LOCAL"
    And User "user1" from server "LOCAL" accepts last pending share
    And User "user1" from server "LOCAL" shares "/reshare.txt" with user "user2" from server "LOCAL"
    And User "user2" from server "LOCAL" accepts last pending share
    And Using server "LOCAL"
    And As an "user2"
    When Downloading file "/reshare.txt" with range "bytes=0-18"
    Then Downloaded content should be "This is a testfile."

  Scenario: Reshare a federated shared file to a federated user back to the original instance
    Given Using server "REMOTE"
    And user "user1" exists
    And Using server "LOCAL"
    And user "user0" exists
    And user "user2" exists
    And User "user0" uploads file "data/textfile.txt" to "/reshare1.txt"
    And User "user0" from server "LOCAL" shares "/reshare1.txt" with user "user1" from server "REMOTE"
    And User "user1" from server "REMOTE" accepts last pending share
    And User "user1" from server "REMOTE" shares "/reshare1.txt" with user "user2" from server "LOCAL"
    And User "user2" from server "LOCAL" accepts last pending share
    And Using server "LOCAL"
    And As an "user2"
    When Downloading file "/reshare1.txt" with range "bytes=0-18"
    Then Downloaded content should be "This is a testfile."

  Scenario: Reshare a federated shared file to a federated user on the same instance
    Given Using server "REMOTE"
    And user "user1" exists
    And user "user2" exists
    And Using server "LOCAL"
    And user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/reshare2.txt"
    And User "user0" from server "LOCAL" shares "/reshare2.txt" with user "user1" from server "REMOTE"
    And User "user1" from server "REMOTE" accepts last pending share
    And User "user1" from server "REMOTE" shares "/reshare2.txt" with user "user2" from server "REMOTE"
    And User "user2" from server "REMOTE" accepts last pending share
    And Using server "REMOTE"
    And As an "user2"
    When Downloading file "/reshare2.txt" with range "bytes=0-18"
    Then Downloaded content should be "This is a testfile."


	Scenario: Overwrite a federated shared file as recipient
		Given Using server "REMOTE"
		And user "user1" exists
		And user "user2" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/textfile0.txt" with user "user1" from server "REMOTE"
		And User "user1" from server "REMOTE" accepts last pending share
		And Using server "REMOTE"
		And As an "user1"
		And User "user1" modifies text of "/textfile0.txt" with text "BLABLABLA"
		When User "user1" uploads file "../../data/user1/files/textfile0.txt" to "/textfile0 (2).txt"
		And Downloading file "/textfile0 (2).txt" with range "bytes=0-8"
		Then Downloaded content should be "BLABLABLA"

	Scenario: Overwrite a federated shared folder as recipient
		Given Using server "REMOTE"
		And user "user1" exists
		And user "user2" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/PARENT" with user "user1" from server "REMOTE"
		And User "user1" from server "REMOTE" accepts last pending share
		And Using server "REMOTE"
		And As an "user1"
		And User "user1" modifies text of "/textfile0.txt" with text "BLABLABLA"
		When User "user1" uploads file "../../data/user1/files/textfile0.txt" to "/PARENT (2)/textfile0.txt"
		And Downloading file "/PARENT (2)/textfile0.txt" with range "bytes=0-8"
		Then Downloaded content should be "BLABLABLA"

	Scenario: Overwrite a federated shared file as recipient using old chunking
		Given Using server "REMOTE"
		And user "user1" exists
		And user "user2" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/textfile0.txt" with user "user1" from server "REMOTE"
		And User "user1" from server "REMOTE" accepts last pending share
		And Using server "REMOTE"
		And As an "user1"
		And user "user1" uploads chunk file "1" of "3" with "AAAAA" to "/textfile0 (2).txt"
		And user "user1" uploads chunk file "2" of "3" with "BBBBB" to "/textfile0 (2).txt"
		And user "user1" uploads chunk file "3" of "3" with "CCCCC" to "/textfile0 (2).txt"
		When Downloading file "/textfile0 (2).txt" with range "bytes=0-4"
		Then Downloaded content should be "AAAAA"

	Scenario: Overwrite a federated shared folder as recipient using old chunking
		Given Using server "REMOTE"
		And user "user1" exists
		And user "user2" exists
		And Using server "LOCAL"
		And user "user0" exists
		And User "user0" from server "LOCAL" shares "/PARENT" with user "user1" from server "REMOTE"
		And User "user1" from server "REMOTE" accepts last pending share
		And Using server "REMOTE"
		And As an "user1"
		And user "user1" uploads chunk file "1" of "3" with "AAAAA" to "/PARENT (2)/textfile0.txt"
		And user "user1" uploads chunk file "2" of "3" with "BBBBB" to "/PARENT (2)/textfile0.txt"
		And user "user1" uploads chunk file "3" of "3" with "CCCCC" to "/PARENT (2)/textfile0.txt"
		When Downloading file "/PARENT (2)/textfile0.txt" with range "bytes=3-13"
		Then Downloaded content should be "AABBBBBCCCC"














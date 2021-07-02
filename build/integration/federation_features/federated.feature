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
		When creating a share with
			| path | /textfile0 (2).txt |
			| shareType | 0 |
			| shareWith | user2 |
			| permissions | 19 |
		#Then the OCS status code should be "100"
		#And the HTTP status code should be "200"
		#And Share fields of last share match with
		#	| id | A_NUMBER |
		#	| item_type | file |
		#	| item_source | A_NUMBER |
		#	| share_type | 0 |
		#	| file_source | A_NUMBER |
		#	| path | /textfile0 (2).txt |
		#	| permissions | 19 |
		#	| stime | A_NUMBER |
		#	| storage | A_NUMBER |
		#	| mail_send | 1 |
		#	| uid_owner | user1 |
		#	| file_parent | A_NUMBER |
		#	| displayname_owner | user1 |
		#	| share_with | user2 |
		#	| share_with_displayname | user2 |

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
		#When User "user1" uploads file "../../data/user1/files/textfile0.txt" to "/PARENT (2)/textfile0.txt"
		#And Downloading file "/PARENT (2)/textfile0.txt" with range "bytes=0-8"
		#Then Downloaded content should be "BLABLABLA"

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
		#And user "user1" uploads chunk file "1" of "3" with "AAAAA" to "/textfile0 (2).txt"
		#And user "user1" uploads chunk file "2" of "3" with "BBBBB" to "/textfile0 (2).txt"
		#And user "user1" uploads chunk file "3" of "3" with "CCCCC" to "/textfile0 (2).txt"
		#When Downloading file "/textfile0 (2).txt" with range "bytes=0-4"
		#Then Downloaded content should be "AAAAA"

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
		#And user "user1" uploads chunk file "1" of "3" with "AAAAA" to "/PARENT (2)/textfile0.txt"
		#And user "user1" uploads chunk file "2" of "3" with "BBBBB" to "/PARENT (2)/textfile0.txt"
		#And user "user1" uploads chunk file "3" of "3" with "CCCCC" to "/PARENT (2)/textfile0.txt"
		#When Downloading file "/PARENT (2)/textfile0.txt" with range "bytes=3-13"
		#Then Downloaded content should be "AABBBBBCCCC"



	Scenario: List federated share from another server not accepted yet
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And Using server "LOCAL"
		When As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		Then the list of returned shares has 0 shares

	Scenario: List federated share from another server
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		When As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		Then the list of returned shares has 1 shares
		And remote share 0 is returned with
			| remote      | http://localhost:8180/ |
			| name        | /remote-share.txt |
			| owner       | user1 |
			| user        | user0 |
			| mountpoint  | /remote-share.txt |
			| mimetype    | text/plain |
			| mtime        | A_NUMBER |
			| permissions | 27 |
			| type        | file |
			| file_id     | A_NUMBER |

	Scenario: List federated share from another server no longer reachable
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		And remote server is stopped
		When As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		Then the list of returned shares has 1 shares
		And remote share 0 is returned with
			| remote      | http://localhost:8180/ |
			| name        | /remote-share.txt |
			| owner       | user1 |
			| user        | user0 |
			| mountpoint  | /remote-share.txt |

	Scenario: List federated share from another server no longer reachable after caching the file entry
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		# Checking that the file exists caches the file entry, which causes an
		# exception to be thrown when getting the file info if the remote server is
		# unreachable.
		And as "user0" the file "/remote-share.txt" exists
		And remote server is stopped
		When As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		Then the list of returned shares has 1 shares
		And remote share 0 is returned with
			| remote      | http://localhost:8180/ |
			| name        | /remote-share.txt |
			| owner       | user1 |
			| user        | user0 |
			| mountpoint  | /remote-share.txt |



	Scenario: Delete federated share with another server
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And As an "user1"
		And sending "GET" to "/apps/files_sharing/api/v1/shares"
		And the list of returned shares has 1 shares
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		And as "user0" the file "/remote-share.txt" exists
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 1 shares
		And Using server "REMOTE"
		When As an "user1"
		And Deleting last share
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And As an "user1"
		And sending "GET" to "/apps/files_sharing/api/v1/shares"
		And the list of returned shares has 0 shares
		And Using server "LOCAL"
		And as "user0" the file "/remote-share.txt" does not exist
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 0 shares

	Scenario: Delete federated share from another server
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And As an "user1"
		And sending "GET" to "/apps/files_sharing/api/v1/shares"
		And the list of returned shares has 1 shares
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		And as "user0" the file "/remote-share.txt" exists
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 1 shares
		When user "user0" deletes last accepted remote share
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And as "user0" the file "/remote-share.txt" does not exist
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 0 shares
		And Using server "REMOTE"
		And As an "user1"
		And sending "GET" to "/apps/files_sharing/api/v1/shares"
		And the list of returned shares has 0 shares

	Scenario: Delete federated share from another server no longer reachable
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		And as "user0" the file "/remote-share.txt" exists
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 1 shares
		And remote server is stopped
		When user "user0" deletes last accepted remote share
		Then the OCS status code should be "100"
		And the HTTP status code should be "200"
		And as "user0" the file "/remote-share.txt" does not exist
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 0 shares

	Scenario: Delete federated share file from another server
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And As an "user1"
		And sending "GET" to "/apps/files_sharing/api/v1/shares"
		And the list of returned shares has 1 shares
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		And as "user0" the file "/remote-share.txt" exists
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 1 shares
		When User "user0" deletes file "/remote-share.txt"
		Then the HTTP status code should be "204"
		And as "user0" the file "/remote-share.txt" does not exist
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 0 shares
		And Using server "REMOTE"
		And As an "user1"
		And sending "GET" to "/apps/files_sharing/api/v1/shares"
		And the list of returned shares has 0 shares

	Scenario: Delete federated share file from another server no longer reachable
		Given Using server "LOCAL"
		And user "user0" exists
		Given Using server "REMOTE"
		And user "user1" exists
		# Rename file so it has a unique name in the target server (as the target
		# server may have its own /textfile0.txt" file)
		And User "user1" copies file "/textfile0.txt" to "/remote-share.txt"
		And User "user1" from server "REMOTE" shares "/remote-share.txt" with user "user0" from server "LOCAL"
		And Using server "LOCAL"
		And User "user0" from server "LOCAL" accepts last pending share
		And as "user0" the file "/remote-share.txt" exists
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 1 shares
		And remote server is stopped
		When User "user0" deletes file "/remote-share.txt"
		Then the HTTP status code should be "204"
		And as "user0" the file "/remote-share.txt" does not exist
		And As an "user0"
		And sending "GET" to "/apps/files_sharing/api/v1/remote_shares"
		And the list of returned shares has 0 shares

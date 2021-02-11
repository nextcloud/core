Feature: transfer-ownership

	Scenario: transferring ownership of a file
		Given user "user0" exists
		And user "user1" exists
		And User "user0" uploads file "data/textfile.txt" to "/somefile.txt"
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the file "/somefile.txt" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the file "/somefile.txt" exists

	Scenario: transferring ownership of a folder
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists

	Scenario: transferring ownership from user with risky display name
		Given user "user0" with displayname "user0 \"risky\"? ヂspḷay 'na|\/|e':.#" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And transfer folder name contains "transferred from user0 -risky- ヂspḷay -na|-|e- on"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists

	Scenario: transferring ownership of file shares
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user0" uploads file "data/textfile.txt" to "/somefile.txt"
		And file "/somefile.txt" of user "user0" is shared with user "user2" with permissions 19
		And user "user2" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the file "/somefile.txt" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the file "/somefile.txt" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user1 |
			| share_with | user2 |

	Scenario: transferring ownership of folder shared with third user
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user1 |
			| share_with | user2 |

	Scenario: transferring ownership of folder shared with transfer recipient
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user0" is shared with user "user1" with permissions 31
		And user "user1" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		Then as "user1" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And Getting info of last share
		And the OCS status code should be "404"

	Scenario: transferring ownership of folder doubly shared with third user
		Given group "group1" exists
		And user "user0" exists
		And user "user1" exists
		And user "user2" exists
    	And user "user2" belongs to group "group1"
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user0" is shared with group "group1" with permissions 31
		And user "user2" accepts last share
		And folder "/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user1 |
			| share_with | user2 |

	Scenario: transferring ownership of file shares to user with the same id as the group
		Given user "user0" exists
		And user "test" exists
		And user "user2" exists
		And group "test" exists
		And user "user2" belongs to group "test"
		And User "user0" uploads file "data/textfile.txt" to "/somefile.txt"
		And file "/somefile.txt" of user "user0" is shared with group "test"
		And user "user2" accepts last share
		When transferring ownership from "user0" to "test"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the file "/somefile.txt" does not exist
		And using received transfer folder of "user1" as dav path
		And as "test" the file "/somefile.txt" exists
		And As an "test"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | test |
			| uid_file_owner | test |
			| share_with | test |

	Scenario: transferring ownership of folder reshared with another user
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And user "user3" exists
		And User "user3" created a folder "/test"
		And User "user3" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user3" is shared with user "user0" with permissions 31
		And user "user0" accepts last share
		And folder "/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" exists
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" does not exist
		And As an "user0"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user0 |
			| uid_file_owner | user3 |
			| share_with | user2 |

	Scenario: transferring ownership of folder reshared with group to a user in the group
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And user "user3" exists
		And group "group1" exists
		And user "user1" belongs to group "group1"
		And User "user3" created a folder "/test"
		And User "user3" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user3" is shared with user "user0" with permissions 31
		And user "user0" accepts last share
		And folder "/test" of user "user0" is shared with group "group1" with permissions 31
		And user "user1" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" exists
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" does not exist
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user3 |
			| share_with | group1 |

	Scenario: transferring ownership of folder reshared with group to a user not in the group
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And user "user3" exists
		And group "group1" exists
		And user "user2" belongs to group "group1"
		And User "user3" created a folder "/test"
		And User "user3" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user3" is shared with user "user0" with permissions 31
		And user "user0" accepts last share
		And folder "/test" of user "user0" is shared with group "group1" with permissions 31
		And user "user2" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" exists
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" does not exist
		And As an "user0"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user0 |
			| uid_file_owner | user3 |
			| share_with | group1 |

	Scenario: transferring ownership does not transfer received shares
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user2" created a folder "/test"
		And folder "/test" of user "user2" is shared with user "user0" with permissions 31
		And user "user0" accepts last share
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then as "user1" the folder "/test" does not exist
		And using old dav path
		And as "user0" the folder "/test" exists
		And As an "user2"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user2 |
			| uid_file_owner | user2 |
			| share_with | user0 |

	@local_storage
	Scenario: transferring ownership does not transfer external storage
		Given user "user0" exists
		And user "user1" exists
		When transferring ownership from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then as "user1" the folder "/local_storage" does not exist

	Scenario: transferring ownership does not fail with shared trashed files
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user0" created a folder "/sub"
		And User "user0" created a folder "/sub/test"
		And folder "/sub/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		And User "user0" deletes folder "/sub"
		When transferring ownership from "user0" to "user1"
		Then the command was successful

	Scenario: transferring ownership fails with invalid source user
		Given user "user0" exists
		When transferring ownership from "invalid_user" to "user0"
		Then the command output contains the text "Unknown source user"
		And the command failed with exit code 1

	Scenario: transferring ownership fails with invalid target user
		Given user "user0" exists
		When transferring ownership from "user0" to "invalid_user"
		Then the command output contains the text "Unknown destination user invalid_user"
		And the command failed with exit code 1

	Scenario: transferring ownership of a file
		Given user "user0" exists
		And user "user1" exists
		And User "user0" uploads file "data/textfile.txt" to "/somefile.txt"
		When transferring ownership of path "somefile.txt" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the file "/somefile.txt" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the file "/somefile.txt" exists

	Scenario: transferring ownership of a folder
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists

	Scenario: transferring ownership from user with risky display name
		Given user "user0" with displayname "user0 \"risky\"? ヂspḷay 'na|\/|e':.#" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And transfer folder name contains "transferred from user0 -risky- ヂspḷay -na|-|e- on"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists

	Scenario: transferring ownership of path does not affect other files
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And User "user0" created a folder "/test2"
		And User "user0" uploads file "data/textfile.txt" to "/test2/somefile.txt"
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And as "user0" the folder "/test2" exists
		And as "user0" the file "/test2/somefile.txt" exists
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And as "user1" the folder "/test2" does not exist

	Scenario: transferring ownership of path does not affect other shares
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And User "user0" created a folder "/test2"
		And User "user0" uploads file "data/textfile.txt" to "/test2/sharedfile.txt"
		And file "/test2/sharedfile.txt" of user "user0" is shared with user "user1" with permissions 19
		And user "user1" accepts last share
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And as "user0" the folder "/test2" exists
		And as "user0" the file "/test2/sharedfile.txt" exists
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And as "user1" the folder "/test2" does not exist
		And using old dav path
		And as "user1" the file "/sharedfile.txt" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user0 |
			| uid_file_owner | user0 |
			| share_with | user1 |

	Scenario: transferring ownership of file shares
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And file "/test/somefile.txt" of user "user0" is shared with user "user2" with permissions 19
		And user "user2" accepts last share
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user1 |
			| share_with | user2 |

	Scenario: transferring ownership of folder shared with third user
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user1 |
			| share_with | user2 |

	Scenario: transferring ownership of folder shared with transfer recipient
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user0" is shared with user "user1" with permissions 31
		And user "user1" accepts last share
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		Then as "user1" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And Getting info of last share
		And the OCS status code should be "404"

	Scenario: transferring ownership of folder doubly shared with third user
		Given group "group1" exists
		And user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And user "user2" belongs to group "group1"
		And User "user0" created a folder "/test"
		And User "user0" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user0" is shared with group "group1" with permissions 31
		And user "user2" accepts last share
		And folder "/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		When transferring ownership of path "test" from "user0" to "user1"
		And the command was successful
		And As an "user2"
		Then Downloaded content when downloading file "/test/somefile.txt" with range "bytes=0-6" should be "This is"
		And using old dav path
		And as "user0" the folder "/test" does not exist
		And using received transfer folder of "user1" as dav path
		And as "user1" the folder "/test" exists
		And As an "user1"
		And Getting info of last share
		And the OCS status code should be "100"
		And Share fields of last share match with
			| uid_owner | user1 |
			| uid_file_owner | user1 |
			| share_with | user2 |

	Scenario: transferring ownership of path fails for reshares
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And user "user3" exists
		And User "user3" created a folder "/test"
		And User "user3" uploads file "data/textfile.txt" to "/test/somefile.txt"
		And folder "/test" of user "user3" is shared with user "user0" with permissions 31
		And user "user0" accepts last share
		And folder "/test" of user "user0" is shared with user "user2" with permissions 31
		And user "user2" accepts last share
		When transferring ownership of path "test" from "user0" to "user1"
		Then the command failed with exit code 1
		And the command output contains the text "Could not transfer files."

	Scenario: transferring ownership does not transfer received shares
		Given user "user0" exists
		And user "user1" exists
		And user "user2" exists
		And User "user2" created a folder "/test"
		And User "user0" created a folder "/sub"
		And folder "/test" of user "user2" is shared with user "user0" with permissions 31
		And user "user0" accepts last share
		And User "user0" moved folder "/test" to "/sub/test"
		When transferring ownership of path "sub" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then as "user1" the folder "/sub" exists
		And as "user1" the folder "/sub/test" does not exist
		And using old dav path
		And as "user0" the folder "/sub" does not exist
		And Getting info of last share
		And the OCS status code should be "404"

	Scenario: transferring ownership does not transfer external storage
		Given user "user0" exists
		And user "user1" exists
		And User "user0" created a folder "/sub"
		When transferring ownership of path "sub" from "user0" to "user1"
		And the command was successful
		And As an "user1"
		And using received transfer folder of "user1" as dav path
		Then as "user1" the folder "/local_storage" does not exist

	Scenario: transferring ownership fails with invalid source user
		Given user "user0" exists
		And User "user0" created a folder "/sub"
		When transferring ownership of path "sub" from "invalid_user" to "user0"
		Then the command output contains the text "Unknown source user"
		And the command failed with exit code 1

	Scenario: transferring ownership fails with invalid target user
		Given user "user0" exists
		And User "user0" created a folder "/sub"
		When transferring ownership of path "sub" from "user0" to "invalid_user"
		Then the command output contains the text "Unknown destination user invalid_user"
		And the command failed with exit code 1

	Scenario: transferring ownership fails with invalid path
		Given user "user0" exists
		And user "user1" exists
		When transferring ownership of path "test" from "user0" to "user1"
		Then the command output contains the text "Unknown path provided: test"
		And the command failed with exit code 1

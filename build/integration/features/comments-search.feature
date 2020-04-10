Feature: comments-search

  Scenario: Search my own comment on a file belonging to myself
    Given user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And "user0" posts a comment with content "My first comment" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "first" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | My first comment |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | My first comment |

  Scenario: Search my own comment on a file shared by someone with me
    Given user "user0" exists
    And user "user1" exists
    And User "user1" uploads file "data/textfile.txt" to "/sharedFileToComment.txt"
    And as "user1" creating a share with
      | path | sharedFileToComment.txt |
      | shareWith | user0 |
      | shareType | 0 |
    And user "user0" accepts last share
    And "user0" posts a comment with content "My first comment" on the file named "/sharedFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "first" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | My first comment |
      | authorId | user0 |
      | authorName | user0 |
      | path | sharedFileToComment.txt |
      | fileName | sharedFileToComment.txt |
      | name | My first comment |

  Scenario: Search other user's comment on a file shared by me
    Given user "user0" exists
    And user "user1" exists
    And User "user0" uploads file "data/textfile.txt" to "/mySharedFileToComment.txt"
    And as "user0" creating a share with
      | path | mySharedFileToComment.txt |
      | shareWith | user1 |
      | shareType | 0 |
    And user "user1" accepts last share
    And "user1" posts a comment with content "Other's first comment" on the file named "/mySharedFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "first" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | Other's first comment |
      | authorId | user1 |
      | authorName | user1 |
      | path | mySharedFileToComment.txt |
      | fileName | mySharedFileToComment.txt |
      | name | Other's first comment |

  Scenario: Search other user's comment on a file shared by someone with me
    Given user "user0" exists
    And user "user1" exists
    And User "user1" uploads file "data/textfile.txt" to "/sharedFileToComment.txt"
    And as "user1" creating a share with
      | path | sharedFileToComment.txt |
      | shareWith | user0 |
      | shareType | 0 |
    And user "user0" accepts last share
    And "user1" posts a comment with content "Other's first comment" on the file named "/sharedFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "first" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | Other's first comment |
      | authorId | user1 |
      | authorName | user1 |
      | path | sharedFileToComment.txt |
      | fileName | sharedFileToComment.txt |
      | name | Other's first comment |

  Scenario: Search several comments on a file belonging to myself
    Given user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And "user0" posts a comment with content "My first comment to be found" on the file named "/myFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "The second comment should not be found" on the file named "/myFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "My third comment to be found" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "comment to be found" in app "files"
    Then the list of search results has "2" results
    And search result "0" contains
      | type | comment |
      | comment | My third comment to be found |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | My third comment to be found |
    And search result "1" contains
      | type | comment |
      | comment | My first comment to be found |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | My first comment to be found |

  Scenario: Search comment with a large message ellipsized on the right
    Given user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And "user0" posts a comment with content "A very verbose message that is meant to be used to test the ellipsized message returned when searching for long comments" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "verbose" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | A very verbose message that is meant to… |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | A very verbose message that is meant to be used to test the ellipsized message returned when searching for long comments |

  Scenario: Search comment with a large message ellipsized on the left
    Given user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And "user0" posts a comment with content "A very verbose message that is meant to be used to test the ellipsized message returned when searching for long comments" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "searching" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | …ed message returned when searching for long comments |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | A very verbose message that is meant to be used to test the ellipsized message returned when searching for long comments |

  Scenario: Search comment with a large message ellipsized on both ends
    Given user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And "user0" posts a comment with content "A very verbose message that is meant to be used to test the ellipsized message returned when searching for long comments" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "ellipsized" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | …t to be used to test the ellipsized message returned when se… |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | A very verbose message that is meant to be used to test the ellipsized message returned when searching for long comments |

  Scenario: Search comment on a file in a subfolder
    Given user "user0" exists
    And user "user0" created a folder "/subfolder"
    And User "user0" uploads file "data/textfile.txt" to "/subfolder/myFileToComment.txt"
    And "user0" posts a comment with content "My first comment" on the file named "/subfolder/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "first" in app "files"
    Then the list of search results has "1" results
    And search result "0" contains
      | type | comment |
      | comment | My first comment |
      | authorId | user0 |
      | authorName | user0 |
      | path | subfolder/myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | My first comment |

  Scenario: Search several comments
    Given user "user0" exists
    And user "user1" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And User "user0" uploads file "data/textfile.txt" to "/mySharedFileToComment.txt"
    And as "user0" creating a share with
      | path | mySharedFileToComment.txt |
      | shareWith | user1 |
      | shareType | 0 |
    And user "user1" accepts last share
    And User "user1" uploads file "data/textfile.txt" to "/sharedFileToComment.txt"
    And as "user1" creating a share with
      | path | sharedFileToComment.txt |
      | shareWith | user0 |
      | shareType | 0 |
    And user "user0" accepts last share
    And "user0" posts a comment with content "My first comment to be found" on the file named "/myFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "The second comment should not be found" on the file named "/myFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "My first comment to be found" on the file named "/mySharedFileToComment.txt" it should return "201"
    And "user1" posts a comment with content "Other's first comment that should not be found" on the file named "/mySharedFileToComment.txt" it should return "201"
    And "user1" posts a comment with content "Other's second comment to be found" on the file named "/mySharedFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "My first comment that should not be found" on the file named "/sharedFileToComment.txt" it should return "201"
    And "user1" posts a comment with content "Other's first comment to be found" on the file named "/sharedFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "My second comment to be found that happens to be more verbose than the others and thus should be ellipsized" on the file named "/sharedFileToComment.txt" it should return "201"
    And "user0" posts a comment with content "My third comment to be found" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "comment to be found" in app "files"
    Then the list of search results has "6" results
    And search result "0" contains
      | type | comment |
      | comment | My third comment to be found |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | My third comment to be found |
    And search result "1" contains
      | type | comment |
      | comment | My second comment to be found that happens to be more … |
      | authorId | user0 |
      | authorName | user0 |
      | path | sharedFileToComment.txt |
      | fileName | sharedFileToComment.txt |
      | name | My second comment to be found that happens to be more verbose than the others and thus should be ellipsized |
    And search result "2" contains
      | type | comment |
      | comment | Other's first comment to be found |
      | authorId | user1 |
      | authorName | user1 |
      | path | sharedFileToComment.txt |
      | fileName | sharedFileToComment.txt |
      | name | Other's first comment to be found |
    And search result "3" contains
      | type | comment |
      | comment | Other's second comment to be found |
      | authorId | user1 |
      | authorName | user1 |
      | path | mySharedFileToComment.txt |
      | fileName | mySharedFileToComment.txt |
      | name | Other's second comment to be found |
    And search result "4" contains
      | type | comment |
      | comment | My first comment to be found |
      | authorId | user0 |
      | authorName | user0 |
      | path | mySharedFileToComment.txt |
      | fileName | mySharedFileToComment.txt |
      | name | My first comment to be found |
    And search result "5" contains
      | type | comment |
      | comment | My first comment to be found |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | My first comment to be found |

  Scenario: Search comment with a query that also matches a file name
    Given user "user0" exists
    And User "user0" uploads file "data/textfile.txt" to "/myFileToComment.txt"
    And "user0" posts a comment with content "A comment in myFileToComment.txt" on the file named "/myFileToComment.txt" it should return "201"
    When Logging in using web as "user0"
    And searching for "myFileToComment" in app "files"
    Then the list of search results has "2" results
    And search result "0" contains
      | type | file |
      | path | /myFileToComment.txt |
      | name | myFileToComment.txt |
    And search result "1" contains
      | type | comment |
      | comment | A comment in myFileToComment.txt |
      | authorId | user0 |
      | authorName | user0 |
      | path | myFileToComment.txt |
      | fileName | myFileToComment.txt |
      | name | A comment in myFileToComment.txt |

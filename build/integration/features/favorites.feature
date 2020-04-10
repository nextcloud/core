Feature: favorite
    Background:
        Given using api version "1"

    Scenario: Favorite a folder
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a folder
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        And user "user0" unfavorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "0"

    Scenario: Favorite a file
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a file
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        And user "user0" unfavorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "0"

    Scenario: Favorite a folder new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a folder new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        And user "user0" unfavorites element "/FOLDER"
        Then as "user0" gets properties of folder "/FOLDER" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "0"

    Scenario: Favorite a file new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "1"

    Scenario: Favorite and unfavorite a file new endpoint
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/textfile0.txt"
        And user "user0" unfavorites element "/textfile0.txt"
        Then as "user0" gets properties of file "/textfile0.txt" with
            |{http://owncloud.org/ns}favorite|
        And the single response should contain a property "{http://owncloud.org/ns}favorite" with value "0"

    Scenario: Get favorited elements of a folder
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        And user "user0" favorites element "/textfile0.txt"
        And user "user0" favorites element "/textfile1.txt"
        Then user "user0" in folder "/" should have favorited the following elements
            | /FOLDER |
            | /textfile0.txt |
            | /textfile1.txt |

    Scenario: Get favorited elements of a folder using new path
        Given using new dav path
        And As an "admin"
        And user "user0" exists
        When user "user0" favorites element "/FOLDER"
        And user "user0" favorites element "/textfile0.txt"
        And user "user0" favorites element "/textfile1.txt"
        Then user "user0" in folder "/" should have favorited the following elements
            | /FOLDER |
            | /textfile0.txt |
            | /textfile1.txt |

    Scenario: Get favorited elements of a subfolder
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        And user "user0" created a folder "/subfolder"
        And User "user0" moves file "/textfile0.txt" to "/subfolder/textfile0.txt"
        And User "user0" moves file "/textfile1.txt" to "/subfolder/textfile1.txt"
        And User "user0" moves file "/textfile2.txt" to "/subfolder/textfile2.txt"
        When user "user0" favorites element "/subfolder/textfile0.txt"
        And user "user0" favorites element "/subfolder/textfile1.txt"
        And user "user0" favorites element "/subfolder/textfile2.txt"
        And user "user0" unfavorites element "/subfolder/textfile1.txt"
        Then user "user0" in folder "/subfolder" should have favorited the following elements
            | /subfolder/textfile0.txt |
            | /subfolder/textfile2.txt |

    Scenario: Get favorited elements of a subfolder using new path
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        And user "user0" created a folder "/subfolder"
        And User "user0" moves file "/textfile0.txt" to "/subfolder/textfile0.txt"
        And User "user0" moves file "/textfile1.txt" to "/subfolder/textfile1.txt"
        And User "user0" moves file "/textfile2.txt" to "/subfolder/textfile2.txt"
        When user "user0" favorites element "/subfolder/textfile0.txt"
        And user "user0" favorites element "/subfolder/textfile1.txt"
        And user "user0" favorites element "/subfolder/textfile2.txt"
        And user "user0" unfavorites element "/subfolder/textfile1.txt"
        Then user "user0" in folder "/subfolder" should have favorited the following elements
            | /subfolder/textfile0.txt |
            | /subfolder/textfile2.txt |

    Scenario: moving a favorite file out of a share keeps favorite state
        Given using old dav path
        And As an "admin"
        And user "user0" exists
        And user "user1" exists
        And user "user0" created a folder "/shared"
        And User "user0" moved file "/textfile0.txt" to "/shared/shared_file.txt"
        And folder "/shared" of user "user0" is shared with user "user1"
        And user "user1" accepts last share
        And user "user1" favorites element "/shared/shared_file.txt"
        When User "user1" moved file "/shared/shared_file.txt" to "/taken_out.txt"
        Then user "user1" in folder "/" should have favorited the following elements
            | /taken_out.txt |

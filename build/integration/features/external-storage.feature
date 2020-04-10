Feature: external-storage
  Background:
    Given using api version "1"
    Given using old dav path

  @local_storage
  Scenario: Share by link a file inside a local external storage
    Given user "user0" exists
    And user "user1" exists
    And As an "user0"
    And user "user0" created a folder "/local_storage/foo"
    And User "user0" moved file "/textfile0.txt" to "/local_storage/foo/textfile0.txt"
    And folder "/local_storage/foo" of user "user0" is shared with user "user1"
    And As an "user1"
    And accepting last share
    When creating a share with
      | path | foo |
      | shareType | 3 |
    Then the OCS status code should be "100"
    And the HTTP status code should be "200"
    And Share fields of last share match with
      | id | A_NUMBER |
      | url | AN_URL |
      | token | A_TOKEN |
      | mimetype | httpd/unix-directory |

  Scenario: Shares dont overwrite external storages
    Given user "user0" exists
    And user "user1" exists
    And As an "user0"
    And User "user0" moved file "/textfile0.txt" to "/local_storage/textfile0.txt"
    And invoking occ with "files_external:create --user user0 test local null::null -c datadir=./build/integration/work/local_storage"
    And invoking occ with "files:scan --path /user0/files/test"
    And as "user0" the file "/local_storage/textfile0.txt" exists
    And as "user0" the folder "/test" exists
    And as "user0" the file "/test/textfile0.txt" exists
    And As an "user1"
    And user "user1" created a folder "/test"
    And User "user1" moved file "/textfile0.txt" to "/test/textfile1.txt"
    And folder "/test" of user "user1" is shared with user "user0"
    And As an "user0"
    Then as "user0" the file "/test/textfile1.txt" does not exist

  Scenario: Move a file into storage works
    Given user "user0" exists
    And user "user1" exists
    And As an "user0"
    And user "user0" created a folder "/local_storage/foo1"
    When User "user0" moved file "/textfile0.txt" to "/local_storage/foo1/textfile0.txt"
    Then as "user1" the file "/local_storage/foo1/textfile0.txt" exists
    And as "user0" the file "/local_storage/foo1/textfile0.txt" exists

  Scenario: Move a file out of the storage works
    Given user "user0" exists
    And user "user1" exists
    And As an "user0"
    And user "user0" created a folder "/local_storage/foo2"
    And User "user0" moved file "/textfile0.txt" to "/local_storage/foo2/textfile0.txt"
    When User "user1" moved file "/local_storage/foo2/textfile0.txt" to "/local.txt"
    Then as "user1" the file "/local_storage/foo2/textfile0.txt" does not exist
    And as "user0" the file "/local_storage/foo2/textfile0.txt" does not exist
    And as "user1" the file "/local.txt" exists

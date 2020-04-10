Feature: sharees_provisioningapiv2
  Background:
    Given using api version "2"
    And user "test" exists
    And user "Sharee1" exists
    And group "ShareeGroup" exists
    And user "test" belongs to group "ShareeGroup"

  Scenario: Search without exact match
    Given As an "test"
    When getting sharees for
      | search | Sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    And "exact groups" sharees returned is empty
    And "groups" sharees returned are
      | ShareeGroup | 1 | ShareeGroup |
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search without exact match not-exact casing
    Given As an "test"
    When getting sharees for
      | search | sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    And "exact groups" sharees returned is empty
    And "groups" sharees returned are
      | ShareeGroup | 1 | ShareeGroup |
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search only with group members - denied
    Given As an "test"
    And parameter "shareapi_only_share_with_group_members" of app "core" is set to "yes"
    When getting sharees for
      | search | sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned is empty
    And "exact groups" sharees returned is empty
    And "groups" sharees returned are
      | ShareeGroup | 1 | ShareeGroup |
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search only with group members - allowed
    Given As an "test"
    And parameter "shareapi_only_share_with_group_members" of app "core" is set to "yes"
    And user "Sharee1" belongs to group "ShareeGroup"
    When getting sharees for
      | search | sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    And "exact groups" sharees returned is empty
    And "groups" sharees returned are
      | ShareeGroup | 1 | ShareeGroup |
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search only with group members - no group as non-member
    Given As an "Sharee1"
    And parameter "shareapi_only_share_with_group_members" of app "core" is set to "yes"
    When getting sharees for
      | search | sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned is empty
    And "exact groups" sharees returned is empty
    And "groups" sharees returned is empty
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search without exact match no iteration allowed
    Given As an "test"
    And parameter "shareapi_allow_share_dialog_user_enumeration" of app "core" is set to "no"
    When getting sharees for
      | search | Sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned is empty
    And "exact groups" sharees returned is empty
    And "groups" sharees returned is empty
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search with exact match no iteration allowed
    Given As an "test"
    And parameter "shareapi_allow_share_dialog_user_enumeration" of app "core" is set to "no"
    When getting sharees for
      | search | Sharee1 |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    And "users" sharees returned is empty
    And "exact groups" sharees returned is empty
    And "groups" sharees returned is empty
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search with exact match group no iteration allowed
    Given As an "test"
    And parameter "shareapi_allow_share_dialog_user_enumeration" of app "core" is set to "no"
    When getting sharees for
      | search | ShareeGroup |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned is empty
    And "exact groups" sharees returned are
      | ShareeGroup | 1 | ShareeGroup |
    And "groups" sharees returned is empty
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

  Scenario: Search with exact match
    Given As an "test"
    When getting sharees for
      | search | Sharee1 |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    Then "exact users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    Then "users" sharees returned is empty
    Then "exact groups" sharees returned is empty
    Then "groups" sharees returned is empty
    Then "exact remotes" sharees returned is empty
    Then "remotes" sharees returned is empty

  Scenario: Search with exact match not-exact casing
    Given As an "test"
    When getting sharees for
      | search | sharee1 |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    Then "exact users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    Then "users" sharees returned is empty
    Then "exact groups" sharees returned is empty
    Then "groups" sharees returned is empty
    Then "exact remotes" sharees returned is empty
    Then "remotes" sharees returned is empty

  Scenario: Search with exact match not-exact casing group
    Given As an "test"
    When getting sharees for
      | search | shareegroup |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    Then "exact users" sharees returned is empty
    Then "users" sharees returned is empty
    Then "exact groups" sharees returned are
      | ShareeGroup | 1 | ShareeGroup |
    Then "groups" sharees returned is empty
    Then "exact remotes" sharees returned is empty
    Then "remotes" sharees returned is empty

  Scenario: Search with "self"
    Given As an "Sharee1"
    When getting sharees for
      | search | Sharee1 |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    Then "exact users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    Then "users" sharees returned is empty
    Then "exact groups" sharees returned is empty
    Then "groups" sharees returned is empty
    Then "exact remotes" sharees returned is empty
    Then "remotes" sharees returned is empty

  Scenario: Remote sharee for files
    Given As an "test"
    When getting sharees for
      | search | test@localhost |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    Then "exact users" sharees returned is empty
    Then "users" sharees returned is empty
    Then "exact groups" sharees returned is empty
    Then "groups" sharees returned is empty
    Then "exact remotes" sharees returned are
      | test (localhost) | 6 | test@localhost |
    Then "remotes" sharees returned is empty

  Scenario: Remote sharee for calendars not allowed
    Given As an "test"
    When getting sharees for
      | search | test@localhost |
      | itemType | calendar |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    Then "exact users" sharees returned is empty
    Then "users" sharees returned is empty
    Then "exact groups" sharees returned is empty
    Then "groups" sharees returned is empty
    Then "exact remotes" sharees returned is empty
    Then "remotes" sharees returned is empty

  Scenario: Group sharees not returned when group sharing is disabled
    Given As an "test"
    And parameter "shareapi_allow_group_sharing" of app "core" is set to "no"
    When getting sharees for
      | search | sharee |
      | itemType | file |
    Then the OCS status code should be "200"
    And the HTTP status code should be "200"
    And "exact users" sharees returned is empty
    And "users" sharees returned are
      | Sharee1 | 0 | Sharee1 |
    And "exact groups" sharees returned is empty
    And "groups" sharees returned is empty
    And "exact remotes" sharees returned is empty
    And "remotes" sharees returned is empty

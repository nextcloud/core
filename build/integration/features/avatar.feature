Feature: avatar

  Background:
    Given user "user0" exists

  Scenario: get default user avatar
    When user "user0" gets avatar for user "user0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: get default user avatar as an anonymous user
    When user "anonymous" gets avatar for user "user0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color



  Scenario: get temporary user avatar before cropping it
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/green-square-256.png"
    When logged in user gets temporary avatar
    Then The following headers should be set
      | Content-Type | image/png |
    # "last avatar" also includes the last temporary avatar
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color

  Scenario: get user avatar before cropping it
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/green-square-256.png"
    # Avatar needs to be cropped to finish setting it even if it is squared
    When user "user0" gets avatar for user "user0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color



  Scenario: set user avatar from file
    Given Logging in using web as "user0"
    When logged in user posts temporary avatar from file "data/coloured-pattern.png"
    And logged in user crops temporary avatar
      | x | 384 |
      | y | 256 |
      | w | 128 |
      | h | 128 |
    Then logged in user gets temporary avatar with 404
    And user "user0" gets avatar for user "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 128
    And last avatar is a single "#FF0000" color
    And user "anonymous" gets avatar for user "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 128
    And last avatar is a single "#FF0000" color

  Scenario: set user avatar from internal path
    Given user "user0" uploads file "data/coloured-pattern.png" to "/internal-coloured-pattern.png"
    And Logging in using web as "user0"
    When logged in user posts temporary avatar from internal path "internal-coloured-pattern.png"
    And logged in user crops temporary avatar
      | x | 704 |
      | y | 320 |
      | w | 64 |
      | h | 64 |
    Then logged in user gets temporary avatar with 404
    And user "user0" gets avatar for user "user0" with size "64"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 64
    And last avatar is a single "#00FF00" color
    And user "anonymous" gets avatar for user "user0" with size "64"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 64
    And last avatar is a single "#00FF00" color

  Scenario: cropped user avatar needs to be squared
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/coloured-pattern.png"
    When logged in user crops temporary avatar with 400
      | x | 384 |
      | y | 256 |
      | w | 192 |
      | h | 128 |



  Scenario: delete user avatar
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/coloured-pattern.png"
    And logged in user crops temporary avatar
      | x | 384 |
      | y | 256 |
      | w | 128 |
      | h | 128 |
    And user "user0" gets avatar for user "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 128
    And last avatar is a single "#FF0000" color
    And user "anonymous" gets avatar for user "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 128
    And last avatar is a single "#FF0000" color
    When logged in user deletes the user avatar
    Then user "user0" gets avatar for user "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color
    And user "anonymous" gets avatar for user "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color



  Scenario: get user avatar with a larger size than the original one
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/coloured-pattern.png"
    And logged in user crops temporary avatar
      | x | 384 |
      | y | 256 |
      | w | 128 |
      | h | 128 |
    When user "user0" gets avatar for user "user0" with size "192"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 192
    And last avatar is a single "#FF0000" color

  Scenario: get user avatar with a smaller size than the original one
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/coloured-pattern.png"
    And logged in user crops temporary avatar
      | x | 384 |
      | y | 256 |
      | w | 128 |
      | h | 128 |
    When user "user0" gets avatar for user "user0" with size "96"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 96
    And last avatar is a single "#FF0000" color



  Scenario: get default guest avatar
    When user "user0" gets avatar for guest "guest0"
    Then The following headers should be set
      | Content-Type | image/png |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: get default guest avatar as an anonymous user
    When user "anonymous" gets avatar for guest "guest0"
    Then The following headers should be set
      | Content-Type | image/png |
    And last avatar is a square of size 128
    And last avatar is not a single color

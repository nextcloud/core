Feature: avatar

  Background:
    Given using api version "2"
    Given user "user0" exists
    Given user "user1" exists

  Scenario: get default generic user avatar
    When user "user0" gets avatar for type "user" and id "user0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: get default generic user avatar as an anonymous user
    When user "anonymous" gets avatar for type "user" and id "user0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: get default generic guest avatar
    When user "user0" gets avatar for type "guest" and id "guest0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: get default generic guest avatar as an anonymous user
    When user "anonymous" gets avatar for type "guest" and id "guest0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: get generic unknown avatar
    When user "user0" gets avatar for type "unknown" and id "user0" with size "128" with 404



  Scenario: set generic user avatar
    When user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    Then user "user0" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color
    And user "anonymous" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color

  Scenario: set generic user avatar as another user
    When user "user1" sets avatar for type "user" and id "user0" from file "data/green-square-256.png" with "404"
    Then user "user0" gets avatar for type "user" and id "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: set generic user avatar as an anonymous user
    When user "anonymous" sets avatar for type "user" and id "user0" from file "data/green-square-256.png" with "404"
    Then user "user0" gets avatar for type "user" and id "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: set non squared image as generic user avatar
    When user "user0" sets avatar for type "user" and id "user0" from file "data/coloured-pattern.png" with "400"
    Then user "user0" gets avatar for type "user" and id "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: set not an image as generic user avatar
    When user "user0" sets avatar for type "user" and id "user0" from file "data/textfile.txt" with "400"
    Then user "user0" gets avatar for type "user" and id "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: set generic guest avatar
    When user "user0" sets avatar for type "guest" and id "guest0" from file "data/green-square-256.png" with "404"
    Then user "user0" gets avatar for type "guest" and id "guest0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: set generic unknown avatar
    When user "user0" sets avatar for type "unknown" and id "user0" from file "data/green-square-256.png" with "404"



  Scenario: delete generic user avatar
    Given user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    And user "user0" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color
    And user "anonymous" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color
    When user "user0" deletes avatar for type "user" and id "user0"
    Then user "user0" gets avatar for type "user" and id "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color
    And user "anonymous" gets avatar for type "user" and id "user0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: delete generic user avatar as another user
    Given user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    And user "user0" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color
    When user "user1" deletes avatar for type "user" and id "user0" with "404"
    Then user "user0" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color

  Scenario: delete generic user avatar as an anonymous user
    Given user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    And user "user0" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color
    When user "anonymous" deletes avatar for type "user" and id "user0" with "404"
    Then user "user0" gets avatar for type "user" and id "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color

  Scenario: delete generic guest avatar
    When user "user0" deletes avatar for type "guest" and id "guest0" with "404"
    Then user "user0" gets avatar for type "guest" and id "guest0"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 0 |
    And last avatar is a square of size 128
    And last avatar is not a single color

  Scenario: delete generic unknown avatar
    When user "user0" deletes avatar for type "unknown" and id "user0" with "404"



  Scenario: get generic user avatar with a larger size than the original one
    Given user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    When user "user0" gets avatar for type "user" and id "user0" with size "512"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 512
    And last avatar is a single "#00FF00" color

  Scenario: get generic user avatar with a smaller size than the original one
    Given user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    When user "user0" gets avatar for type "user" and id "user0" with size "128"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 128
    And last avatar is a single "#00FF00" color



  Scenario: get user avatar after setting generic user avatar
    Given user "user0" sets avatar for type "user" and id "user0" from file "data/green-square-256.png"
    When user "user0" gets avatar for user "user0" with size "256"
    And The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 256
    And last avatar is a single "#00FF00" color

  Scenario: get generic user avatar after setting user avatar
    Given Logging in using web as "user0"
    And logged in user posts temporary avatar from file "data/coloured-pattern.png"
    And logged in user crops temporary avatar
      | x | 384 |
      | y | 256 |
      | w | 128 |
      | h | 128 |
    When user "user0" gets avatar for type "user" and id "user0"
    Then The following headers should be set
      | Content-Type | image/png |
      | X-NC-IsCustomAvatar | 1 |
    And last avatar is a square of size 128
    And last avatar is a single "#FF0000" color



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

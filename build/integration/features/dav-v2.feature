Feature: dav-v2
	Background:
		Given using api version "1"

	Scenario: moving a file new endpoint way
		Given using new dav path
		And As an "admin"
		And user "user0" exists
		When User "user0" moves file "/textfile0.txt" to "/FOLDER/textfile0.txt"
		Then the HTTP status code should be "201"

	Scenario: download a file with range using new endpoint
		Given using new dav path
		And As an "admin"
		And user "user0" exists
		And As an "user0"
		When Downloading file "/welcome.txt" with range "bytes=52-78"
		Then Downloaded content should be "example file for developers"

	Scenario: Downloading a file on the new endpoint should serve security headers
		Given using new dav path
		And As an "admin"
		When Downloading file "/welcome.txt"
		Then The following headers should be set
			|Content-Disposition|attachment; filename*=UTF-8''welcome.txt; filename="welcome.txt"|
			|Content-Security-Policy|default-src 'none';|
			|X-Content-Type-Options |nosniff|
			|X-Download-Options|noopen|
			|X-Frame-Options|SAMEORIGIN|
			|X-Permitted-Cross-Domain-Policies|none|
			|X-Robots-Tag|none|
			|X-XSS-Protection|1; mode=block|
		And Downloaded content should start with "Welcome to your Nextcloud account!"

	Scenario: Doing a GET with a web login should work without CSRF token on the new backend
		Given Logging in using web as "admin"
		When Sending a "GET" to "/remote.php/dav/files/admin/welcome.txt" without requesttoken
		Then Downloaded content should start with "Welcome to your Nextcloud account!"
		Then the HTTP status code should be "200"

	Scenario: Doing a GET with a web login should work with CSRF token on the new backend
		Given Logging in using web as "admin"
		When Sending a "GET" to "/remote.php/dav/files/admin/welcome.txt" with requesttoken
		Then Downloaded content should start with "Welcome to your Nextcloud account!"
		Then the HTTP status code should be "200"

	Scenario: Doing a PROPFIND with a web login should not work without CSRF token on the new backend
		Given Logging in using web as "admin"
		When Sending a "PROPFIND" to "/remote.php/dav/files/admin/welcome.txt" without requesttoken
		Then the HTTP status code should be "401"

	Scenario: Doing a PROPFIND with a web login should work with CSRF token on the new backend
		Given Logging in using web as "admin"
		When Sending a "PROPFIND" to "/remote.php/dav/files/admin/welcome.txt" with requesttoken
		Then the HTTP status code should be "207"

	Scenario: Uploading a file having 0B as quota
		Given using new dav path
		And As an "admin"
		And user "user0" exists
		And user "user0" has a quota of "0 B"
		And As an "user0"
		When User "user0" uploads file "data/textfile.txt" to "/asdf.txt"
		Then the HTTP status code should be "507"

	Scenario: Uploading a file as recipient using webdav new endpoint having quota
		Given using new dav path
		And As an "admin"
		And user "user0" exists
		And user "user1" exists
		And user "user0" has a quota of "10 MB"
		And user "user1" has a quota of "10 MB"
		And As an "user1"
		And user "user1" created a folder "/testquota"
		And as "user1" creating a share with
		  | path | testquota |
		  | shareType | 0 |
		  | permissions | 31 |
		  | shareWith | user0 |
        And user "user0" accepts last share
		And As an "user0"
		When User "user0" uploads file "data/textfile.txt" to "/testquota/asdf.txt"
		Then the HTTP status code should be "201"

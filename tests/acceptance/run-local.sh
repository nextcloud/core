#!/usr/bin/env bash

# @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
#
# @license GNU AGPL version 3 or any later version
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.

# Helper script to run the acceptance tests, which test a running Nextcloud
# instance from the point of view of a real user, configured to start the
# Nextcloud server themselves and from their grandparent directory.
#
# The acceptance tests are written in Behat so, besides running the tests, this
# script installs Behat, its dependencies, and some related packages in the
# "vendor" subdirectory of the acceptance tests. The acceptance tests expect
# that the last commit in the Git repository provides the default state of the
# Nextcloud server, so the script installs the Nextcloud server and saves a
# snapshot of the whole grandparent directory (no .gitignore file is used) in
# the Git repository. Finally, the acceptance tests also use the Selenium server
# to control a web browser, so this script waits for the Selenium server
# (which should have been started before executing this script) to be ready
# before running the tests.
#
# By default the acceptance tests run are those for the Nextcloud server;
# acceptance tests for apps can be run by providing the
# "--acceptance-tests-dir XXX" option. When this option is used the Behat
# configuration and the Nextcloud installation script used by the acceptance
# tests for the Nextcloud server are ignored; they must be provided in the given
# acceptance tests directory. Note, however, that the context classes for the
# Nextcloud server and the core acceptance test framework classes are
# automatically loaded; there is no need to explicitly set them in the Behat
# configuration. Also, even when that option is used, the packages installed by
# this script end in the "vendor" subdirectory of the acceptance tests for the
# Nextcloud server, not in the one given in the option.

# Exit immediately on errors.
set -o errexit

# Ensure working directory is script directory, as some actions (like installing
# Behat through Composer or running Behat) expect that.
cd "$(dirname $0)"

# "--acceptance-tests-dir XXX" option can be provided to set the directory
# (relative to the root directory of the Nextcloud server) used to look for the
# Behat configuration and the Nextcloud installation script.
# By default it is "tests/acceptance", that is, the acceptance tests for the
# Nextcloud server itself.
ACCEPTANCE_TESTS_DIR="tests/acceptance"
if [ "$1" = "--acceptance-tests-dir" ]; then
	ACCEPTANCE_TESTS_DIR=$2

	shift 2
fi

ACCEPTANCE_TESTS_CONFIG_DIR="../../$ACCEPTANCE_TESTS_DIR/config"

# "--timeout-multiplier N" option can be provided to set the timeout multiplier
# to be used in ActorContext.
TIMEOUT_MULTIPLIER=""
if [ "$1" = "--timeout-multiplier" ]; then
	if [[ ! "$2" =~ ^[0-9]+$ ]]; then
		echo "--timeout-multiplier must be followed by a positive integer"

		exit 1
	fi

	TIMEOUT_MULTIPLIER=$2

	shift 2
fi

# "--nextcloud-server-domain XXX" option can be provided to set the domain used
# by the Selenium server to access the Nextcloud server.
DEFAULT_NEXTCLOUD_SERVER_DOMAIN="127.0.0.1"
NEXTCLOUD_SERVER_DOMAIN="$DEFAULT_NEXTCLOUD_SERVER_DOMAIN"
if [ "$1" = "--nextcloud-server-domain" ]; then
	NEXTCLOUD_SERVER_DOMAIN=$2

	shift 2
fi

# "--selenium-server XXX" option can be provided to set the domain and port used
# by the acceptance tests to access the Selenium server.
DEFAULT_SELENIUM_SERVER="127.0.0.1:4444"
SELENIUM_SERVER="$DEFAULT_SELENIUM_SERVER"
if [ "$1" = "--selenium-server" ]; then
	SELENIUM_SERVER=$2

	shift 2
fi

# Safety parameter to prevent executing this script by mistake and messing with
# the Git repository.
if [ "$1" != "allow-git-repository-modifications" ]; then
	echo "To run the acceptance tests use \"run.sh\" instead"

	exit 1
fi

SCENARIO_TO_RUN=$2
if [ "$ACCEPTANCE_TESTS_DIR" != "tests/acceptance" ]; then
	if [ "$SCENARIO_TO_RUN" == "" ]; then
		echo "When an acceptance tests directory is given the scenario to run" \
			 "should be provided too (paths are relative to the acceptance" \
			 "tests directory; use the features directory to run all tests)"
		echo "No scenario was given, so \"features/\" was automatically used"

		SCENARIO_TO_RUN="features/"
	fi

	SCENARIO_TO_RUN=../../$ACCEPTANCE_TESTS_DIR/$SCENARIO_TO_RUN
fi

if [ "$TIMEOUT_MULTIPLIER" != "" ]; then
	# Although Behat documentation states that using the BEHAT_PARAMS
	# environment variable "You can set any value for any option that is
	# available in a behat.yml file" this is currently not true for the
	# constructor parameters of contexts (see
	# https://github.com/Behat/Behat/issues/983). Thus, the default "behat.yml"
	# configuration file has to be adjusted to provide the appropriate
	# parameters for ActorContext.
	ORIGINAL="\
        - ActorContext"
	REPLACEMENT="\
        - ActorContext:\n\
            actorTimeoutMultiplier: $TIMEOUT_MULTIPLIER"
	sed --in-place "s/$ORIGINAL/$REPLACEMENT/" $ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml
fi

if [ "$NEXTCLOUD_SERVER_DOMAIN" != "$DEFAULT_NEXTCLOUD_SERVER_DOMAIN" ]; then
	# Although Behat documentation states that using the BEHAT_PARAMS
	# environment variable "You can set any value for any option that is
	# available in a behat.yml file" this is currently not true for the
	# constructor parameters of contexts (see
	# https://github.com/Behat/Behat/issues/983). Thus, the default "behat.yml"
	# configuration file has to be adjusted to provide the appropriate
	# parameters for NextcloudTestServerContext.
	#
	# Note that the substitution below is only valid if no parameters for
	# the helper are set in behat.yml, although it is valid if a specific
	# helper is.
	ORIGINAL="\
        - NextcloudTestServerContext:\?"
	REPLACEMENT="\
        - NextcloudTestServerContext:\n\
            nextcloudTestServerHelperParameters:\n\
              - $NEXTCLOUD_SERVER_DOMAIN"
	sed --in-place "s/$ORIGINAL/$REPLACEMENT/" $ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml
fi

# Due to a bug in the Mink Extension for Behat it is not possible to use the
# "paths.base" variable in the path to the custom Firefox profile. Thus, the
# default "behat.yml" configuration file has to be adjusted to replace the
# variable by its value before the configuration file is parsed by Behat.
ORIGINAL="profile: %paths.base%"
REPLACEMENT="profile: $ACCEPTANCE_TESTS_CONFIG_DIR"
# As the substitution does not involve regular expressions or multilines it can
# be done just with Bash. Moreover, this does not require escaping the regular
# expression characters that may appear in the path, like "/".
FILE_CONTENTS=$(<$ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml)
echo "${FILE_CONTENTS//$ORIGINAL/$REPLACEMENT}" > $ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml

# Set the Selenium server to be used by Mink. Although Mink sessions can be
# extended through BEHAT_PARAMS this would require adding here too each new
# session added to "behat.yml", including those added in the acceptance
# tests of apps. Instead, the default "behat.yml" configuration file is
# adjusted to replace the simulated "selenium.server" variable by its value
# before the configuration file is parsed by Behat.
ORIGINAL="wd_host: %selenium.server%"
REPLACEMENT="wd_host: http://$SELENIUM_SERVER/wd/hub"
# As the substitution does not involve regular expressions or multilines it
# can be done just with Bash. Moreover, this does not require escaping the
# regular expression characters that may appear in the URL, like "/".
FILE_CONTENTS=$(<$ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml)
echo "${FILE_CONTENTS//$ORIGINAL/$REPLACEMENT}" > $ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml

composer install

cd ../../

# Link the default Apache directory to the root directory of the Nextcloud
# server to make possible to run the Nextcloud server on Apache if needed.
ln --symbolic $(pwd) /var/www/html

# Add Notifications app to the "apps" directory (unless it is already there).
if [ ! -e "apps/notifications" ]; then
	(cd apps && git clone --depth 1 https://github.com/nextcloud/notifications)
fi

INSTALL_AND_CONFIGURE_SERVER_PARAMETERS=""
if [ "$NEXTCLOUD_SERVER_DOMAIN" != "$DEFAULT_NEXTCLOUD_SERVER_DOMAIN" ]; then
	INSTALL_AND_CONFIGURE_SERVER_PARAMETERS+="--nextcloud-server-domain $NEXTCLOUD_SERVER_DOMAIN"
fi

echo "Installing and configuring Nextcloud server"
# The server is installed and configured using the www-data user as it is the
# user that Apache sub-processes will be run as; the PHP built-in web server is
# run as the root user, and in that case the permissions of apps, config and
# data dirs makes no difference, so this is valid for both cases.
mkdir data
chown -R www-data:www-data apps config data
NEXTCLOUD_DIR=`pwd`
su --shell /bin/bash --login www-data --command "cd $NEXTCLOUD_DIR && $ACCEPTANCE_TESTS_DIR/installAndConfigureServer.sh $INSTALL_AND_CONFIGURE_SERVER_PARAMETERS"

echo "Saving the default state so acceptance tests can reset to it"
find . -name ".gitignore" -exec rm --force {} \;
# Create dummy files in empty directories to force Git to save the directories.
find . -not -path "*.git*" -type d -empty -exec touch {}/.keep \;
git add --all && echo 'Default state' | git -c user.name='John Doe' -c user.email='john@doe.org' commit --quiet --file=-

cd tests/acceptance

# Ensure that the Selenium server is ready before running the tests.
echo "Waiting for Selenium"
timeout 60s bash -c "while ! curl $SELENIUM_SERVER >/dev/null 2>&1; do sleep 1; done"

vendor/bin/behat --config=$ACCEPTANCE_TESTS_CONFIG_DIR/behat.yml $SCENARIO_TO_RUN

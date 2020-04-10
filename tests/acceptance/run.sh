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
# instance from the point of view of a real user.
#
# The acceptance tests are run in its own Docker container; the grandparent
# directory of the acceptance tests directory (that is, the root directory of
# the Nextcloud server) is copied to the container and the acceptance tests are
# run inside it. Once the tests end the container is stopped. The acceptance
# tests also use the Selenium server to control a web browser, so the Selenium
# server is also launched before the tests start in its own Docker container (it
# will be stopped automatically too once the tests end).
#
# To perform its job, the script requires the "docker" command to be available.
#
# The Docker Command Line Interface (the "docker" command) requires special
# permissions to talk to the Docker daemon, and those permissions are typically
# available only to the root user. Please see the Docker documentation to find
# out how to give access to a regular user to the Docker daemon:
# https://docs.docker.com/engine/installation/linux/linux-postinstall/
#
# Note, however, that being able to communicate with the Docker daemon is the
# same as being able to get root privileges for the system. Therefore, you must
# give access to the Docker daemon (and thus run this script as) ONLY to trusted
# and secure users:
# https://docs.docker.com/engine/security/security/#docker-daemon-attack-surface
#
# Finally, take into account that this script will automatically remove the
# Docker containers named "selenium-nextcloud-local-test-acceptance" and
# "nextcloud-local-test-acceptance", even if the script did not create them
# (probably you will not have containers nor images with those names, but just
# in case).

# Sets the variables that abstract the differences in command names and options
# between operating systems.
#
# Switches between timeout on GNU/Linux and gtimeout on macOS (same for mktemp
# and gmktemp).
function setOperatingSystemAbstractionVariables() {
	case "$OSTYPE" in
		darwin*)
			if [ "$(which gtimeout)" == "" ]; then
				echo "Please install coreutils (brew install coreutils)"
				exit 1
			fi

			MKTEMP=gmktemp
			TIMEOUT=gtimeout
			DOCKER_OPTIONS="-e no_proxy=localhost "
			;;
		linux*)
			MKTEMP=mktemp
			TIMEOUT=timeout
			DOCKER_OPTIONS=" "
			;;
		*)
			echo "Operating system ($OSTYPE) not supported"
			exit 1
			;;
	esac
}

# Launches the Selenium server in a Docker container.
#
# The acceptance tests use Firefox by default but, unfortunately, Firefox >= 48
# does not provide yet the same level of support as earlier versions for certain
# features related to automated testing. Therefore, the Docker image used is not
# the latest one, but an older version known to work.
#
# The acceptance tests expect the Selenium server to be accessible at
# "127.0.0.1:4444"; as the Selenium server container and the container in which
# the acceptance tests are run share the same network nothing else needs to be
# done for the acceptance tests to access the Selenium server and for the
# Selenium server to access the Nextcloud server. However, in order to ensure
# from this script that the Selenium server was started the 4444 port of its
# container is mapped to the 4444 port of the host.
#
# Besides the Selenium server, the Docker image also provides a VNC server, so
# the 5900 port of the container is also mapped to the 5900 port of the host.
#
# The Docker container started here will be automatically stopped when the
# script exits (see cleanUp). If the Selenium server can not be started then the
# script will be exited immediately with an error state; the most common cause
# for the Selenium server to fail to start is that another server is already
# using the mapped ports in the host.
#
# As the web browser is run inside the Docker container it is not visible by
# default. However, it can be viewed using VNC (for example,
# "vncviewer 127.0.0.1:5900"); when asked for the password use "secret".
function prepareSelenium() {
	SELENIUM_CONTAINER=selenium-nextcloud-local-test-acceptance

	echo "Starting Selenium server"
	docker run --detach --name=$SELENIUM_CONTAINER --publish 4444:4444 --publish 5900:5900 $DOCKER_OPTIONS selenium/standalone-firefox-debug:2.53.1-beryllium

	echo "Waiting for Selenium server to be ready"
	if ! $TIMEOUT 10s bash -c "while ! curl 127.0.0.1:4444 >/dev/null 2>&1; do sleep 1; done"; then
		echo "Could not start Selenium server; running" \
		     "\"docker run --rm --publish 4444:4444 --publish 5900:5900 $DOCKER_OPTIONS selenium/standalone-firefox-debug:2.53.1-beryllium\"" \
		     "could give you a hint of the problem"

		exit 1
	fi
}

# Creates a Docker container to run both the acceptance tests and the Nextcloud
# server used by them.
#
# This function starts a Docker container with a copy the Nextcloud code from
# the grandparent directory, although ignoring any configuration or data that it
# may provide (for example, if that directory was used directly to deploy a
# Nextcloud instance in a web server). As the Nextcloud code is copied to the
# container instead of referenced the original code can be modified while the
# acceptance tests are running without interfering in them.
function prepareDocker() {
	NEXTCLOUD_LOCAL_CONTAINER=nextcloud-local-test-acceptance

	echo "Starting the Nextcloud container"
	# As the Nextcloud server container uses the network of the Selenium server
	# container the Nextcloud server can be accessed at "127.0.0.1" from the
	# Selenium server.
	# The container exits immediately if no command is given, so a Bash session
	# is created to prevent that.
	docker run --detach --name=$NEXTCLOUD_LOCAL_CONTAINER --network=container:$SELENIUM_CONTAINER --interactive --tty nextcloudci/acceptance-php7.3:acceptance-php7.3-2 bash

	# Use the $TMPDIR or, if not set, fall back to /tmp.
	NEXTCLOUD_LOCAL_TAR="$($MKTEMP --tmpdir="${TMPDIR:-/tmp}" --suffix=.tar nextcloud-local-XXXXXXXXXX)"

	# Setting the user and group of files in the tar would be superfluous, as
	# "docker cp" does not take them into account (the extracted files are set
	# to root).
	echo "Copying local Git working directory of Nextcloud to the container"
	tar --create --file="$NEXTCLOUD_LOCAL_TAR" \
		--exclude=".git" \
		--exclude="./build" \
		--exclude="./config/config.php" \
		--exclude="./data" \
		--exclude="./data-autotest" \
		--exclude="./tests" \
		--exclude="./apps-extra" \
		--exclude="./apps-writable" \
		--exclude="node_modules" \
		--directory=../../ \
		.
	tar --append --file="$NEXTCLOUD_LOCAL_TAR" --directory=../../ tests/acceptance/

	docker exec $NEXTCLOUD_LOCAL_CONTAINER mkdir /nextcloud
	docker cp - $NEXTCLOUD_LOCAL_CONTAINER:/nextcloud/ < "$NEXTCLOUD_LOCAL_TAR"

	# run-local.sh expects a Git repository to be available in the root of the
	# Nextcloud server, but it was excluded when the Git working directory was
	# copied to the container to avoid copying the large and unneeded history of
	# the repository.
	docker exec $NEXTCLOUD_LOCAL_CONTAINER bash -c "cd nextcloud && git init"
}

# Removes/stops temporal elements created/started by this script.
function cleanUp() {
	# Disable (yes, "+" disables) exiting immediately on errors to ensure that
	# all the cleanup commands are executed (well, no errors should occur during
	# the cleanup anyway, but just in case).
	set +o errexit

	echo "Cleaning up"

	if [ -f "$NEXTCLOUD_LOCAL_TAR" ]; then
		echo "Removing $NEXTCLOUD_LOCAL_TAR"
	    rm $NEXTCLOUD_LOCAL_TAR
	fi

	# The name filter must be specified as "^/XXX$" to get an exact match; using
	# just "XXX" would match every name that contained "XXX".
	if [ -n "$(docker ps --all --quiet --filter name="^/$NEXTCLOUD_LOCAL_CONTAINER$")" ]; then
		echo "Removing Docker container $NEXTCLOUD_LOCAL_CONTAINER"
		docker rm --volumes --force $NEXTCLOUD_LOCAL_CONTAINER
	fi

	if [ -n "$(docker ps --all --quiet --filter name="^/$SELENIUM_CONTAINER$")" ]; then
		echo "Removing Docker container $SELENIUM_CONTAINER"
		docker rm --volumes --force $SELENIUM_CONTAINER
	fi
}

# Exit immediately on errors.
set -o errexit

# Execute cleanUp when the script exits, either normally or due to an error.
trap cleanUp EXIT

# Ensure working directory is script directory, as some actions (like copying
# the Git working directory to the container) expect that.
cd "$(dirname $0)"

# "--acceptance-tests-dir XXX" option can be provided to set the directory
# (relative to the root directory of the Nextcloud server) used to look for the
# Behat configuration and the Nextcloud installation script.
# By default it is "tests/acceptance", that is, the acceptance tests for the
# Nextcloud server itself.
ACCEPTANCE_TESTS_DIR_OPTION=""
if [ "$1" = "--acceptance-tests-dir" ]; then
	ACCEPTANCE_TESTS_DIR_OPTION="--acceptance-tests-dir $2"

	shift 2
fi

# "--timeout-multiplier N" option can be provided before the specific scenario
# to run, if any, to set the timeout multiplier to be used in the acceptance
# tests.
TIMEOUT_MULTIPLIER_OPTION=""
if [ "$1" = "--timeout-multiplier" ]; then
	if [[ ! "$2" =~ ^[0-9]+$ ]]; then
		echo "--timeout-multiplier must be followed by a positive integer"

		exit 1
	fi

	TIMEOUT_MULTIPLIER_OPTION="--timeout-multiplier $2"

	shift 2
fi

# If no parameter is provided to this script all the acceptance tests are run.
SCENARIO_TO_RUN=$1

setOperatingSystemAbstractionVariables

prepareSelenium
prepareDocker

echo "Running tests"
docker exec $NEXTCLOUD_LOCAL_CONTAINER bash -c "cd nextcloud && tests/acceptance/run-local.sh $ACCEPTANCE_TESTS_DIR_OPTION $TIMEOUT_MULTIPLIER_OPTION allow-git-repository-modifications $SCENARIO_TO_RUN"

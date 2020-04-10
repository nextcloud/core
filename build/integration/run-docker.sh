#!/usr/bin/env bash

# @copyright Copyright (c) 2017, Daniel Calviño Sánchez (danxuliu@gmail.com)
# @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

# Helper script to run the integration tests on a fresh Nextcloud server through
# Docker.
#
# The integration tests are run in its own Docker container; the grandparent
# directory of the integration tests directory (that is, the root directory of
# the Nextcloud server) is copied to the container and the integration tests are
# run inside it; in the container the configuration/data from the original
# Nextcloud server is ignored, and a new server installation is performed inside
# the container instead. Once the tests end the container is stopped.
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
# Docker containers named "database-nextcloud-local-test-integration" and
# "nextcloud-local-test-integration", even if the script did not create them
# (probably you will not have containers nor images with that name, but just in
# case).

# Sets the variables that abstract the differences in command names and options
# between operating systems.
#
# Switches between mktemp on GNU/Linux and gmktemp on macOS.
function setOperatingSystemAbstractionVariables() {
	case "$OSTYPE" in
		darwin*)
			if [ "$(which gtimeout)" == "" ]; then
				echo "Please install coreutils (brew install coreutils)"
				exit 1
			fi

			MKTEMP=gmktemp
			TIMEOUT=gtimeout
			;;
		linux*)
			MKTEMP=mktemp
			TIMEOUT=timeout
			;;
		*)
			echo "Operating system ($OSTYPE) not supported"
			exit 1
			;;
	esac
}

# Launches the database server in a Docker container.
#
# No server is started if "SQLite" is being used; in other cases the database
# is set up as needed and generic "$DATABASE_NAME/USER/PASSWORD" variables
# (independent of the database type) are set to be used when installing the
# Nextcloud server.
#
# The Docker container started here will be automatically stopped when the
# script exits (see cleanUp). If the database server can not be started then the
# script will be exited immediately with an error state.
function prepareDatabase() {
	if [ "$DATABASE" = "sqlite" ]; then
		return
	fi

	DATABASE_CONTAINER=database-nextcloud-local-test-integration

	DATABASE_NAME=oc_autotest
	DATABASE_USER=oc_autotest
	DATABASE_PASSWORD=nextcloud

	DATABASE_CONTAINER_OPTIONS="--env MYSQL_ROOT_PASSWORD=nextcloud_root --env MYSQL_USER=$DATABASE_USER --env MYSQL_PASSWORD=$DATABASE_PASSWORD --env MYSQL_DATABASE=$DATABASE_NAME"
	if [ "$DATABASE" = "pgsql" ]; then
		DATABASE_CONTAINER_OPTIONS=" --env POSTGRES_USER=$DATABASE_USER --env POSTGRES_PASSWORD=$DATABASE_PASSWORD --env POSTGRES_DB=${DATABASE_NAME}_dummy"
	fi

	echo "Starting database server"
	docker run --detach --name=$DATABASE_CONTAINER $DATABASE_CONTAINER_OPTIONS $DATABASE_IMAGE

	DATABASE_IP=$(docker inspect --format='{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' $DATABASE_CONTAINER)

	DATABASE_PORT=3306
	if [ "$DATABASE" = "pgsql" ]; then
		DATABASE_PORT=5432
	fi

	echo "Waiting for database server to be ready"
	if ! $TIMEOUT 600s bash -c "while ! (</dev/tcp/$DATABASE_IP/$DATABASE_PORT) >/dev/null 2>&1; do sleep 1; done"; then
		echo "Could not start database server after 600 seconds" >&2

		exit 1
	fi
}

# Creates a Docker container to run the integration tests.
#
# This function starts a Docker container with a copy of the Nextcloud code from
# the grandparent directory, although ignoring any configuration or data that it
# may provide (for example, if that directory was used directly to deploy a
# Nextcloud instance in a web server). As the Nextcloud code is copied to the
# container instead of referenced the original code can be modified while the
# integration tests are running without interfering in them.
function prepareDocker() {
	NEXTCLOUD_LOCAL_CONTAINER=nextcloud-local-test-integration

	NEXTCLOUD_LOCAL_CONTAINER_NETWORK_OPTIONS=""
	if [ -n "$DATABASE_CONTAINER" ]; then
		# The network stack is shared between the database and the Nextcloud
		# container, so the Nextcloud server can access the database directly on
		# 127.0.0.1.
		NEXTCLOUD_LOCAL_CONTAINER_NETWORK_OPTIONS="--network=container:$DATABASE_CONTAINER"
	fi

	echo "Starting the Nextcloud container"
	# When using "nextcloudci/phpX.Y" images the container exits immediately if
	# no command is given, so a Bash session is created to prevent that.
	docker run --detach --name=$NEXTCLOUD_LOCAL_CONTAINER $NEXTCLOUD_LOCAL_CONTAINER_NETWORK_OPTIONS --interactive --tty $NEXTCLOUD_LOCAL_IMAGE bash

	# Use the $TMPDIR or, if not set, fall back to /tmp.
	NEXTCLOUD_LOCAL_TAR="$($MKTEMP --tmpdir="${TMPDIR:-/tmp}" --suffix=.tar nextcloud-local-XXXXXXXXXX)"

	# Setting the user and group of files in the tar would be superfluous, as
	# "docker cp" does not take them into account (the extracted files are set
	# to root).
	echo "Copying local Git working directory of Nextcloud to the container"
	tar --create --file="$NEXTCLOUD_LOCAL_TAR" \
		--exclude=".git" \
		--exclude="./config/config.php" \
		--exclude="./data" \
		--exclude="./data-autotest" \
		--exclude="./tests" \
		--exclude="node_modules" \
		--directory=../../ \
		.

	docker exec $NEXTCLOUD_LOCAL_CONTAINER mkdir /nextcloud
	docker cp - $NEXTCLOUD_LOCAL_CONTAINER:/nextcloud/ < "$NEXTCLOUD_LOCAL_TAR"

	# Database options are needed only when a database other than SQLite is
	# used.
	NEXTCLOUD_LOCAL_CONTAINER_INSTALL_DATABASE_OPTIONS=""
	if [ -n "$DATABASE_CONTAINER" ]; then
		NEXTCLOUD_LOCAL_CONTAINER_INSTALL_DATABASE_OPTIONS="--database=$DATABASE --database-name=$DATABASE_NAME --database-user=$DATABASE_USER --database-pass=$DATABASE_PASSWORD --database-host=127.0.0.1"
	fi

	echo "Installing Nextcloud in the container"
	docker exec $NEXTCLOUD_LOCAL_CONTAINER bash -c "cd nextcloud && php occ maintenance:install --admin-pass=admin $NEXTCLOUD_LOCAL_CONTAINER_INSTALL_DATABASE_OPTIONS"
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

	if [ -n "$DATABASE_CONTAINER" -a -n "$(docker ps --all --quiet --filter name="^/$DATABASE_CONTAINER$")" ]; then
		echo "Removing Docker container $DATABASE_CONTAINER"
		docker rm --volumes --force $DATABASE_CONTAINER
	fi
}

# Exit immediately on errors.
set -o errexit

# Execute cleanUp when the script exits, either normally or due to an error.
trap cleanUp EXIT

# Ensure working directory is script directory, as some actions (like copying
# the Git working directory to the container) expect that.
cd "$(dirname $0)"

# "--image XXX" option can be provided to set the Docker image to use to run
# the integration tests (one of the "nextcloudci/phpX.Y:phpX.Y-Z" images).
NEXTCLOUD_LOCAL_IMAGE="nextcloudci/php7.3:php7.3-5"
if [ "$1" = "--image" ]; then
	NEXTCLOUD_LOCAL_IMAGE=$2

	shift 2
fi

# "--database XXX" option can be provided to set the database to use to run the
# integration tests (one of "sqlite", "mysql" or "pgsql"; "sqlite" is used
# by default).
DATABASE="sqlite"
if [ "$1" = "--database" ]; then
	DATABASE=$2

	shift 2
fi

if [ "$DATABASE" != "sqlite" ] && [ "$DATABASE" != "mysql" ] && [ "$DATABASE" != "pgsql" ]; then
	echo "--database must be followed by one of: sqlite, mysql or pgsql"

	exit 1
fi

# "--database-image XXX" option can be provided to set the Docker image to use
# for the database container (ignored when using "sqlite").
if [ "$DATABASE" = "mysql" ]; then
	DATABASE_IMAGE="mysql:5.7"
elif [ "$DATABASE" = "pgsql" ]; then
	DATABASE_IMAGE="postgres:10"
fi
if [ "$1" = "--database-image" ]; then
	DATABASE_IMAGE=$2

	shift 2
fi

# If no parameter is provided to this script all the integration tests are run.
SCENARIO_TO_RUN=$1

setOperatingSystemAbstractionVariables

prepareDatabase
prepareDocker

echo "Running tests"
# --tty is needed to get colourful output.
docker exec --tty $NEXTCLOUD_LOCAL_CONTAINER bash -c "cd nextcloud/build/integration && ./run.sh $SCENARIO_TO_RUN"

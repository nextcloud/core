#!/usr/bin/env bash
#
# ownCloud
#
# @author Vincent Petry
# @author Morris Jobke
# @author Robin McCorkell
# @author Thomas Müller
# @author Andreas Fischer
# @author Joas Schilling
# @author Lukas Reschke
# @author Jörn Friedrich Dreyer
# @copyright 2012-2015 Thomas Müller thomas.mueller@tmit.eu
#

DATABASENAME=oc_autotest
DATABASEUSER=oc_autotest
DATABASEHOST=localhost
ADMINLOGIN=admin
BASEDIR=$PWD

PRIMARY_STORAGE_CONFIGS="local swift"
DBCONFIGS="sqlite mysql mariadb pgsql oci mysqlmb4"

# $PHP_EXE is run through 'which' and as such e.g. 'php' is usually
# sufficient. Due to the behaviour of 'which', $PHP_EXE may also be a path
# (absolute or not) to an executable, e.g. ./code/projects/php-src/sapi/cli/php.
if [ -z "$PHP_EXE" ]; then
	PHP_EXE=php
fi
PHP=$(which "$PHP_EXE")
PHPUNIT=$(which phpunit)

set -e

_XDEBUG_CONFIG=$XDEBUG_CONFIG
unset XDEBUG_CONFIG

function print_syntax {
	echo -e "Syntax: ./autotest.sh [dbconfigname] [testfile]\n" >&2
	echo -e "\t\"dbconfigname\" can be one of: $DBCONFIGS" >&2
	echo -e "\t\"testfile\" is the name of a test file, for example lib/template.php" >&2
	echo -e "\nExample: ./autotest.sh sqlite lib/template.php" >&2
	echo "will run the test suite from \"tests/lib/template.php\"" >&2
	echo -e "\nIf no arguments are specified, all tests will be run with all database configs" >&2
}

if [ -x "$PHP" ]; then
	echo "Using PHP executable $PHP"
else
	echo "Could not find PHP executable $PHP_EXE" >&2
	exit 3
fi

if ! [ -x "$PHPUNIT" ]; then
	echo "phpunit executable not found, please install phpunit version >= 6.5" >&2
	exit 3
fi

# PHPUnit might also be installed via a facade binary script
if [[ "$PHPUNIT" =~ \.phar$ ]]; then
  PHPUNIT=( "$PHP" "$PHPUNIT" )
else
  PHPUNIT=( "$PHPUNIT" )
fi

PHPUNIT_VERSION=$($PHPUNIT --version | cut -d" " -f2)
PHPUNIT_MAJOR_VERSION=$(echo "$PHPUNIT_VERSION" | cut -d"." -f1)
PHPUNIT_MINOR_VERSION=$(echo "$PHPUNIT_VERSION" | cut -d"." -f2)

if ! [ "$PHPUNIT_MAJOR_VERSION" -gt 6 -o \( "$PHPUNIT_MAJOR_VERSION" -eq 6 -a "$PHPUNIT_MINOR_VERSION" -ge 5 \) ]; then
	echo "phpunit version >= 6.5 required. Version found: $PHPUNIT_VERSION" >&2
	exit 4
fi

if ! [ \( -w config -a ! -f config/config.php \) -o \( -f config/config.php -a -w config/config.php \) ]; then
	echo "Please enable write permissions on config and config/config.php" >&2
	exit 1
fi

if [ "$1" ]; then
	FOUND=0
	for DBCONFIG in $DBCONFIGS; do
		if [ "$1" = "$DBCONFIG" ]; then
			FOUND=1
			break
		fi
	done
	if [ $FOUND = 0 ]; then
		echo -e "Unknown database config name \"$1\"\n" >&2
		print_syntax
		exit 2
	fi
fi
if [ "$PRIMARY_STORAGE_CONFIG" ]; then
	FOUND=0
	for PSC in $PRIMARY_STORAGE_CONFIGS; do
		if [ "$PRIMARY_STORAGE_CONFIG" = "$PSC" ]; then
			FOUND=1
			break
		fi
	done
	if [ $FOUND = 0 ]; then
		echo -e "Unknown primary storage config name \"$PRIMARY_STORAGE_CONFIG\"\n" >&2
		print_syntax
		exit 2
	fi
else
	PRIMARY_STORAGE_CONFIG="local"
fi

# check for the presence of @since in all OCP methods
$PHP build/OCPSinceChecker.php

# Back up existing (dev) config if one exists and backup not already there
if [ -f config/config.php ] && [ ! -f config/config-autotest-backup.php ]; then
	mv config/config.php config/config-autotest-backup.php
fi

function cleanup_config {

	if [ ! -z "$DOCKER_CONTAINER_ID" ]; then
		echo "Kill the docker $DOCKER_CONTAINER_ID"
		docker stop "$DOCKER_CONTAINER_ID"
		docker rm -f "$DOCKER_CONTAINER_ID"
	fi

	cd "$BASEDIR"
	if [ "$PRIMARY_STORAGE_CONFIG" == "swift" ] ; then
		echo "Kill the swift docker"
		tests/objectstore/stop-swift-ceph.sh
	fi
	# Restore existing config
	if [ -f config/config-autotest-backup.php ]; then
		mv config/config-autotest-backup.php config/config.php
	fi
	# Remove autotest config
	if [ -f config/autoconfig.php ]; then
		rm config/autoconfig.php
	fi
	# Remove autotest swift storage config
	if [ -f config/autotest-storage-swift.config.php ]; then
		rm config/autotest-storage-swift.config.php
	fi
	# Remove autotest redis config
	if [ -f config/redis.config.php ]; then
		rm config/redis.config.php
	fi
	# Remove mysqlmb4.config.php
	rm -f config/mysqlmb4.config.php
}

# restore config on exit
trap cleanup_config EXIT

# use tmpfs for datadir - should speedup unit test execution
if [ -d /dev/shm ]; then
  DATADIR=/dev/shm/data-autotest$EXECUTOR_NUMBER
else
  DATADIR=$BASEDIR/data-autotest
fi

echo "Using database $DATABASENAME"

function execute_tests {
	DB=$1
	echo "Setup environment for $DB testing on $PRIMARY_STORAGE_CONFIG storage ..."
	# back to root folder
	cd "$BASEDIR"

	# revert changes to tests/data
	git checkout tests/data

	# reset data directory
	rm -rf "$DATADIR"
	mkdir "$DATADIR"

	if [ "$PRIMARY_STORAGE_CONFIG" == "swift" ] ; then
		tests/objectstore/start-swift-ceph.sh
		cp tests/objectstore/swift.config.php config/autotest-storage-swift.config.php
	fi
	cp tests/preseed-config.php config/config.php

	if [ "$ENABLE_REDIS" == "true" ] ; then
		cp tests/redis.config.php config/redis.config.php
	elif [ "$ENABLE_REDIS_CLUSTER" == "true" ] ; then
		cp tests/redis-cluster.config.php config/redis.config.php
	fi

	_DB=$DB

	# drop database
	if [ "$DB" == "mysql" ] ; then
		if [ ! -z "$USEDOCKER" ] ; then
			echo "Fire up the mysql docker"
			DOCKER_CONTAINER_ID=$(docker run \
				-v $BASEDIR/tests/docker/mariadb:/etc/mysql/conf.d \
				-e MYSQL_ROOT_PASSWORD=owncloud \
				-e MYSQL_USER="$DATABASEUSER" \
				-e MYSQL_PASSWORD=owncloud \
				-e MYSQL_DATABASE="$DATABASENAME" \
				-d mysql)
			DATABASEHOST=$(docker inspect --format="{{.NetworkSettings.IPAddress}}" "$DOCKER_CONTAINER_ID")

		else
			if [ -z "$DRONE" ] ; then # no need to drop the DB when we are on CI
				if [ "mysql" != "$(mysql --version | grep -o mysql)" ] ; then
					echo "Your mysql binary is not provided by mysql"
					echo "To use the docker container set the USEDOCKER environment variable"
					exit -1
				fi
				mysql -u "$DATABASEUSER" -powncloud -e "DROP DATABASE IF EXISTS $DATABASENAME" -h $DATABASEHOST || true
			else
				DATABASEHOST=mysql
			fi
		fi
		echo "Waiting for MySQL initialisation ..."
		if ! apps/files_external/tests/env/wait-for-connection $DATABASEHOST 3306 300; then
			echo "[ERROR] Waited 300 seconds, no response" >&2
			exit 1
		fi
	fi
	if [ "$DB" == "mysqlmb4" ] ; then
		if [ ! -z "$USEDOCKER" ] ; then
			echo "Fire up the mysql docker"
			DOCKER_CONTAINER_ID=$(docker run \
				-v $BASEDIR/tests/docker/mysqlmb4:/etc/mysql/conf.d \
				-e MYSQL_ROOT_PASSWORD=owncloud \
				-e MYSQL_USER="$DATABASEUSER" \
				-e MYSQL_PASSWORD=owncloud \
				-e MYSQL_DATABASE="$DATABASENAME" \
				-d mysql:5.7 \
				--innodb_large_prefix=true \
				--innodb_file_format=barracuda \
				--innodb_file_per_table=true)

			DATABASEHOST=$(docker inspect --format="{{.NetworkSettings.IPAddress}}" "$DOCKER_CONTAINER_ID")

		else
			if [ -z "$DRONE" ] ; then # no need to drop the DB when we are on CI
				if [ "mysql" != "$(mysql --version | grep -o mysql)" ] ; then
					echo "Your mysql binary is not provided by mysql"
					echo "To use the docker container set the USEDOCKER environment variable"
					exit -1
				fi
				mysql -u "$DATABASEUSER" -powncloud -e "DROP DATABASE IF EXISTS $DATABASENAME" -h $DATABASEHOST || true
			else
				DATABASEHOST=mysqlmb4
			fi
		fi

		echo "Waiting for MySQL(utf8mb4) initialisation ..."

		if ! apps/files_external/tests/env/wait-for-connection $DATABASEHOST 3306 300; then
			echo "[ERROR] Waited 300 seconds, no response" >&2
			exit 1
		fi
		sleep 1

		echo "MySQL(utf8mb4)  is up."
		_DB="mysql"

		cp tests/docker/mysqlmb4.config.php config
	fi
	if [ "$DB" == "mariadb" ] ; then
		if [ ! -z "$USEDOCKER" ] ; then
			echo "Fire up the mariadb docker"
			DOCKER_CONTAINER_ID=$(docker run \
				-v $BASEDIR/tests/docker/mariadb:/etc/mysql/conf.d \
				-e MYSQL_ROOT_PASSWORD=owncloud \
				-e MYSQL_USER="$DATABASEUSER" \
				-e MYSQL_PASSWORD=owncloud \
				-e MYSQL_DATABASE="$DATABASENAME" \
				-d mariadb)
			DATABASEHOST=$(docker inspect --format="{{.NetworkSettings.IPAddress}}" "$DOCKER_CONTAINER_ID")

			echo "Waiting for MariaDB initialisation ..."
			if ! apps/files_external/tests/env/wait-for-connection $DATABASEHOST 3306 300; then
				echo "[ERROR] Waited 300 seconds, no response" >&2
				exit 1
			fi

			echo "MariaDB is up."

		else
			if [ -z "$DRONE" ] ; then # no need to drop the DB when we are on CI
				if [ "MariaDB" != "$(mysql --version | grep -o MariaDB)" ] ; then
					echo "Your mysql binary is not provided by MariaDB"
					echo "To use the docker container set the USEDOCKER environment variable"
					exit -1
				fi
				mysql -u "$DATABASEUSER" -powncloud -e "DROP DATABASE IF EXISTS $DATABASENAME" -h $DATABASEHOST || true
			else
				DATABASEHOST=mariadb
			fi
		fi

		echo "Waiting for MariaDB initialisation ..."
		if ! apps/files_external/tests/env/wait-for-connection $DATABASEHOST 3306 300; then
			echo "[ERROR] Waited 300 seconds, no response" >&2
			exit 1
		fi

		#Reset _DB to mysql since that is what we use internally
		_DB="mysql"
	fi
	if [ "$DB" == "pgsql" ] ; then
		if [ ! -z "$USEDOCKER" ] ; then
			echo "Fire up the postgres docker"
			DOCKER_CONTAINER_ID=$(docker run -e POSTGRES_USER="$DATABASEUSER" -e POSTGRES_PASSWORD=owncloud -d postgres)
			DATABASEHOST=$(docker inspect --format="{{.NetworkSettings.IPAddress}}" "$DOCKER_CONTAINER_ID")

			echo "Waiting for Postgres initialisation ..."

			# grep exits on the first match and then the script continues
			docker logs -f "$DOCKER_CONTAINER_ID" 2>&1 | grep -q "database system is ready to accept connections"

			echo "Postgres is up."
		else
			if [ ! -z "$DRONE" ] ; then
				DATABASEHOST="postgres-$POSTGRES"
			fi
			echo "Waiting for Postgres to be available ..."
			if ! apps/files_external/tests/env/wait-for-connection $DATABASEHOST 5432 60; then
				echo "[ERROR] Waited 60 seconds for $DATABASEHOST, no response" >&2
				exit 1
			fi
			echo "Give it 10 additional seconds ..."
			sleep 10

			if [ -z "$DRONE" ] ; then # no need to drop the DB when we are on CI
				dropdb -U "$DATABASEUSER" "$DATABASENAME" || true
			fi
		fi
	fi
	if [ "$DB" == "oci" ] ; then
		echo "Fire up the oracle docker"
		DOCKER_CONTAINER_ID=$(docker run -d deepdiver/docker-oracle-xe-11g)
		DATABASEHOST=$(docker inspect --format="{{.NetworkSettings.IPAddress}}" "$DOCKER_CONTAINER_ID")

		echo "Waiting for Oracle initialization ... "

		# Try to connect to the OCI host via sqlplus to ensure that the connection is already running
		for i in {1..48}
		do
			if sqlplus "autotest/owncloud@(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(Host=$DATABASEHOST)(Port=1521))(CONNECT_DATA=(SID=XE)))" < /dev/null | grep 'Connected to'; then
				break;
			fi
			sleep 5
		done

		DATABASEUSER=autotest
		DATABASENAME='XE'
	fi

	# trigger installation
	echo "Installing ...."
	"$PHP" ./occ maintenance:install -vvv --database="$_DB" --database-name="$DATABASENAME" --database-host="$DATABASEHOST" --database-user="$DATABASEUSER" --database-pass=owncloud --database-table-prefix=oc_ --admin-user="$ADMINLOGIN" --admin-pass=admin --data-dir="$DATADIR"

	#test execution
	echo "Testing with $DB ..."
	cd tests
	rm -rf "coverage-html-$DB"
	mkdir "coverage-html-$DB"
	"$PHP" -f enable_all.php | grep -i -C9999 error && echo "Error during setup" && exit 101
	if [[ "$_XDEBUG_CONFIG" ]]; then
		export XDEBUG_CONFIG=$_XDEBUG_CONFIG
	fi
	GROUP=''
	if [ "$TEST_SELECTION" == "QUICKDB" ]; then
		GROUP='--group DB --exclude-group=SLOWDB'
	fi
	if [ "$TEST_SELECTION" == "DB" ]; then
		GROUP='--group DB,SLOWDB'
	fi
	if [ "$TEST_SELECTION" == "NODB" ]; then
		GROUP='--exclude-group DB,SLOWDB'
	fi
	if [ "$TEST_SELECTION" == "PRIMARY-s3" ]; then
		GROUP='--group PRIMARY-s3'
	fi
	if [ "$TEST_SELECTION" == "PRIMARY-azure" ]; then
		GROUP='--group PRIMARY-azure'
	fi
	if [ "$TEST_SELECTION" == "PRIMARY-swift" ]; then
		GROUP='--group PRIMARY-swift'
	fi

	COVER=''
	if [ -z "$NOCOVERAGE" ]; then
		COVER="--coverage-clover autotest-clover-$DB.xml --coverage-html coverage-html-$DB"
	else
		echo "No coverage"
	fi

	echo "${PHPUNIT[@]}" --configuration phpunit-autotest.xml $GROUP $COVER --log-junit "autotest-results-$DB.xml" "$2" "$3"
	"${PHPUNIT[@]}" --configuration phpunit-autotest.xml $GROUP $COVER --log-junit "autotest-results-$DB.xml" "$2" "$3"
	RESULT=$?

	if [ "$PRIMARY_STORAGE_CONFIG" == "swift" ] ; then
		cd ..
		echo "Kill the swift docker"
		tests/objectstore/stop-swift-ceph.sh
	fi

	if [ ! -z "$DOCKER_CONTAINER_ID" ] ; then
		echo "Kill the docker $DOCKER_CONTAINER_ID"
		docker stop $DOCKER_CONTAINER_ID
		docker rm -f $DOCKER_CONTAINER_ID
		unset DOCKER_CONTAINER_ID
	fi
}

#
# start test execution
#
if [ -z "$1" ]
  then
	# run all known database configs
	for DBCONFIG in $DBCONFIGS; do
		execute_tests "$DBCONFIG"
	done
else
	FILENAME="$2"
	if [ ! -z "$2" ] && [ ! -f "tests/$FILENAME" ] && [ "${FILENAME:0:2}" != "--" ]; then
		FILENAME="../$FILENAME"
	fi
	execute_tests "$1" "$FILENAME" "$3"
fi

#
# NOTES on mysql:
#  - CREATE DATABASE oc_autotest;
#  - CREATE USER 'oc_autotest'@'localhost' IDENTIFIED BY 'owncloud';
#  - grant all on oc_autotest.* to 'oc_autotest'@'localhost';
#
#  - for parallel executor support with EXECUTOR_NUMBER=0:
#  - CREATE DATABASE oc_autotest0;
#  - CREATE USER 'oc_autotest0'@'localhost' IDENTIFIED BY 'owncloud';
#  - grant all on oc_autotest0.* to 'oc_autotest0'@'localhost';
#
# NOTES on pgsql:
#  - su - postgres
#  - createuser -P oc_autotest (enter password and enable superuser)
#  - to enable dropdb I decided to add following line to pg_hba.conf
#    (this is not the safest way but I don't care for the testing machine):
# local	all	all	trust
#
#  - for parallel executor support with EXECUTOR_NUMBER=0:
#  - createuser -P oc_autotest0 (enter password and enable superuser)
#
# NOTES on oci:
#  - it's a pure nightmare to install Oracle on a Linux-System
#  - DON'T TRY THIS AT HOME!
#  - if you really need it: we feel sorry for you
#

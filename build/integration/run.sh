#!/usr/bin/env bash

OC_PATH=../../
OCC=${OC_PATH}occ
TAGS=""
if [ "$1" = "--tags" ]; then
	TAGS="--tags=$2"

	shift 2
fi
SCENARIO_TO_RUN=$1
HIDE_OC_LOGS=$2

INSTALLED=$($OCC status | grep installed: | cut -d " " -f 5)

if [ "$INSTALLED" == "true" ]; then
    # Disable bruteforce protection because the integration tests do trigger them
    $OCC config:system:set auth.bruteforce.protection.enabled --value false --type bool
    # Allow local remote urls otherwise we can not share
    $OCC config:system:set allow_local_remote_servers --value true --type bool
else
    if [ "$SCENARIO_TO_RUN" != "setup_features/setup.feature" ]; then
        echo "Nextcloud instance needs to be installed" >&2
        exit 1
    fi
fi
NC_DATADIR=$($OCC config:system:get datadirectory)

composer install

# avoid port collision on jenkins - use $EXECUTOR_NUMBER
if [ -z "$EXECUTOR_NUMBER" ]; then
    EXECUTOR_NUMBER=0
fi
PORT=$((8080 + $EXECUTOR_NUMBER))
echo $PORT
php -S localhost:$PORT -t ../.. &
PHPPID=$!
echo $PHPPID

# The federated server is started and stopped by the tests themselves
PORT_FED=$((8180 + $EXECUTOR_NUMBER))
echo $PORT_FED
export PORT_FED

export TEST_SERVER_URL="http://localhost:$PORT/ocs/"
export TEST_SERVER_FED_URL="http://localhost:$PORT_FED/ocs/"

if [ "$INSTALLED" == "true" ]; then

    #Enable external storage app
    $OCC app:enable files_external user_ldap

    mkdir -p work/local_storage
    OUTPUT_CREATE_STORAGE=`$OCC files_external:create local_storage local null::null -c datadir=$PWD/work/local_storage`

    ID_STORAGE=`echo $OUTPUT_CREATE_STORAGE | tr ' ' '\n' | tail -n1`

    $OCC files_external:option $ID_STORAGE enable_sharing true

fi

vendor/bin/behat --strict -f junit -f pretty $TAGS $SCENARIO_TO_RUN
RESULT=$?

kill $PHPPID

if [ "$INSTALLED" == "true" ]; then

    $OCC files_external:delete -y $ID_STORAGE

    #Disable external storage app
    $OCC app:disable files_external user_ldap
fi

if [ -z $HIDE_OC_LOGS ]; then
	tail "${NC_DATADIR}/nextcloud.log"
fi

echo "runsh: Exit code: $RESULT"
exit $RESULT

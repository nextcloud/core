#!/usr/bin/env bash
#
RESULT=0

bash ./build/autoloaderchecker.sh
RESULT=$(($RESULT+$?))
php ./build/translation-checker.php
RESULT=$(($RESULT+$?))
php ./build/triple-dot-checker.php
RESULT=$(($RESULT+$?))
php ./build/htaccess-checker.php
RESULT=$(($RESULT+$?))
bash ./build/ca-bundle-checker.sh
RESULT=$(($RESULT+$?))

dataDirCreated=0
if ! [ -e data/ ]; then
    dataDirCreated=1
    echo "Create directory 'data/'"
    mkdir data/
fi
for app in $(find "apps/" -mindepth 1 -maxdepth 1 -type d -exec basename {} \;); do
    echo "Testing $app"
    if
        [ "$app" == "dav" ] || \
        [ "$app" == "encryption" ] || \
        [ "$app" == "federatedfilesharing" ] || \
        [ "$app" == "files" ] || \
        [ "$app" == "files_external" ] || \
        [ "$app" == "files_sharing" ] || \
        [ "$app" == "files_trashbin" ] || \
        [ "$app" == "files_versions" ] || \
        [ "$app" == "provisioning_api" ] || \
        [ "$app" == "settings" ] || \
        [ "$app" == "updatenotification" ] || \
        [ "$app" == "user_ldap" ]
    then
        ./occ app:check-code --skip-checkers "$app"
    else
        ./occ app:check-code "$app"
    fi
    RESULT=$(($RESULT+$?))
done;
if [ $dataDirCreated == 1 ]; then
    echo "Delete created directory 'data/'"
    rm -rf data/
fi

php ./build/files-checker.php
RESULT=$(($RESULT+$?))

exit $RESULT

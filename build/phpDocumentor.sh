#!/bin/bash

# Use a cached version of phpDocumentor for now since there is no release yet
# and the github actions artifacts might disappear
# This phar is downloaded from https://github.com/phpDocumentor/phpDocumentor/actions/runs/221599704

wget https://bitgrid.net/~jus/phpDocumentor.phar

mkdir -p api/

php7.4 phpDocumentor.phar -t "./api" -d "./lib/public" --title="Nextcloud PHP API ($BRANCH)"

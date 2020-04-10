#!/usr/bin/env bash

REPODIR=`git rev-parse --show-toplevel`

cd $REPODIR

# Settings
node node_modules/handlebars/bin/handlebars -n OC.Settings.Templates  apps/settings/js/templates -f apps/settings/js/templates.js

# Systemtags
node node_modules/handlebars/bin/handlebars -n OC.SystemTags.Templates core/js/systemtags/templates -f core/js/systemtags/templates.js

# Files app
node node_modules/handlebars/bin/handlebars -n OCA.Files.Templates apps/files/js/templates -f apps/files/js/templates.js

# Sharing
node node_modules/handlebars/bin/handlebars -n OCA.Sharing.Templates apps/files_sharing/js/templates -f apps/files_sharing/js/templates.js

# Files external
node node_modules/handlebars/bin/handlebars -n OCA.Files_External.Templates apps/files_external/js/templates -f apps/files_external/js/templates.js

if [[ $(git diff --name-only) ]]; then
    echo "Please submit your compiled handlebars templates"
    echo
    git diff
    exit 1
fi


echo "All up to date! Carry on :D"
exit 0

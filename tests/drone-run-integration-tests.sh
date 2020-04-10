#!/bin/bash

echo "========================="
echo "= List of changed files ="
echo "========================="
git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA
echo "========================="

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | wc -l) -eq 0 ]] && echo "No files are modified => merge commit" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c ".php$") -gt 0 ]] && echo "PHP files are modified" && exit 0

[[ $(git diff --name-only origin/$DRONE_TARGET_BRANCH...$DRONE_COMMIT_SHA | grep -c "^build/integration/") -gt 0 ]] && echo "Integration test files are modified" && exit 0

exit 1

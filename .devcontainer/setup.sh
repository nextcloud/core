#!/bin/bash
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/../" >/dev/null 2>&1 && pwd )"

cd $DIR/
git submodule update --init

# Codespace config
cp .devcontainer/codespace.config.php config/codespace.config.php

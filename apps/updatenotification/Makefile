app_name=updatenotification

project_dir=$(CURDIR)/../$(app_name)
build_dir=$(CURDIR)/build
source_dir=$(build_dir)/$(app_name)

all: dev-setup build-js-production

dev-setup: clean clean-dev npm-init

npm-init:
	npm install

npm-update:
	npm update

build-js:
	npm run dev

build-js-production:
	npm run build

watch-js:
	npm run watch

clean:
	rm -f js/$(app_name).js
	rm -f js/$(app_name).js.map
	rm -rf $(build_dir)

clean-dev:
	rm -rf node_modules

package: clean build-js-production
	mkdir -p $(source_dir)
	rsync -a \
	--exclude=/build \
	--exclude=/docs \
	--exclude=/js-src \
	--exclude=/l10n/.tx \
	--exclude=/tests \
	--exclude=/.git \
	--exclude=/.github \
	--exclude=/CONTRIBUTING.md \
	--exclude=/issue_template.md \
	--exclude=/README.md \
	--exclude=/SECURITY.md \
	--exclude=/.gitignore \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/.drone.yml \
	--exclude=/node_modules \
	--exclude=/npm-debug.log \
	--exclude=/package.json \
	--exclude=/package-lock.json \
	--exclude=/Makefile \
	$(project_dir)/ $(source_dir)

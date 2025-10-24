# Makefile for building the project
#
# SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
# SPDX-FileCopyrightText: 2025 Markus Katharina Brechtel <markus.katharina.brechtel@thengo.net>
# SPDX-License-Identifier: AGPL-3.0-or-later
#
app_name=repos
project_dir=$(CURDIR)
build_dir=$(project_dir)/build
appstore_dir=$(build_dir)/appstore
sign_dir=$(build_dir)/sign
package_name=$(app_name)
cert_dir=$(HOME)/.nextcloud/certificates
version=21.0.0-dev.1

jssources=$(wildcard src/**/*) $(wildcard css/*/*)  $(wildcard css/*)
othersources=$(wildcard appinfo/*) $(wildcard css/*/*) $(wildcard controller/*/*) $(wildcard templates/*/*) $(wildcard log/*/*)

all: js

clean:
	rm -rf $(sign_dir)
	rm -rf $(build_dir)/$(app_name)-$(version).tar.gz
	rm -rf node_modules
	rm -rf js/

node_modules: package.json
	npm install

.PHONY: js
js: node_modules $(jssources)
	npm run build

.PHONY: dev
dev: node_modules
	npm run dev

.PHONY: watch
watch: node_modules
	npm run watch

release: appstore create-tag

create-tag:
	git tag -s -a v$(version) -m "Tagging the $(version) release."
	git push origin v$(version)

appstore: clean js
	mkdir -p $(sign_dir)
	rsync -a \
	--exclude=/.babelrc.js \
	--exclude=/.drone.yml \
	--exclude=/.git \
	--exclude=/.gitattributes \
	--exclude=/.github \
	--exclude=/.gitignore \
	--exclude=/.php_cs.dist \
	--exclude=/.scrutinizer.yml \
	--exclude=/.travis.yml \
	--exclude=/.tx \
	--exclude=/CONTRIBUTING.md \
	--exclude=/Makefile \
	--exclude=/README.md \
	--exclude=/build/sign \
	--exclude=/composer.json \
	--exclude=/composer.lock \
	--exclude=/docs \
	--exclude=/issue_template.md \
	--exclude=/l10n/l10n.pl \
	--exclude=/node_modules \
	--exclude=/package-lock.json \
	--exclude=/package.json \
	--exclude=/postcss.config.js \
	--exclude=/src \
	--exclude=/tests \
	--exclude=/translationfiles \
	--exclude=/tsconfig.json \
	--exclude=/vendor \
	--exclude=/rollup.config.js \
	$(project_dir)/ $(sign_dir)/$(app_name)
	tar -czf $(build_dir)/$(app_name)-$(version).tar.gz \
		-C $(sign_dir) $(app_name)
	@if [ -f $(cert_dir)/$(app_name).key ]; then \
		echo "Signing package…"; \
		openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(build_dir)/$(app_name)-$(version).tar.gz | openssl base64; \
	fi


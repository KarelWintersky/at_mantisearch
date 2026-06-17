#!/usr/bin/make
PROJECT  = at_manticsearch

help:
	@perl -e '$(HELP_ACTION)' $(MAKEFILE_LIST)

dchr:		##@development Publish release
	@dch --controlmaint --release --distribution unstable

dchv:		##@development Append release
	@export DEBEMAIL="wombat@fontanka.ru" && \
	export DEBFULLNAME="Wombat" && \
	echo "$(YELLOW)------------------ Previous version header: ------------------$(GREEN)" && \
	head -n 3 debian/changelog && \
	echo "$(YELLOW)--------------------------------------------------------------$(RESET)" && \
	read -p "Next version: " VERSION && \
	dch --controlmaint -v $$VERSION

dump_manticore:
	mysqldump -h0 -P9306 manticore rt_at_works > rt_at_works.sql

dump_mysql:
	mysqldump at_search works > at_search_works.sql

manticore:
	mysql --host 127.0.0.1 --port=9306 --prompt='Manticore> '


# --- local rules ---
clear_smarty_cache:           ##@tools Clear SMARTY cache
	@echo Clearing SMARTY cache...
	@php $(PATH_PROJECT)/admin.tools/tool.clear_cache.php --smarty
	@echo Ok.

clear_nginx_cache:            ##@tools Clear NGINX cache
	@echo Clearing NGINX cache...
	@php $(PATH_PROJECT)/admin.tools/tool.clear_cache.php --nginx
	@echo Ok.

clear_redis_cache:            ##@tools Clear REDIS cache
	@echo Clearing REDIS cache...
	@php $(PATH_PROJECT)/admin.tools/tool.clear_cache.php --redis
	@echo Ok.

# ------------------------------------------------
# Add the following 'help' target to your makefile, add help text after each target name starting with '\#\#'
# A category can be added with @category
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
WHITE  := $(shell tput -Txterm setaf 7)
RESET  := $(shell tput -Txterm sgr0)
HELP_ACTION = \
	%help; while(<>) { push @{$$help{$$2 // 'options'}}, [$$1, $$3] if /^([a-zA-Z\-_]+)\s*:.*\#\#(?:@([a-zA-Z\-]+))?\s(.*)$$/ }; \
	print "usage: make [target]\n\n"; for (sort keys %help) { print "${WHITE}$$_:${RESET}\n"; \
	for (@{$$help{$$_}}) { $$sep = " " x (32 - length $$_->[0]); print "  ${YELLOW}$$_->[0]${RESET}$$sep${GREEN}$$_->[1]${RESET}\n"; }; \
	print "\n"; }

# -eof-


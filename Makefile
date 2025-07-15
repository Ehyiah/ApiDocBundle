ENV?= dev
BRANCH?= dev
DOCKER_COMPOSE?= docker compose
EXEC?= $(DOCKER_COMPOSE) exec
PHP?= $(EXEC) php_api_doc_bundle
COMPOSER?= $(PHP) composer
PHPUNIT?= $(PHP) php bin/phpunit

help:  ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage:\n  make \033[36m\033[0m\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) } ' $(MAKEFILE_LIST)

##@ Installation
install: build up vendor down ## Install new project with docker

##@ Update project when pulling

update: vendor up clear-logs ## update after checkout

##@ Docker
build: ## Build the images with local current user replication in PHP container
	$(DOCKER_COMPOSE) build --build-arg APP_USER_ID=$$(id -u) --build-arg APP_USER=$$(id -u -n)

build-no-cache: ## Build the images with local current user replication in PHP container
	$(DOCKER_COMPOSE) build --no-cache --build-arg APP_USER_ID=$$(id -u) --build-arg APP_USER=$$(id -u -n)

up: ## Up the images
	$(DOCKER_COMPOSE) up -d --remove-orphans

down: ## Down the images
	$(DOCKER_COMPOSE) down

clear-logs: ## clear application logs
	@if [ -d ./var/log ]; \
	then\
  		rm -R ./var/log;\
  	fi

## don't forget this if you dont want makefile to get files with this name
.PHONY: build up down clear-logs update install reload build-no-cache

##@ Composer
vendor: ## Install composer dependencies
	$(COMPOSER) install

.PHONY: vendor

##@ Utility
bash-php: ## Launch PHP bash
	$(PHP) bash

.PHONY: bash-php

##@ CI
ci: ## Launch csfixer and phpstan and javascript quality check
	$(COMPOSER) ci

fixer-php: ## Launch csfixer no dry
	$(COMPOSER) phpcsfixer

.PHONY: fixer-php ci

##@ PHP TEST Commands
phptests:  ## Execute phpunit
	$(PHP) vendor/bin/phpunit

.PHONY: phptests

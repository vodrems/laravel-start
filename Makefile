HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
export HOST_UID HOST_GID

DC := docker compose
ARTISAN := $(DC) exec app php artisan

.PHONY: help init up down build restart logs shell test purge

help: ## Show available commands
	@grep -E '^[a-z]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "}; {printf "  \033[36m%-10s\033[0m %s\n", $$1, $$2}'

init: ## First run: build images, start containers, install dependencies, migrate
	@test -f .env || cp .env.example .env
	$(DC) build
	@# Worker and scheduler start only after dependencies and tables exist.
	$(DC) up -d mysql rabbitmq mailpit app
	$(DC) exec app composer install --no-interaction
	@grep -q '^APP_KEY=base64' .env || $(ARTISAN) key:generate
	$(ARTISAN) migrate --force
	$(DC) up -d
	@echo ""
	@echo "App:      http://localhost:$${APP_PORT:-8080}"
	@echo "RabbitMQ: http://localhost:15672"
	@echo "Mailpit:  http://localhost:8025"

up: ## Start containers
	$(DC) up -d

down: ## Stop containers
	$(DC) down

build: ## Rebuild the PHP image
	$(DC) build

restart: ## Restart the queue worker and scheduler (after code changes)
	$(DC) restart queue scheduler

logs: ## Follow logs of the app, queue worker and scheduler
	$(DC) logs -f app queue scheduler

shell: ## Open a shell in the app container
	$(DC) exec app bash

test: ## Run the test suite
	$(ARTISAN) test

purge: ## Delete expired files right now (normally done by the scheduler)
	$(ARTISAN) files:purge-expired

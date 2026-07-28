.DEFAULT_GOAL := help

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start the whole stack
	docker compose up -d --build

down: ## Stop the stack
	docker compose down

logs: ## Tail logs
	docker compose logs -f

migrate: ## Run database migrations
	docker compose exec app1 php bin/console doctrine:migrations:migrate --no-interaction

consume: ## Run a Kafka consumer (handlers) in the foreground
	docker compose exec app1 php bin/console messenger:consume events_kafka -vv

expire: ## Block transactions stuck in PENDING for too long
	docker compose exec app1 php bin/console app:transactions:expire

test: ## Run the test suite
	docker compose exec app1 php bin/phpunit

sh: ## Shell into the primary app container
	docker compose exec app1 sh

deploy: ## Deploy the merged branch to the VPS over SSH (pull + rebuild + migrate)
	ssh user@host "cd /www/micropayment && bash deploy.sh"

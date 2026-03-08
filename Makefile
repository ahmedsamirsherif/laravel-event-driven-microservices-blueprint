.DEFAULT_GOAL := help

.PHONY: up down logs status clean build demo-monitor \
        test test-hr test-hub test-unit test-feature test-integration test-arch test-contract \
        hr-shell hub-shell fresh migrate replay-events nuke verify help


# ── Infrastructure ────────────────────────────────────────────────────────
up:
	cp -n .env.example .env 2>/dev/null || true
	docker compose up -d --build

down:
	docker compose down

clean:
	docker compose down -v --remove-orphans

build:
	docker compose build

demo-monitor:
	./scripts/demo-monitor.sh

logs:
	docker compose logs -f

logs-hr:
	docker compose logs -f hr-service

logs-hub:
	docker compose logs -f hub-service hub-consumer

status:
	docker compose ps

# ── Testing ───────────────────────────────────────────────────────────────
test: test-hr test-hub

test-hr:
	docker compose exec -T hr-service php artisan test --no-ansi

test-hub:
	docker compose exec -T hub-service php artisan test --no-ansi

test-unit:
	docker compose exec -T hr-service php artisan test --testsuite=Unit --no-ansi
	docker compose exec -T hub-service php artisan test --testsuite=Unit --no-ansi

test-feature:
	docker compose exec -T hr-service php artisan test --testsuite=Feature --no-ansi
	docker compose exec -T hub-service php artisan test --testsuite=Feature --no-ansi

test-integration:
	docker compose exec -T hub-service php artisan test --testsuite=Integration --no-ansi

test-arch:
	docker compose exec -T hr-service php artisan test --testsuite=Arch --no-ansi
	docker compose exec -T hub-service php artisan test --testsuite=Arch --no-ansi

test-contract:
	docker compose exec -T hr-service php artisan test --testsuite=Contract --no-ansi
	docker compose exec -T hub-service php artisan test --testsuite=Contract --no-ansi

# ── Development ───────────────────────────────────────────────────────────
hr-shell:
	docker compose exec hr-service sh

hub-shell:
	docker compose exec hub-service sh

migrate:
	docker compose exec hr-service php artisan migrate
	docker compose exec hub-service php artisan migrate

fresh:
	docker compose exec hr-service php artisan migrate:fresh --seed
	docker compose exec hub-service php artisan migrate:fresh

replay-events:
	docker compose exec hub-service php artisan events:replay

nuke:
	@echo "\033[1;31m⚠  Tearing down everything…\033[0m"
	docker compose down -v --remove-orphans
	@echo "\033[1;33m⏳ Rebuilding & starting services…\033[0m"
	docker compose up -d --build
	@echo "\033[1;33m⏳ Waiting for databases…\033[0m"
	@sleep 5
	docker compose exec -T hr-service php artisan migrate:fresh --seed
	docker compose exec -T hub-service php artisan migrate:fresh
	docker compose exec -T hr-service php artisan optimize:clear
	docker compose exec -T hub-service php artisan optimize:clear
	docker compose exec -T hr-service sh -c 'rm -f storage/logs/*.log'
	docker compose exec -T hub-service sh -c 'rm -f storage/logs/*.log'
	@echo "\033[1;32m✔  Clean slate ready.\033[0m"

# ── Verify ────────────────────────────────────────────────────────────────
verify:
	curl -sf http://localhost:8001/api/health | python3 -m json.tool
	curl -sf http://localhost:8002/api/health | python3 -m json.tool

help:
	@grep -E '^[a-zA-Z_-]+:' $(MAKEFILE_LIST) | grep -v '^\s*#' | awk -F: '{printf "\033[36m%-20s\033[0m\n", $$1}'

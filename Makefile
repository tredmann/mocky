.PHONY: build up down restart logs shell

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose down && docker compose up -d

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

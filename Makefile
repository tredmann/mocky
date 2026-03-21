.PHONY: build up down down-volumes restart logs shell

build:
	docker compose build

up:
	docker compose up -d

down:
	docker compose down

down-volumes:
	docker compose down -v

restart:
	docker compose down && docker compose up -d

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

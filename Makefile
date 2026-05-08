up:
	docker compose up -d --build

install:
	docker compose exec app composer install

download:
	curl -fL https://www.ukrposhta.ua/files/shares/out/postindex.zip -o storage/imports/postindex.zip

fresh:
	docker compose down -v
	docker compose up -d --build
	docker compose exec app composer install
	$(MAKE) download
	@echo "Waiting for database..."
	@until docker compose exec -T app bash -c "echo >/dev/tcp/db/3306" 2>/dev/null; do printf '.'; sleep 1; done
	@echo " ready!"
	docker compose exec app php bin/import.php

down:
	docker compose down

restart:
	docker compose down
	docker compose up -d --build

bash:
	docker compose exec app bash

logs:
	docker compose logs -f

import:
	docker compose exec app php bin/import.php

db:
	docker compose exec db mariadb -u postindex -psecret postindex

test:
	docker compose exec app ./vendor/bin/phpunit --testdox

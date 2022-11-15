init: docker-down docker-pull docker-up
init-clean: docker-down docker-pull docker-up-clean
up: docker-up
down: docker-down
restart: docker-down docker-up

docker-up:
	docker-compose -f docker-compose.yml -p ns-crawler up --build -d
	@echo "OK!"
docker-up-clean:
	docker-compose -f docker-compose.yml build --no-cache
	docker-compose -f docker-compose.yml -p ns-crawler up -d
docker-pull:
	docker-compose -f docker-compose.yml pull
docker-down:
	docker-compose -f docker-compose.yml down --remove-orphans
# Down with clear volumes
docker-down-clear:
	docker-compose -f docker-compose.yml down -v --remove-orphans
docker-ssh-php:
	docker exec -ti ns-crawler-services-php /bin/sh
docker-ssh-mysql:
	docker exec -ti ns-crawler-services-mysql /bin/sh

run:
	docker exec -ti ns-crawler-services-php /usr/local/bin/php /app/ns-crawler.php
run-silent:
	docker exec -ti ns-crawler-services-php /usr/local/bin/php /app/ns-crawler.php silent
migrate-db:
	docker exec -ti ns-crawler-services-php /bin/sh -c 'cd "migrations" && /usr/local/bin/php /root/.composer/vendor/bin/doctrine migrations:migrate'
composer-update:
	docker exec -ti ns-crawler-services-php /bin/sh -c '/usr/local/bin/php /usr/local/bin/composer install -o'
deploy:
	git pull origin master
	make composer-update
	make migrate-db
init:
	apt install docker
	make docker-up
	make composer-update
	make migrate-db


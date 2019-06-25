dev:
	$(MAKE) composer
	$(MAKE) env

composer:
	composer install

env:
	cp .env.example .env

fixture:
	docker exec -it $(shell docker-compose ps -q api) php bin/fixture.php

phpunit:
	docker exec -it $(shell docker-compose ps -q api) vendor/bin/phpunit

phpstan:
	docker exec -it $(shell docker-compose ps -q api) vendor/bin/phpstan analyse -l 6 -c config/phpstan/phpstan.neon src

phpcs:
	docker exec -it $(shell docker-compose ps -q api) vendor/bin/phpcs --standard=config/phpcs/ruleset.xml

deploy-build:
	GIT_REVISION=${GIT_REVISION} docker-compose -f ./config/ecs/api.yml build
	docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend:${GIT_REVISION}

deploy:
	ecs-cli configure --region ap-northeast-2 --cluster ridi-pay-backend-${APP_ENV}
	APP_ENV=${APP_ENV} GIT_REVISION=${GIT_REVISION} ecs-cli compose \
		-f docker-compose.prod.yml \
		--project-name api \
		service up \
		--force-deployment \
		--target-group-arn ${API_TARGET_GROUP_ARN} \
		--container-name api \
		--container-port 80
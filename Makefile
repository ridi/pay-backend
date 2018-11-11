dev:
	$(MAKE) composer
	$(MAKE) mkcert

composer:
	composer install

mkcert:
	mkcert -install
	[ -d config/certs ] || mkdir -p config/certs
	cd config/certs/ && mkcert api.pay.local.ridi.io \
	&& mv api.pay.local.ridi.io.pem api.pay.local.ridi.io.crt \
	&& mv api.pay.local.ridi.io-key.pem api.pay.local.ridi.io.key

phpunit:
	docker exec -it api vendor/bin/phpunit

phpcs:
	docker exec -it api vendor/bin/phpcs --standard=docs/lint/php/ruleset.xml

deploy-build:
	GIT_REVISION=$(shell git rev-parse HEAD) docker-compose -f ./config/ecs/api.yml build
	docker-compose -f ./config/ecs/fluentd.yml build
	docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend:$(shell git rev-parse HEAD)
	docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend-fluentd:latest

deploy:
	ecs-cli configure --region ap-northeast-2 --cluster ridi-pay-backend-${APP_ENV}
	ecs-cli compose -f ./config/ecs/fluentd.yml \
		--ecs-params ./config/ecs/ecs-params-fluentd.yml \
		--project-name fluentd \
		service up \
		--timeout 10 \
		--force-deployment \
		--target-group-arn ${FLUENTD_TARGET_GROUP_ARN} \
		--container-name fluentd \
		--container-port 24224
	APP_ENV=${APP_ENV} GIT_REVISION=$(shell git rev-parse HEAD) ecs-cli compose -f ./config/ecs/api.yml \
		--ecs-params ./config/ecs/ecs-params-api.yml \
		--project-name api \
		service up \
		--timeout 10 \
		--force-deployment \
		--target-group-arn ${API_TARGET_GROUP_ARN} \
		--container-name api \
		--container-port 80
	

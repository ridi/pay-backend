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

fixture:
	docker exec -it api php bin/fixture.php

phpunit:
	docker exec -it api vendor/bin/phpunit

phpcs:
	docker exec -it api vendor/bin/phpcs --standard=docs/lint/php/ruleset.xml

deploy-build:
	GIT_REVISION=${GIT_REVISION} docker-compose -f ./config/ecs/api.yml build
	GIT_REVISION=${GIT_REVISION} docker-compose -f ./config/ecs/fluentd.yml build
	docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend:${GIT_REVISION}
	docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend-fluentd:${GIT_REVISION}

deploy:
	ecs-cli configure --region ap-northeast-2 --cluster ridi-pay-backend-${APP_ENV}
	GIT_REVISION=${GIT_REVISION} ecs-cli compose -f ./config/ecs/fluentd.yml \
		--ecs-params ./config/ecs/ecs-params-fluentd.yml \
		--project-name fluentd \
		service up \
		--timeout 10 \
		--force-deployment \
		--target-group-arn ${FLUENTD_TARGET_GROUP_ARN} \
		--container-name fluentd \
		--container-port 24224
	APP_ENV=${APP_ENV} GIT_REVISION=${GIT_REVISION} FLUENTD_ADDRESS=${FLUENTD_ADDRESS} ecs-cli compose \
		-f ./config/ecs/api.yml \
		--ecs-params ./config/ecs/ecs-params-api.yml \
		--project-name api \
		service up \
		--timeout 10 \
		--force-deployment \
		--target-group-arn ${API_TARGET_GROUP_ARN} \
		--container-name api \
		--container-port 80
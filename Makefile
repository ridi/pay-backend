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

deploy-setup:
	docker start ridi-pay-backend-deploy || docker run --privileged -itd \
		-v $(shell pwd):/app \
		-e "AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}" \
		-e "AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}" \
		--name ridi-pay-backend-deploy \
		docker:dind
	docker exec -it \
		ridi-pay-backend-deploy \
		/bin/sh -c "apk --update add py3-pip curl \
			&& pip3 install awscli docker-compose urllib3==1.21.1 \
			&& curl -o /usr/local/bin/ecs-cli https://s3.amazonaws.com/amazon-ecs-cli/ecs-cli-linux-amd64-latest \
			&& chmod +x /usr/local/bin/ecs-cli \
			&& $$(aws ecr get-login --no-include-email --region ap-northeast-2)"

deploy-build:
	docker exec -it -w /app \
		ridi-pay-backend-deploy \
		/bin/sh -c "GIT_REVISION=$(shell git rev-parse HEAD) docker-compose -f docker-compose.build.yml build \
		&& docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend:$(shell git rev-parse HEAD) \
		&& docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-logger:latest"

deploy:
	docker exec -it -w /app \
		ridi-pay-backend-deploy \
		/bin/sh -c "ecs-cli configure --region ap-northeast-2 --cluster ridi-pay-backend-${APP_ENV} \
		&& APP_ENV=${APP_ENV} GIT_REVISION=$(shell git rev-parse HEAD) ecs-cli compose -f docker-compose.build.yml --project-name api \
			service up \
			--target-group-arn ${TARGET_GROUP_ARN} \
			--container-name api \
			--container-port 80"
	

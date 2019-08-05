dev:
	composer install
	$(MAKE) env

env:
	cp .env.example .env

fixture:
	docker exec -it $(shell docker-compose ps -q api) php bin/fixture.php

test:
	docker-compose -f docker-compose.test.yml up --build --exit-code-from api
	docker-compose -f docker-compose.test.yml down

build:
	GIT_REVISION=${GIT_REVISION} docker-compose -f docker-compose.prod.yml build
	docker push 023315198496.dkr.ecr.ap-northeast-2.amazonaws.com/ridi/pay-backend:${GIT_REVISION}

deploy:
	ecs-cli configure --region ap-northeast-2 --cluster ridi-pay-backend-${APP_ENV} --default-launch-type FARGATE
	APP_ENV=${APP_ENV} \
	GIT_REVISION=${GIT_REVISION} \
	AP_NORTHEAST_2A_PRIVATE_SUBNET_ID=${AP_NORTHEAST_2A_PRIVATE_SUBNET_ID} \
	AP_NORTHEAST_2C_PRIVATE_SUBNET_ID=${AP_NORTHEAST_2C_PRIVATE_SUBNET_ID} \
	SECURITY_GROUP_ID=${SECURITY_GROUP_ID} \
	ecs-cli compose -f docker-compose.prod.yml service up \
	--force-deployment \
	--target-group-arn ${TARGET_GROUP_ARN} \
	--container-name api \
	--container-port 80
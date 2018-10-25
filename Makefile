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

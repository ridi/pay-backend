dev:
	$(MAKE) composer
	$(MAKE) mkcert

composer:
	composer install

mkcert:
	mkcert -install
	[ -d config/certs ] || mkdir -p config/certs
	cd config/certs/ && mkcert pay.local.ridi.io *.pay.local.ridi.io

phpunit:
	docker exec -it apache vendor/bin/phpunit

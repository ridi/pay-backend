dev:
	$(MAKE) composer-dev
	$(MAKE) mkcert

composer:
	composer install --no-dev --optimize-autoloader

composer-dev:
	composer install

mkcert:
	mkcert -install
	[ -d config/certs ] || mkdir config/certs
	site=$(subst SITE=,,$(shell grep SITE= .env)) && \
	cd config/certs/ && mkcert $$site $(join '*.', $$site);

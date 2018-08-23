dev:
	$(MAKE) composer
	$(MAKE) mkcert

composer:
	composer install

mkcert:
	mkcert -install
	[ -d config/certs ] || mkdir config/certs
	site=$(subst SITE=,,$(shell grep SITE= .env)) && \
	cd config/certs/ && mkcert $$site $(join '*.', $$site);

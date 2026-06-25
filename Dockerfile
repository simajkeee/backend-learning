#syntax=docker/dockerfile:1

FROM dunglas/frankenphp:1-php8.4 AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

ARG REDIS_VERSION=6.2.0

RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends \
		file \
		git \
		curl \
		ca-certificates
	install-php-extensions \
		@composer \
		intl \
		zip
	rm -rf /var/lib/apt/lists/*
EOF

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

RUN install-php-extensions pdo_pgsql

RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends $PHPIZE_DEPS

	curl -fsSL "https://github.com/phpredis/phpredis/archive/refs/tags/${REDIS_VERSION}.tar.gz" -o /tmp/redis.tar.gz
	mkdir -p /tmp/redis
	tar -xzf /tmp/redis.tar.gz -C /tmp/redis --strip-components=1

	cd /tmp/redis
	phpize
	./configure
	make -j"$(nproc)"
	make install
	docker-php-ext-enable redis

	cd /app
	rm -rf /tmp/redis /tmp/redis.tar.gz /var/lib/apt/lists/*
EOF

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD php -r 'exit(false === @file_get_contents("http://localhost:2019/metrics", context: stream_context_create(["http" => ["timeout" => 5]])) ? 1 : 0);'

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off
ENV FRANKENPHP_WORKER_CONFIG=watch

ARG XDEBUG_VERSION=3.5.3

RUN <<-EOF
	mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

	apt-get update
	apt-get install -y --no-install-recommends $PHPIZE_DEPS

	curl -fsSL "https://xdebug.org/files/xdebug-${XDEBUG_VERSION}.tgz" -o /tmp/xdebug.tgz
	mkdir -p /tmp/xdebug
	tar -xzf /tmp/xdebug.tgz -C /tmp/xdebug --strip-components=1

	cd /tmp/xdebug
	phpize
	./configure --enable-xdebug
	make -j"$(nproc)"
	make install
	docker-php-ext-enable xdebug

	cd /app
	rm -rf /tmp/xdebug /tmp/xdebug.tgz /var/lib/apt/lists/*

	useradd -m -s /bin/bash nonroot
	git config --system --add safe.directory /app
EOF

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

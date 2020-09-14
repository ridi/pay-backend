[![CI](https://github.com/ridi/pay-backend/workflows/CI/badge.svg?branch=master)](https://github.com/ridi/pay-backend/actions?query=workflow%3ACI+branch%3Amaster)
[![codecov](https://codecov.io/gh/ridi/pay-backend/branch/master/graph/badge.svg?token=g1l9Hrb9zH)](https://codecov.io/gh/ridi/pay-backend)

## Reporting a vulnerability

[Link](https://github.com/ridi/pay-backend/security/policy)

## Settings for development environment

#### 0. Requirements
- PHP 7.2
```
brew install php@7.2
```

- [Composer](https://getcomposer.org/doc/00-intro.md#globally)

- [Docker](https://store.docker.com/editions/community/docker-ce-desktop-mac)

- [traefik](https://github.com/ridi/traefik/blob/master/README.md)

#### 1. Make
```
make dev
```

#### 2. Build docker containers
```
docker-compose up [--build] 
```

#### 3. Make database fixtures
```
make fixture
```

#### 4. Try to connect to https://pay-api.local.ridi.io 

## API document
[Link](https://s3.ap-northeast-2.amazonaws.com/ridi-pay-backend-api-doc/master/api.html)

## Deploy
- We are using github actions and releases for deploy. For details, please refer to `.github/workflows`.

[![Build Status](https://travis-ci.com/ridi/pay-backend.svg?token=xPAQFHxECFy2kMpwAYno&branch=master)](https://travis-ci.com/ridi/pay-backend)
[![codecov](https://codecov.io/gh/ridi/pay-backend/branch/master/graph/badge.svg?token=g1l9Hrb9zH)](https://codecov.io/gh/ridi/pay-backend)

## Security bug report

All security bugs in RIDI Pay should be reported by email to security@ridi.com.

## Settings for development environment

#### 0. Requirements
- PHP 7.2
```
brew install php@7.2
```

- [Composer](https://getcomposer.org/doc/00-intro.md#globally)

- mkcert
```
brew install mkcert
brew install nss # If you use Firefox browser, please install.
```

- [Docker](https://store.docker.com/editions/community/docker-ce-desktop-mac)

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

#### 4. Add the following line into your `/etc/hosts`
```
127.0.0.1 api.pay.local.ridi.io
```

#### 5. Try to connect to https://api.pay.local.ridi.io 

## API document
[Link](https://s3.ap-northeast-2.amazonaws.com/ridi-pay-backend-api-doc/api.html)

## Deploy
- We are using travis ci and github releases for deploy. For details, please refer to `.travis.yml`.

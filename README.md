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
  
- [aws-vault](https://github.com/99designs/aws-vault)
  - `brew cask install aws-vault`
  - For setting your AWS profile, please refer to [Usage](https://github.com/99designs/aws-vault#usage).

#### 1. Make
```
make dev
```

#### 2. Build docker containers
```
aws-vault exec <profile_name> -- docker-compose up [--build] 
```

#### 3. Add the following line into your `/etc/hosts`
```
127.0.0.1 api.pay.local.ridi.io
```

#### 4. Try to connect to https://api.pay.local.ridi.io

## Overriding environment variables
- In AWS Parameter Store, environment variables are already pre-defined for your convenience.
- You can override the pre-defined environment variables if you add a `.env` file in the project root directory. 

## API document
[Link](https://s3.ap-northeast-2.amazonaws.com/ridi-pay-backend-api-doc/api.html)

## Deploy
- We are using travis ci and github releases for deploy. For details, please refer to `.travis.yml`.

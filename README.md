[![Build Status](https://travis-ci.com/ridi/pay-backend.svg?token=xPAQFHxECFy2kMpwAYno&branch=master)](https://travis-ci.com/ridi/pay-backend)
[![codecov](https://codecov.io/gh/ridi/pay-backend/branch/master/graph/badge.svg?token=g1l9Hrb9zH)](https://codecov.io/gh/ridi/pay-backend)

## 개발 환경 구성

#### 0. Requirements
- PHP 7.2
```
brew install php@7.2
```

- [Composer](https://getcomposer.org/doc/00-intro.md#globally)

- mkcert
```
brew install mkcert
brew install nss # Firefox 사용 시
```

- [Docker](https://store.docker.com/editions/community/docker-ce-desktop-mac)
  
- [aws-vault](https://github.com/99designs/aws-vault)
  - `brew cask install aws-vault`
  - AWS Profile 설정: [Usage](https://github.com/99designs/aws-vault#usage) 참고

#### 1. Make
```
make dev
```

#### 2. Docker 컨테이너 생성
```
aws-vault exec <profile_name> -- docker-compose up [--build] 
```

#### 3. 로컬 `/etc/hosts`에 아래 내용 추가
```
127.0.0.1 api.pay.local.ridi.io
```

#### 4. https://api.pay.local.ridi.io 접속

## Overriding environment variables
- 개발 환경의 편의를 위해 AWS Parameter Store 내 Environment Variables가 정의되어 있습니다.
- 프로젝트 root 경로에 `.env` 파일을 추가하여 이 Environment Variables를 Overriding 할 수 있습니다. 

## API 문서
[링크](https://s3.ap-northeast-2.amazonaws.com/ridi-pay-backend-api-doc/api.html)

## Deploy
1.
```
git clone git@github.com:ridi/pay-backend.git pay-backend-deploy
cd pay-backend-deploy
aws-vault exec <profile_name> -- make deploy-build
```

2.
- test 환경
```
aws-vault exec <profile_name> -- bash -c "APP_ENV=test FLUENTD_TARGET_GROUP_ARN={FLUENTD_TARGET_GROUP_ARN} API_TARGET_GROUP_ARN={API_TARGET_GROUP_ARN} FLUENTD_ADDRESS={FLUENTD_NLB_DNS_NAME}:24224 make deploy"
```

- staging 환경
```
aws-vault exec <profile_name> -- bash -c "APP_ENV=staging FLUENTD_TARGET_GROUP_ARN={FLUENTD_TARGET_GROUP_ARN} API_TARGET_GROUP_ARN={API_TARGET_GROUP_ARN} FLUENTD_ADDRESS={FLUENTD_NLB_DNS_NAME}:24224 make deploy"
```

- prod 환경
```
aws-vault exec <profile_name> -- bash -c "APP_ENV=prod FLUENTD_TARGET_GROUP_ARN={FLUENTD_TARGET_GROUP_ARN} API_TARGET_GROUP_ARN={API_TARGET_GROUP_ARN} FLUENTD_ADDRESS={FLUENTD_NLB_DNS_NAME}:24224 make deploy"
```

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
127.0.0.1 pay.local.ridi.io
```

#### 4. https://pay.local.ridi.io 접속

## Overriding environment variables
- 개발 환경의 편의를 위해 AWS Parameter Store 내 Environment Variables가 정의되어 있습니다.
- 프로젝트 root 경로에 `.env` 파일을 추가하여 이 Environment Variables를 Overriding 할 수 있습니다. 

## API 문서
[링크](`https://gitlab.ridi.io/pay/ridi-pay/raw/master/docs/api/swagger.yaml`)의 내용을 [Swagger Editor](https://editor.swagger.io)에 붙여넣기 하면 API 문서가 렌더링 됩니다.

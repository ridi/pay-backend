## 0. Requirements
- PHP 7.2
```
brew install php72
```

- Composer
  - https://getcomposer.org/doc/00-intro.md#globally

- mkcert
```
brew install mkcert
brew install nss # Firefox 사용 시
```

- Docker
  - https://store.docker.com/editions/community/docker-ce-desktop-mac

#### 1. Make
```
make dev
```

#### 2. Docker 컨테이너 생성
```
docker-compose up [--build] 
```
#### 3. 로컬 `/etc/hosts`에 아래 내용 추가
```
127.0.0.1 pay.local.ridi.io
```

#### 4. pay.local.ridi.io 접속

## API 문서
`https://gitlab.ridi.io/pay/ridi-pay/raw/master/docs/api/swagger.yaml`의 내용을 `https://editor.swagger.io/`에 붙여넣기하면 API 문서가 렌더링 됩니다.

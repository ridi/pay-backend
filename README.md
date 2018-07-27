## 개발 환경 셋팅
#### 0. PHP 7.2 설치
```
brew install php72
```

#### 1. php 의존성 패키지 설치
```
composer install
# composer 전역 설치는 https://getcomposer.org/doc/00-intro.md#globally 페이지를 참고합니다.
```

#### 2. Docker 컨테이너 생성
```
docker-compose up [--build] 
```
#### 3. 로컬 `/etc/hosts`에 아래 내용 추가
```
127.0.0.1 pay.dev.ridi.com
```

#### 4. pay.dev.ridi.com 접속
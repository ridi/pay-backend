FROM node:lts

ENV LC_ALL=C.UTF-8

COPY . /app
WORKDIR /app

RUN yarn install --frozen-lockfile
RUN yarn run build

CMD ["yarn", "start:prod"]

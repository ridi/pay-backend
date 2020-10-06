import 'reflect-metadata';
import express, { Application } from 'express'
import bodyParser from 'body-parser';
import { createConnection } from 'typeorm';
import Sentry from '@sentry/node';
import { HealthCheckController } from 'src/controllers/HealthCheckController';
import { ready } from 'libsodium-wrappers';

export enum Environment {
  PROD = 'prod',
  DEV = 'dev',
  LOCAL = 'local',
}

export class App {
  constructor() {
    App.configureSentry();
  }

  public async start() {
    await createConnection({
      type: 'mysql',
      url: process.env.DATABASE_URL,
    });

    await ready;

    const app: Application = express();
    app.use(bodyParser.json());

    app.get('/health-check', HealthCheckController.handle);

    const port = 80;
    const server = app.listen(port, () => {
      console.info(`Listening on port ${port}`);
    });

    // Configurations for AWS ELB
    server.keepAliveTimeout = 65 * 1000;
    server.headersTimeout = 70 * 1000;
  }

  private static configureSentry() {
    if (process.env.APP_ENV !== Environment.LOCAL) {
      Sentry.init({
        dsn: process.env.SENTRY_DSN,
        environment: process.env.APP_ENV,
      });
    }
  }
}

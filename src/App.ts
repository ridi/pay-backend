import 'reflect-metadata';
import express, { Application } from 'express'
import bodyParser from 'body-parser';
import { createConnection } from 'typeorm';
import Sentry from '@sentry/node';
import { HealthCheckController } from 'src/controllers/HealthCheckController';
import { ready } from 'libsodium-wrappers';
import morgan from 'morgan';
import { UserController } from 'src/controllers/UserController';
import { User } from 'src/user/domain/entities/User';
import { PaymentMethod } from 'src/user/domain/entities/PaymentMethod';
import { Card } from 'src/user/domain/entities/Card';
import { CardIssuer } from 'src/user/domain/entities/CardIssuer';
import { JwtAuthorizationMiddleware } from 'src/middlewares/JwtAuthorizationMiddleware';
import { FirstPartyService } from 'src/libraries/JwtAuthorizer';

export enum Environment {
  PROD = 'prod',
  DEV = 'dev',
  LOCAL = 'local',
}

export class App {
  private readonly expressServer: Application;

  constructor() {
    this.expressServer = express();
    this.expressServer.use(bodyParser.json());
    this.expressServer.use(morgan('combined'));

    this.expressServer.get('/health-check', HealthCheckController.handle);

    this.expressServer.delete(
      '/users/:uIdx',
      JwtAuthorizationMiddleware([FirstPartyService.STORE]),
      UserController.deleteUser
    );
  }

  public async init() {
    await createConnection({
      type: 'mysql',
      url: process.env.DATABASE_URL,
      entities: [
        Card,
        CardIssuer,
        PaymentMethod,
        User,
      ]
    });

    await ready;

    App.configureSentry();
  }

  public start() {
    const port = 80;
    const server = this.expressServer.listen(port, () => {
      console.info(`Listening on port ${port}`);
    });

    // Configurations for AWS ELB
    server.keepAliveTimeout = 65 * 1000;
    server.headersTimeout = 70 * 1000;
  }

  public getExpressServer(): Application {
    return this.expressServer;
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

import 'reflect-metadata';
import supertest from 'supertest';
import { FirstPartyService, JwtAuthorizer } from 'src/libraries/JwtAuthorizer';
import path from 'path';
import fs from 'fs';
import express, { Application } from 'express';
import { JwtAuthorizationMiddleware } from 'src/middlewares/JwtAuthorizationMiddleware';
import { DummyController } from './Dummy/DummyController';

const getRequestHeaders = () => {
  const privateKey = fs.readFileSync(path.join(__dirname, 'Dummy', 'dummy.key')).toString();
  const publicKey = fs.readFileSync(path.join(__dirname, 'Dummy', 'dummy.key.pub')).toString();

  process.env.STORE_TO_RIDI_PAY_PRIVATE_KEY = privateKey;
  process.env.STORE_TO_RIDI_PAY_PUBLIC_KEY = publicKey;
  process.env.RIDISELECT_TO_RIDI_PAY_PRIVATE_KEY = privateKey;
  process.env.RIDISELECT_TO_RIDI_PAY_PUBLIC_KEY = publicKey;

  const jwt = JwtAuthorizer.sign(FirstPartyService.STORE, FirstPartyService.RIDI_PAY);
  const jwtWithNotAllowedIss = JwtAuthorizer.sign(FirstPartyService.RIDISELECT, FirstPartyService.RIDI_PAY);

  return [
    [{ Authorization: `Bearer ${jwt}` }, 200],
    [{ Authorization: `Bearer ${jwtWithNotAllowedIss}` }, 401],
    [{ Authorization: 'Bearer abcde' }, 401],
    [{ Authorization: '12345' }, 401],
  ];
};

test.each(getRequestHeaders())(
  'Test jwt authorization for a request including headers(%o)',
  async (requestHeaders: any, expectedHttpStatusCode: any) => {
    const app: Application = express();
    app.get(
      '/jwt-authorization',
      JwtAuthorizationMiddleware([FirstPartyService.STORE]),
      DummyController.jwtAuthorization
    );

    const result = await supertest(app).get('/jwt-authorization').set(requestHeaders);
    expect(result.status).toEqual(expectedHttpStatusCode);
  },
);

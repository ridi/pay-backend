import { getConnection } from 'typeorm';
import Sentry from '@sentry/node';
import { NextFunction, Request, Response } from "express";

export class HealthCheckController {
  public static async handle(req: Request, res: Response, next: NextFunction) {
    try {
      const connection = getConnection();
      const result = await connection.query('SELECT 1');
      if (!result) {
        throw new Error('MariaDB connection is not working.');
      }
    } catch (e) {
      Sentry.captureException(e);

      return res.json({
        code: 'INTERNAL_SERVER_ERROR',
        message: '오류가 발생했습니다.',
      });
    }

    return res.json();
  }
}

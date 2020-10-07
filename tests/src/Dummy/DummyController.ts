import { NextFunction, Request, Response } from 'express';

export class DummyController {
  public static jwtAuthorization(req: Request, res: Response, next: NextFunction): Response {
    return res.json();
  }
}

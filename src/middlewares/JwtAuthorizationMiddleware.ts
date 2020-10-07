import { FirstPartyService, JwtAuthorizer } from 'src/libraries/JwtAuthorizer';
import {
  Request, Response, NextFunction,
} from 'express';

export function JwtAuthorizationMiddleware(isses: FirstPartyService[]) {
  return (request: Request, response: Response, next: NextFunction) => {
    try {
      const authorizationHeader = request.header('Authorization');
      if (authorizationHeader === undefined) {
        throw new Error("Authorization header doesn't exist.");
      }

      const splitAuthorizationHeader = authorizationHeader.split(' ');
      if (splitAuthorizationHeader.length !== 2
        || splitAuthorizationHeader[0] !== 'Bearer'
        || splitAuthorizationHeader[1] === ''
      ) {
        throw new Error('Invalid authorization header.');
      }

      const jwt = splitAuthorizationHeader[1];
      JwtAuthorizer.verify(jwt, isses, FirstPartyService.RIDI_PAY);
    } catch (e) {
      response
        .status(401)
        .json({
          code: 'INVALID_JWT',
          message: e.message,
        });
    }

    next();
  };
}

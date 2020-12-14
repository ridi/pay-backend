import Sentry from '@sentry/node';
import { NextFunction, Request, Response } from 'express';
import { UserAppService } from 'src/user/application/service/UserAppService';
import { UserLeavedError } from 'src/user/domain/errors/UserLeavedError';
import { UserNotFoundError } from 'src/user/domain/errors/UserNotFoundError';

export class UserController {
  public static async deleteUser(req: Request, res: Response, next: NextFunction) {
    try {
      await UserAppService.deleteUser(parseInt(req.params.uIdx));
    } catch (e) {
      if (e instanceof UserLeavedError) {
        return res
          .status(403)
          .json({
            code: 'LEAVED_USER',
            message: '탈퇴한 사용자입니다.',
          });
      } else if (e instanceof UserNotFoundError) {
        return res
          .status(404)
          .json({
            code: 'NOT_FOUND_USER',
            message: '이용자가 아닙니다.',
          });
      } else {
        Sentry.captureException(e);

        return res
          .status(500)
          .json({
            code: 'INTERNAL_SERVER_ERROR',
            message: '오류가 발생했습니다.',
          });
      }
    }

    return res.json();
  }
}

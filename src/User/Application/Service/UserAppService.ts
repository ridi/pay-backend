import { EntityManager, getConnection, getCustomRepository } from 'typeorm';
import { UserRepository } from 'src/user/domain/Repository/UserRepository';
import { UserNotFoundError } from 'src/user/domain/errors/UserNotFoundError';
import { UserLeavedError } from 'src/user/domain/errors/UserLeavedError';
import { PaymentMethodAppService } from 'src/user/application/service/PaymentMethodAppService';
import { User } from 'src/user/domain/entities/User';

export class UserAppService {
  public static async createUser(uIdx: number) {
    const user = User.create(uIdx);
    await getCustomRepository(UserRepository).save(user);
  }

  public static async deleteUser(uIdx: number) {
    await getConnection().transaction(async (transactionalEntityManager: EntityManager) => {
      const user = await transactionalEntityManager.getCustomRepository(UserRepository).findOneByUidx(uIdx);
      if (user === undefined) {
        throw new UserNotFoundError();
      }
      if (user.isLeaved()) {
        throw new UserLeavedError();
      }

      user.leave();
      await transactionalEntityManager.save(user);

      await PaymentMethodAppService.deleteAllPaymentMethods(uIdx);
    });
  }
}

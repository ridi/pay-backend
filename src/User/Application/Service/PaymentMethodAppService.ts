import { PaymentMethodRepository } from 'src/user/domain/Repository/PaymentMethodRepository';
import { EntityManager, getConnection, getCustomRepository } from 'typeorm';

export class PaymentMethodAppService {
  public static async deleteAllPaymentMethods(uIdx: number): Promise<void> {
    const paymentMethods = await getCustomRepository(PaymentMethodRepository).findAllPaymentMethods(uIdx);
    await getConnection().transaction(async (transactionalEntityManager: EntityManager) => {
      for await (const paymentMethod of paymentMethods) {
        paymentMethod.delete();
        await transactionalEntityManager.save(paymentMethod);
      }
    });
  }
}

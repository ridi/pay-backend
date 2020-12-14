import { EntityRepository, IsNull, Repository } from 'typeorm';
import { PaymentMethod } from 'src/user/domain/entities/PaymentMethod';
import { PgAppService } from 'src/pg/application/service/PgAppService';
import { PgDto } from 'src/pg/application/dto/PgDto';

@EntityRepository(PaymentMethod)
export class PaymentMethodRepository extends Repository<PaymentMethod> {
  public findAllPaymentMethods(uIdx: number) {
    return this.find({ where: { uIdx, deletedAt: IsNull() } });
  }

  public async findAvailablePaymentMethods(uIdx: number) {
    const pgs = await PgAppService.findPayablePgs();
    const pgIds = pgs.map((pg: PgDto) => pg.id);

    return this.createQueryBuilder('pm')
      .leftJoinAndSelect('pm.cards', 'c', 'c.pg_id IN (:pgIds)', { pgIds })
      .innerJoinAndSelect('c.card_issuer', 'ci')
      .where('pm.u_idx = :uIdx', { uIdx })
      .andWhere('pm.deleted_at IS NULL')
      .getMany();
  }
}

import { EntityRepository, In, Repository } from 'typeorm';
import { Pg, PgStatus } from 'src/pg/domain/entities/Pg';

@EntityRepository(Pg)
export class PgRepository extends Repository<Pg> {
  public findPayablePgs() {
    return this.find({
      where: {
        status: In([PgStatus.ACTIVE.valueOf(), PgStatus.KEPT.valueOf()]),
      },
    });
  }
}

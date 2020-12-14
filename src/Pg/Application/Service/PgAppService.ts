import { Pg } from 'src/pg/domain/entities/Pg';
import { PgRepository } from 'src/pg/domain/repositories/PgRepository';
import { PgDto } from 'src/pg/application/dto/PgDto';
import { getCustomRepository } from "typeorm";

export class PgAppService {
  public static async findPayablePgs() {
    const pgs = await getCustomRepository(PgRepository).findPayablePgs();
    return pgs.map((pg: Pg) => new PgDto(pg));
  }
}

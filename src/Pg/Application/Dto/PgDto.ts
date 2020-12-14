import { Pg } from 'src/pg/domain/entities/Pg';

export class PgDto {
  public id: number

  public name: string

  public constructor(pg: Pg) {
    this.id = pg.getId();
    this.name = pg.getName();
  }
}

import {
  Column, Entity, PrimaryGeneratedColumn,
} from 'typeorm';

export enum PgStatus {
  ACTIVE = 'ACTIVE',
  INACTIVE = 'INACTIVE',
  KEPT = 'KEPT',
}

export enum PgName {
  KCP = 'KCP',
}

@Entity()
export class Pg {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @Column({
    name: 'name',
    type: 'string',
    length: 16,
    comment: 'PG사 이름',
  })
  name: PgName;

  @Column({
    name: 'status',
    type: 'enum',
    enum: PgStatus,
    default: PgStatus.ACTIVE,
    comment: 'ACTIVE: 사용, INACTIVE: 미사용, KEPT: 기존 유저는 사용, 신규 유저는 미사용',
  })
  status: PgStatus;

  @Column({
    name: 'updated_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
    onUpdate: 'CURRENT_TIMESTAMP',
  })
  updatedAt: Date;

  public constructor(name: PgName) {
    this.name = name;
    this.status = PgStatus.ACTIVE;
    this.updatedAt = new Date();
  }
}

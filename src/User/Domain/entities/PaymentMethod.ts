import {
  Column, Entity, OneToMany, PrimaryGeneratedColumn,
} from 'typeorm';
import { Card } from 'src/user/domain/entities/Card';
import { v4 } from 'uuid';

export enum PaymentMethodType {
  CARD = 'CARD',
}

@Entity()
export class PaymentMethod {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @Column({
    name: 'uuid',
    type: 'string',
    comment: 'id 값 유추 방지를 위한 uuid',
  })
  uuid: string;

  @Column({
    name: 'u_idx',
    type: 'int',
    unsigned: true,
    comment: 'user.u_idx',
  })
  uIdx: number;

  @Column({
    name: 'name',
    type: 'enum',
    enum: PaymentMethodType,
    default: PaymentMethodType.CARD,
    comment: '결제 수단',
  })
  type: PaymentMethodType;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
    comment: '결제 수단 등록 시각',
  })
  createdAt: Date;

  @Column({
    name: 'leaved_at',
    type: 'datetime',
    comment: '결제 수단 삭제 시각',
  })
  deletedAt: Date | null;

  @OneToMany(type => Card, card => card.paymentMethod)
  cards: Card[];

  public constructor(
    uIdx: number,
    type: PaymentMethodType,
  ) {
    this.uuid = v4();
    this.uIdx = uIdx;
    this.type = type;
    this.createdAt = new Date();
    this.deletedAt = null;
    this.cards = [];
  }
}

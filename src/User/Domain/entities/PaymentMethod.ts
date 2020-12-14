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
    comment: 'id 값 유추 방지를 위한 uuid',
  })
  uuid!: string;

  @Column({
    name: 'u_idx',
    type: 'int',
    unsigned: true,
    comment: 'user.u_idx',
  })
  uIdx!: number;

  @Column({
    name: 'type',
    type: 'enum',
    enum: PaymentMethodType,
    default: PaymentMethodType.CARD,
    comment: '결제 수단',
  })
  type!: PaymentMethodType;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
    comment: '결제 수단 등록 시각',
  })
  createdAt!: Date;

  @Column({
    name: 'deleted_at',
    type: 'datetime',
    comment: '결제 수단 삭제 시각',
  })
  deletedAt?: Date | null;

  @OneToMany(type => Card, card => card.paymentMethod)
  cards!: Card[];

  public static create(
    uIdx: number,
    type: PaymentMethodType,
  ) {
    const paymentMethod = new PaymentMethod();
    paymentMethod.uuid = v4();
    paymentMethod.uIdx = uIdx;
    paymentMethod.type = type;
    paymentMethod.createdAt = new Date();
    paymentMethod.deletedAt = null;
    paymentMethod.cards = [];

    return paymentMethod;
  }

  public delete() {
    this.deletedAt = new Date();
  }
}

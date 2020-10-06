import {
  Column, Entity, PrimaryGeneratedColumn,
} from 'typeorm';
import { v4 } from 'uuid';

@Entity()
export class Subscription {
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
    name: 'payment_method_id',
    type: 'int',
    unsigned: true,
    comment: 'payment_method.id',
  })
  paymentMethodId: number;

  @Column({
    name: 'partner_id',
    type: 'int',
    unsigned: true,
    comment: 'partner.id',
  })
  partnerId: number;

  @Column({
    name: 'product_name',
    type: 'string',
    length: 32,
    comment: '구독 상품',
  })
  productName: string;

  @Column({
    name: 'subscribed_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
  })
  subscribedAt: Date;

  @Column({
    name: 'unsubscribed_at',
    type: 'datetime',
    nullable: true,
  })
  unsubscribedAt: Date | null;

  public constructor(paymentMethodId: number, partnerId: number, productName: string) {
    this.uuid = v4();
    this.paymentMethodId = paymentMethodId;
    this.partnerId = partnerId;
    this.productName = productName;
    this.subscribedAt = new Date();
    this.unsubscribedAt = null;
  }
}

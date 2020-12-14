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
    comment: 'id 값 유추 방지를 위한 uuid',
  })
  uuid!: string;

  @Column({
    name: 'payment_method_id',
    type: 'int',
    unsigned: true,
    comment: 'payment_method.id',
  })
  paymentMethodId!: number;

  @Column({
    name: 'partner_id',
    type: 'int',
    unsigned: true,
    comment: 'partner.id',
  })
  partnerId!: number;

  @Column({
    name: 'product_name',
    type: 'varchar',
    length: 32,
    comment: '구독 상품',
  })
  productName!: string;

  @Column({
    name: 'subscribed_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
  })
  subscribedAt!: Date;

  @Column({
    name: 'unsubscribed_at',
    type: 'datetime',
    nullable: true,
  })
  unsubscribedAt?: Date | null;

  public static create(
    paymentMethodId: number,
    partnerId: number,
    productName: string,
  ) {
    const subscription = new Subscription();
    subscription.uuid = v4();
    subscription.paymentMethodId = paymentMethodId;
    subscription.partnerId = partnerId;
    subscription.productName = productName;
    subscription.subscribedAt = new Date();
    subscription.unsubscribedAt = null;

    return subscription;
  }
}

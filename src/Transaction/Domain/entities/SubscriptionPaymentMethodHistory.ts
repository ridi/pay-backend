import {
  Column, Entity, ManyToOne, PrimaryGeneratedColumn,
} from 'typeorm';
import { Subscription } from 'src/transaction/domain/entities/Subscription';
import { PaymentMethod } from 'src/user/domain/entities/PaymentMethod';

@Entity()
export class SubscriptionPaymentMethodHistory {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @ManyToOne((type) => Subscription)
  subscription: Subscription;

  @ManyToOne((type) => PaymentMethod)
  paymentMethod: PaymentMethod;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
  })
  createdAt: Date;

  public constructor(subscription: Subscription, paymentMethod: PaymentMethod) {
    this.subscription = subscription;
    this.paymentMethod = paymentMethod;
    this.createdAt = new Date();
  }
}

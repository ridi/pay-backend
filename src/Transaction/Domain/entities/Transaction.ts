import {
  Column, Entity, PrimaryGeneratedColumn,
} from 'typeorm';
import { v4 } from "uuid";

export enum TransactionStatus {
  RESERVED = 'RESERVED',
  APPROVED = 'APPROVED',
  CANCELED = 'CANCELED',
}

@Entity()
export class Transaction {
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
    name: 'pg_id',
    type: 'int',
    unsigned: true,
    comment: 'pg.id',
  })
  pgId!: number;

  @Column({
    name: 'partner_transaction_id',
    type: 'varchar',
    length: 64,
    comment: '가맹점 Transaction ID',
  })
  partnerTransactionId!: string;

  @Column({
    name: 'pg_transaction_id',
    type: 'varchar',
    length: 64,
    nullable: true,
    comment: 'PG사 Transaction ID',
  })
  pgTransactionId?: string | null;

  @Column({
    name: 'product_name',
    type: 'varchar',
    length: 32,
    comment: '상품명',
  })
  productName!: string;

  @Column({
    name: 'amount',
    type: 'int',
    comment: '결제 금액',
  })
  amount: number;

  @Column({
    name: 'status',
    type: 'enum',
    enum: TransactionStatus,
    default: TransactionStatus.RESERVED,
    comment: 'Transaction 상태',
  })
  status!: TransactionStatus;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
    comment: 'Transaction 예약 시각',
  })
  reservedAt!: Date;

  @Column({
    name: 'approved_at',
    type: 'datetime',
    nullable: true,
    comment: 'Transaction 승인 시각',
  })
  approvedAt?: Date | null;

  @Column({
    name: 'approved_at',
    type: 'datetime',
    nullable: true,
    comment: 'Transaction 취소 시각',
  })
  canceledAt?: Date | null;

  public static create(
    uIdx: number,
    paymentMethodId: number,
    pgId: number,
    partnerId: number,
    partnerTransactionId: string,
    productName: string,
    amount: number,
    reservedAt: Date,
  ) {
    const transaction = new Transaction();
    transaction.uuid = v4();
    transaction.uIdx = uIdx;
    transaction.paymentMethodId = paymentMethodId;
    transaction.pgId = pgId;
    transaction.partnerId = partnerId;
    transaction.partnerTransactionId = partnerTransactionId;
    transaction.pgTransactionId = null;
    transaction.productName = productName;
    transaction.amount = amount;
    transaction.status = TransactionStatus.RESERVED;
    transaction.reservedAt = reservedAt;
    transaction.approvedAt = null;
    transaction.canceledAt = null;

    return transaction;
  }
}

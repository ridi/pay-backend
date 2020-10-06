import {
  Column, Entity, ManyToOne, PrimaryGeneratedColumn,
} from 'typeorm';
import { Transaction } from 'src/transaction/domain/entities/Transaction';

export enum TransactionAction {
  APPROVE = 'APPROVE',
  CANCEL = 'CANCEL',
}

@Entity()
export class TransactionHistory {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @ManyToOne((type) => Transaction)
  transaction: Transaction;

  @Column({
    name: 'action',
    type: 'enum',
    enum: TransactionAction,
    default: TransactionAction.APPROVE,
    comment: 'APPROVE: 승인, CANCEL: 취소',
  })
  action: TransactionAction;

  @Column({
    name: 'is_success',
    type: 'boolean',
    comment: '결제 성공 여부',
  })
  isSuccess: boolean;

  @Column({
    name: 'pg_response_code',
    type: 'string',
    length: 16,
    comment: 'PG사 결제 응답 코드',
  })
  pgResponseCode: string;

  @Column({
    name: 'pg_response_message',
    type: 'string',
    length: 64,
    comment: 'PG사 결제 응답 메시지',
  })
  pgResponseMessage: string;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
  })
  createdAt: Date;

  public constructor(
    transaction: Transaction,
    action: TransactionAction,
    isSuccess: boolean,
    pgResponseCode: string,
    pgResponseMessage: string,
  ) {
    this.transaction = transaction;
    this.action = action;
    this.isSuccess = isSuccess;
    this.pgResponseCode = pgResponseCode;
    this.pgResponseMessage = pgResponseMessage;
    this.createdAt = new Date();
  }
}

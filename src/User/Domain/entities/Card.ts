import {
  Column, Entity, ManyToOne, PrimaryGeneratedColumn,
} from 'typeorm';
import { CardIssuer } from 'src/user/domain/entities/CardIssuer';
import { PaymentMethod } from 'src/user/domain/entities/PaymentMethod';
import { Crypto } from 'src/libraries/Crypto';

export enum CardPurpose {
  ONE_TIME = 'ONE_TIME',
  ONE_TIME_TAX_DEDUCTION = 'ONE_TIME_TAX_DEDUCTION',
  BILLING = 'BILLING',
}

@Entity()
export class Card {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @ManyToOne((type) => PaymentMethod, (paymentMethod) => paymentMethod.cards)
  paymentMethod!: PaymentMethod;

  @ManyToOne((type) => CardIssuer)
  cardIssuer!: CardIssuer;

  @Column({
    name: 'pg_id',
    type: 'int',
    comment: 'pg.id',
  })
  pgId!: number;

  @Column({
    name: 'pg_bill_key',
    type: 'varchar',
    comment: 'PG사에서 발급한 bill key',
  })
  pgBillKey!: string;

  @Column({
    name: 'iin',
    type: 'char',
    length: 6,
    comment: 'Issuer Identification Number(카드 번호 앞 6자리)',
  })
  iin!: string;

  @Column({
    name: 'purpose',
    type: 'enum',
    enum: CardPurpose,
    default: CardPurpose.ONE_TIME,
    comment: '용도(ONE_TIME: 소득 공제 불가능 단건 결제, ONE_TIME_TAX_DEDUCTION: 소득 공제 가능 단건 결제, BILLING: 정기 결제)',
  })
  purpose!: CardPurpose;
  
  public static create(
    paymentMethod: PaymentMethod,
    cardIssuer: CardIssuer,
    pgId: number,
    pgBillKey: string,
    cardNumber: string,
    purpose: CardPurpose,
  ) {
    const card = new Card();
    card.paymentMethod = paymentMethod;
    card.cardIssuer = cardIssuer;
    card.pgId = pgId;
    card.setEncryptedPgBillKey(pgBillKey);
    card.setIin(cardNumber);
    card.purpose = purpose;

    return card;
  }

  private setEncryptedPgBillKey(pgBillKey: string) {
    const key = process.env.PG_BILL_KEY_SECRET;
    if (key === undefined) {
      throw new Error("An environment variable 'PG_BILL_KEY_SECRET' is not defined.");
    }

    this.pgBillKey = Crypto.encrypt(pgBillKey, Buffer.from(key, 'base64'));
  }

  private setIin(cardNumber: string) {
    this.iin = cardNumber.substring(0, 6);
  }
}

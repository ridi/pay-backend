import {
  Column, Entity, PrimaryGeneratedColumn,
} from 'typeorm';
import { hash } from 'bcrypt';
import { Crypto } from 'src/libraries/Crypto';
import { v4 } from 'uuid';

@Entity()
export class Partner {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @Column({
    name: 'name',
    type: 'varchar',
    length: 32,
    comment: '가맹점 관리자 로그인 Username',
  })
  name!: string;

  @Column({
    name: 'password',
    type: 'varchar',
    length: 255,
    comment: '가맹점 관리자 로그인 Password',
  })
  password!: string;

  @Column({
    name: 'api_key',
    comment: 'API 연동 Key',
  })
  apiKey!: string;

  @Column({
    name: 'secret_key',
    type: 'varchar',
    length: 255,
    comment: 'API 연동 Secret Key',
  })
  secretKey!: string;

  @Column({
    name: 'is_valid',
    type: 'boolean',
  })
  isValid!: boolean;

  @Column({
    name: 'is_first_party',
    type: 'boolean',
    comment: 'First Party(RIDI) Partner 여부',
  })
  isFirstParty!: boolean;

  @Column({
    name: 'updated_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
    onUpdate: 'CURRENT_TIMESTAMP',
  })
  updatedAt!: Date;

  public static async create(
    name: string,
    password: string,
    isFirstParty: boolean,
  ) {
    const partner = new Partner();
    partner.name = name;
    partner.password = await hash(password, 10);
    partner.apiKey = v4();
    partner.setEncryptedSecretKey();
    partner.isValid = true;
    partner.isFirstParty = isFirstParty;
    partner.updatedAt = new Date();

    return partner;
  }

  private setEncryptedSecretKey() {
    const key = process.env.PARTNER_SECRET_KEY_SECRET;
    if (key === undefined) {
      throw new Error("An environment variable 'PARTNER_SECRET_KEY_SECRET' is not defined.");
    }

    this.secretKey = Crypto.encrypt(v4(), Buffer.from(key, 'base64'));
  }
}

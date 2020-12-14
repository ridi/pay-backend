import {
  Column, Entity, PrimaryGeneratedColumn,
} from 'typeorm';

@Entity()
export class CardIssuer {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @Column({
    name: 'pg_id',
    type: 'int',
    comment: 'pg.id',
  })
  pgId!: number;

  @Column({
    name: 'code',
    type: 'varchar',
    length: 32,
    comment: '카드 발급사 코드',
  })
  code!: string;

  @Column({
    name: 'name',
    type: 'varchar',
    length: 32,
    comment: '카드 발급사 이름',
  })
  name!: string;

  @Column({
    name: 'color',
    type: 'varchar',
    length: 7,
    comment: '카드 발급사 색상',
  })
  color!: string;

  @Column({
    name: 'logo_image_url',
    type: 'varchar',
    length: 128,
    comment: '카드 발급사 로고 이미지 URL',
  })
  logoImageUrl!: string;

  public static create(
    pgId: number,
    code: string,
    name: string,
    color: string,
    logoImageUrl: string,
  ) {
    const cardIssuer = new CardIssuer();
    cardIssuer.pgId = pgId;
    cardIssuer.code = code;
    cardIssuer.name = name;
    cardIssuer.color = color;
    cardIssuer.logoImageUrl = logoImageUrl;

    return cardIssuer;
  }
}

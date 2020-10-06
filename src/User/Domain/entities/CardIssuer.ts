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
  pgId: number;

  @Column({
    name: 'code',
    type: 'string',
    length: 32,
    comment: '카드 발급사 코드',
  })
  code: string;

  @Column({
    name: 'name',
    type: 'string',
    length: 32,
    comment: '카드 발급사 이름',
  })
  name: string;

  @Column({
    name: 'color',
    type: 'string',
    length: 7,
    comment: '카드 발급사 색상',
  })
  color: string;

  @Column({
    name: 'logo_image_url',
    type: 'string',
    length: 128,
    comment: '카드 발급사 로고 이미지 URL',
  })
  logoImageUrl: string;

  public constructor(
    pgId: number,
    code: string,
    name: string,
    color: string,
    logoImageUrl: string
  ) {
    this.pgId = pgId;
    this.code = code;
    this.name = name;
    this.color = color;
    this.logoImageUrl = logoImageUrl;
  }
}

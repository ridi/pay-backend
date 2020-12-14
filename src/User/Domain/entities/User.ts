import { Column, Entity, PrimaryColumn } from 'typeorm';

@Entity()
export class User {
  @PrimaryColumn({
    name: 'u_idx',
    type: 'int',
    unsigned: true,
    comment: 'RIDIBOOKS 유저 고유 번호',
  })
  uIdx!: number;

  @Column({
    name: 'pin',
    type: 'varchar',
    comment: '결제 비밀번호',
  })
  pin?: string | null;

  @Column({
    name: 'is_using_onetouch_pay',
    type: 'boolean',
    comment: '원터치 결제 사용 여부',
  })
  isUsingOnetouchPay?: boolean | null;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
    comment: 'RIDI PAY 가입 시각(최초 결제 수단 등록일)',
  })
  createdAt!: Date;

  @Column({
    name: 'leaved_at',
    type: 'datetime',
    comment: '회원 탈퇴로 인한 RIDI PAY 해지 시각',
  })
  leavedAt?: Date | null;

  public static create(uIdx: number) {
    const user = new User();
    user.uIdx = uIdx;
    user.pin = null;
    user.isUsingOnetouchPay = null; // 신규 유저 생성 시 원터치 결제 미설정
    user.createdAt = new Date();
    user.leavedAt = null;

    return user;
  }

  public isLeaved(): boolean {
    return this.leavedAt !== null;
  }

  public leave() {
    this.leavedAt = new Date();
  }
}

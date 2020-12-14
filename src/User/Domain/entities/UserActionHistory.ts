import {
  Column, Entity, ManyToOne, PrimaryGeneratedColumn,
} from 'typeorm';
import { User } from 'src/user/domain/entities/User';

@Entity()
export class UserActionHistory {
  @PrimaryGeneratedColumn({
    name: 'id',
    unsigned: true,
  })
  id!: number;

  @ManyToOne((type) => User)
  user!: User;

  @Column({
    name: 'action',
    type: 'varchar',
    length: 32,
    comment: 'RIDI PAY 사용자 액션',
  })
  action!: string;

  @Column({
    name: 'created_at',
    type: 'datetime',
    default: 'CURRENT_TIMESTAMP',
  })
  createdAt!: Date;

  public static create(user: User, action: string) {
    const userActionHistory = new UserActionHistory();
    userActionHistory.user = user;
    userActionHistory.action = action;
    userActionHistory.createdAt = new Date();

    return userActionHistory;
  }
}

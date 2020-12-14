import { EntityRepository, Repository } from 'typeorm';
import { User } from 'src/user/domain/entities/User';

@EntityRepository(User)
export class UserRepository extends Repository<User> {
  public findOneByUidx(uIdx: number) {
    return this.findOne({ uIdx });
  }
}

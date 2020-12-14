export class TestUtil {
  public static generateRandomUidx(): number {
    return Math.floor(Math.random() * 10000000) + 1;
  }
}
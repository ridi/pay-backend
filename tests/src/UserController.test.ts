import 'reflect-metadata';
import fs from 'fs';
import path from 'path';
import supertest from 'supertest';
import { App } from 'src/App';
import { UserAppService } from 'src/user/application/service/UserAppService';
import { TestUtil } from 'tests/src/TestUtil';
import { FirstPartyService, JwtAuthorizer } from 'src/libraries/JwtAuthorizer';

const getCases = (): Array<[number, number, number, string | null]> => {
  return [
    [0, TestUtil.generateRandomUidx(), 200, null],
    [1, TestUtil.generateRandomUidx(), 403, 'LEAVED_USER'],
    [2, TestUtil.generateRandomUidx(), 404, 'NOT_FOUND_USER'],
  ];
};

const app = new App();
beforeAll(async () => {
  await app.init();
});

test.each(getCases())(
  'Test UserController',
  async (caseNumber: number, uIdx: number, expectedHttpStatusCode: number, expectedErrorCode: string | null) => {
    switch (caseNumber) {
      case 0:
        // CASE 0: 가입 완료
        await UserAppService.createUser(uIdx);
        break;
      case 1:
        // CASE 1: 탈퇴 완료
        await UserAppService.createUser(uIdx);
        await UserAppService.deleteUser(uIdx);
        break;
      case 2:
      // 유저 2: 가입 미완료
        break;
    }

    const privateKey = fs.readFileSync(path.join(__dirname, 'Dummy', 'dummy.key')).toString();
    const publicKey = fs.readFileSync(path.join(__dirname, 'Dummy', 'dummy.key.pub')).toString();
    process.env.STORE_TO_RIDI_PAY_PRIVATE_KEY = privateKey;
    process.env.STORE_TO_RIDI_PAY_PUBLIC_KEY = publicKey;
    const jwt = JwtAuthorizer.sign(FirstPartyService.STORE, FirstPartyService.RIDI_PAY);

    const result = await supertest(app.getExpressServer()).delete(`/users/${uIdx}`).set({ Authorization: `Bearer ${jwt}` });
    expect(result.status).toEqual(expectedHttpStatusCode);
    if (expectedErrorCode !== null) {
      expect(result.body.code).toEqual(expectedErrorCode);
    }
  },
);

import { decode, sign, verify } from 'jsonwebtoken';

export enum FirstPartyService {
  STORE = 'store',
  RIDISELECT = 'ridiselect',
  RIDI_PAY = 'ridi-pay',
}

export class JwtAuthorizer {
  public static sign(iss: FirstPartyService, aud: FirstPartyService): string {
    const key = this.getKey(iss, aud, false);
    if (key === null) {
      throw new Error("A private key doesn't exist.");
    }

    const payload = {
      iss,
      aud,
      exp: (new Date()).getTime() + (5 * 60),
    };
    return sign(payload, key, { algorithm: 'RS256' });
  }

  public static verify(jwt: string, isses: FirstPartyService[], aud: FirstPartyService): void {
    const decodedJwt = decode(jwt);
    if (decodedJwt === null || typeof decodedJwt === 'string') {
      throw new Error('Invalid jwt.');
    }

    const payload: {[key: string]: any} = decodedJwt;
    if (payload.iss === undefined || !isses.includes(payload.iss)) {
      throw new Error('Invalid jwt iss.');
    }

    if (payload.aud === undefined || payload.aud !== aud) {
      throw new Error('Invalid jwt aud.');
    }

    const key = this.getKey(payload.iss, payload.aud, true);
    if (key === null) {
      throw new Error("A public key doesn't exist.");
    }
    verify(jwt, key, { algorithms: ['RS256'] });
  }

  private static getKey(
    iss: FirstPartyService,
    aud: FirstPartyService,
    isPublic: boolean,
  ): string | null {
    const issToAud = `${iss}_to_${aud}`;
    const keyType = `${(isPublic ? 'public' : 'private')}_key`;

    /**
     * 환경 변수 이름 규칙
     * - hyphen을 지원하지 않아서 underscore로 치환
     * - 대문자 이용
     */
    const keyName = `${issToAud}_${keyType}`.toUpperCase().replace('-', '_');
    const key = process.env[keyName];

    return key === undefined ? null : key.replace('\\n', '\n');
  }
}

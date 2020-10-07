import {
  crypto_secretbox_KEYBYTES,
  randombytes_buf,
  ready,
} from 'libsodium-wrappers';
import { v4 } from 'uuid';
import { Crypto } from 'src/libraries/Crypto';

test('Test encryption and decryption', async () => {
  await ready;

  const message = v4();
  const key = randombytes_buf(crypto_secretbox_KEYBYTES);

  const encryptedMessage = Crypto.encrypt(message, key);
  expect(Crypto.decrypt(encryptedMessage, key)).toEqual(message);
});

import {
  crypto_secretbox_easy,
  crypto_secretbox_MACBYTES,
  crypto_secretbox_NONCEBYTES,
  crypto_secretbox_open_easy,
  from_base64,
  from_string,
  pad,
  randombytes_buf,
  to_base64,
  to_string,
  unpad,
} from 'libsodium-wrappers';

const BLOCK_SIZE = 16;

export class Crypto {
  public static encrypt(message: string, key: Uint8Array): string {
    const nonce = randombytes_buf(crypto_secretbox_NONCEBYTES);
    const paddedMessage = pad(from_string(message), BLOCK_SIZE);
    const secretbox = crypto_secretbox_easy(paddedMessage, nonce, key);

    const encryptedMessage = new Uint8Array(nonce.length + secretbox.length);
    encryptedMessage.set(nonce);
    encryptedMessage.set(secretbox, nonce.length);
    return to_base64(encryptedMessage);
  }

  public static decrypt(encryptedMessage: string, key: Uint8Array): string {
    const encryptedMessageBytes = from_base64(encryptedMessage);
    if (encryptedMessageBytes.byteLength < crypto_secretbox_NONCEBYTES + crypto_secretbox_MACBYTES) {
      throw new Error('The message was truncated.');
    }

    const nonce = encryptedMessageBytes.subarray(0, crypto_secretbox_NONCEBYTES);
    const secretbox = encryptedMessageBytes.subarray(crypto_secretbox_NONCEBYTES);
    const paddedMessage = crypto_secretbox_open_easy(secretbox, nonce, key);
    const messageBytes = unpad(paddedMessage, BLOCK_SIZE);
    return to_string(messageBytes);
  }
}

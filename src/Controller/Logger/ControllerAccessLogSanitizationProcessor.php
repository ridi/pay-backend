<?php
declare(strict_types=1);

namespace RidiPay\Controller\Logger;

use Monolog\Processor\ProcessorInterface;

class ControllerAccessLogSanitizationProcessor implements ProcessorInterface
{
    /**
     * @param array $records
     * @return array
     */
    public function __invoke(array $records): array
    {
        $message_components = json_decode($records['message'], true);
        if (isset($message_components['request_body'])) {
            $sanitized_body = [];
            foreach (json_decode($message_components['request_body']) as $key => $value) {
                $sanitized_body[$key] = self::sanitize($key, $value);
            }
            $message_components['request_body'] = json_encode($sanitized_body);

            $records['message'] = json_encode($message_components);
        }

        return $records;
    }

    /**
     * @param string $key
     * @param $value
     * @return string
     */
    private static function sanitize(string $key, $value)
    {
        if (self::isCardNumber($key, $value)) {
            $value = substr($value, 0, 6) . str_repeat('*', strlen($value) - 6);
        } elseif (self::isCardExpirationDate($key, $value)) {
            $value = '****';
        } elseif (self::isCardPassword($key, $value)) {
            $value = '**';
        } elseif (self::isBirthDate($key, $value)) {
            $value = '******';
        } elseif (self::isPin($key, $value)) {
            $value = '******';
        } elseif (self::isBuyerName($key, $value)) {
            $value = str_repeat('*', mb_strlen($value));
        } elseif (self::isBuyerEmail($key, $value)) {
            [$id, $domain] = explode('@', $value);
            $value = str_repeat('*', mb_strlen($id)) . '@' . str_repeat('*', mb_strlen($domain));
        }

        return $value;
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isCardNumber(string $key, $value): bool
    {
        return $key === 'card_number' && is_string($value) && preg_match('/^\d{13,16}$/', $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isCardExpirationDate($key, $value): bool
    {
        return $key === 'card_expiration_date' && is_string($value) && preg_match('/^\d{2}(0[1-9]|1[0-2])$/', $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isCardPassword(string $key, $value): bool
    {
        return $key === 'card_password' && is_string($value) && preg_match('/^\d{2}$/', $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isBirthDate(string $key, $value): bool
    {
        return $key === 'tax_id' && is_string($value) && preg_match('/^\d{2}(0[1-9]|1[0-2])(0[1-9]|[1-2][0-9]|3[0-1])$/', $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isPin(string $key, $value): bool
    {
        return $key === 'pin' && is_string($value) && preg_match('/\d{6}/', $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isBuyerName(string $key, $value): bool
    {
        return $key === 'buyer_name' && is_string($value) && preg_match('/.+/', $value);
    }

    /**
     * @param string $key
     * @param $value
     * @return bool
     */
    private static function isBuyerEmail(string $key, $value): bool
    {
        return $key === 'buyer_email' && is_string($value) && preg_match('/.+\@.+\..+/', $value);
    }
}

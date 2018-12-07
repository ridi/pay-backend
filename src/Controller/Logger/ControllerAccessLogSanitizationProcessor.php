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
        if (self::isCardNumber($key)) {
            $value = substr($value, 0, 6) . str_repeat('*', strlen($value) - 6);
        } elseif (self::isCardExpirationDate($key)) {
            $value = '****';
        } elseif (self::isCardPassword($key)) {
            $value = '**';
        } elseif (self::isTaxId($key)) {
            $value = str_repeat('*', strlen($value));
        } elseif (self::isPin($key)) {
            $value = '******';
        } elseif (self::isBuyerName($key)) {
            $value = str_repeat('*', mb_strlen($value));
        } elseif (self::isBuyerEmail($key)) {
            [$id, $domain] = explode('@', $value);
            $value = str_repeat('*', mb_strlen($id)) . '@' . str_repeat('*', mb_strlen($domain));
        }

        return $value;
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isCardNumber(string $key): bool
    {
        return $key === 'card_number';
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isCardExpirationDate($key): bool
    {
        return $key === 'card_expiration_date';
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isCardPassword(string $key): bool
    {
        return $key === 'card_password';
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isTaxId(string $key): bool
    {
        return $key === 'tax_id';
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isPin(string $key): bool
    {
        return $key === 'pin';
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isBuyerName(string $key): bool
    {
        return $key === 'buyer_name';
    }

    /**
     * @param string $key
     * @return bool
     */
    private static function isBuyerEmail(string $key): bool
    {
        return $key === 'buyer_email';
    }
}

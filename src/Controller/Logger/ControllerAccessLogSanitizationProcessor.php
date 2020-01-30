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
            $decoded_request_body = json_decode($message_components['request_body'], true);
            if (is_array($decoded_request_body) && !empty($decoded_request_body)) {
                $sanitized_body = [];
                foreach ($decoded_request_body as $key => $value) {
                    $sanitized_body[$key] = self::sanitize($key, $value);
                }
                $message_components['request_body'] = json_encode($sanitized_body);

                $records['message'] = json_encode($message_components);
            }
        }

        return $records;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return string
     */
    private static function sanitize(string $key, $value)
    {
        if ($key === 'card_number' && preg_match('/^\d{13,16}$/', strval($value))) {
            return substr(strval($value), 0, 6) . '*';
        } elseif (in_array(
            $key,
            [
                'card_expiration_date',
                'card_password',
                'tax_id',
                'pin',
                'buyer_name',
                'buyer_email'
            ],
            true
        )) {
            return str_repeat('*', mb_strlen(strval($value), 'utf-8'));
        } else {
            return $value;
        }
    }
}

<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Exception;

class PgException extends \Exception
{
    /** @var string */
    private $pg_message;

    /**
     * @param string $message
     * @param string $pg_message
     */
    public function __construct(string $message, string $pg_message)
    {
        $this->pg_message = $pg_message;

        parent::__construct($message);
    }

    /**
     * @return string
     */
    public function getPgMessage(): string
    {
        return $this->pg_message;
    }
}

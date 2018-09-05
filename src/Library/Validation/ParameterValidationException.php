<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation;

class ParameterValidationException extends \Exception
{
    /** @var string */
    private $parameter;

    /**
     * @param string $message
     * @param string $parameter
     */
    public function __construct(string $message, string $parameter)
    {
        parent::__construct($message);

        $this->parameter = $parameter;
    }

    /**
     * @return string
     */
    public function getParameter(): string
    {
        return $this->parameter;
    }
}

<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation;

use Symfony\Component\Validator\Constraint;

class Rule
{
    /** @var string */
    private $parameter;

    /** @var Constraint[] */
    private $constraints;

    /**
     * @param string $parameter
     * @param Constraint[] $constraints
     */
    public function __construct(string $parameter, array $constraints)
    {
        $this->parameter = $parameter;
        $this->constraints = $constraints;
    }

    /**
     * @return string
     */
    public function getParameter(): string
    {
        return $this->parameter;
    }

    /**
     * @return Constraint[]
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }
}

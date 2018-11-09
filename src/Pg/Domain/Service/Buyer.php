<?php
declare(strict_types=1);

namespace RidiPay\Pg\Domain\Service;

class Buyer
{
    /** @var string $id */
    private $id;

    /** @var string $name */
    private $name;

    /** @var string $email */
    private $email;

    /**
     * @param string $id
     * @param string $name
     * @param string $email
     */
    public function __construct(string $id, string $name, string $email)
    {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }
}

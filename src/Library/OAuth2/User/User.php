<?php
declare(strict_types=1);

namespace RidiPay\Library\OAuth2\User;

use Ridibooks\OAuth2\Authorization\Exception\AuthorizationException;

class User
{
    /** @var int */
    private $u_idx;

    /** @var string */
    private $u_id;

    /**
     * @param string $user_info_json
     * @throws AuthorizationException
     */
    public function __construct(string $user_info_json)
    {
        $json = json_decode($user_info_json);
        if (is_null($json) || !isset($json->result)) {
            throw new AuthorizationException('Invalid json response');
        }
        $result = $json->result;

        $this->u_idx = $result->u_idx;
        $this->u_id = $result->u_idx;
    }

    /**
     * @return int
     */
    public function getUidx(): int
    {
        return $this->u_idx;
    }

    /**
     * @return string
     */
    public function getUid(): string
    {
        return $this->u_id;
    }
}

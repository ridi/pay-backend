<?php
declare(strict_types=1);

namespace RidiPay\Library\Jwt\Annotation;

/**
 * Annotation class for @Jwt().
 *
 * Annotated classes or methods with annotation @Jwt use JwtMiddleware.
 * @see JwtMiddleware
 *
 * @Annotation
 * @Target({"CLASS", "METHOD"})
 */
class Jwt
{
}

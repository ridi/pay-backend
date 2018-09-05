<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation\Annotation;

use RidiPay\Library\Validation\Rule;

/**
 * Annotation class for @ParamValidator().
 *
 * Annotated classes or methods with annotation @ParamValidator use ParameterValidationMiddleware.
 * @see ParameterValidationMiddleware.
 *
 * A reference about supported constraints is below.
 * @see https://symfony.com/doc/current/reference/constraints.html
 *
 * @Annotation
 * @Target({"METHOD"})
 */
class ParamValidator
{
    private const SYMFONY_VALIDATOR_CONSTRAINTS_NAMESPACE = 'Symfony\\Component\\Validator\\Constraints';

    /** @var Rule[] */
    private $rules;

    /**
     * @param array $rules
     */
    public function __construct(array $rules)
    {
        $this->setRules($rules);
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * @param array $rules
     */
    private function setRules(array $rules): void
    {
        $this->rules = array_map(
            function ($rule) {
                $param = $rule['param'];
                $constraints = array_map(
                    function ($constraint) {
                        if (self::isKeyValue($constraint)) {
                            foreach ($constraint as $name => $option) {
                                $constraint_class = self::SYMFONY_VALIDATOR_CONSTRAINTS_NAMESPACE . '\\' . $name;
                                return new $constraint_class($option);
                            }
                        } else {
                            $constraint_class = self::SYMFONY_VALIDATOR_CONSTRAINTS_NAMESPACE . '\\' . $constraint;
                            return new $constraint_class();
                        }
                    },
                    $rule['constraints']
                );

                return new Rule($param, $constraints);
            },
            $rules
        );
    }

    /**
     * @param $arr
     * @return bool
     */
    private static function isKeyValue($arr): bool
    {
        return is_array($arr) && (array_values($arr) !== $arr) && (count($arr) === 1);
    }
}

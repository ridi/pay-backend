<?php
declare(strict_types=1);

namespace RidiPay\Library\Validation;

use Symfony\Component\Validator\Validation;

class ParameterValidator
{
    /**
     * @param array $parameters (ex: ['pin' => '123456'])
     * @param Rule[] $rules
     * @throws ParameterValidationException
     */
    public static function validate(array $parameters, array $rules): void
    {
        $validator = Validation::createValidator();

        foreach ($rules as $rule) {
            $parameter = $rule->getParameter();
            $constraints = $rule->getConstraints();

            foreach ($constraints as $constraint) {
                if (!isset($parameters[$parameter])) {
                    throw new ParameterValidationException("Parameter doesn't exist.", $parameter);
                }

                $violations = $validator->validate($parameters[$parameter], $constraint);
                if (count($violations) > 0) {
                    throw new ParameterValidationException($violations->get(0)->getMessage(), $parameter);
                }
            }
        }
    }
}

<?php

namespace App\Validator;

use App\Repository\WpUserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolation;

final class uniqueEmailValidator extends ConstraintValidator
{

    public function __construct( private WpUserRepository $wpUserRepo){}

    
    public function validate(mixed $value, Constraint $constraint): void
    {
        /* @var UniqueEmail $constraint */

        if (null === $value || '' === $value) {
            return;
        }

        $existingUser = $this->wpUserRepo->findByEmail($value);

        if($existingUser !== null){
            $violations = new ConstraintViolationList();
            $violation = new ConstraintViolation(
                $constraint->message,
                $constraint->message,
                ['{{ value }}' => $value],
                $value,
                'email',
                $value,
                null,
                null,
                null,
                null
            );
            $violations->add($violation);
            
            throw new ValidationFailedException($value, $violations);
        }
    }
}

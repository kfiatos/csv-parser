<?php

namespace App\Service;

use App\Service\Interfaces\EmailDataParserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;


class CsvEmailDataParserService implements EmailDataParserInterface
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * CsvEmailDataValidatorService constructor.
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param array $inputData
     * @return array
     */
    public function getProperEmailAddressees(array $inputData): array
    {
        $properEmails = [];

        foreach ($inputData as $email) {
            $valid = $this->isEmailValid($email[0]);
            if ($valid === true){
                $properEmails[] = [$email[0]];
            }
        }
        return $properEmails;
    }

    /**
     * @param array $inputData
     * @return array
     */
    public function getWrongEmailAddressees(array $inputData): array
    {
        $wrongEmails = [];

        foreach ($inputData as $email) {
            $valid = $this->isEmailValid($email[0]);
            if ($valid === false){
                $wrongEmails[] = [$email[0]];
            }
        }
        return $wrongEmails;
    }

    /**
     * @param string $email
     * @return bool
     */
    protected function isEmailValid($email): bool
    {
        $emailConstraint = new Assert\Email();
        // use the validator to validate the value
        $errors = $this->validator->validate(
            $email,
            $emailConstraint
        );

        if (0 === count($errors)) {
            return true;
        }
        return false;
    }
}
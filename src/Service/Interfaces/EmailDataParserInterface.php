<?php

namespace App\Service\Interfaces;


interface EmailDataParserInterface
{
    /**
     * @param array $inputData
     * @return array
     */
    public function getProperEmailAddressees(array $inputData): array;

    /**
     * @param array $inputData
     * @return array
     */
    public function getWrongEmailAddressees(array $inputData): array;

}
<?php

namespace App\Services;

use RuntimeException;

class InvalidAdventureDataException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Abenteuerdaten sind ungültig: '.implode('; ', array_slice($errors, 0, 5)));
    }
}

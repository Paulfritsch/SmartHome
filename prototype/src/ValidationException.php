<?php

declare(strict_types=1);

namespace SmartSave;

use RuntimeException;

// Eingabefehler eines Nutzers; alle Meldungen sind fuer die Anzeige gedacht.
final class ValidationException extends RuntimeException
{
    /** @param string[] $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(implode(' ', $errors));
    }

    /** @return string[] */
    public function getErrors(): array
    {
        return $this->errors;
    }
}

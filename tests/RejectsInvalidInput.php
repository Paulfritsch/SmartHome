<?php

declare(strict_types=1);

namespace SmartSave\Tests;

use SmartSave\ValidationException;

// Hilfsfunktion fuer Tests: Jeder ungueltige Fall muss eine ValidationException ausloesen.
trait RejectsInvalidInput
{
    /**
     * @param iterable<string, array<int, mixed>> $cases Beschreibung => Argumente fuer $action
     */
    private function assertAllRejected(iterable $cases, callable $action): void
    {
        foreach ($cases as $label => $arguments) {
            try {
                $action(...$arguments);
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
                continue;
            }
            $this->fail("Ungültige Eingabe wurde akzeptiert: $label");
        }
    }
}

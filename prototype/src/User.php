<?php

declare(strict_types=1);

namespace SmartSave;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly float $pricePerKwh,
        public readonly float $oldMonthlyCost
    ) {
    }
}

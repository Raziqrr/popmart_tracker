<?php

namespace App\Services\Jules;

enum JulesTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Ultra = 'ultra';
    case Custom = 'custom';

    public function dailyLimit(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return match ($this) {
            self::Free => 15,
            self::Pro => 100,
            self::Ultra => 300,
            self::Custom => 15,
        };
    }

    public function monthlyLimit(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return match ($this) {
            self::Free => 450,
            self::Pro => 3000,
            self::Ultra => 9000,
            self::Custom => 450,
        };
    }

    public function concurrencyLimit(?int $override = null): int
    {
        if ($override !== null && $override > 0) {
            return $override;
        }

        return match ($this) {
            self::Free => 3,
            self::Pro => 15,
            self::Ultra => 60,
            self::Custom => 3,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Google Jules (Free Tier)',
            self::Pro => 'Google Jules in Pro (Google AI Pro)',
            self::Ultra => 'Google Jules in Ultra (Google AI Ultra)',
            self::Custom => 'Google Jules (Custom Quota)',
        };
    }

    public static function fromString(?string $tier): self
    {
        if ($tier === null) {
            return self::Free;
        }

        return self::tryFrom(strtolower(trim($tier))) ?? self::Free;
    }
}

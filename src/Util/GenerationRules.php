<?php

namespace App\Util;

final class GenerationRules
{
    private function __construct()
    {
    }

    public static function isValidChildGeneration(?int $parentOrder, ?int $childOrder): bool
    {
        if ($parentOrder === null || $childOrder === null) {
            return false;
        }

        return $childOrder > $parentOrder;
    }

    public static function isValidPartnerGeneration(?int $personOrder, ?int $partnerOrder): bool
    {
        if ($personOrder === null || $partnerOrder === null) {
            return false;
        }

        return $partnerOrder === $personOrder;
    }

    public static function isValidParentGeneration(?int $childOrder, ?int $parentOrder): bool
    {
        if ($childOrder === null || $parentOrder === null) {
            return false;
        }

        return $parentOrder < $childOrder;
    }
}

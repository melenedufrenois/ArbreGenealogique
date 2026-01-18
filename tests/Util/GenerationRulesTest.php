<?php

namespace App\Tests\Util;

use App\Util\GenerationRules;
use PHPUnit\Framework\TestCase;

final class GenerationRulesTest extends TestCase
{
    public function testChildGenerationMustBeGreaterThanParent(): void
    {
        self::assertFalse(GenerationRules::isValidChildGeneration(null, 3));
        self::assertFalse(GenerationRules::isValidChildGeneration(2, null));
        self::assertFalse(GenerationRules::isValidChildGeneration(2, 1));
        self::assertFalse(GenerationRules::isValidChildGeneration(2, 2));
        self::assertTrue(GenerationRules::isValidChildGeneration(2, 3));
    }

    public function testPartnerGenerationMustMatchPerson(): void
    {
        self::assertFalse(GenerationRules::isValidPartnerGeneration(null, 2));
        self::assertFalse(GenerationRules::isValidPartnerGeneration(2, null));
        self::assertFalse(GenerationRules::isValidPartnerGeneration(2, 1));
        self::assertTrue(GenerationRules::isValidPartnerGeneration(2, 2));
    }

    public function testParentGenerationMustBeAboveChild(): void
    {
        self::assertFalse(GenerationRules::isValidParentGeneration(null, 1));
        self::assertFalse(GenerationRules::isValidParentGeneration(2, null));
        self::assertFalse(GenerationRules::isValidParentGeneration(2, 2));
        self::assertFalse(GenerationRules::isValidParentGeneration(2, 3));
        self::assertTrue(GenerationRules::isValidParentGeneration(2, 1));
    }
}

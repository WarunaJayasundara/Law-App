<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Notifications\TextLkSmsNotificationService;
use PHPUnit\Framework\TestCase;

final class TextLkPhoneFormatTest extends TestCase
{
    /** @dataProvider validNumbers */
    public function testConvertsSriLankanMobilesToInternationalFormat(string $input): void
    {
        $this->assertSame('94764089523', TextLkSmsNotificationService::toInternationalFormat($input));
    }

    public static function validNumbers(): array
    {
        return [
            'local' => ['0764089523'],
            'local with spaces' => ['076 408 9523'],
            'local with dashes' => ['076-408-9523'],
            'international' => ['94764089523'],
            'international plus' => ['+94764089523'],
            'without leading zero' => ['764089523'],
        ];
    }

    /** @dataProvider invalidNumbers */
    public function testRejectsNumbersSmsCannotReach(string $input): void
    {
        $this->assertNull(TextLkSmsNotificationService::toInternationalFormat($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'landline' => ['0112345678'],
            'too short' => ['07640'],
            'too long' => ['07640895231'],
            'letters' => ['not-a-number'],
            'empty' => [''],
        ];
    }
}

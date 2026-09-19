<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SmsTemplate;
use PHPUnit\Framework\TestCase;

final class SmsTemplateTest extends TestCase
{
    public function testDefaultTemplateIsValidAndKeepsTheOfficeWording(): void
    {
        $this->assertNull(SmsTemplate::validate(SmsTemplate::DEFAULT_RECEIVED));

        $sms = SmsTemplate::render(SmsTemplate::DEFAULT_RECEIVED, '14367');
        $this->assertStringContainsString('Your deed (No. 14367) has been duly registered and received at the office.', $sms);
        $this->assertStringContainsString('ඔබගේ අංක 14367 දරණ ඔප්පුව', $sms);
        $this->assertStringContainsString('Karunarathne Notary Office, Dippitiya.', $sms);
        $this->assertSame(2, substr_count($sms, '0717644555'));
        $this->assertStringNotContainsString('{deed_number}', $sms);
    }

    public function testRenderReplacesEveryPlaceholderAndNormalizesNewlines(): void
    {
        $out = SmsTemplate::render("A {deed_number}\r\nB {deed_number}\r\n", 'X-1');
        $this->assertSame("A X-1\nB X-1", $out);
    }

    public function testRenderDoesNotInterpretTheDeedNumberAsATemplate(): void
    {
        $this->assertSame('No. {deed_number}', SmsTemplate::render('No. {deed_number}', '{deed_number}'));
        $this->assertSame('No. $1 \\1', SmsTemplate::render('No. {deed_number}', '$1 \\1'));
    }

    /** @dataProvider invalidTemplates */
    public function testValidateRejectsBadTemplates(string $template, string $expectedFragment): void
    {
        $error = SmsTemplate::validate($template);
        $this->assertNotNull($error);
        $this->assertStringContainsString($expectedFragment, $error);
    }

    public static function invalidTemplates(): array
    {
        return [
            'empty' => ['   ', 'cannot be empty'],
            'no placeholder' => ['Your deed is registered.', '{deed_number}'],
            'unknown placeholder' => ['Deed {deed_number} for {buyer_name}', '{buyer_name}'],
            'too long' => [str_repeat('a', SmsTemplate::MAX_LENGTH) . ' {deed_number}', 'characters or fewer'],
            'control character' => ["Deed {deed_number}\x07", 'control characters'],
            'invalid utf-8' => ["Deed {deed_number} \xC3\x28", 'invalid characters'],
        ];
    }

    public function testEstimateGsmSevenBit(): void
    {
        $this->assertSame(['encoding' => 'GSM-7', 'length' => 5, 'parts' => 1], SmsTemplate::estimate('Hello'));
        $this->assertSame(1, SmsTemplate::estimate(str_repeat('a', 160))['parts']);
        $this->assertSame(2, SmsTemplate::estimate(str_repeat('a', 161))['parts']);
        $this->assertSame(2, SmsTemplate::estimate(str_repeat('a', 306))['parts']);
        $this->assertSame(3, SmsTemplate::estimate(str_repeat('a', 307))['parts']);
        // Extension characters such as { } [ ] and the euro sign take two GSM positions.
        $this->assertSame(4, SmsTemplate::estimate('{}€]')['length'] - 4);
        $this->assertSame(0, SmsTemplate::estimate('')['parts']);
    }

    public function testEstimateUnicodeMatchesTextLkBilling(): void
    {
        // Verified against text.lk: this 72-character Sinhala message was billed as 2 parts.
        $sinhala = 'ඔබගේ අංක 14367 දරණ ඔප්පුව නිසි පරිදි ලියාපදිංචි වී කාර්යාලය වෙත ලැබී ඇත.';
        $this->assertSame(['encoding' => 'Unicode', 'length' => 72, 'parts' => 2], SmsTemplate::estimate($sinhala));

        // And the full bilingual default (335 characters) was quoted by text.lk as needing 5.
        $full = SmsTemplate::estimate(SmsTemplate::render(SmsTemplate::DEFAULT_RECEIVED, '14367'));
        $this->assertSame('Unicode', $full['encoding']);
        $this->assertSame(335, $full['length']);
        $this->assertSame(5, $full['parts']);

        $this->assertSame(1, SmsTemplate::estimate(str_repeat('අ', 70))['parts']);
        $this->assertSame(2, SmsTemplate::estimate(str_repeat('අ', 71))['parts']);
        $this->assertSame(2, SmsTemplate::estimate(str_repeat('අ', 134))['parts']);
        $this->assertSame(3, SmsTemplate::estimate(str_repeat('අ', 135))['parts']);
    }
}

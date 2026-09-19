<?php

namespace App\Services;

/**
 * The "deed received" SMS sent to the buyer and seller. The wording is
 * editable by an admin (Settings page); only {deed_number} may vary per deed.
 * Pure logic, no I/O, so it is unit-tested without a database.
 */
final class SmsTemplate
{
    public const PLACEHOLDER = '{deed_number}';
    public const MAX_LENGTH = 700;

    public const DEFAULT_RECEIVED = <<<'TXT'
Your deed (No. {deed_number}) has been duly registered and received at the office.
Thank you for placing your trust in us.
Karunarathne Notary Office, Dippitiya.
0717644555

ඔබගේ අංක {deed_number} දරණ ඔප්පුව නිසි පරිදි ලියාපදිංචි වී කාර්යාලය වෙත ලැබී ඇත.
අප ආයතනය කෙරෙහි විශ්වාසය තැබූ ඔබට ස්තුතියි...
කරුණාරත්න නොතාරිස් කාර්යාලය. දිප්පිටිය.
0717644555
TXT;

    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    private const GSM_EXTENDED = "^{}\\[~]|€";

    public static function normalize(string $template): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $template));
    }

    /** @return string|null an error message, or null when the template is acceptable */
    public static function validate(string $template): ?string
    {
        $template = self::normalize($template);

        if ($template === '') {
            return 'The message cannot be empty.';
        }
        if (!mb_check_encoding($template, 'UTF-8')) {
            return 'The message contains invalid characters.';
        }
        if (mb_strlen($template) > self::MAX_LENGTH) {
            return 'The message must be ' . self::MAX_LENGTH . ' characters or fewer.';
        }
        if (preg_match('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', $template)) {
            return 'The message contains control characters.';
        }
        if (!str_contains($template, self::PLACEHOLDER)) {
            return 'The message must include ' . self::PLACEHOLDER . ' so each deed number is filled in.';
        }
        preg_match_all('/\{[^}]*\}/', $template, $tokens);
        foreach ($tokens[0] as $token) {
            if ($token !== self::PLACEHOLDER) {
                return "Unknown placeholder {$token}. Only " . self::PLACEHOLDER . ' is supported.';
            }
        }

        return null;
    }

    public static function render(string $template, string $deedNumber): string
    {
        return str_replace(self::PLACEHOLDER, $deedNumber, self::normalize($template));
    }

    /**
     * How the text will be billed: GSM-7 (160 / 153 per part) when every
     * character fits, otherwise Unicode/UCS-2 (70 / 67 per part), which is
     * what Sinhala needs. Matches text.lk's own count (a 72-character
     * Sinhala message was billed as 2 parts).
     *
     * @return array{encoding:string, length:int, parts:int}
     */
    public static function estimate(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $gsmLength = 0;
        $isGsm = true;

        foreach (mb_str_split($text) as $char) {
            if (mb_strpos(self::GSM_BASIC, $char) !== false) {
                $gsmLength += 1;
            } elseif (mb_strpos(self::GSM_EXTENDED, $char) !== false) {
                $gsmLength += 2;
            } else {
                $isGsm = false;
                break;
            }
        }

        if ($isGsm) {
            return ['encoding' => 'GSM-7', 'length' => $gsmLength, 'parts' => self::parts($gsmLength, 160, 153)];
        }

        $units = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);
        return ['encoding' => 'Unicode', 'length' => $units, 'parts' => self::parts($units, 70, 67)];
    }

    private static function parts(int $length, int $single, int $multi): int
    {
        if ($length === 0) {
            return 0;
        }
        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}

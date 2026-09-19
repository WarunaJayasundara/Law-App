<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFailsOnEmpty(): void
    {
        $v = new Validator(['name' => '']);
        $v->required('name', 'Name');
        $this->assertTrue($v->fails());
    }

    public function testNicAcceptsOldAndNewFormats(): void
    {
        $v = new Validator(['nic' => '962345678V']);
        $v->nic('nic', 'NIC');
        $this->assertFalse($v->fails());

        $v2 = new Validator(['nic' => '199623456789']);
        $v2->nic('nic', 'NIC');
        $this->assertFalse($v2->fails());
    }

    public function testNicRejectsGarbage(): void
    {
        $v = new Validator(['nic' => 'not-a-nic']);
        $v->nic('nic', 'NIC');
        $this->assertTrue($v->fails());
    }

    public function testDeedNumberAcceptsRealFormatsAndRejectsFreeText(): void
    {
        foreach (['14367', 'A 123/2024', 'D-2026-001', '12.5'] as $ok) {
            $v = new Validator(['n' => $ok]);
            $v->deedNumber('n', 'Deed no.');
            $this->assertFalse($v->fails(), $ok);
        }
        foreach (['see http://evil.example', 'x<script>', "line1\nline2", '-leading', '<b>'] as $bad) {
            $v = new Validator(['n' => $bad]);
            $v->deedNumber('n', 'Deed no.');
            $this->assertTrue($v->fails(), $bad);
        }
    }

    public function testMaxBytesCountsBytesNotCharacters(): void
    {
        $v = new Validator(['p' => str_repeat('අ', 25)]); // 25 characters, 75 bytes
        $v->maxBytes('p', 72, 'Password');
        $this->assertTrue($v->fails());
    }

    public function testDecimalRejectsNonNumeric(): void
    {
        $v = new Validator(['amount' => 'abc']);
        $v->decimal('amount', 'Amount');
        $this->assertTrue($v->fails());
    }

    public function testDecimalAcceptsValidAmount(): void
    {
        $v = new Validator(['amount' => '1250.50']);
        $v->decimal('amount', 'Amount');
        $this->assertFalse($v->fails());
    }
}

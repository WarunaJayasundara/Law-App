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

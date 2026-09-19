<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testAcceptsAStrongPassword(): void
    {
        $this->assertNull(PasswordPolicy::check('correct horse battery staple'));
    }

    public function testRejectsShortAndOverlongPasswords(): void
    {
        $this->assertNotNull(PasswordPolicy::check('short'));
        $this->assertNotNull(PasswordPolicy::check(str_repeat('a', 73)));
        $this->assertNull(PasswordPolicy::check(str_repeat('a', 72)));
    }

    public function testRejectsThePublishedDefaultPassword(): void
    {
        $this->assertTrue(PasswordPolicy::isKnownDefault('ChangeMe123!'));
        $this->assertNotNull(PasswordPolicy::check('ChangeMe123!'));
    }

    public function testRejectsTheConfiguredSeedPassword(): void
    {
        $this->assertTrue(PasswordPolicy::isKnownDefault('SeedValue-9999', 'SeedValue-9999'));
        $this->assertFalse(PasswordPolicy::isKnownDefault('SeedValue-9999', ''));
        $this->assertNotNull(PasswordPolicy::check('SeedValue-9999', [], 'SeedValue-9999'));
    }

    public function testRejectsPasswordEqualToUsernameOrEmail(): void
    {
        $this->assertNotNull(PasswordPolicy::check('administrator', ['Administrator', 'a@b.lk']));
        $this->assertNotNull(PasswordPolicy::check('someone@office.lk', ['x', 'SOMEONE@office.lk']));
    }
}

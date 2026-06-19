<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testPlainUserDoesNotInheritAgentRole(): void
    {
        $user = (new User())->setRoles(['ROLE_USER']);

        self::assertSame(['ROLE_USER'], $user->getRoles());
        self::assertFalse($user->isAgent());
    }

    public function testAgentKeepsUserRoleForAuthenticatedRoutes(): void
    {
        $user = (new User())->setRoles(['ROLE_AGENT']);

        self::assertContains('ROLE_AGENT', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertTrue($user->isAgent());
    }

    public function testAdminIsConsideredAgentWithoutExposingAgentRole(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN']);

        self::assertContains('ROLE_ADMIN', $user->getRoles());
        self::assertContains('ROLE_USER', $user->getRoles());
        self::assertNotContains('ROLE_AGENT', $user->getRoles());
        self::assertTrue($user->isAgent());
    }
}

<?php

use PHPUnit\Framework\TestCase;

final class DatabaseSeedTest extends TestCase
{
    private string $sql;

    protected function setUp(): void
    {
        $this->sql = file_get_contents(dirname(__DIR__) . '/database/dvmd_db.sql');
    }

    public function testDashboardSeedUsersExist(): void
    {
        foreach ([
            'eric@gmail.com',
            'lim@gmail.com',
            'yeekientanpro@gmail.com',
        ] as $email) {
            $this->assertStringContainsString($email, $this->sql);
        }
    }

    public function testSeedPasswordMatchesDocumentedDemoPassword(): void
    {
        $matched = preg_match("/'eric@gmail\\.com'.*?'(\\$2y\\$[^']+)'/s", $this->sql, $matches);

        $this->assertSame(1, $matched, 'Eric demo user password hash was not found in the SQL seed.');
        $this->assertTrue(password_verify('Password@123', $matches[1]));
    }

    public function testRequiredRoleIdsAreSeeded(): void
    {
        $this->assertMatchesRegularExpression("/\\(\\d+, '0',/", $this->sql);
        $this->assertMatchesRegularExpression("/\\(\\d+, '1',/", $this->sql);
        $this->assertMatchesRegularExpression("/\\(\\d+, '2',/", $this->sql);
    }
}


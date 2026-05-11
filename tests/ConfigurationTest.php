<?php

use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    public function testDatabaseConnectionUsesEnvironmentOverrides(): void
    {
        $source = file_get_contents($this->root . '/backend/includes/dbconnect.php');

        $this->assertStringContainsString("getenv('DVMD_DB_HOST')", $source);
        $this->assertStringContainsString("getenv('DVMD_DB_PORT')", $source);
        $this->assertStringContainsString("getenv('DVMD_DB_USER')", $source);
        $this->assertStringContainsString("getenv('DVMD_DB_PASSWORD')", $source);
        $this->assertStringContainsString("getenv('DVMD_DB_NAME')", $source);
    }

    public function testKubernetesConfigPointsToMysqlService(): void
    {
        $config = file_get_contents($this->root . '/k8s/configmap.yaml');

        $this->assertStringContainsString('DVMD_DB_HOST: "dvmd-mysql"', $config);
        $this->assertStringContainsString('DVMD_DB_PORT: "3306"', $config);
        $this->assertStringContainsString('DVMD_DB_NAME: "dvmd_db"', $config);
    }
}


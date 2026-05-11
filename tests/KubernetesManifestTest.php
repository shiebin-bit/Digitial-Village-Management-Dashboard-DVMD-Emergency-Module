<?php

use PHPUnit\Framework\TestCase;

final class KubernetesManifestTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__);
    }

    public function testCloudManifestUsesGhcrImage(): void
    {
        $app = file_get_contents($this->root . '/k8s/app.yaml');

        $this->assertStringContainsString('image: ghcr.io/shiebin-bit/dvmd-emergency-module:latest', $app);
        $this->assertStringContainsString('imagePullPolicy: Always', $app);
    }

    public function testMysqlDeploymentUsesRecreateStrategyForPersistentVolume(): void
    {
        $mysql = file_get_contents($this->root . '/k8s/mysql.yaml');

        $this->assertStringContainsString('strategy:', $mysql);
        $this->assertStringContainsString('type: Recreate', $mysql);
    }

    public function testWebServiceKeepsDemoNodePort(): void
    {
        $app = file_get_contents($this->root . '/k8s/app.yaml');

        $this->assertStringContainsString('type: NodePort', $app);
        $this->assertStringContainsString('nodePort: 30081', $app);
    }
}


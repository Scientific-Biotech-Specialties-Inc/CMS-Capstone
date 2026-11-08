<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

final class AnalyticsDatabaseProvisioningTest extends TestCase
{
    public function test_startup_schema_creation_creates_the_analytics_database_when_absent(): void
    {
        $this->requireIntegrationEnvironment();

        $databaseName = $this->isolatedDatabaseName();
        $admin = $this->adminConnection();

        try {
            $this->assertFalse($this->schemaExists($admin, $databaseName));

            $this->runStartupSchemaCreation($admin, $databaseName);

            $this->assertTrue($this->schemaExists($admin, $databaseName));
        } finally {
            $this->dropDatabase($admin, $databaseName);
        }
    }

    public function test_startup_schema_creation_preserves_existing_analytics_data(): void
    {
        $this->requireIntegrationEnvironment();

        $databaseName = $this->isolatedDatabaseName();
        $admin = $this->adminConnection();

        try {
            $this->runStartupSchemaCreation($admin, $databaseName);

            $database = $this->databaseConnection($databaseName);
            $database->exec(
                'CREATE TABLE provisioning_sentinel (id INT PRIMARY KEY, value VARCHAR(255) NOT NULL)'
            );
            $insert = $database->prepare(
                'INSERT INTO provisioning_sentinel (id, value) VALUES (:id, :value)'
            );
            $insert->execute(['id' => 1, 'value' => 'preserve-this-row']);

            // This is the exact idempotent operation used by the analytics-service Compose command.
            $this->runStartupSchemaCreation($admin, $databaseName);

            $row = $database->query(
                'SELECT id, value FROM provisioning_sentinel WHERE id = 1'
            )->fetch(PDO::FETCH_ASSOC);

            $this->assertSame(['id' => 1, 'value' => 'preserve-this-row'], $row);
        } finally {
            $this->dropDatabase($admin, $databaseName);
        }
    }

    private function requireIntegrationEnvironment(): void
    {
        if (getenv('ANALYTICS_SCHEMA_INTEGRATION') !== '1') {
            $this->markTestSkipped(
                'Set ANALYTICS_SCHEMA_INTEGRATION=1 to run the isolated MySQL integration tests.'
            );
        }
    }

    private function adminConnection(): PDO
    {
        $config = $this->connectionConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=utf8mb4',
            $config['host'],
            $config['port']
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function databaseConnection(string $databaseName): PDO
    {
        $config = $this->connectionConfig();
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'],
            $config['port'],
            $databaseName
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    private function runStartupSchemaCreation(PDO $admin, string $databaseName): void
    {
        $admin->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS %s',
            $this->quoteIdentifier($databaseName)
        ));
    }

    private function schemaExists(PDO $admin, string $databaseName): bool
    {
        $query = $admin->prepare(
            'SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = :database_name'
        );
        $query->execute(['database_name' => $databaseName]);

        return (int) $query->fetchColumn() === 1;
    }

    private function dropDatabase(PDO $admin, string $databaseName): void
    {
        $admin->exec(sprintf('DROP DATABASE IF EXISTS %s', $this->quoteIdentifier($databaseName)));
    }

    private function isolatedDatabaseName(): string
    {
        return 'cms_analytics_it_'.bin2hex(random_bytes(8));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    /** @return array{host: string, port: string, username: string, password: string} */
    private function connectionConfig(): array
    {
        return [
            'host' => getenv('ANALYTICS_SCHEMA_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('ANALYTICS_SCHEMA_DB_PORT') ?: '3307',
            'username' => getenv('ANALYTICS_SCHEMA_DB_USERNAME') ?: 'root',
            'password' => getenv('ANALYTICS_SCHEMA_DB_PASSWORD')
                ?: getenv('MYSQL_PASSWORD')
                ?: 'ejaydetera12',
        ];
    }
}

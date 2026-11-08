<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;
use Tests\TestCase;

final class EmbeddingMigrationSafetyTest extends TestCase
{
    private ?string $databaseName = null;
    private ?string $roleName = null;

    public function test_extension_enable_failure_aborts_embeddings_migration_without_database_changes(): void
    {
        $this->requireIntegrationEnvironment();

        $this->databaseName = 'cms_ai_it_'.bin2hex(random_bytes(8));
        $this->roleName = 'cms_ai_it_role_'.bin2hex(random_bytes(6));
        $rolePassword = bin2hex(random_bytes(16));
        $admin = $this->adminConnection();

        try {
            $this->provisionDatabaseAndRestrictedRole($admin, $rolePassword);
            $baselineTables = $this->tablesInDatabase($admin);

            config([
                'database.default' => 'pgsql',
                'database.connections.pgsql.host' => $this->connectionConfig()['host'],
                'database.connections.pgsql.port' => $this->connectionConfig()['port'],
                'database.connections.pgsql.database' => $this->databaseName,
                'database.connections.pgsql.username' => $this->roleName,
                'database.connections.pgsql.password' => $rolePassword,
            ]);
            DB::purge('pgsql');
            DB::setDefaultConnection('pgsql');

            $migration = require base_path('database/migrations/2026_07_01_000004_create_embeddings_table.php');

            try {
                $migration->up();
                $this->fail('The embeddings migration unexpectedly succeeded without extension privileges.');
            } catch (Throwable $exception) {
                $message = strtolower($exception->getMessage());

                $this->assertStringContainsString('vector', $message);
                $this->assertTrue(
                    str_contains($message, 'permission') || str_contains($message, 'privilege'),
                    'The migration should fail because the restricted role cannot enable pgvector.'
                );
            }

            DB::disconnect('pgsql');
            DB::purge('pgsql');

            $this->assertSame($baselineTables, $this->tablesInDatabase($admin));
            $this->assertFalse($this->tableExists($admin, 'embeddings'));
            $this->assertFalse($this->vectorExtensionExists($admin));
        } finally {
            DB::disconnect('pgsql');
            DB::purge('pgsql');
        }
    }

    protected function tearDown(): void
    {
        try {
            if ($this->databaseName !== null && $this->roleName !== null) {
                $admin = $this->adminConnection();
                $admin->exec(sprintf(
                    'DROP DATABASE IF EXISTS %s WITH (FORCE)',
                    $this->quoteIdentifier($this->databaseName)
                ));
                $admin->exec(sprintf('DROP ROLE IF EXISTS %s', $this->quoteIdentifier($this->roleName)));
            }
        } finally {
            parent::tearDown();
        }
    }

    private function requireIntegrationEnvironment(): void
    {
        if (getenv('AI_SCHEMA_INTEGRATION') !== '1') {
            $this->markTestSkipped(
                'Set AI_SCHEMA_INTEGRATION=1 to run the isolated PostgreSQL integration test.'
            );
        }
    }

    private function provisionDatabaseAndRestrictedRole(PDO $admin, string $rolePassword): void
    {
        $admin->exec(sprintf('CREATE DATABASE %s', $this->quoteIdentifier($this->databaseName)));
        $admin->exec(sprintf(
            'CREATE ROLE %s LOGIN PASSWORD %s',
            $this->quoteIdentifier($this->roleName),
            $admin->quote($rolePassword)
        ));

        $databaseAdmin = $this->databaseAdminConnection();
        $databaseAdmin->exec('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
        $databaseAdmin->exec(sprintf(
            'GRANT USAGE, CREATE ON SCHEMA public TO %s',
            $this->quoteIdentifier($this->roleName)
        ));
    }

    private function adminConnection(): PDO
    {
        $config = $this->connectionConfig();

        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=postgres', $config['host'], $config['port']),
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    private function databaseAdminConnection(): PDO
    {
        $config = $this->connectionConfig();

        return new PDO(
            sprintf('pgsql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'], $this->databaseName),
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    /** @return list<array{table_schema: string, table_name: string}> */
    private function tablesInDatabase(PDO $connection): array
    {
        return $connection->query(
            "SELECT table_schema, table_name
             FROM information_schema.tables
             WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
             ORDER BY table_schema, table_name"
        )->fetchAll();
    }

    private function tableExists(PDO $connection, string $tableName): bool
    {
        $query = $connection->prepare(
            "SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema = 'public' AND table_name = :table_name"
        );
        $query->execute(['table_name' => $tableName]);

        return (int) $query->fetchColumn() === 1;
    }

    private function vectorExtensionExists(PDO $connection): bool
    {
        return (int) $connection->query(
            "SELECT COUNT(*) FROM pg_extension WHERE extname = 'vector'"
        )->fetchColumn() === 1;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    /** @return array{host: string, port: string, username: string, password: string} */
    private function connectionConfig(): array
    {
        return [
            'host' => getenv('AI_SCHEMA_DB_HOST') ?: '127.0.0.1',
            'port' => getenv('AI_SCHEMA_DB_PORT') ?: '5433',
            'username' => getenv('AI_SCHEMA_DB_USERNAME') ?: 'postgres',
            'password' => getenv('AI_SCHEMA_DB_PASSWORD') ?: 'ejaydetera12',
        ];
    }
}

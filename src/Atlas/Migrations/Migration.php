<?php

declare(strict_types=1);

namespace Libxa\Atlas\Migrations;

use Libxa\Atlas\Schema\SchemaBuilder;
use Libxa\Atlas\Connection\ConnectionPool;

/**
 * Base Migration Class — all migration files extend this.
 */
abstract class Migration
{
    /** @var SchemaBuilder The schema builder instance */
    protected SchemaBuilder $schema;

    /**
     * @param \PDO|null $pdo Optional PDO connection
     */
    public function __construct(protected ?\PDO $pdo = null)
    {
        $this->pdo    = $this->pdo ?? ConnectionPool::getInstance()->get();
        $this->schema = new SchemaBuilder($this->pdo);
    }

    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;
}

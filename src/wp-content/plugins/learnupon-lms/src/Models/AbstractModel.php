<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

abstract class AbstractModel
{
    protected \wpdb $wpdb;
    protected string $table;
    protected string $primaryKey = 'id';

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->table = $this->getTableName();
    }

    /**
     * Define the database table name in the child class.
     */
    abstract protected function getTableName(): string;

    /**
     * Create a record.
     */
    public function create(array $data): int|false
    {
        $result = $this->wpdb->insert($this->table, $data);

        if ($result === false) {
            $error = $this->wpdb->last_error;
            throw new \RuntimeException(
                'Database insert failed: ' . $error
            );
        }

        return (int) $this->wpdb->insert_id;
    }

    /**
     * Retrieve a record by primary key.
     */
    public function find(int $id): ?object
    {
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
             WHERE {$this->primaryKey} = %d",
            $id
        );

        $result = $this->wpdb->get_row($sql);

        return $result ?: null;
    }

    /**
     * Retrieve all records.
     */
    public function getAll(): array
    {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table}",
            OBJECT
        ) ?: [];
    }

    /**
     * Update a record by primary key.
     */
    public function update(int $id, array $data): bool
    {
        $result = $this->wpdb->update(
            $this->table,
            $data,
            [$this->primaryKey => $id]
        );

        return $result !== false;
    }

    /**
     * Delete a record by primary key.
     */
    public function delete(int $id): bool
    {
        $result = $this->wpdb->delete(
            $this->table,
            [$this->primaryKey => $id],
            ['%d']
        );

        return $result !== false;
    }
}

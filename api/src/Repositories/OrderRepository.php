<?php

namespace App\Repositories;

use App\Exceptions\DuplicateOrderException;
use PDO;
use PDOException;

readonly class OrderRepository
{
    public function __construct(private PDO $pdo) {}

    public function save(array $data): array
    {
        $sql = '
            INSERT INTO orders (customer_name, customer_email, items, total, idempotency_key, metadata)
            VALUES (:customer_name, :customer_email, :items, :total, :idempotency_key, :metadata)
            RETURNING *
        ';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':customer_name'   => $data['customer_name'],
                ':customer_email'  => $data['customer_email'],
                ':items'           => json_encode($data['items']),
                ':total'           => $data['total'],
                ':idempotency_key' => $data['idempotency_key'] ?? null,
                ':metadata'        => json_encode($data['metadata'] ?? []),
            ]);

            $row = $stmt->fetch();
            return $this->decodeJsonFields($row);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'unique') || str_contains($e->getMessage(), 'duplicate')) {
                throw new DuplicateOrderException(
                    "Order with idempotency_key '{$data['idempotency_key']}' already exists.",
                    0,
                    $e
                );
            }
            throw $e;
        }
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch();
        return $row ? $this->decodeJsonFields($row) : null;
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM orders WHERE idempotency_key = :key');
        $stmt->execute([':key' => $key]);

        $row = $stmt->fetch();
        return $row ? $this->decodeJsonFields($row) : null;
    }

    public function findAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'status = :status';
            $params[':status'] = $filters['status'];
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset      = ($page - 1) * $perPage;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM orders {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $this->pdo->prepare(
            "SELECT * FROM orders {$whereClause} ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $dataStmt->bindValue($key, $value);
        }
        $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $dataStmt->execute();

        $rows = $dataStmt->fetchAll();

        return [
            'data' => array_map([$this, 'decodeJsonFields'], $rows),
            'meta' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
            ],
        ];
    }

    public function updateStatus(string $id, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET status = :status, updated_at = clock_timestamp() WHERE id = :id'
        );
        $stmt->execute([':status' => $status, ':id' => $id]);
    }

    public function updateMetadata(string $id, array $metadata): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE orders SET metadata = metadata || :metadata::jsonb, updated_at = clock_timestamp() WHERE id = :id'
        );
        $stmt->execute([':metadata' => json_encode($metadata), ':id' => $id]);
    }

    private function decodeJsonFields(array $row): array
    {
        if (isset($row['items']) && is_string($row['items'])) {
            $row['items'] = json_decode($row['items'], true);
        }
        if (isset($row['metadata']) && is_string($row['metadata'])) {
            $row['metadata'] = json_decode($row['metadata'], true);
        }
        return $row;
    }
}

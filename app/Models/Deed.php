<?php

namespace App\Models;

use App\Core\Database;
use PDO;

final class Deed
{
    public const CATEGORIES = ['Transfer Deed', 'Lease Deed', 'Mortgage Deed', 'Gift Deed', 'Power of Attorney', 'Other'];
    public const STATUSES = ['Submitted', 'Reviewed', 'Received'];
    public const PER_PAGE = 20;

    public static function statusLabel(string $status): string
    {
        return $status;
    }

    /**
     * Server-side search/filter/pagination — the full table is never sent to the browser.
     * Filters: q (deed no / buyer NIC / seller NIC / buyer name / seller name), category, status, from/to date.
     */
    public static function search(array $filters, int $page = 1): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * self::PER_PAGE;

        $where = ['d.is_archived = 0'];
        $params = [];

        $q = trim($filters['q'] ?? '');
        if ($q !== '') {
            $where[] = '(d.deed_number LIKE :q1 OR b.nic LIKE :q2 OR s.nic LIKE :q3 OR b.full_name LIKE :q4 OR s.full_name LIKE :q5 OR d.folio_number LIKE :q6)';
            $needle = "%{$q}%";
            $params['q1'] = $needle;
            $params['q2'] = $needle;
            $params['q3'] = $needle;
            $params['q4'] = $needle;
            $params['q5'] = $needle;
            $params['q6'] = $needle;
        }

        if (!empty($filters['category']) && in_array($filters['category'], self::CATEGORIES, true)) {
            $where[] = 'd.category = :category';
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'rd.register_date >= :from';
            $params['from'] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where[] = 'rd.register_date <= :to';
            $params['to'] = $filters['to'];
        }

        $whereSql = implode(' AND ', $where);

        $db = Database::connection();

        $countStmt = $db->prepare(
            "SELECT COUNT(*) FROM deeds d
             JOIN buyers b ON b.id = d.buyer_id
             JOIN sellers s ON s.id = d.seller_id
             LEFT JOIN registration_details rd ON rd.deed_id = d.id
             WHERE {$whereSql}"
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sortColumn = in_array($filters['sort'] ?? '', ['deed_number', 'category', 'status', 'updated_at'], true)
            ? $filters['sort'] : 'updated_at';
        $sortDir = ($filters['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

        $stmt = $db->prepare(
            "SELECT d.*, b.full_name AS buyer_name, b.nic AS buyer_nic, s.full_name AS seller_name, s.nic AS seller_nic
             FROM deeds d
             JOIN buyers b ON b.id = d.buyer_id
             JOIN sellers s ON s.id = d.seller_id
             LEFT JOIN registration_details rd ON rd.deed_id = d.id
             WHERE {$whereSql}
             ORDER BY d.{$sortColumn} {$sortDir}
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', self::PER_PAGE, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'perPage' => self::PER_PAGE,
            'lastPage' => max(1, (int) ceil($total / self::PER_PAGE)),
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.*, b.full_name AS buyer_name, b.nic AS buyer_nic, b.mobile AS buyer_mobile,
                    s.full_name AS seller_name, s.nic AS seller_nic, s.mobile AS seller_mobile
             FROM deeds d
             JOIN buyers b ON b.id = d.buyer_id
             JOIN sellers s ON s.id = d.seller_id
             WHERE d.id = :id'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByNumber(string $deedNumber): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id FROM deeds WHERE deed_number = :n');
        $stmt->execute(['n' => $deedNumber]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data, int $userId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO deeds (deed_number, category, buyer_id, seller_id, folio_number, amount, value, other_document_numbers, status, created_by, updated_by)
             VALUES (:deed_number, :category, :buyer_id, :seller_id, :folio_number, :amount, :value, :other_document_numbers, :status, :created_by, :updated_by)'
        );
        $stmt->execute([
            'deed_number' => $data['deed_number'],
            'category' => $data['category'],
            'buyer_id' => $data['buyer_id'],
            'seller_id' => $data['seller_id'],
            'folio_number' => $data['folio_number'] ?: null,
            'amount' => $data['amount'] !== '' ? $data['amount'] : null,
            'value' => $data['value'] !== '' ? $data['value'] : null,
            'other_document_numbers' => $data['other_document_numbers'] ?: null,
            'status' => 'Submitted',
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        $deedId = (int) Database::connection()->lastInsertId();

        $regStmt = Database::connection()->prepare('INSERT INTO registration_details (deed_id) VALUES (:deed_id)');
        $regStmt->execute(['deed_id' => $deedId]);

        return $deedId;
    }

    public static function updateDetails(int $id, array $data, int $userId): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE deeds SET deed_number = :deed_number, category = :category, folio_number = :folio_number,
             amount = :amount, value = :value, other_document_numbers = :other_document_numbers, updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->execute([
            'deed_number' => $data['deed_number'],
            'category' => $data['category'],
            'folio_number' => $data['folio_number'] ?: null,
            'amount' => $data['amount'] !== '' ? $data['amount'] : null,
            'value' => $data['value'] !== '' ? $data['value'] : null,
            'other_document_numbers' => $data['other_document_numbers'] ?: null,
            'updated_by' => $userId,
            'id' => $id,
        ]);
    }

    public static function updateStatus(int $id, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE deeds SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    public static function archive(int $id): void
    {
        $stmt = Database::connection()->prepare('UPDATE deeds SET is_archived = 1 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function counts(): array
    {
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(status = 'Submitted') AS submitted,
                SUM(status = 'Reviewed') AS reviewed,
                SUM(status = 'Received') AS received
             FROM deeds WHERE is_archived = 0"
        )->fetch();

        return [
            'total' => (int) ($row['total'] ?? 0),
            'submitted' => (int) ($row['submitted'] ?? 0),
            'reviewed' => (int) ($row['reviewed'] ?? 0),
            'received' => (int) ($row['received'] ?? 0),
        ];
    }

    public static function recent(int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.id, d.deed_number, d.status, b.full_name AS buyer_name, s.full_name AS seller_name, rd.register_date
             FROM deeds d
             JOIN buyers b ON b.id = d.buyer_id
             JOIN sellers s ON s.id = d.seller_id
             LEFT JOIN registration_details rd ON rd.deed_id = d.id
             WHERE d.is_archived = 0
             ORDER BY d.updated_at DESC LIMIT :limit'
        );
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

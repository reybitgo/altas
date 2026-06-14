<?php

/**
 * @file   models/Product.php
 * @brief  Product catalog for repeat-purchase PV (Phase 5)
 */
class Product
{
    public static function find(int $id): ?array
    {
        $st = db()->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM products';
        if ($activeOnly) {
            $sql .= " WHERE status = 'active'";
        }
        $sql .= ' ORDER BY name ASC';
        return db()->query($sql)->fetchAll();
    }

    public static function active(): array
    {
        return self::all(true);
    }

    /**
     * Save or update a product.
     *
     * @param array $data name, price, pv_value, status
     */
    public static function save(array $data, ?int $id = null): int
    {
        $pdo = db();

        $fields = [
            'name'     => trim($data['name'] ?? ''),
            'price'    => (float)($data['price'] ?? 0),
            'pv_value' => (float)($data['pv_value'] ?? 0),
            'status'   => $data['status'] ?? 'active',
        ];

        if ($id) {
            $sets = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                $sets[] = "{$k} = ?";
                $vals[] = $v;
            }
            $vals[] = $id;
            $pdo->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?')
                ->execute($vals);
        } else {
            $cols = array_keys($fields);
            $placeholders = array_fill(0, count($cols), '?');
            $pdo->prepare('INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')')
                ->execute(array_values($fields));
            $id = (int)$pdo->lastInsertId();
        }

        return $id;
    }

    public static function delete(int $id): bool
    {
        // Do not allow deletion if repeat purchases reference the product
        $inUse = (int)db()->query("SELECT COUNT(*) FROM repeat_purchases WHERE product_id = {$id}")->fetchColumn();
        if ($inUse > 0) {
            return false;
        }
        db()->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
        return true;
    }
}

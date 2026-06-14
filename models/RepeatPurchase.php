<?php

/**
 * @file   models/RepeatPurchase.php
 * @brief  Repeat purchase records & approval (Phase 5)
 */
class RepeatPurchase
{
    public static function find(int $id): ?array
    {
        $st = db()->prepare('
            SELECT rp.*,
                   p.name AS product_name,
                   m.username AS member_username
            FROM   repeat_purchases rp
            JOIN   products p ON p.id = rp.product_id
            JOIN   users    m ON m.id = rp.member_id
            WHERE  rp.id = ?
        ');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function forMember(int $memberId, int $page = 1, int $perPage = 20): array
    {
        return paginate(
            "SELECT rp.*, p.name AS product_name
             FROM   repeat_purchases rp
             JOIN   products p ON p.id = rp.product_id
             WHERE  rp.member_id = ?
             ORDER BY rp.created_at DESC",
            [$memberId],
            $page,
            $perPage
        );
    }

    public static function pending(int $page = 1, int $perPage = 25): array
    {
        return paginate(
            "SELECT rp.*,
                    p.name AS product_name,
                    m.username AS member_username,
                    m.full_name AS member_full_name
             FROM   repeat_purchases rp
             JOIN   products p ON p.id = rp.product_id
             JOIN   users    m ON m.id = rp.member_id
             WHERE  rp.status = 'pending'
             ORDER BY rp.created_at ASC",
            [],
            $page,
            $perPage
        );
    }

    public static function all(int $page = 1, int $perPage = 25): array
    {
        return paginate(
            "SELECT rp.*,
                    p.name AS product_name,
                    m.username AS member_username,
                    m.full_name AS member_full_name
             FROM   repeat_purchases rp
             JOIN   products p ON p.id = rp.product_id
             JOIN   users    m ON m.id = rp.member_id
             ORDER BY rp.created_at DESC",
            [],
            $page,
            $perPage
        );
    }

    /**
     * Create a pending repeat-purchase request.
     */
    public static function create(int $memberId, int $productId, int $quantity): int
    {
        $pdo = db();
        $product = Product::find($productId);
        if (!$product || $product['status'] !== 'active') {
            throw new InvalidArgumentException('Product not found or inactive.');
        }
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        $totalPv    = (float)$product['pv_value'] * $quantity;
        $totalPrice = (float)$product['price']    * $quantity;

        $pdo->prepare("
            INSERT INTO repeat_purchases
              (member_id, product_id, quantity, total_pv, total_price, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ")->execute([
            $memberId,
            $productId,
            $quantity,
            $totalPv,
            $totalPrice,
        ]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Approve a repeat purchase and trigger PV distribution.
     */
    public static function approve(int $purchaseId, int $adminId): bool
    {
        $pdo = db();
        $purchase = self::find($purchaseId);
        if (!$purchase || $purchase['status'] !== 'pending') {
            return false;
        }

        $st = $pdo->prepare("
            UPDATE repeat_purchases
            SET status = 'approved',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $st->execute([$adminId, $purchaseId]);

        // rowCount() is unreliable with some PDO drivers; verify with a SELECT.
        $verify = $pdo->prepare("SELECT id FROM repeat_purchases WHERE id = ? AND status = 'approved'");
        $verify->execute([$purchaseId]);
        if (!$verify->fetch()) {
            return false;
        }

        Commission::processProductPV($purchaseId);
        return true;
    }

    public static function reject(int $purchaseId, int $adminId): bool
    {
        $st = db()->prepare("
            UPDATE repeat_purchases
            SET status = 'rejected',
                approved_by = ?,
                approved_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $st->execute([$adminId, $purchaseId]);
        return (int)$st->rowCount() > 0;
    }
}

<?php

class RepeatPurchaseOrder
{
    public static function find(int $id): ?array
    {
        $st = db()->prepare("SELECT * FROM repeat_purchase_orders WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function findWithItems(int $id): ?array
    {
        $order = self::find($id);
        if (!$order) return null;

        $st = db()->prepare("
            SELECT oi.*, p.name, p.image_url
            FROM   repeat_purchase_order_items oi
            JOIN   products p ON p.id = oi.product_id
            WHERE  oi.order_id = ?
        ");
        $st->execute([$id]);
        $order['items'] = $st->fetchAll();
        return $order;
    }

    public static function forMember(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $pdo = db();
        $countSt = $pdo->prepare("SELECT COUNT(*) FROM repeat_purchase_orders WHERE member_id = ?");
        $countSt->execute([$memberId]);
        $total = (int)$countSt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        $offset     = ($page - 1) * $perPage;

        $st = $pdo->prepare("
            SELECT o.*,
                   (SELECT COUNT(*) FROM repeat_purchase_order_items oi WHERE oi.order_id = o.id) AS item_count
            FROM   repeat_purchase_orders o
            WHERE  o.member_id = ?
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $st->execute([$memberId, $perPage, $offset]);

        return [
            'data'        => $st->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        ];
    }

    public static function pending(int $page = 1, int $perPage = 25): array
    {
        return self->_paginate("status = 'pending'", $page, $perPage);
    }

    public static function paid(int $page = 1, int $perPage = 25): array
    {
        return self->_paginate("status = 'paid'", $page, $perPage);
    }

    public static function all(int $page = 1, int $perPage = 25): array
    {
        return self->_paginate('1=1', $page, $perPage);
    }

    private static function _paginate(string $where, int $page, int $perPage): array
    {
        $pdo = db();
        $countSt = $pdo->prepare("SELECT COUNT(*) FROM repeat_purchase_orders o WHERE $where");
        $countSt->execute();
        $total = (int)$countSt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $perPage));
        $page       = max(1, min($page, $totalPages));
        $offset     = ($page - 1) * $perPage;

        $st = $pdo->prepare("
            SELECT o.*, m.username AS member_username, m.full_name AS member_full_name,
                   (SELECT COUNT(*) FROM repeat_purchase_order_items oi WHERE oi.order_id = o.id) AS item_count
            FROM   repeat_purchase_orders o
            JOIN   users m ON m.id = o.member_id
            WHERE  $where
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $st->execute([$perPage, $offset]);

        return [
            'data'        => $st->fetchAll(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $totalPages,
            'has_prev'    => $page > 1,
            'has_next'    => $page < $totalPages,
        ];
    }

    public static function createFromCart(int $memberId, int $cartId, string $binaryPosition, string $paymentMethod, ?string $proofImage = null): int
    {
        $pdo = db();
        $cart = Cart::getOrCreate($memberId);
        if ((int)$cart['id'] !== $cartId) {
            throw new RuntimeException('Cart mismatch.');
        }

        $items = Cart::getItems($cartId);
        if (empty($items)) {
            throw new RuntimeException('Cart is empty.');
        }

        $stockErrors = Cart::validateStock($cartId);
        if (!empty($stockErrors)) {
            throw new RuntimeException('Stock issue: ' . implode(' | ', $stockErrors));
        }

        $totals = Cart::getTotals($cartId);
        $totalPv   = (float)$totals['total_pv'];
        $totalPrice = (float)$totals['total_price'];

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("
                INSERT INTO repeat_purchase_orders
                  (member_id, total_pv, total_price, binary_position, payment_method, proof_image, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $st->execute([$memberId, $totalPv, $totalPrice, $binaryPosition, $paymentMethod, $proofImage]);
            $orderId = (int)$pdo->lastInsertId();

            $itemSt = $pdo->prepare("
                INSERT INTO repeat_purchase_order_items
                  (order_id, product_id, quantity, unit_price, unit_pv, total_price, total_pv)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($items as $item) {
                $itemSt->execute([
                    $orderId,
                    (int)$item['product_id'],
                    (int)$item['quantity'],
                    (float)$item['unit_price'],
                    (float)$item['unit_pv'],
                    (float)$item['unit_price'] * (int)$item['quantity'],
                    (float)$item['unit_pv'] * (int)$item['quantity'],
                ]);
            }

            Cart::markConverted($cartId);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return $orderId;
    }

    public static function markPaid(int $orderId, int $adminId): void
    {
        $st = db()->prepare("
            UPDATE repeat_purchase_orders
            SET status = 'paid', approved_by = ?, approved_at = NOW()
            WHERE id = ? AND status = 'pending'
        ");
        $st->execute([$adminId, $orderId]);
        if ($st->rowCount() === 0) {
            throw new RuntimeException('Order not found or not in pending status.');
        }
    }

    public static function approve(int $orderId, int $adminId): void
    {
        $pdo = db();
        $st = $pdo->prepare("
            UPDATE repeat_purchase_orders
            SET status = 'approved', approved_by = ?, approved_at = NOW()
            WHERE id = ? AND status = 'paid'
        ");
        $st->execute([$adminId, $orderId]);
        if ($st->rowCount() === 0) {
            $st2 = $pdo->prepare("SELECT status FROM repeat_purchase_orders WHERE id = ?");
            $st2->execute([$orderId]);
            $current = $st2->fetchColumn();
            if ($current === 'approved') {
                throw new RuntimeException('Order is already approved.');
            }
            throw new RuntimeException('Order not found or not in paid status.');
        }

        Commission::processProductPV($orderId);
    }

    public static function reject(int $orderId, int $adminId): void
    {
        $st = db()->prepare("
            UPDATE repeat_purchase_orders
            SET status = 'rejected', approved_by = ?, approved_at = NOW()
            WHERE id = ? AND status IN ('pending', 'paid')
        ");
        $st->execute([$adminId, $orderId]);
        if ($st->rowCount() === 0) {
            throw new RuntimeException('Order not found or cannot be rejected.');
        }
    }
}

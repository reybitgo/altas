<?php

/**
 * @file   core/Commission.php
 * @brief  Commission management class (v2 with Lifetime Income Capping)
 */
class Commission
{
    // ══════════════════════════════════════════════════════════════════════════
    //  BINARY PLACEMENT ENGINE
    //  Called immediately after a new member is inserted.
    //  Walks the binary tree upward, updating leg counts on every ancestor and
    //  firing pairing bonuses in real time for each ancestor that earns one.
    //  v2: Capped members are SKIPPED — their subtree exists but earns no pairs.
    // ══════════════════════════════════════════════════════════════════════════

    public static function processBinaryPlacement(
        int $newUserId,
        int $parentId,
        string $position          // 'left' | 'right'
    ): void {
        $pdo  = db();
        $cur  = $parentId;
        $side = $position;

        while ($cur !== null) {

            // 1. Increment the correct leg count on this ancestor
            $col = ($side === 'left') ? 'left_count' : 'right_count';
            $pdo->prepare("UPDATE users SET {$col} = {$col} + 1 WHERE id = ?")
                ->execute([$cur]);

            // v2: Skip capped/perminact members entirely — no pairing bonuses
            if (!CapEngine::isActiveForPairs($cur)) {
                // Move to parent but do NOT process pairs for this capped ancestor
                $upRow = $pdo->prepare(
                    'SELECT binary_parent_id, binary_position FROM users WHERE id = ?'
                );
                $upRow->execute([$cur]);
                $up = $upRow->fetch();
                $side = $up['binary_position'] ?? null;
                $cur  = isset($up['binary_parent_id']) ? (int)$up['binary_parent_id'] : null;
                if (!$cur) break;
                continue;
            }

            // 2. Read fresh state (after increment) with package info
            $st = $pdo->prepare("
                SELECT u.id, u.left_count, u.right_count,
                       u.pairs_paid, u.pairs_flushed, u.pairs_paid_today,
                       p.pairing_bonus, p.daily_pair_cap
                FROM   users u
                LEFT JOIN packages p ON p.id = u.package_id
                WHERE  u.id = ? AND u.status = 'active'
                  AND  p.pairing_bonus IS NOT NULL
            ");
            $st->execute([$cur]);
            $ancestor = $st->fetch();

            if ($ancestor) {
                $processed = $ancestor['pairs_paid'] + $ancestor['pairs_flushed'];
                $available = min($ancestor['left_count'], $ancestor['right_count']);
                $newPairs  = $available - $processed;

                if ($newPairs > 0) {
                    $capRemaining = (int)$ancestor['daily_pair_cap'] - (int)$ancestor['pairs_paid_today'];
                    $payNow       = min($newPairs, max(0, $capRemaining));
                    $flushNow     = $newPairs - $payNow;

                    // Credit earned pairs immediately — v2: cap-aware
                    if ($payNow > 0) {
                        $bonus = $payNow * (float)$ancestor['pairing_bonus'];
                        self::creditPairing($cur, $bonus, $payNow, $newUserId);
                    }

                    // Record flushed pairs (money permanently lost)
                    if ($flushNow > 0) {
                        self::recordFlush($cur, $flushNow, $newUserId);
                    }

                    // Update counters in one atomic statement
                    $pdo->prepare("
                        UPDATE users
                        SET pairs_paid       = pairs_paid       + :pay,
                            pairs_flushed    = pairs_flushed    + :flush,
                            pairs_paid_today = pairs_paid_today + :pay2
                        WHERE id = :id
                    ")->execute([
                        ':pay'   => $payNow,
                        ':flush' => $flushNow,
                        ':pay2'  => $payNow,
                        ':id'    => $cur,
                    ]);
                }
            }

            // 3. Move to this ancestor's own parent
            $upRow = $pdo->prepare(
                'SELECT binary_parent_id, binary_position FROM users WHERE id = ?'
            );
            $upRow->execute([$cur]);
            $up = $upRow->fetch();

            $side = $up['binary_position'] ?? null;
            $cur  = isset($up['binary_parent_id']) ? (int)$up['binary_parent_id'] : null;
            if (!$cur) break;
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  DIRECT REFERRAL BONUS
    //  Fires immediately to the sponsor when their direct recruit registers.
    //  v2: Now subject to lifetime income cap.
    // ══════════════════════════════════════════════════════════════════════════

    public static function processDirectReferral(
        int $sponsorId,
        int $newUserId,
        int $packageId
    ): void {
        $pkg = Package::find($packageId);
        if (!$pkg || (float)$pkg['direct_ref_bonus'] <= 0) return;

        $bonus = (float)$pkg['direct_ref_bonus'];

        // v2: Check lifetime cap before crediting
        $capCheck = CapEngine::canEarn($sponsorId, $bonus);
        if ($capCheck['allowed'] <= 0) {
            // Cap reached — record as blocked (audit trail)
            self::recordCapBlocked($sponsorId, $bonus, 'direct_referral', $newUserId);
            return;
        }

        $actualBonus = $capCheck['allowed'];
        $blocked     = $capCheck['blocked'];

        $pdo = db();
        $pdo->prepare("
            INSERT INTO commissions
              (user_id, type, amount, cap_deduction, source_user_id, description, status)
            VALUES (?, 'direct_referral', ?, ?, ?, 'Direct referral bonus', 'credited')
        ")->execute([$sponsorId, $actualBonus, $blocked, $newUserId]);

        $commId = (int)$pdo->lastInsertId();
        Ewallet::credit($sponsorId, $actualBonus, $commId, 'commission', 'Direct referral bonus');

        // v2: Record against lifetime cap
        CapEngine::recordEarning($sponsorId, $actualBonus, 'direct_referral');
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  UNILEVEL GENERATIONAL REFERRAL BONUSES
    //  Pure Sponsor Chain — No Binary Tree involvement at all
    //  v2: Now subject to lifetime income cap.
    // ══════════════════════════════════════════════════════════════════════════

    public static function processIndirectReferral(
        int $directSponsorId,
        int $newUserId,
        int $packageId
    ): void {
        $levels = Package::getIndirectLevels($packageId);
        if (empty($levels)) return;

        $pdo = db();
        $cur = $directSponsorId;
        $visited = [$directSponsorId => true];

        for ($lvl = 1; $lvl <= 10; $lvl++) {

            $bonus = (float)($levels[$lvl] ?? 0);

            if ($bonus > 0) {
                // v2: Check lifetime cap before crediting each level
                $capCheck = CapEngine::canEarn($cur, $bonus);

                if ($capCheck['allowed'] <= 0) {
                    // Cap reached at this level — record blocked, stop climbing
                    self::recordCapBlocked($cur, $bonus, 'indirect_referral', $newUserId, $lvl);
                    break; // Stop — higher levels won't get paid either
                }

                $actualBonus = $capCheck['allowed'];
                $blocked     = $capCheck['blocked'];

                $pdo->prepare("
                    INSERT INTO commissions
                      (user_id, type, amount, cap_deduction, source_user_id, level, description, status)
                    VALUES (?, 'indirect_referral', ?, ?, ?, ?, ?, 'credited')
                ")->execute([
                    $cur,
                    $actualBonus,
                    $blocked,
                    $newUserId,
                    $lvl,
                    "Unilevel Level {$lvl} Bonus"
                ]);

                $commId = (int)$pdo->lastInsertId();

                Ewallet::credit(
                    $cur,
                    $actualBonus,
                    $commId,
                    'commission',
                    "Unilevel Level {$lvl} Bonus"
                );

                // v2: Record against lifetime cap
                CapEngine::recordEarning($cur, $actualBonus, 'indirect_referral');
            }

            // Move up using ONLY sponsor_id
            $row = $pdo->prepare('SELECT sponsor_id FROM users WHERE id = ?');
            $row->execute([$cur]);
            $upRow = $row->fetch();

            if (!$upRow || empty($upRow['sponsor_id'])) {
                break;
            }

            $next = (int)$upRow['sponsor_id'];

            if (isset($visited[$next])) {
                break;
            }

            $visited[$next] = true;
            $cur = $next;
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private static function creditPairing(
        int $userId,
        float $amount,
        int $pairs,
        int $sourceId
    ): void {
        $pdo = db();
        $perPair = $pairs > 0 ? fmt_money($amount / $pairs) : '₱0.00';

        // v2: Check cap before crediting
        $capCheck = CapEngine::canEarn($userId, $amount);
        $actualAmount = $capCheck['allowed'];
        $blocked      = $capCheck['blocked'];

        if ($actualAmount <= 0) {
            // Fully blocked by cap — record as such
            self::recordCapBlocked($userId, $amount, 'pairing', $sourceId, null, $pairs);
            return;
        }

        $pdo->prepare("
            INSERT INTO commissions
              (user_id, type, amount, cap_deduction, source_user_id, pairs_count, description, status)
            VALUES (?, 'pairing', ?, ?, ?, ?, ?, 'credited')
        ")->execute([
            $userId,
            $actualAmount,
            $blocked,
            $sourceId,
            $pairs,
            "{$pairs} pair(s) × {$perPair}"
        ]);

        $commId = (int)$pdo->lastInsertId();
        Ewallet::credit($userId, $actualAmount, $commId, 'commission', "Pairing bonus — {$pairs} pair(s)");

        // v2: Record against lifetime cap
        CapEngine::recordEarning($userId, $actualAmount, 'pairing');
    }

    private static function recordFlush(int $userId, int $pairs, int $sourceId): void
    {
        db()->prepare("
            INSERT INTO commissions
              (user_id, type, amount, source_user_id, pairs_count, description, status)
            VALUES (?, 'pairing', 0.00, ?, ?, ?, 'flushed')
        ")->execute([
            $userId,
            $sourceId,
            $pairs,
            "{$pairs} pair(s) flushed — daily cap reached"
        ]);
    }

    /**
     * v2: Record commission blocked by lifetime cap (audit trail).
     */
    private static function recordCapBlocked(
        int $userId,
        float $amount,
        string $type,
        int $sourceId,
        ?int $level = null,
        ?int $pairs = null
    ): void {
        $desc = match ($type) {
            'pairing' => ($pairs ?? 0) . " pair(s) blocked — lifetime cap reached",
            'direct_referral' => "Direct referral blocked — lifetime cap reached",
            'indirect_referral' => "Unilevel L{$level} blocked — lifetime cap reached",
            default => "Commission blocked — lifetime cap reached",
        };

        db()->prepare("
            INSERT INTO commissions
              (user_id, type, amount, cap_deduction, source_user_id, level, pairs_count, description, status)
            VALUES (?, ?, 0.00, ?, ?, ?, ?, ?, 'flushed')
        ")->execute([
            $userId,
            $type,
            $amount,
            $sourceId,
            $level,
            $pairs,
            $desc,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    //  QUERY HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    public static function summary(int $userId): array
    {
        $st = db()->prepare("
            SELECT
              COALESCE(SUM(CASE WHEN type='pairing'           AND status='credited' THEN amount END), 0) AS total_pairing,
              COALESCE(SUM(CASE WHEN type='direct_referral'   AND status='credited' THEN amount END), 0) AS total_direct,
              COALESCE(SUM(CASE WHEN type='indirect_referral' AND status='credited' THEN amount END), 0) AS total_indirect,
              COALESCE(SUM(CASE WHEN status='credited'                              THEN amount END), 0) AS total_earned,
              COALESCE(SUM(CASE WHEN type='pairing' AND status='flushed' THEN pairs_count END), 0)       AS total_flushed_pairs,
              COALESCE(SUM(cap_deduction), 0) AS total_cap_blocked
            FROM commissions
            WHERE user_id = ?
        ");
        $st->execute([$userId]);
        return $st->fetch();
    }

    public static function recent(int $userId, int $limit = 10): array
    {
        $st = db()->prepare("
            SELECT c.*,
                   u.username AS source_username
            FROM   commissions c
            LEFT JOIN users u ON u.id = c.source_user_id
            WHERE  c.user_id = ?
            ORDER BY c.created_at DESC
            LIMIT  {$limit}
        ");
        $st->execute([$userId]);
        return $st->fetchAll();
    }

    public static function history(int $userId, int $page = 1, int $perPage = 20, string $type = ''): array
    {
        $where  = 'c.user_id = ?';
        $params = [$userId];

        if ($type && in_array($type, ['pairing', 'direct_referral', 'indirect_referral'])) {
            $where  .= ' AND c.type = ?';
            $params[] = $type;
        }

        return paginate(
            "SELECT c.*, u.username AS source_username
             FROM   commissions c
             LEFT JOIN users u ON u.id = c.source_user_id
             WHERE  {$where}
             ORDER BY c.created_at DESC",
            $params,
            $page,
            $perPage
        );
    }
}

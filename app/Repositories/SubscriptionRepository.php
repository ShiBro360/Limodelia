<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SubscriptionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(): array
    {
        return $this->pdo
            ->query(
                "SELECT id, name, slug, lessons_count, price, currency, billing_period, description, sort_order
                 FROM subscriptions
                 WHERE status = 'active'
                 ORDER BY sort_order ASC, lessons_count ASC, price ASC, id ASC"
            )
            ->fetchAll();
    }
}

<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PaymentMethodRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(): array
    {
        return $this->pdo
            ->query("SELECT id, name, slug FROM payment_methods WHERE status = 'active' ORDER BY sort_order, name")
            ->fetchAll();
    }
}

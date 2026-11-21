<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ExpenseCategoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id, name, slug
             FROM expense_categories
             WHERE status = 'active'
             ORDER BY sort_order ASC, name ASC"
        );

        return $stmt->fetchAll();
    }
}

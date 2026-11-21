<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/page.php';

use App\Helpers\FormatHelper;
use App\Helpers\IconHelper;
use App\Helpers\ResponseHelper;
use App\Repositories\ExpenseRepository;
use App\Repositories\MarketingRepository;
use App\Services\ExpenseService;
use App\Services\MarketingService;
use App\Validators\ExpenseValidator;
use App\Validators\MarketingValidator;

$errors = [];
$notice = $_GET['saved'] ?? null;
$marketingService = null;

try {
    $pdo = db();
    $marketingService = new MarketingService(
        new MarketingRepository($pdo),
        new MarketingValidator(),
        new ExpenseService(new ExpenseRepository($pdo), new ExpenseValidator())
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $campaignId = (int) ($_POST['campaign_id'] ?? 0);

        if ($action === 'create') {
            $errors = $marketingService->create($_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/marketing.php', ['saved' => 'created']);
            }
        } elseif ($action === 'update') {
            $errors = $marketingService->update($campaignId, $_POST);
            if ($errors === []) {
                ResponseHelper::redirect('pages/marketing.php', ['saved' => 'updated']);
            }
        } elseif ($action === 'cancel') {
            $errors = $marketingService->cancel($campaignId);
            if ($errors === []) {
                ResponseHelper::redirect('pages/marketing.php', ['saved' => 'cancelled']);
            }
        }
    }
} catch (Throwable $exception) {
    $errors[] = 'Błąd modułu marketingu: ' . $exception->getMessage();
}

render_page('Marketing', 'marketing', function () use ($marketingService, $errors, $notice): void {
    $filters = [
        'search' => '',
        'period' => 'month',
        'month' => date('Y-m'),
        'from' => date('Y-m-01'),
        'to' => date('Y-m-t'),
        'status' => '',
        'page' => 1,
        'per_page' => 10,
    ];
    $campaigns = [];
    $summary = [
        'budget' => 0,
        'leads' => 0,
        'new_students' => 0,
        'cost_per_lead' => 0,
        'cost_per_student' => 0,
        'conversion' => 0,
        'campaigns_count' => 0,
    ];
    $trend = [];
    $sources = [];
    $topCampaigns = [];
    $total = 0;
    $pageCount = 1;
    $editCampaign = null;

    if ($marketingService instanceof MarketingService) {
        try {
            $filters = $marketingService->filters($_GET);
            $data = $marketingService->paginated($filters);
            $campaigns = $data['campaigns'];
            $summary = $data['summary'];
            $trend = $data['trend'];
            $sources = $data['sources'];
            $topCampaigns = $data['top'];
            $total = $data['total'];
            $pageCount = $data['page_count'];
            $filters = $data['filters'];

            if (($_GET['modal'] ?? '') === 'edit') {
                $editCampaign = $marketingService->find((int) ($_GET['id'] ?? 0));
            }
        } catch (Throwable $exception) {
            $errors[] = 'Nie udało się pobrać kampanii: ' . $exception->getMessage();
        }
    }

    $showAddModal = ($_GET['modal'] ?? '') === 'add' || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create' && $errors !== []);
    $showEditModal = (($_GET['modal'] ?? '') === 'edit' && $editCampaign) || ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update' && $errors !== []);
    $formCampaign = $showEditModal ? ($editCampaign ?: $_POST) : $_POST;
    $sourceRows = marketing_source_rows($sources);
    $trendRows = marketing_trend_rows($trend);
    $funnelConversations = max(0, (int) $summary['leads'] - (int) $summary['new_students']);
    ?>
    <section class="module-hero">
        <div>
            <h1>Marketing</h1>
            <p>Analizuj skuteczność działań marketingowych na Instagramie.</p>
        </div>
        <div class="module-actions">
            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/marketing.php?modal=add')) ?>">
                <?= IconHelper::svg('add') ?> Dodaj kampanię
            </a>
        </div>
    </section>

    <?php if ($notice): ?>
        <div class="alert alert-info">Zmiany zostały zapisane.</div>
    <?php endif; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-info"><?= FormatHelper::e($error) ?></div>
    <?php endforeach; ?>

    <section class="kpi-grid marketing-kpi-grid">
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('instagram') ?></span>
            <div><span class="kpi-label">Wydatki na reklamy</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($summary['budget'])) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('users') ?></span>
            <div><span class="kpi-label">Zapytania</span><strong class="kpi-value"><?= FormatHelper::e((string) $summary['leads']) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('add') ?></span>
            <div><span class="kpi-label">Nowe uczennice</span><strong class="kpi-value"><?= FormatHelper::e((string) $summary['new_students']) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('money') ?></span>
            <div><span class="kpi-label">Koszt uczennicy</span><strong class="kpi-value"><?= FormatHelper::e(FormatHelper::money($summary['cost_per_student'])) ?></strong></div>
        </article>
        <article class="card kpi-card">
            <span class="kpi-icon" aria-hidden="true"><?= IconHelper::svg('percent') ?></span>
            <div><span class="kpi-label">Konwersja</span><strong class="kpi-value"><?= FormatHelper::e(number_format((float) $summary['conversion'], 1, ',', ' ')) ?>%</strong></div>
        </article>
    </section>

    <section class="card module-panel marketing-shell">
        <form class="filter-bar marketing-filter-bar" action="<?= FormatHelper::e(url('pages/marketing.php')) ?>" method="get" aria-label="Filtry marketingu" data-live-filter data-filter-target="#marketing-results">
            <label class="form-field filter-search">
                <span class="form-label">Szukaj</span>
                <span class="search-field"><input type="search" name="search" value="<?= FormatHelper::e($filters['search']) ?>" placeholder="Szukaj kampanii..."></span>
            </label>
            <label class="form-field">
                <span class="form-label">Okres</span>
                <select class="select" name="period">
                    <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>Wybrany miesiąc</option>
                    <option value="all" <?= $filters['period'] === 'all' ? 'selected' : '' ?>>Cały okres</option>
                </select>
            </label>
            <label class="form-field">
                <span class="form-label">Miesiąc</span>
                <input class="input" type="month" name="month" value="<?= FormatHelper::e($filters['month']) ?>">
            </label>
            <label class="form-field">
                <span class="form-label">Status</span>
                <select class="select" name="status">
                    <option value="">Wszystkie</option>
                    <?php foreach (marketing_statuses() as $value => $label): ?>
                        <option value="<?= FormatHelper::e($value) ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions marketing-filter-reset">
                <a class="btn btn-secondary btn-icon" href="<?= FormatHelper::e(url('pages/marketing.php')) ?>" aria-label="Reset filtrów" title="Reset filtrów">
                    <?= IconHelper::svg('close') ?>
                </a>
            </div>
        </form>

        <div id="marketing-results" data-live-results>
            <div class="marketing-grid">
                <section class="marketing-campaigns-card">
                    <div class="card-header">
                        <h2 class="card-title">Kampanie reklamowe</h2>
                    </div>
                    <?php if ($campaigns === []): ?>
                        <section class="empty-state module-empty">
                            <div class="empty-illustration" aria-hidden="true"><?= IconHelper::svg('instagram') ?></div>
                            <h2 class="empty-state-title">Brak kampanii</h2>
                            <p class="empty-state-text">Dodaj pierwszą kampanię Instagram, aby mierzyć skuteczność marketingu.</p>
                            <a class="btn btn-primary" href="<?= FormatHelper::e(url('pages/marketing.php?modal=add')) ?>">Dodaj kampanię</a>
                        </section>
                    <?php else: ?>
                        <div class="table-wrap marketing-table-wrap">
                            <table class="table marketing-table">
                                <thead>
                                    <tr>
                                        <th>Nazwa kampanii</th>
                                        <th>Okres</th>
                                        <th>Budżet</th>
                                        <th>Zapytania</th>
                                        <th>Nowe uczennice</th>
                                        <th>Koszt leada</th>
                                        <th>Koszt uczennicy</th>
                                        <th>Konwersja</th>
                                        <th>Status</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaigns as $campaign): ?>
                                        <tr>
                                            <td>
                                                <div class="marketing-campaign-name">
                                                    <span class="category-icon" aria-hidden="true"><?= IconHelper::svg('instagram') ?></span>
                                                    <div>
                                                        <strong><?= FormatHelper::e($campaign['name']) ?></strong>
                                                        <small><?= FormatHelper::e(marketing_source_label((string) ($campaign['source'] ?? 'instagram'))) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= FormatHelper::e(marketing_period($campaign)) ?></td>
                                            <td><strong><?= FormatHelper::e(FormatHelper::money($campaign['budget'], $campaign['currency'])) ?></strong></td>
                                            <td><?= FormatHelper::e((string) $campaign['leads_count']) ?></td>
                                            <td><?= FormatHelper::e((string) $campaign['new_students_count']) ?></td>
                                            <td><?= FormatHelper::e(FormatHelper::money($campaign['cost_per_lead'], $campaign['currency'])) ?></td>
                                            <td><?= FormatHelper::e($campaign['new_students_count'] > 0 ? FormatHelper::money($campaign['cost_per_student'], $campaign['currency']) : '-') ?></td>
                                            <td><?= FormatHelper::e(number_format((float) $campaign['conversion'], 1, ',', ' ')) ?>%</td>
                                            <td><span class="badge badge-dot <?= FormatHelper::e(marketing_status_badge($campaign['status'])) ?>"><?= FormatHelper::e(marketing_statuses()[$campaign['status']] ?? $campaign['status']) ?></span></td>
                                            <td>
                                                <div class="row-actions">
                                                    <a class="btn btn-secondary btn-icon btn-sm" href="<?= FormatHelper::e(url('pages/marketing.php?modal=edit&id=' . $campaign['id'])) ?>" aria-label="Edytuj"><?= IconHelper::svg('edit') ?></a>
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="cancel">
                                                        <input type="hidden" name="campaign_id" value="<?= FormatHelper::e((string) $campaign['id']) ?>">
                                                        <button class="btn btn-secondary btn-icon btn-sm" type="submit" aria-label="Anuluj"><?= IconHelper::svg('cancel') ?></button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <footer class="table-footer">
                            <p>Wyświetlanie <?= FormatHelper::e((string) count($campaigns)) ?> z <?= FormatHelper::e((string) $total) ?> kampanii</p>
                            <nav class="pagination" aria-label="Paginacja kampanii">
                                <?php
                                $baseParams = array_filter([
                                    'search' => $filters['search'],
                                    'period' => $filters['period'],
                                    'month' => $filters['month'],
                                    'status' => $filters['status'],
                                ], static fn ($value) => $value !== '');
                                ?>
                                <?php for ($page = 1; $page <= $pageCount; $page++): ?>
                                    <a class="page-link <?= $page === $filters['page'] ? 'is-active' : '' ?>" href="<?= FormatHelper::e(url('pages/marketing.php?' . http_build_query($baseParams + ['page' => $page]))) ?>"><?= $page ?></a>
                                <?php endfor; ?>
                            </nav>
                        </footer>
                    <?php endif; ?>
                </section>

                <aside class="marketing-side">
                    <section class="card marketing-side-card marketing-funnel-card">
                        <h2 class="card-title">Lejek pozyskiwania uczennic</h2>
                        <div class="marketing-funnel-ref">
                            <div class="funnel-shape">
                                <span style="--level-width: 100%"></span>
                                <span style="--level-width: 76%"></span>
                                <span style="--level-width: 48%"></span>
                            </div>
                            <div class="funnel-values">
                                <div><span>Wydatki</span><strong><?= FormatHelper::e(FormatHelper::money($summary['budget'])) ?></strong></div>
                                <div><span>Zapytania</span><strong><?= FormatHelper::e((string) $summary['leads']) ?></strong></div>
                                <div><span>Nowe uczennice</span><strong><?= FormatHelper::e((string) $summary['new_students']) ?></strong></div>
                            </div>
                        </div>
                    </section>
                    <section class="card marketing-side-card top-campaigns-card">
                        <div class="card-header">
                            <h2 class="card-title">Top kampanie</h2>
                            <span class="mini-select">Według nowych uczennic</span>
                        </div>
                        <?php if ($topCampaigns === []): ?>
                            <p class="profile-note">Brak danych dla wybranego okresu.</p>
                        <?php endif; ?>
                        <?php foreach ($topCampaigns as $campaign): ?>
                            <div class="top-campaign-row">
                                <span class="top-campaign-thumb" aria-hidden="true"><?= IconHelper::svg('instagram') ?></span>
                                <div>
                                    <strong><?= FormatHelper::e($campaign['name']) ?></strong>
                                    <small><?= FormatHelper::e((string) $campaign['new_students_count']) ?> uczennice</small>
                                </div>
                                <span><?= FormatHelper::e($campaign['new_students_count'] > 0 ? FormatHelper::money($campaign['cost_per_student'], $campaign['currency']) . ' / ucz.' : '-') ?></span>
                            </div>
                        <?php endforeach; ?>
                    </section>
                </aside>

                <article class="card marketing-chart-card">
                    <h2 class="card-title">Wydatki vs nowe uczennice</h2>
                    <div class="marketing-chart-legend">
                        <span><i class="legend-dot gold"></i>Wydatki</span>
                        <span><i class="legend-dot rose"></i>Nowe uczennice</span>
                    </div>
                    <div class="marketing-column-chart">
                        <?php foreach ($trendRows as $row): ?>
                            <div class="marketing-column">
                                <div class="column-bars">
                                    <span class="column-bar budget" style="--bar-height: <?= FormatHelper::e($row['budget_percent']) ?>%"></span>
                                    <span class="column-bar students" style="--bar-height: <?= FormatHelper::e($row['students_percent']) ?>%"></span>
                                </div>
                                <small><?= FormatHelper::e($row['label']) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="card marketing-sources-card">
                    <h2 class="card-title">Źródła zapytań</h2>
                    <div class="marketing-sources-layout">
                        <div class="marketing-donut" style="<?= FormatHelper::e(marketing_sources_gradient($sourceRows)) ?>">
                            <span><strong><?= FormatHelper::e((string) $summary['leads']) ?></strong><small>Zapytania</small></span>
                        </div>
                        <div class="marketing-source-list">
                            <?php foreach ($sourceRows as $row): ?>
                                <div class="marketing-source-row">
                                    <span><i style="--source-color: <?= FormatHelper::e($row['color']) ?>"></i><?= FormatHelper::e($row['label']) ?></span>
                                    <strong><?= FormatHelper::e((string) $row['leads']) ?> <small>(<?= FormatHelper::e($row['percent_label']) ?>)</small></strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </section>

    <?php
    $renderModal = static function (string $mode, array $values): void {
        $isEdit = $mode === 'edit';
        ?>
        <div class="modal-backdrop is-open">
            <section class="modal payment-modal">
                <div class="card-header">
                    <h2 class="card-title"><?= $isEdit ? 'Edytuj kampanię' : 'Dodaj kampanię' ?></h2>
                    <a class="btn btn-ghost btn-icon" href="<?= FormatHelper::e(url('pages/marketing.php')) ?>" aria-label="Zamknij"><?= IconHelper::svg('close') ?></a>
                </div>
                <form class="payment-form-grid" method="post">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="campaign_id" value="<?= FormatHelper::e((string) ($values['id'] ?? '')) ?>">
                    <?php endif; ?>
                    <label class="form-field">
                        <span class="form-label">Nazwa kampanii</span>
                        <input class="input" name="name" value="<?= FormatHelper::e((string) ($values['name'] ?? '')) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Typ Instagram</span>
                        <select class="select" name="source">
                            <?php foreach (marketing_sources() as $value => $label): ?>
                                <option value="<?= FormatHelper::e($value) ?>" <?= ($values['source'] ?? 'posty') === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data startu</span>
                        <input class="input" type="date" name="start_date" value="<?= FormatHelper::e((string) ($values['start_date'] ?? date('Y-m-d'))) ?>" required>
                    </label>
                    <label class="form-field">
                        <span class="form-label">Data zakończenia</span>
                        <input class="input" type="date" name="end_date" value="<?= FormatHelper::e((string) ($values['end_date'] ?? '')) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Budżet</span>
                        <input class="input" type="number" min="0" step="0.01" name="budget" value="<?= FormatHelper::e((string) ($values['budget'] ?? '0.00')) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Liczba zapytań</span>
                        <input class="input" type="number" min="0" step="1" name="leads_count" value="<?= FormatHelper::e((string) ($values['leads_count'] ?? '0')) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Nowe uczennice</span>
                        <input class="input" type="number" min="0" step="1" name="new_students_count" value="<?= FormatHelper::e((string) ($values['new_students_count'] ?? '0')) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Instagram post URL</span>
                        <input class="input" type="url" name="instagram_post_url" value="<?= FormatHelper::e((string) ($values['instagram_post_url'] ?? '')) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Campaign URL</span>
                        <input class="input" type="url" name="campaign_url" value="<?= FormatHelper::e((string) ($values['campaign_url'] ?? '')) ?>">
                    </label>
                    <label class="form-field">
                        <span class="form-label">Status</span>
                        <select class="select" name="status">
                            <?php foreach (marketing_statuses() as $value => $label): ?>
                                <option value="<?= FormatHelper::e($value) ?>" <?= ($values['status'] ?? 'planned') === $value ? 'selected' : '' ?>><?= FormatHelper::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="form-field payment-form-wide">
                        <span class="form-label">Notatki</span>
                        <textarea class="textarea" name="description"><?= FormatHelper::e((string) ($values['description'] ?? '')) ?></textarea>
                    </label>
                    <div class="payment-form-actions">
                        <a class="btn btn-secondary" href="<?= FormatHelper::e(url('pages/marketing.php')) ?>">Anuluj</a>
                        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Zapisz zmiany' : 'Zapisz kampanię' ?></button>
                    </div>
                </form>
            </section>
        </div>
        <?php
    };

    if ($showAddModal) {
        $renderModal('add', $_POST);
    }
    if ($showEditModal) {
        $renderModal('edit', $formCampaign);
    }
});

function marketing_statuses(): array
{
    return [
        'planned' => 'Planowana',
        'active' => 'Aktywna',
        'paused' => 'Pauza',
        'completed' => 'Zakończona',
        'cancelled' => 'Anulowana',
    ];
}

function marketing_sources(): array
{
    return [
        'posty' => 'Instagram - posty',
        'reels' => 'Instagram - reels',
        'stories' => 'Instagram - stories',
        'wspolpraca' => 'Współpraca',
        'instagram' => 'Instagram',
    ];
}

function marketing_source_label(string $source): string
{
    return marketing_sources()[$source] ?? 'Instagram';
}

function marketing_status_badge(string $status): string
{
    return match ($status) {
        'active', 'completed' => 'badge-success',
        'paused', 'planned' => 'badge-warning',
        'cancelled' => 'badge-muted',
        default => 'badge-sand',
    };
}

function marketing_period(array $campaign): string
{
    $start = FormatHelper::date((string) ($campaign['start_date'] ?? ''));
    $end = !empty($campaign['end_date']) ? FormatHelper::date((string) $campaign['end_date']) : $start;

    return $start === $end ? $start : $start . ' - ' . $end;
}

function marketing_trend_rows(array $trend): array
{
    $months = ['Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru'];
    $map = array_fill(1, 12, ['budget' => 0.0, 'new_students' => 0]);
    foreach ($trend as $row) {
        $map[(int) $row['month']] = ['budget' => (float) $row['budget'], 'new_students' => (int) $row['new_students']];
    }
    $maxBudget = max(1, ...array_map(static fn ($row) => (float) $row['budget'], $map));
    $maxStudents = max(1, ...array_map(static fn ($row) => (int) $row['new_students'], $map));
    $rows = [];
    foreach ($map as $month => $row) {
        $rows[] = [
            'label' => $months[$month - 1],
            'budget' => $row['budget'],
            'new_students' => $row['new_students'],
            'budget_percent' => number_format(max(3, ($row['budget'] / $maxBudget) * 100), 2, '.', ''),
            'students_percent' => number_format(max(3, ($row['new_students'] / $maxStudents) * 100), 2, '.', ''),
        ];
    }
    return $rows;
}

function marketing_source_rows(array $sources): array
{
    $colors = ['#C89B3C', '#E05B7D', '#3F7FE8', '#8D6A55', '#D8CEC4'];
    $totalLeads = array_sum(array_map(static fn ($row) => (int) ($row['leads'] ?? 0), $sources));
    $rows = [];

    foreach ($sources as $index => $row) {
        $leads = (int) ($row['leads'] ?? 0);
        $percent = $totalLeads > 0 ? ($leads / $totalLeads) * 100 : 0;
        $rows[] = [
            'label' => marketing_source_label((string) ($row['source'] ?? 'instagram')),
            'leads' => $leads,
            'percent' => $percent,
            'percent_label' => number_format($percent, 1, ',', ' ') . '%',
            'color' => $colors[$index % count($colors)],
        ];
    }

    if ($rows === []) {
        $rows[] = ['label' => 'Instagram', 'leads' => 0, 'percent' => 100, 'percent_label' => '0,0%', 'color' => '#D8CEC4'];
    }

    return $rows;
}

function marketing_sources_gradient(array $rows): string
{
    $cursor = 0.0;
    $segments = [];
    foreach ($rows as $row) {
        $start = $cursor;
        $end = $cursor + (float) $row['percent'];
        if ($end <= $start) {
            continue;
        }
        $segments[] = $row['color'] . ' ' . number_format($start, 2, '.', '') . '% ' . number_format($end, 2, '.', '') . '%';
        $cursor = $end;
    }

    if ($segments === []) {
        $segments[] = '#D8CEC4 0% 100%';
    }

    return '--donut-gradient: conic-gradient(' . implode(', ', $segments) . ');';
}

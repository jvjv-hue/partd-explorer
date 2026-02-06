<?php
require_once 'config.php';

$brand = trim($_GET['brand'] ?? '');
$generic = trim($_GET['generic'] ?? '');

if (empty($brand)) {
    die("<div class='container mt-5'><div class='alert alert-danger'>No drug selected.</div></div>");
}

// Pagination settings
$per_page = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

try {
    // Aggregated totals
    $where_agg = "Brnd_Name = :brand";
    $params_agg = ['brand' => $brand];
    if ($generic !== '') {
        $where_agg .= " AND Gnrc_Name = :generic";
        $params_agg['generic'] = $generic;
    }
    $sql_agg = "
        SELECT
            SUM(Tot_Clms) AS Tot_Clms,
            SUM(Tot_30day_Fills) AS Tot_30day_Fills,
            SUM(Tot_Day_Suply) AS Tot_Day_Suply,
            SUM(Tot_Drug_Cst) AS Tot_Drug_Cst,
            SUM(Tot_Benes) AS Tot_Benes,
            MAX(GE65_Sprsn_Flag) AS GE65_Sprsn_Flag,
            SUM(GE65_Tot_Clms) AS GE65_Tot_Clms,
            SUM(GE65_Tot_30day_Fills) AS GE65_Tot_30day_Fills,
            SUM(GE65_Tot_Drug_Cst) AS GE65_Tot_Drug_Cst,
            SUM(GE65_Tot_Day_Suply) AS GE65_Tot_Day_Suply,
            MAX(GE65_Bene_Sprsn_Flag) AS GE65_Bene_Sprsn_Flag,
            SUM(GE65_Tot_Benes) AS GE65_Tot_Benes,
            COUNT(DISTINCT Prscrbr_NPI) AS Prescriber_Count
        FROM mup_dpr
        WHERE $where_agg
    ";
    $stmt_agg = $pdo->prepare($sql_agg);
    $stmt_agg->execute($params_agg);
    $agg = $stmt_agg->fetch();

    // Total count
    $count_sql = "SELECT COUNT(DISTINCT Prscrbr_NPI) AS total FROM mup_dpr WHERE $where_agg";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params_agg);
    $total_prescribers = (int)$count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_prescribers / $per_page));

    // Paginated prescribers
    $sql_list = "
        SELECT
            Prscrbr_NPI,
            ANY_VALUE(Prscrbr_Last_Org_Name) AS Prscrbr_Last_Org_Name,
            ANY_VALUE(Prscrbr_First_Name) AS Prscrbr_First_Name,
            ANY_VALUE(Prscrbr_City) AS Prscrbr_City,
            ANY_VALUE(Prscrbr_State_Abrvtn) AS Prscrbr_State_Abrvtn,
            ANY_VALUE(Prscrbr_Type) AS Prscrbr_Type,
            SUM(Tot_Clms) AS Prescriber_Clms,
            SUM(Tot_Drug_Cst) AS Prescriber_Cost
        FROM mup_dpr
        WHERE $where_agg
        GROUP BY Prscrbr_NPI
        ORDER BY Prescriber_Clms DESC
        LIMIT :limit OFFSET :offset
    ";
    $stmt_list = $pdo->prepare($sql_list);
    foreach ($params_agg as $k => $v) {
        $stmt_list->bindValue(":$k", $v);
    }
    $stmt_list->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $prescribers = $stmt_list->fetchAll();

    // Export ALL
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="npis_' . urlencode($brand) . ($generic ? '_' . urlencode($generic) : '') . '.csv"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['NPI', 'Last/Org Name', 'First Name', 'City', 'State', 'Type', 'Claims for this Drug', 'Drug Cost for this Drug']);

        $export_sql = "
            SELECT
                Prscrbr_NPI,
                ANY_VALUE(Prscrbr_Last_Org_Name) AS Prscrbr_Last_Org_Name,
                ANY_VALUE(Prscrbr_First_Name) AS Prscrbr_First_Name,
                ANY_VALUE(Prscrbr_City) AS Prscrbr_City,
                ANY_VALUE(Prscrbr_State_Abrvtn) AS Prscrbr_State_Abrvtn,
                ANY_VALUE(Prscrbr_Type) AS Prscrbr_Type,
                SUM(Tot_Clms) AS Prescriber_Clms,
                SUM(Tot_Drug_Cst) AS Prescriber_Cost
            FROM mup_dpr
            WHERE $where_agg
            GROUP BY Prscrbr_NPI
            ORDER BY Prescriber_Clms DESC
        ";
        $stmt_export = $pdo->prepare($export_sql);
        foreach ($params_agg as $k => $v) {
            $stmt_export->bindValue(":$k", $v);
        }
        $stmt_export->execute();

        while ($p = $stmt_export->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $p['Prscrbr_NPI'] ?? '',
                $p['Prscrbr_Last_Org_Name'] ?? '',
                $p['Prscrbr_First_Name'] ?? '',
                $p['Prscrbr_City'] ?? '',
                $p['Prscrbr_State_Abrvtn'] ?? '',
                $p['Prscrbr_Type'] ?? '',
                $p['Prescriber_Clms'] ?? 0,
                $p['Prescriber_Cost'] ?? 0.00
            ]);
        }
        fclose($output);
        exit;
    }

} catch (PDOException $e) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drug: <?= htmlspecialchars($brand) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>

<div class="container my-4">
    <a href="javascript:history.back()" class="btn btn-outline-secondary mb-4">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>

    <div class="card shadow-sm position-relative">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-1"><?= htmlspecialchars($brand) ?></h4>
            <?php if ($generic): ?>
                <div class="small">(Generic: <?= htmlspecialchars($generic) ?>)</div>
            <?php endif; ?>
            <div class="small mt-1">
                Nationwide • <?= number_format($agg['Prescriber_Count'] ?? 0) ?> prescribers
            </div>
        </div>

        <div class="card-body position-relative">
            <!-- Loading overlay -->
            <div class="loading-overlay active" id="loading-overlay">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="loading-text">Loading prescribers...</div>
                </div>
            </div>

            <!-- Aggregated totals -->
            <div class="row g-3 mb-5 bg-light p-3 rounded">
                <div class="col-md-3 col-6">
                    <div class="fw-bold text-muted small">Total Claims</div>
                    <div class="fs-4"><?= number_format($agg['Tot_Clms'] ?? 0) ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="fw-bold text-muted small">Drug Cost</div>
                    <div class="fs-4 text-danger">$<?= number_format($agg['Tot_Drug_Cst'] ?? 0, 2) ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="fw-bold text-muted small">Beneficiaries</div>
                    <div class="fs-4"><?= number_format($agg['Tot_Benes'] ?? 0) ?></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="fw-bold text-muted small">Prescribers</div>
                    <div class="fs-4"><?= number_format($agg['Prescriber_Count'] ?? 0) ?></div>
                </div>
            </div>

            <!-- Prescribers list + export -->
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                <h5 class="mb-0">Prescribers Prescribing This Drug</h5>
                <a href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&export=csv"
                   class="btn btn-sm btn-success">
                    <i class="bi bi-download me-1"></i> Export ALL NPIs (CSV)
                </a>
            </div>

            <?php if (empty($prescribers)): ?>
                <div class="alert alert-info">No prescribers found for this drug.</div>
            <?php else: ?>
                <!-- Pagination (top) -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Prescribers pagination" class="mb-4">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php
                        $range = 2;
                        $start = max(1, $page - $range);
                        $end = min($total_pages, $page + $range);
                        for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>NPI</th>
                                <th>Name</th>
                                <th>City, State</th>
                                <th>Type</th>
                                <th class="text-end">Claims</th>
                                <th class="text-end">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prescribers as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['Prscrbr_NPI'] ?? '') ?></td>
                                <td>
                                    <?= htmlspecialchars($p['Prscrbr_Last_Org_Name'] ?? '') ?>,
                                    <?= htmlspecialchars($p['Prscrbr_First_Name'] ?? '') ?>
                                </td>
                                <td><?= htmlspecialchars($p['Prscrbr_City'] ?? 'N/A') ?>,
                                    <?= htmlspecialchars($p['Prscrbr_State_Abrvtn'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($p['Prscrbr_Type'] ?? 'N/A') ?></td>
                                <td class="text-end"><?= number_format($p['Prescriber_Clms'] ?? 0) ?></td>
                                <td class="text-end">$<?= number_format($p['Prescriber_Cost'] ?? 0, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination (bottom) -->
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Prescribers pagination bottom" class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&page=<?= $page - 1 ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?brand=<?= urlencode($brand) ?>&generic=<?= urlencode($generic) ?>&page=<?= $page + 1 ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

                <div class="text-center text-muted small mt-3">
                    Showing <?= count($prescribers) ?> of <?= number_format($total_prescribers) ?> prescribers
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Hide loading overlay once page is ready
window.addEventListener('load', function() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.classList.remove('active');
});

// Re-show on pagination clicks
document.querySelectorAll('.pagination a').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!this.parentElement.classList.contains('disabled')) {
            const overlay = document.getElementById('loading-overlay');
            if (overlay) overlay.classList.add('active');
        }
    });
});
</script>

</body>
</html>

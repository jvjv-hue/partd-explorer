<?php
require_once 'config.php';

$npi = filter_input(INPUT_GET, 'npi', FILTER_VALIDATE_INT);
if (!$npi) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Invalid NPI.</div></div>");
}

try {
    // Prescriber info
    $stmt = $pdo->prepare("
        SELECT Prscrbr_Last_Org_Name, Prscrbr_First_Name, Prscrbr_City, 
               Prscrbr_State_Abrvtn, Prscrbr_Type
        FROM mup_dpr 
        WHERE Prscrbr_NPI = :npi 
        LIMIT 1
    ");
    $stmt->execute(['npi' => $npi]);
    $prescriber = $stmt->fetch();

    if (!$prescriber) {
        die("<div class='container mt-5'><div class='alert alert-warning'>No data found for NPI $npi.</div></div>");
    }

    // Aggregated drugs with unique prescriber count per drug
    $stmt = $pdo->prepare("
        SELECT 
            Brnd_Name,
            Gnrc_Name,
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
            COUNT(DISTINCT Prscrbr_NPI) AS unique_prescribers
        FROM mup_dpr
        WHERE Prscrbr_NPI = :npi
        GROUP BY Brnd_Name, Gnrc_Name
        ORDER BY Brnd_Name
    ");
    $stmt->execute(['npi' => $npi]);
    $drugs = $stmt->fetchAll();

    // Grand totals
    $totals = [
        'Tot_Clms' => 0, 'Tot_30day_Fills' => 0, 'Tot_Day_Suply' => 0,
        'Tot_Drug_Cst' => 0, 'Tot_Benes' => 0,
        'GE65_Tot_Clms' => 0, 'GE65_Tot_30day_Fills' => 0,
        'GE65_Tot_Day_Suply' => 0, 'GE65_Tot_Drug_Cst' => 0, 'GE65_Tot_Benes' => 0
    ];
    foreach ($drugs as $drug) {
        foreach ($totals as $key => &$val) {
            $val += (float)($drug[$key] ?? 0);
        }
    }

} catch (PDOException $e) {
    die("<div class='container mt-5'><div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriber: <?= htmlspecialchars($prescriber['Prscrbr_Last_Org_Name'] ?? 'N/A') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>

<div class="container">
    <a href="index.php" class="btn btn-outline-secondary mb-4">
        <i class="bi bi-arrow-left me-2"></i>Back to Search
    </a>

    <div class="card mb-5">
        <div class="card-header">
            <h4>
                <?= htmlspecialchars($prescriber['Prscrbr_Last_Org_Name'] ?? 'N/A') ?>, 
                <?= htmlspecialchars($prescriber['Prscrbr_First_Name'] ?: 'N/A') ?>
            </h4>
            <div class="small mt-1">
                NPI: <?= htmlspecialchars($npi) ?> • 
                <?= htmlspecialchars($prescriber['Prscrbr_City'] ?? 'N/A') ?>, 
                <?= htmlspecialchars($prescriber['Prscrbr_State_Abrvtn'] ?? 'N/A') ?> • 
                <?= htmlspecialchars($prescriber['Prscrbr_Type'] ?? 'N/A') ?>
            </div>
        </div>

        <div class="card-body position-relative">
            <!-- Loading Overlay -->
            <div class="loading-overlay active" id="loading-overlay">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="loading-text">Loading prescriber data...</div>
                </div>
            </div>

            <?php if (empty($drugs)): ?>
                <div class="p-5 text-center text-muted fs-5">
                    No prescription records found for this prescriber.
                </div>
            <?php else: ?>
                <!-- Grand Totals -->
                <div class="bg-light p-3 rounded mb-4 border">
                    <h5 class="mb-3 text-primary">Grand Totals</h5>
                    <div class="row g-3">
                        <div class="col-6 col-md-3">
                            <div class="fw-bold">Total Claims</div>
                            <div class="fs-5"><?= number_format($totals['Tot_Clms']) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fw-bold">30-Day Fills</div>
                            <div class="fs-5"><?= number_format($totals['Tot_30day_Fills'], 1) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fw-bold">Day Supply</div>
                            <div class="fs-5"><?= number_format($totals['Tot_Day_Suply']) ?></div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fw-bold">Drug Cost</div>
                            <div class="fs-5 text-danger">$<?= number_format($totals['Tot_Drug_Cst'], 2) ?></div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Brand Name</th>
                                <th>Generic Name</th>
                                <th class="text-end">Total Claims</th>
                                <th class="text-end">30-Day Fills</th>
                                <th class="text-end">Day Supply</th>
                                <th class="text-end">Drug Cost</th>
                                <th class="text-end">Beneficiaries</th>
                                <th class="text-end">Prescribers</th>
                                <th class="text-end">GE65 Claims / Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($drugs as $drug): ?>
                            <tr>
                                <td data-label="Brand Name"><?= htmlspecialchars($drug['Brnd_Name'] ?: 'N/A') ?></td>
                                <td data-label="Generic Name"><?= htmlspecialchars($drug['Gnrc_Name'] ?: 'N/A') ?></td>
                                <td data-label="Total Claims" class="text-end"><?= number_format($drug['Tot_Clms'] ?? 0) ?></td>
                                <td data-label="30-Day Fills" class="text-end"><?= number_format($drug['Tot_30day_Fills'] ?? 0, 1) ?></td>
                                <td data-label="Day Supply" class="text-end"><?= number_format($drug['Tot_Day_Suply'] ?? 0) ?></td>
                                <td data-label="Drug Cost" class="text-end">$<?= number_format($drug['Tot_Drug_Cst'] ?? 0, 2) ?></td>
                                <td data-label="Beneficiaries" class="text-end"><?= number_format($drug['Tot_Benes'] ?? 0) ?></td>
                                <td data-label="Prescribers" class="text-end">
                                    <a href="drug.php?brand=<?= urlencode($drug['Brnd_Name'] ?? '') ?>&generic=<?= urlencode($drug['Gnrc_Name'] ?? '') ?>" 
                                       class="btn btn-sm btn-outline-info">
                                        <?= number_format($drug['unique_prescribers'] ?? 1) ?> doctor<?= ($drug['unique_prescribers'] ?? 1) != 1 ? 's' : '' ?>
                                    </a>
                                </td>
                                <td data-label="GE65 Claims / Cost" class="text-end">
                                    <?= htmlspecialchars($drug['GE65_Sprsn_Flag'] ?? '') ?> 
                                    <?= number_format($drug['GE65_Tot_Clms'] ?? 0) ?> / 
                                    $<?= number_format($drug['GE65_Tot_Drug_Cst'] ?? 0, 2) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
// Hide loading overlay once page is fully loaded
window.addEventListener('load', function() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.classList.remove('active');
});
</script>

</body>
</html>

<?php
require_once 'config.php';

header('Content-Type: application/json');

$term = trim($_GET['term'] ?? '');
if (strlen($term) < 2) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            Brnd_Name,
            Gnrc_Name
        FROM mup_dpr
        WHERE LOWER(Brnd_Name) LIKE LOWER(:prefix1)
           OR LOWER(Gnrc_Name) LIKE LOWER(:prefix2)
           OR LOWER(Brnd_Name) LIKE LOWER(:contains1)
           OR LOWER(Gnrc_Name) LIKE LOWER(:contains2)
        ORDER BY 
            LOWER(Brnd_Name) LIKE LOWER(:prefix_order1) DESC,
            LOWER(Gnrc_Name) LIKE LOWER(:prefix_order2) DESC,
            Brnd_Name,
            Gnrc_Name
        LIMIT 15
    ");

    $prefix   = $term . '%';
    $contains = '%' . $term . '%';

    $stmt->execute([
        'prefix1'     => $prefix,
        'prefix2'     => $prefix,
        'contains1'   => $contains,
        'contains2'   => $contains,
        'prefix_order1' => $prefix,
        'prefix_order2' => $prefix
    ]);

    $results = $stmt->fetchAll();

    $suggestions = [];
    foreach ($results as $row) {
        $brand_clean   = trim($row['Brnd_Name'] ?? '');
        $generic_clean = trim($row['Gnrc_Name'] ?? '');

        $label = $brand_clean ?: '(Unknown Brand)';
        if ($generic_clean && $generic_clean !== $brand_clean) {
            $label .= " (" . $generic_clean . ")";
        }

        $suggestions[] = [
            'label'   => $label,
            'brand'   => $brand_clean,
            'generic' => $generic_clean
        ];
    }

    echo json_encode($suggestions);

} catch (PDOException $e) {
    echo json_encode(['debug_error' => $e->getMessage()]);
}
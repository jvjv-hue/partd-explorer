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
            Prscrbr_NPI, 
            Prscrbr_Last_Org_Name, 
            Prscrbr_First_Name
        FROM mup_dpr
        WHERE Prscrbr_Last_Org_Name LIKE :term
        ORDER BY Prscrbr_Last_Org_Name
        LIMIT 20
    ");

    $search_term = $term . '%';
    $stmt->execute(['term' => $search_term]);

    $results = $stmt->fetchAll();

    $suggestions = [];
    foreach ($results as $row) {
        $label = $row['Prscrbr_Last_Org_Name'];
        if (!empty($row['Prscrbr_First_Name'])) {
            $label .= ", " . $row['Prscrbr_First_Name'];
        }
        $label .= " (NPI: " . $row['Prscrbr_NPI'] . ")";

        $suggestions[] = [
            'label' => $label,
            'value' => $row['Prscrbr_NPI']
        ];
    }

    echo json_encode($suggestions);

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

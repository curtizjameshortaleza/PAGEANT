<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// Set headers for JSON response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00');

// Get year from request
$year = $_GET['year'] ?? '';

if (!$year) {
    http_response_code(400);
    echo json_encode(['error' => 'Year parameter required']);
    exit;
}

// Check if there are any scores with status = 0 (incomplete) for this year
$stmt = $mysqli->prepare("SELECT COUNT(*) as incomplete_count FROM tbl_scores WHERE year = ? AND status = 0");
$stmt->bind_param('s', $year);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$stmt->close();

$hasIncompleteScores = $result['incomplete_count'] > 0;

// Also check completed scores count
$stmt = $mysqli->prepare("SELECT COUNT(*) as completed_count FROM tbl_scores WHERE year = ? AND status = 1");
$stmt->bind_param('s', $year);
$stmt->execute();
$completedResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Return status information
echo json_encode([
    'year' => $year,
    'has_incomplete_scores' => $hasIncompleteScores,
    'incomplete_count' => (int)$result['incomplete_count'],
    'completed_count' => (int)$completedResult['completed_count'],
    'status' => $hasIncompleteScores ? 0 : 1,
    'timestamp' => time()
]);
?>

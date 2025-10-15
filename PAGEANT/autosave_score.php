<?php
require_once __DIR__ . '/db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

$mysqli = db_connect();
$mysqli->set_charset('utf8mb4');

if (!isset($_SESSION['judge_id'], $_SESSION['judge_year'])) {
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

// Accept both JSON and form-encoded POST
$input = file_get_contents('php://input');
$data = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false && $input) {
    $data = json_decode($input, true) ?? [];
} else {
    $data = $_POST;
}

$judge_id = (int)$_SESSION['judge_id'];
$year = (string)$_SESSION['judge_year'];
$candidate_id = isset($data['candidate_id']) ? (int)$data['candidate_id'] : 0;
$criteria_id = isset($data['criteria_id']) ? (int)$data['criteria_id'] : 0;
$score_raw = isset($data['score']) ? $data['score'] : null;

if (!$candidate_id || !$criteria_id || $score_raw === null || $score_raw === '') {
    echo json_encode(['ok' => false, 'error' => 'params']);
    exit;
}

// Normalize numeric input (allow "1", "1.0", "1.00", etc.)
if (!is_numeric($score_raw)) {
    echo json_encode(['ok' => false, 'error' => 'invalid_score']);
    exit;
}

$score = (float)$score_raw;

// Verify criteria belongs to this year and fetch its max (percentage) to validate bounds
$chk = $mysqli->prepare('SELECT percentage FROM tbl_criteria WHERE id = ? AND year = ? LIMIT 1');
if (!$chk) {
    echo json_encode(['ok' => false, 'error' => 'db_prepare', 'mysqli_error' => $mysqli->error]);
    exit;
}
$chk->bind_param('is', $criteria_id, $year);
if (!$chk->execute()) {
    echo json_encode(['ok' => false, 'error' => 'db_execute', 'mysqli_error' => $chk->error]);
    $chk->close();
    exit;
}
$chk->store_result();
if ($chk->num_rows === 0) {
    $chk->close();
    echo json_encode(['ok' => false, 'error' => 'criteria_not_found']);
    exit;
}
$chk->bind_result($percentage);
$chk->fetch();
$chk->close();

$max_allowed = (float)$percentage;
if ($score < 1 || $score > $max_allowed) {
    echo json_encode(['ok' => false, 'error' => 'score_out_of_range', 'min' => 1, 'max' => $max_allowed]);
    exit;
}

// Verify candidate belongs to this year
$chk2 = $mysqli->prepare('SELECT id FROM tbl_candidates WHERE id = ? AND year = ? LIMIT 1');
if (!$chk2) {
    echo json_encode(['ok' => false, 'error' => 'db_prepare', 'mysqli_error' => $mysqli->error]);
    exit;
}
$chk2->bind_param('is', $candidate_id, $year);
if (!$chk2->execute()) {
    echo json_encode(['ok' => false, 'error' => 'db_execute', 'mysqli_error' => $chk2->error]);
    $chk2->close();
    exit;
}
$chk2->store_result();
if ($chk2->num_rows === 0) {
    $chk2->close();
    echo json_encode(['ok' => false, 'error' => 'candidate_not_found']);
    exit;
}
$chk2->close();

// Upsert the score. Use status = 0 for autosave (final submit should set status = 1).
$stmt = $mysqli->prepare(
    'INSERT INTO tbl_scores (judge_id, candidate_id, criteria_id, score, status, year, updated_at) 
     VALUES (?, ?, ?, ?, 0, ?, NOW())
     ON DUPLICATE KEY UPDATE score = VALUES(score), status = VALUES(status), updated_at = NOW()'
);

if (!$stmt) {
    echo json_encode(['ok' => false, 'error' => 'db_prepare_insert', 'mysqli_error' => $mysqli->error]);
    exit;
}

// bind: judge_id (i), candidate_id (i), criteria_id (i), score (d), year (s)
$stmt->bind_param('iiids', $judge_id, $candidate_id, $criteria_id, $score, $year);
if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => 'db_execute', 'mysqli_error' => $stmt->error]);
    $stmt->close();
    exit;
}
$stmt->close();

echo json_encode(['ok' => true]);

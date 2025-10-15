<?php
require_once __DIR__ . '/db.php';
session_start();
$mysqli = db_connect();

if (!isset($_SESSION['judge_id'], $_SESSION['judge_year'])) {
	header('Location: judge_start.php');
	exit;
}

$judge_id = (int)$_SESSION['judge_id'];
$year = $_SESSION['judge_year'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('Location: judge_criteria.php');
	exit;
}

$criteria_id = (int)($_POST['criteria_id'] ?? 0);
$scores = $_POST['score'] ?? [];

// Check if this criteria already has submitted scores for this judge
$check = $mysqli->prepare('SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id=? AND criteria_id=? AND status=1 LIMIT 1');
$check->bind_param('ii', $judge_id, $criteria_id);
$check->execute();
$r = $check->get_result()->fetch_assoc();
$check->close();
if ($r && (int)$r['count'] > 0) {
	header('Location: pageant_criteria.php');
	exit;
}

// Validate all candidates exist for year
$candIds = array_map('intval', array_keys($scores));
if (!$candIds) {
	header('Location: pageant_criteria.php');
	exit;
}

// Insert or update scores atomically
$mysqli->begin_transaction();
try {
$stmt = $mysqli->prepare('INSERT INTO tbl_scores (judge_id, candidate_id, criteria_id, score, status, year) VALUES (?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), status = 1');
	foreach ($scores as $cid => $val) {
		$cid = (int)$cid;
		$score = (float)$val;
	$stmt->bind_param('iiids', $judge_id, $cid, $criteria_id, $score, $year);
		$stmt->execute();
	}
	$stmt->close();

	$mysqli->commit();
} catch (Throwable $e) {
	$mysqli->rollback();
	die('Failed to save scores: ' . $e->getMessage());
}

// Redirect back to criteria page to continue next criteria or show done message
header('Location: pageant_criteria.php');
exit;



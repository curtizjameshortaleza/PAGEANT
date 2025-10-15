<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// Helpers
function get_post($key, $default = '') { return isset($_POST[$key]) ? trim($_POST[$key]) : $default; }

$message = $_GET['msg'] ?? '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = get_post('action');
	if ($action === 'add_year') {
		$year = get_post('year');
		if ($year !== '') {
			$stmt = $mysqli->prepare("INSERT IGNORE INTO tbl_admin (year) VALUES (?)");
			$stmt->bind_param('s', $year);
			$stmt->execute();
			$stmt->close();
			header('Location: admin.php?year=' . urlencode($year) . '&msg=Year saved.');
			exit;
		}
	}
	if ($action === 'toggle_lock') {
		$year = get_post('year_lock');
		if ($year !== '') {
			// Get current lock status
			$check = $mysqli->query("SELECT is_locked FROM tbl_admin WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			$current_status = $check->fetch_assoc();
			$is_locked = $current_status['is_locked'];
			
			// Toggle lock status
			$mysqli->query("UPDATE tbl_admin SET is_locked = 1 - is_locked WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			
			$new_status = $is_locked ? 'unlocked' : 'locked';
			header('Location: admin.php?year=' . urlencode($year) . '&msg=success:Year "' . $year . '" has been ' . $new_status . ' successfully.');
			exit;
		}
	}
	if ($action === 'add_criteria') {
		$year = get_post('criteria_year');
		$name = get_post('criteria_name');
		$percentage = (int)get_post('criteria_percentage');
		if ($year && $name && $percentage > 0 && $percentage <= 100) {
			// Check if criteria name already exists for this year
			$check_name = $mysqli->prepare("SELECT id FROM tbl_criteria WHERE name = ? AND year = ?");
			$check_name->bind_param('ss', $name, $year);
			$check_name->execute();
			$existing = $check_name->get_result()->fetch_assoc();
			$check_name->close();
			
		if ($existing) {
			header('Location: admin.php?year=' . urlencode($year) . '&msg=error:Criteria name "' . $name . '" already exists for this year.');
			exit;
		} else {
				// Check total percentage doesn't exceed 100%
				$check = $mysqli->prepare("SELECT SUM(percentage) as total FROM tbl_criteria WHERE year = ?");
				$check->bind_param('s', $year);
				$check->execute();
				$result = $check->get_result()->fetch_assoc();
				$check->close();
				$current_total = (float)($result['total'] ?? 0);
				
				if ($current_total + $percentage <= 100) {
					$stmt = $mysqli->prepare("INSERT INTO tbl_criteria (name, percentage, year) VALUES (?, ?, ?)");
					$stmt->bind_param('sds', $name, $percentage, $year);
					$stmt->execute();
					$stmt->close();
					if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
						header('Content-Type: application/json');
						echo json_encode(['ok' => true, 'message' => 'Criteria saved successfully.']);
						exit;
					}
					header('Location: admin.php?year=' . urlencode($year) . '&msg=success:Criteria saved successfully.');
					exit;
				} else {
					if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
						header('Content-Type: application/json');
						echo json_encode(['ok' => false, 'message' => 'Total percentage cannot exceed 100%. Current: ' . $current_total . '%, Adding: ' . $percentage . '%']);
						exit;
					}
					header('Location: admin.php?year=' . urlencode($year) . '&msg=error:Total percentage cannot exceed 100%. Current: ' . $current_total . '%, Adding: ' . $percentage . '%');
					exit;
				}
			}
		} else {
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => false, 'message' => 'Invalid criteria data. Percentage must be 1-100.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($year) . '&msg=error:Invalid criteria data. Percentage must be 1-100.');
			exit;
		}
	}
	if ($action === 'edit_criteria') {
		$id = (int)get_post('criteria_id');
		$name = get_post('criteria_name');
		$percentage = (int)get_post('criteria_percentage');
		$year = get_post('criteria_year');
		if ($id && $name && $percentage > 0 && $percentage <= 100) {
			// Check duplicate criteria name for this year excluding current id
			$dup = $mysqli->prepare("SELECT id FROM tbl_criteria WHERE name = ? AND year = ? AND id != ? LIMIT 1");
			$dup->bind_param('ssi', $name, $year, $id);
			$dup->execute();
			$dupRes = $dup->get_result()->fetch_assoc();
			$dup->close();
			if ($dupRes) {
				if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
					header('Content-Type: application/json');
					echo json_encode(['ok' => false, 'message' => 'Criteria name "' . $name . '" already exists for this year.']);
					exit;
				}
				header('Location: admin.php?year=' . urlencode($year) . '&msg=error:Criteria name "' . $name . '" already exists for this year.');
				exit;
			}
			// Check total percentage doesn't exceed 100% (excluding current criteria)
			$check = $mysqli->prepare("SELECT SUM(percentage) as total FROM tbl_criteria WHERE year = ? AND id != ?");
			$check->bind_param('si', $year, $id);
			$check->execute();
			$result = $check->get_result()->fetch_assoc();
			$check->close();
			$current_total = (float)($result['total'] ?? 0);
			
			if ($current_total + $percentage <= 100) {
				$stmt = $mysqli->prepare("UPDATE tbl_criteria SET name = ?, percentage = ? WHERE id = ?");
				$stmt->bind_param('sdi', $name, $percentage, $id);
				$stmt->execute();
				$stmt->close();
				if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
					header('Content-Type: application/json');
					echo json_encode(['ok' => true, 'message' => 'Criteria updated.']);
					exit;
				}
				header('Location: admin.php?year=' . urlencode($year) . '&msg=Criteria updated.');
				exit;
			} else {
				if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
					header('Content-Type: application/json');
					echo json_encode(['ok' => false, 'message' => 'Total percentage cannot exceed 100%. Current: ' . $current_total . '%, Adding: ' . $percentage . '%']);
					exit;
				}
				header('Location: admin.php?year=' . urlencode($year) . '&msg=Total percentage cannot exceed 100%. Current: ' . $current_total . '%, Adding: ' . $percentage . '%');
				exit;
			}
		}
	}
	if ($action === 'delete_criteria') {
		$id = (int)get_post('criteria_id');
		if ($id) {
			$mysqli->query("DELETE FROM tbl_criteria WHERE id = {$id}");
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => true, 'message' => 'Criteria deleted.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($sel_year) . '&msg=Criteria deleted.');
			exit;
		}
	}
	if ($action === 'add_judge') {
		$year = get_post('judge_year');
		$name = get_post('judge_name');
		$judge_number = get_post('judge_number');
		if ($year && $name && $judge_number) {
			// Check if judge number already exists for this year
			$check_number = $mysqli->prepare("SELECT id FROM tbl_judges WHERE judge_number = ? AND year = ?");
			$check_number->bind_param('ss', $judge_number, $year);
			$check_number->execute();
			$existing = $check_number->get_result()->fetch_assoc();
			$check_number->close();
			
		if ($existing) {
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => false, 'message' => 'Judge number "' . $judge_number . '" already exists for this year.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($year) . '&msg=error:Judge number "' . $judge_number . '" already exists for this year.');
			exit;
		} else {
			$access = bin2hex(random_bytes(8));
			$stmt = $mysqli->prepare("INSERT INTO tbl_judges (name, judge_number, access_code, year) VALUES (?, ?, ?, ?)");
			$stmt->bind_param('ssss', $name, $judge_number, $access, $year);
			$stmt->execute();
			$stmt->close();
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => true, 'message' => 'Judge saved successfully.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($year) . '&msg=success:Judge saved successfully.');
			exit;
		}
		} else {
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => false, 'message' => 'Please fill in all required fields.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($year) . '&msg=error:Please fill in all required fields.');
			exit;
		}
	}
	if ($action === 'regenerate_judge_code') {
		$judge_id = (int)get_post('judge_id');
		if ($judge_id) {
			$new_access = bin2hex(random_bytes(8));
			$stmt = $mysqli->prepare("UPDATE tbl_judges SET access_code = ? WHERE id = ?");
			$stmt->bind_param('si', $new_access, $judge_id);
			$stmt->execute();
			$stmt->close();
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => true, 'message' => 'Access code regenerated successfully.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($sel_year) . '&msg=Access code regenerated successfully.');
			exit;
		}
	}
	if ($action === 'edit_judge') {
		$judge_id = (int)get_post('judge_id');
		$judge_number = get_post('judge_number');
		$judge_name = get_post('judge_name');
		if ($judge_id && $judge_number && $judge_name) {
			// Check if judge number already exists for this year (excluding current judge)
			$check_number = $mysqli->prepare("SELECT id FROM tbl_judges WHERE judge_number = ? AND year = (SELECT year FROM tbl_judges WHERE id = ?) AND id != ?");
			$check_number->bind_param('sii', $judge_number, $judge_id, $judge_id);
			$check_number->execute();
			$existing = $check_number->get_result()->fetch_assoc();
			$check_number->close();
			
		if ($existing) {
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => false, 'message' => 'Judge number "' . $judge_number . '" already exists for this year.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($sel_year) . '&msg=error:Judge number "' . $judge_number . '" already exists for this year.');
			exit;
		} else {
			$stmt = $mysqli->prepare("UPDATE tbl_judges SET judge_number = ?, name = ? WHERE id = ?");
			$stmt->bind_param('ssi', $judge_number, $judge_name, $judge_id);
			$stmt->execute();
			$stmt->close();
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => true, 'message' => 'Judge updated successfully.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($sel_year) . '&msg=success:Judge updated successfully.');
			exit;
		}
		} else {
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => false, 'message' => 'Please fill in all required fields.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($sel_year) . '&msg=error:Please fill in all required fields.');
			exit;
		}
	}
	if ($action === 'delete_judge') {
		$judge_id = (int)get_post('judge_id');
		if ($judge_id) {
			$mysqli->query("DELETE FROM tbl_scores WHERE judge_id = {$judge_id}");
			$mysqli->query("DELETE FROM tbl_judges WHERE id = {$judge_id}");
			if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
				header('Content-Type: application/json');
				echo json_encode(['ok' => true, 'message' => 'Judge deleted successfully.']);
				exit;
			}
			header('Location: admin.php?year=' . urlencode($sel_year) . '&msg=success:Judge deleted successfully.');
			exit;
		}
	}
	if ($action === 'auto_generate_candidates') {
		$year = get_post('candidate_year');
		$male_count = (int)get_post('male_count');
		$female_count = (int)get_post('female_count');
		
		// Remove debug message
		
		if ($year && $male_count >= 0 && $female_count >= 0) {
			$mysqli->query("DELETE FROM tbl_candidates WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			
			for ($i = 1; $i <= $male_count; $i++) {
				$stmt = $mysqli->prepare("INSERT INTO tbl_candidates (number, gender, year) VALUES (?, 'Male', ?)");
				$stmt->bind_param('is', $i, $year);
				$stmt->execute();
			}
			
			for ($i = 1; $i <= $female_count; $i++) {
				$stmt = $mysqli->prepare("INSERT INTO tbl_candidates (number, gender, year) VALUES (?, 'Female', ?)");
				$stmt->bind_param('is', $i, $year);
				$stmt->execute();
			}
			
			$stmt->close();
			header('Location: admin.php?year=' . urlencode($year) . '&msg=Generated ' . $male_count . ' male and ' . $female_count . ' female candidates.');
			exit;
		}
	}
	if ($action === 'delete_year') {
		$year = get_post('delete_year');
		if ($year) {
			$mysqli->query("DELETE FROM tbl_scores WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			$mysqli->query("DELETE FROM tbl_judges WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			$mysqli->query("DELETE FROM tbl_candidates WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			$mysqli->query("DELETE FROM tbl_criteria WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			$mysqli->query("DELETE FROM tbl_admin WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			header('Location: admin.php?msg=success:Year "' . $year . '" and all related data deleted successfully.');
			exit;
		}
	}
	if ($action === 'clear_candidates') {
		$year = get_post('clear_year');
		if ($year) {
			$mysqli->query("DELETE FROM tbl_scores WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			$mysqli->query("DELETE FROM tbl_candidates WHERE year = '" . $mysqli->real_escape_string($year) . "'");
			header('Location: admin.php?year=' . urlencode($year) . '&msg=All candidates and scores cleared for "' . $year . '".');
			exit;
		}
	}
	if ($action === 'finalize_autosaved') {
		$year = get_post('finalize_year');
		if ($year) {
			$mysqli->query("UPDATE tbl_scores SET status = 1 WHERE year = '" . $mysqli->real_escape_string($year) . "' AND status = 0");
			header('Location: admin.php?year=' . urlencode($year) . '&msg=success:All autosaved scores have been submitted.');
			exit;
		}
	}
}

$years = get_years($mysqli);
$sel_year = isset($_GET['year']) ? $_GET['year'] : (count($years) ? $years[0]['year'] : '');

// Auto-select year if only one year exists and no year is currently selected
if (!$sel_year && count($years) === 1) {
	$sel_year = $years[0]['year'];
	// Redirect to auto-select the year
	header('Location: admin.php?year=' . urlencode($sel_year));
	exit;
}

// Fetch data for selected year
$criteria = [];
if ($sel_year) {
	$res = $mysqli->query("SELECT * FROM tbl_criteria WHERE year='".$mysqli->real_escape_string($sel_year)."' ORDER BY id ASC");
	while ($row = $res->fetch_assoc()) $criteria[] = $row;
	$res->free();
}
$judges = [];
// Fetch judges for the selected year only
if ($sel_year) {
	$res = $mysqli->query("SELECT * FROM tbl_judges WHERE year='".$mysqli->real_escape_string($sel_year)."' ORDER BY id ASC");
	while ($row = $res->fetch_assoc()) $judges[] = $row;
	$res->free();
}
$candidates = [];
if ($sel_year) {
	$res = $mysqli->query("SELECT * FROM tbl_candidates WHERE year='".$mysqli->real_escape_string($sel_year)."' ORDER BY number ASC");
	while ($row = $res->fetch_assoc()) $candidates[] = $row;
	$res->free();
}

// Results computations
function fetch_totals($mysqli, $year) {
	$sql = "SELECT c.id as candidate_id, c.number, c.gender, cr.id as criteria_id, cr.name as criteria_name,
		SUM(s.score) as total_score
		FROM tbl_candidates c
		JOIN tbl_scores s ON s.candidate_id = c.id AND s.year = c.year AND s.status = 1
		JOIN tbl_criteria cr ON cr.id = s.criteria_id AND cr.year = c.year
		WHERE c.year = ?
		GROUP BY c.id, cr.id
		ORDER BY c.number, cr.id";
	$stmt = $mysqli->prepare($sql);
	$stmt->bind_param('s', $year);
	$stmt->execute();
	$res = $stmt->get_result();
	$data = [];
	while ($row = $res->fetch_assoc()) {
		$data[] = $row;
	}
	$stmt->close();
	return $data;
}

function fetch_overall($mysqli, $year) {
	$sql = "SELECT c.id as candidate_id, c.number, c.gender, SUM(s.score) as overall_total
		FROM tbl_candidates c
		LEFT JOIN tbl_scores s ON s.candidate_id = c.id AND s.year = c.year AND s.status = 1
		WHERE c.year = ?
		GROUP BY c.id
		ORDER BY overall_total DESC";
	$stmt = $mysqli->prepare($sql);
	$stmt->bind_param('s', $year);
	$stmt->execute();
	$res = $stmt->get_result();
	$data = [];
	while ($row = $res->fetch_assoc()) { $data[] = $row; }
	$stmt->close();
	return $data;
}

$totals = $sel_year ? fetch_totals($mysqli, $sel_year) : [];
$overall = $sel_year ? fetch_overall($mysqli, $sel_year) : [];

// Group for display
$totals_by_candidate = [];
foreach ($totals as $t) {
	$cid = $t['candidate_id'];
	if (!isset($totals_by_candidate[$cid])) {
		$totals_by_candidate[$cid] = [
			'number' => $t['number'],
			'gender' => $t['gender'],
			'criteria' => []
		];
	}
	$totals_by_candidate[$cid]['criteria'][$t['criteria_name']] = (float)$t['total_score'];
}

function top3_by_gender($overall, $gender) {
	$filtered = array_values(array_filter($overall, function($r) use ($gender) { return $r['gender'] === $gender; }));
	usort($filtered, function($a,$b){ return ($b['overall_total'] ?? 0) <=> ($a['overall_total'] ?? 0); });
	return array_slice($filtered, 0, 3);
}

?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Admin - Pageant Criteria System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		html, body { height: 100%; overflow-x: hidden; }
		body { overflow-y: auto; }
		.bg-success { background-color: #28a745 !important; }
		.bg-warning { background-color: #ffc107 !important; }
		.bg-danger { background-color: #dc3545 !important; }
		.table td { padding: 12px 8px; }

		/* Professional Admin UI refinements */
		:root { --primary:#0d6efd; --primary-600:#0b5ed7; --surface:#ffffff; --muted:#6c757d; --border:#dee2e6; --header:#101828; }
		/* Page layout */
		body.bg-light { background: linear-gradient(180deg, #f6f8fb 0%, #eef2f7 100%); }
		.container, .container-fluid { padding-left: 20px; padding-right: 20px; }
		.navbar { box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: fixed; top: 0; left: 0; right: 0; z-index: 1040; }
		/* Tabs → modern pill look */
		.nav-tabs { border-bottom: 0; padding: 6px; background: #fff; border-radius: 0; box-shadow: 0 4px 12px rgba(16,24,40,0.04); position: fixed; top: 55px; left: 0; right: 0; z-index: 1030; margin-bottom: 0; }
		.nav-tabs .nav-link { font-weight: 600; color: #475467; border: 1px solid transparent; margin-right: 6px; border-radius: 999px; padding: 8px 14px; transition: background .2s ease, color .2s ease, border-color .2s ease, box-shadow .2s ease; }
		.nav-tabs .nav-link:hover { background: #eef2ff; color: #1d4ed8; }
		.nav-tabs .nav-link.active { color: #0d6efd; background: #e7f1ff; border-color: rgba(13,110,253,.25); box-shadow: inset 0 0 0 1px rgba(13,110,253,.15), 0 4px 10px rgba(13,110,253,.08); }
		.tab-content { background: #ffffff; border: 1px solid var(--border); border-top: none; border-radius: 0 0 12px 12px; padding: 20px; box-shadow: 0 6px 16px rgba(0,0,0,0.05); overflow: visible; }
		/* Add padding to body to account for fixed navbar and tabs */
		body { padding-top: 120px; }
		
		/* Results toggle button */
		.results-toggle {
			position: fixed;
			top: 100px;
			right: 20px;
			z-index: 1025;
			background: #0d6efd;
			color: white;
			border: none;
			border-radius: 8px;
			padding: 8px 12px;
			font-size: 12px;
			font-weight: 600;
			box-shadow: 0 2px 8px rgba(13,110,253,0.3);
			transition: all 0.2s ease;
		}
		.results-toggle:hover {
			background: #0b5ed7;
			transform: translateY(-1px);
			box-shadow: 0 4px 12px rgba(13,110,253,0.4);
		}
		
		/* Real-time status indicator */
		.realtime-status {
			position: fixed;
			top: 140px;
			right: 20px;
			z-index: 1025;
			background: #28a745;
			color: white;
			border: none;
			border-radius: 8px;
			padding: 8px 12px;
			font-size: 12px;
			font-weight: 600;
			box-shadow: 0 2px 8px rgba(40,167,69,0.3);
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.realtime-status i {
			animation: spin 1s linear infinite;
		}
		@keyframes spin {
			from { transform: rotate(0deg); }
			to { transform: rotate(360deg); }
		}
		/* Top performer highlighting */
		.top-performer {
			background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
			border: 2px solid #ffc107 !important;
			box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3) !important;
		}
		.top-performer td {
			font-weight: 700 !important;
			color: #856404 !important;
		}
		
		/* Hide highlights when toggled */
		.highlights-hidden .top-performer {
			background: transparent !important;
			border: 1px solid #dee2e6 !important;
			box-shadow: none !important;
		}
		.highlights-hidden .top-performer td {
			font-weight: normal !important;
			color: inherit !important;
		}
		.highlights-hidden .color-square {
			display: none !important;
		}
		.card { border-radius: 14px; border: 1px solid rgba(16,24,40,0.06); box-shadow: 0 1px 2px rgba(16,24,40,0.04); }
		.card:hover { box-shadow: 0 6px 20px rgba(16,24,40,0.06); }
		.card-header { background: #fff; border-bottom: 1px solid #eef2f7; border-top-left-radius: 14px !important; border-top-right-radius: 14px !important; }
		.card-header h5 { font-weight: 700; color: #0f172a; letter-spacing: .2px; }
		.form-label { font-weight: 600; color: #495057; }
		.form-control, .form-select { border-radius: 10px; border-color: var(--border); transition: border-color .2s ease, box-shadow .2s ease; }
		.form-control:hover, .form-select:hover { border-color: #b6cdfc; }
		.btn { border-radius: 10px; transition: transform .12s ease, box-shadow .12s ease; }
		.btn-primary { background: var(--primary); border-color: var(--primary); }
		.btn-primary:hover { background: var(--primary-600); border-color: var(--primary-600); }
		.btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(16,24,40,0.08); }
		hr { opacity: 0.1; }

		/* Results tables tightening */
		.legend {
			background: #f8f9fa;
			border: 1px solid #dee2e6; 
			border-radius: 5px;
			padding: 10px;
			margin-bottom: 20px;
		}
		
		/* Small colored squares like [🟩] 12.00 */
		.color-square {
			display: inline-block;
			width: 16px;
			height: 16px;
			border-radius: 2px;
			margin-right: 6px;
			vertical-align: middle;
			border: 1px solid rgba(0,0,0,0.1);
		}
		.color-square.green { background-color: #28a745; }
		.color-square.yellow { background-color: #ffc107; }
		.color-square.red { background-color: #dc3545; }
		
		.score-with-indicator {
			display: flex;
			align-items: center;
			justify-content: center;
		}
		
		/* Professional UI Enhancements */
		.avatar-sm {
			width: 40px;
			height: 40px;
			font-size: 16px;
		}
		
		.card-header {
			background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
			border-bottom: 2px solid #dee2e6;
		}
		
		.table th {
			background: #f8fafc;
			border-bottom: 1px solid #e5e7eb;
			font-weight: 600;
			color: #495057;
		}
		
		.btn-group .btn {
			border-radius: 0.375rem;
			margin: 0 2px;
		}
		
		.btn-group .btn:first-child {
			border-top-left-radius: 0.375rem;
			border-bottom-left-radius: 0.375rem;
		}
		
		.btn-group .btn:last-child {
			border-top-right-radius: 0.375rem;
			border-bottom-right-radius: 0.375rem;
		}
		
		.form-control:focus, .form-select:focus {
			border-color: #0d6efd;
			box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
		}
		
		.badge {
			font-size: 0.75em;
			padding: 0.5em 0.75em;
		}
		
		code {
			font-size: 0.875em;
			font-weight: 600;
		}
		
		/* Hover effects */
		.table tbody tr:hover {
			background-color: rgba(13, 110, 253, 0.05);
			transform: translateY(-1px);
			transition: all 0.2s ease;
		}
		
		.btn:hover {
			transform: translateY(-1px);
			box-shadow: 0 4px 8px rgba(0,0,0,0.15);
		}
		
		/* Professional spacing */
		.card {
			border: none;
			box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
			transition: box-shadow 0.15s ease-in-out;
		}
		
		.card:hover {
			box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
		}
		
		/* Compact Results Tables with Fixed Scrolling */
		.results-table {
			font-size: 0.9rem;
		}
		
		.results-table th {
			background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
			border-bottom: 2px solid #dee2e6;
			font-weight: 600;
			font-size: 0.9rem;
			padding: 12px 8px;
			text-align: center;
			vertical-align: middle;
			position: sticky;
			top: 0;
			z-index: 10;
		}
		
		/* Fixed first column (#) and last column (Overall) */
		.results-table th:first-child,
		.results-table td:first-child {
			position: sticky;
			left: 0;
			background: #f8f9fa;
			z-index: 5;
			border-right: 2px solid #dee2e6;
		}
		
		.results-table th:last-child,
		.results-table td:last-child {
			position: sticky;
			right: 0;
			background: #f8f9fa;
			z-index: 5;
			border-left: 2px solid #dee2e6;
		}
		
		/* Header row sticky positioning */
		.results-table thead th:first-child {
			z-index: 15;
		}
		
		.results-table thead th:last-child {
			z-index: 15;
		}

		/* Icon button hover cues */
		.btn-outline-primary:hover i, .btn-outline-danger:hover i { transform: translateY(-1px); }
		.btn-outline-primary i, .btn-outline-danger i { transition: transform .15s ease; }
		
		.results-table td {
			padding: 10px 8px;
			text-align: center;
			vertical-align: middle;
			font-size: 0.9rem;
			font-weight: 500;
			transition: none; /* Remove all transitions to prevent glitches */
		}
		
		.results-table tbody tr {
			border-bottom: 1px solid #f8f9fa;
		}
		
		.results-table tbody tr:hover {
			background: rgba(13, 110, 253, 0.05);
		}
		
		/* Remove all transitions and transforms to prevent glitches */
		.score-cell, .overall-score {
			transition: none;
		}
		
		/* Smooth opacity transition for updates */
		#resultsTables {
			transition: opacity 0.2s ease;
		}
		
	
		
		.results-table .candidate-number {
			font-size: 1rem;
			font-weight: 600;
			color: #495057;
		}
		
		.results-table .overall-score {
			font-size: 1rem;
			font-weight: 700;
			background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
			border: 1px solid #dee2e6;
			border-radius: 4px;
			padding: 6px;
		}
		
		.results-table .score-cell {
			font-size: 0.9rem;
			font-weight: 500;
			min-width: 60px;
		}
		
		/* Compact color squares */
		.color-square {
			width: 14px;
			height: 14px;
			border-radius: 2px;
			margin-right: 4px;
			vertical-align: middle;
			border: 1px solid rgba(255, 255, 255, 0.8);
			box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
		}
		
		/* Compact Results card */
		.results-card {
			margin-bottom: 1.25rem;
			border-radius: 8px;
			overflow: hidden;
		}

		.results-card .table-responsive {
			overflow-x: auto !important;
			overflow-y: auto !important;
			max-height: 70vh;
			border: 1px solid #dee2e6;
			border-radius: 8px;
		}
		
		.results-card .card-header {
			padding: 15px 20px;
			font-size: 1rem;
			font-weight: 600;
		}
		
		.results-card .card-body {
			padding: 0;
		}

		/* Responsive adjustments */
		@media (max-width: 768px) {
			.tab-content { padding: 12px; border-radius: 0 0 10px 10px; }
			.nav-tabs .nav-link { padding: 8px 12px; font-size: 14px; }
			.card-header h5 { font-size: 1rem; }
			.container, .container-fluid { padding-left: 20px; padding-right: 20px; }
		}
	</style>
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
	<div class="container-fluid">
		<a class="navbar-brand" href="index.php">Pageant Admin</a>
	</div>
</nav>

<div class="container-fluid py-4">
	<?php if ($message): ?>
		<?php 
		$message_type = 'info';
		$message_text = $message;
		if (strpos($message, 'success:') === 0) {
			$message_type = 'success';
			$message_text = substr($message, 8);
		} elseif (strpos($message, 'error:') === 0) {
			$message_type = 'error';
			$message_text = substr($message, 6);
		}
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			Swal.fire({
				toast: true,
				position: 'top-end',
				icon: '<?= $message_type === 'error' ? 'error' : ($message_type === 'success' ? 'success' : 'info') ?>',
				title: '<?= h($message_text) ?>',
				showConfirmButton: false,
				timer: 3000,
				timerProgressBar: true
			});
			
			// Clear the message from URL after showing
			if (window.history.replaceState) {
				const url = new URL(window.location);
				url.searchParams.delete('msg');
				window.history.replaceState({}, document.title, url.pathname + url.search);
			}
		});
		</script>
	<?php endif; ?>
	
	<!-- Admin Tabs: Setting | Results -->
	<ul class="nav nav-tabs mb-3" id="adminTabs" role="tablist">
		<li class="nav-item" role="presentation">
			<button class="nav-link active" id="tab-setting" data-bs-toggle="tab" data-bs-target="#pane-setting" type="button" role="tab">Setting</button>
		</li>
		<li class="nav-item" role="presentation">
			<button class="nav-link" id="tab-results" data-bs-toggle="tab" data-bs-target="#pane-results" type="button" role="tab">Results</button>
		</li>
	</ul>
	<div class="tab-content">
		<div class="tab-pane fade show active" id="pane-setting" role="tabpanel" aria-labelledby="tab-setting">

	<!-- Event Year + Candidates side-by-side -->
	<div class="row g-3">
		<div class="col-12 col-lg-6">
			<div class="card shadow-sm mb-3 h-100">
				<div class="card-header">Event Year</div>
				<div class="card-body">
					<form method="post" class="row g-2">
						<input type="hidden" name="action" value="add_year">
						<div class="col-8">
							<input type="text" class="form-control" name="year" placeholder="e.g. 2025-2026" required>
						</div>
						<div class="col-4">
							<button class="btn btn-primary w-100" type="submit">Save</button>
						</div>
					</form>
					<hr>
					<form method="post" class="row g-2">
						<input type="hidden" name="action" value="toggle_lock">
						<div class="col-8">
							<select name="year_lock" class="form-select" required id="lockYearSelect" onchange="selectYearForManagement(this.value)">
								<option value="">Select year to manage</option>
								<?php foreach ($years as $y): ?>
									<option value="<?= h($y['year']) ?>" <?= $y['year'] === $sel_year ? 'selected' : '' ?>>
										<?= h($y['year']) ?> 
										<?= $y['is_locked'] ? '(Locked)' : '' ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-2">
							<button class="btn btn-warning w-100" type="submit" id="lockBtn" disabled>Lock/Unlock</button>
						</div>
						<div class="col-2">
							<button class="btn btn-danger w-100" type="button" id="deleteBtn" disabled onclick="deleteYear()">Delete</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<div class="col-12 col-lg-6">
			<?php if ($sel_year): ?>
			<!-- Candidates Total Male and Female Add (moved next to Event Year) -->
			<div class="card shadow-sm mb-3 h-100">
				<div class="card-header">
					<h5 class="mb-0"><i class="fas fa-users text-primary"></i> Candidates Management</h5>
					<small class="text-muted">Manage candidates for <?= h($sel_year) ?></small>
				</div>
				<div class="card-body">
                    <!-- <form method="post" class="mb-3 d-flex gap-2 align-items-end">
                        <input type="hidden" name="action" value="finalize_autosaved">
                        <input type="hidden" name="finalize_year" value="<?= h($sel_year) ?>">
                        <button class="btn btn-success" type="submit"><i class="fas fa-check me-1"></i>Finalize Autosaved to Submitted</button>
                    </form> -->
					<?php 
					$current_male_count = count(array_filter($candidates, function($c) { return $c['gender'] === 'Male'; }));
					$current_female_count = count(array_filter($candidates, function($c) { return $c['gender'] === 'Female'; }));
					?>
					<?php if (!empty($candidates)): ?>
						<?php $total_candidates = $current_male_count + $current_female_count; ?>
						<div class="alert alert-info mb-3">
							<strong>Current:</strong> <?= $current_male_count ?> Male, <?= $current_female_count ?> Female candidates
							<br><strong>Total:</strong> <?= $total_candidates ?> candidates
						</div>
					<?php endif; ?>
					<form method="post" class="vstack gap-3" onsubmit="return validateForm()">
						<input type="hidden" name="action" value="auto_generate_candidates">
						<input type="hidden" name="candidate_year" value="<?= h($sel_year) ?>">
						
						<div class="row g-2">
							<div class="col-4">
								<label class="form-label">Total Male</label>
								<input type="number" min="0" name="male_count" id="male_count" class="form-control" placeholder="Total Male" value="<?= $current_male_count ?>" required oninput="updateTotal()">
							</div>
							<div class="col-4">
								<label class="form-label">Total Female</label>
								<input type="number" min="0" name="female_count" id="female_count" class="form-control" placeholder="Total Female" value="<?= $current_female_count ?>" required oninput="updateTotal()">
							</div>
							<div class="col-4">
								<label class="form-label">Total Candidates</label>
								<input type="number" class="form-control" id="totalCandidates" readonly style="background-color: #f8f9fa;">
							</div>
						</div>
						
						<div class="row g-2">
							<div class="col-6">
								<button class="btn btn-primary w-100"><?= !empty($candidates) ? 'Update' : 'Generate' ?> for <?= h($sel_year) ?></button>
							</div>
							<?php if (!empty($candidates)): ?>
								<div class="col-6">
									<button class="btn btn-outline-danger w-100" type="button" onclick="clearCandidates()">Clear All</button>
								</div>
							<?php endif; ?>
						</div>
					</form>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if (!$sel_year): ?>
	<!-- No Year Selected Message -->
	<div class="card shadow-sm mb-3">
		<div class="card-body text-center py-5">
			<i class="fas fa-calendar-alt fa-4x text-muted mb-4"></i>
			<h4 class="text-muted mb-3">Select a Year to Continue</h4>
			<p class="text-muted mb-4">
				<?php if (count($years) === 0): ?>
					No years are available. Please add a new year first.
				<?php else: ?>
					Please select a year from the dropdown above to manage criteria, judges, and candidates for that specific pageant year.
				<?php endif; ?>
			</p>
			<?php if (count($years) > 0): ?>
			<div class="row justify-content-center">
				<div class="col-md-8">
					<div class="alert alert-info">
						<h6><i class="fas fa-info-circle me-2"></i>What you can do after selecting a year:</h6>
						<ul class="list-unstyled mb-0 text-start">
							<li><i class="fas fa-check text-success me-2"></i>Manage scoring criteria</li>
							<li><i class="fas fa-check text-success me-2"></i>Add and manage judges</li>
							<li><i class="fas fa-check text-success me-2"></i>Generate candidates</li>
							<li><i class="fas fa-check text-success me-2"></i>View results and rankings</li>
						</ul>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($sel_year): ?>
	<!-- Criteria Add -->
	<div class="card shadow-sm mb-3" id="criteriaCard">
		<div class="card-header">
			<h5 class="mb-0"><i class="fas fa-list-check text-primary"></i> Criteria Management</h5>
			<small class="text-muted">Manage scoring criteria for <?= h($sel_year) ?></small>
		</div>
		<div class="card-body">
			<form method="post" class="row g-2" onsubmit="return submitCriteriaAjax(event)">
				<input type="hidden" name="action" value="add_criteria">
				<input type="hidden" name="criteria_year" value="<?= h($sel_year) ?>">
				<div class="col-5"><input type="text" name="criteria_name" class="form-control" placeholder="Name" required></div>
				<div class="col-3"><input type="number" step="1" min="1" max="100" name="criteria_percentage" class="form-control" placeholder="%" required></div>
				<div class="col-4"><button class="btn btn-primary w-100">Add to <?= h($sel_year) ?></button></div>
			</form>
			<hr>
			<div class="d-flex justify-content-between align-items-center mb-2">
				<div class="small text-muted">For year: <?= h($sel_year) ?></div>
				<?php 
				$total_percentage = 0;
				foreach ($criteria as $cr) { $total_percentage += (float)$cr['percentage']; }
				?>
				<div class="small <?= $total_percentage == 100 ? 'text-success' : ($total_percentage > 100 ? 'text-danger' : 'text-warning') ?>">
					<strong>Total: <?= rtrim(rtrim(number_format($total_percentage, 1), '0'), '.') ?>%</strong>
					<?php if ($total_percentage < 100): ?>
						<span class="text-muted">(<?= rtrim(rtrim(number_format(100 - $total_percentage, 1), '0'), '.') ?>% remaining)</span>
					<?php elseif ($total_percentage > 100): ?>
						<span class="text-danger">(<?= rtrim(rtrim(number_format($total_percentage - 100, 1), '0'), '.') ?>% over limit)</span>
					<?php endif; ?>
				</div>
			</div>
			<?php if (!empty($criteria)): ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead class="table-light">
							<tr>
								<th><i class="fas fa-list-check me-2"></i>Criteria Name</th>
								<th><i class="fas fa-percentage me-2"></i>Percentage</th>
								<th><i class="fas fa-cog me-2"></i>Actions</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($criteria as $cr): ?>
								<tr>
									<td>
										<div class="d-flex align-items-center">
											<div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
												<i class="fas fa-list-check"></i>
											</div>
											<div>
												<div class="fw-semibold"><?= h($cr['name']) ?></div>
											</div>
										</div>
									</td>
									<td>
										<span class="badge bg-info"><?= rtrim(rtrim($cr['percentage'], '0'), '.') ?>%</span>
									</td>
									<td>
										<div class="btn-group" role="group">
											<button class="btn btn-sm btn-outline-primary" onclick="editCriteria(<?= $cr['id'] ?>, '<?= h($cr['name']) ?>', <?= $cr['percentage'] ?>)" title="Edit Criteria">
												<i class="fas fa-edit"></i>
											</button>
							<button class="btn btn-sm btn-outline-danger" onclick="deleteCriteriaAjax(<?= $cr['id'] ?>, '<?= h($cr['name']) ?>')" title="Delete Criteria">
												<i class="fas fa-trash"></i>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="text-center py-5">
					<i class="fas fa-list-check fa-3x text-muted mb-3"></i>
					<h5 class="text-muted">No Criteria Found</h5>
					<p class="text-muted">Add your first criteria to get started with the pageant scoring system.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($sel_year): ?>
	<!-- Judges Management -->
	<div class="card shadow-sm mb-3" id="judgesCard">
		<div class="card-header">
			<h5 class="mb-0"><i class="fas fa-gavel text-primary"></i> Judges Management</h5>
			<small class="text-muted">Manage judges for <?= h($sel_year) ?></small>
		</div>
		<div class="card-body">
			<!-- Add New Judge Form -->
			<form method="post" class="row g-3 mb-4" onsubmit="return submitJudgeAjax(event)">
				<input type="hidden" name="action" value="add_judge">
				<input type="hidden" name="judge_year" value="<?= h($sel_year) ?>">
				<div class="col-md-3">
					<label class="form-label fw-semibold">Judge Number</label>
					<input type="text" name="judge_number" class="form-control" placeholder="e.g. 1, 2, J001" required>
				</div>
				<div class="col-md-5">
					<label class="form-label fw-semibold">Judge Name</label>
					<input type="text" name="judge_name" class="form-control" placeholder="Enter judge full name" required>
				</div>
				<div class="col-md-4 d-flex align-items-end">
					<button class="btn btn-primary w-100" type="submit">
						<i class="fas fa-plus me-2"></i>Add Judge
					</button>
				</div>
			</form>

			<!-- Judges List -->
			<?php if (!empty($judges)): ?>
				<div class="table-responsive">
					<table class="table table-hover">
						<thead class="table-light">
							<tr>
								<th><i class="fas fa-hashtag me-2"></i>Judge Number</th>
								<th><i class="fas fa-user me-2"></i>Judge Name</th>
								<th><i class="fas fa-cog me-2"></i>Actions</th>
							</tr>
						</thead>
						<tbody id="judgesTableBody">
							<?php foreach ($judges as $judge): ?>
								<tr>
									<td>
										<div class="d-flex align-items-center">
											<div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
												<i class="fas fa-hashtag"></i>
											</div>
											<div>
												<div class="fw-semibold"><?= h($judge['judge_number']) ?></div>
											</div>
										</div>
									</td>
									<td>
										<div class="d-flex align-items-center">
											<div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
												<i class="fas fa-user"></i>
											</div>
											<div>
												<div class="fw-semibold"><?= h($judge['name']) ?></div>
											</div>
										</div>
									</td>
									<td>
										<div class="btn-group" role="group">
											<button class="btn btn-sm btn-outline-primary" onclick="editJudge(<?= $judge['id'] ?>, '<?= h($judge['name']) ?>', '<?= h($judge['judge_number']) ?>', '<?= h($judge['year']) ?>')" title="Edit Judge">
												<i class="fas fa-edit"></i>
											</button>
						<button class="btn btn-sm btn-outline-danger" onclick="deleteJudgeAjax(<?= $judge['id'] ?>, '<?= h($judge['name']) ?>')" title="Delete Judge">
												<i class="fas fa-trash"></i>
											</button>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="text-center py-5">
					<i class="fas fa-gavel fa-3x text-muted mb-3"></i>
					<h5 class="text-muted">No Judges Found</h5>
					<p class="text-muted">Add your first judge to get started with the pageant scoring system.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>



		</div>

		<div class="tab-pane fade" id="pane-results" role="tabpanel" aria-labelledby="tab-results">
		
		<!-- Highlights Toggle Button -->
		<button class="results-toggle" id="resultsToggle" onclick="toggleHighlights()">
			<i class="fas fa-eye" id="toggleIcon"></i> <span id="toggleText">Hide Highlights</span>
		</button>

		<div id="resultsTables" class="results-tables"></div> <!-- /.results-tables -->
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
	// Ensure dynamic forms use AJAX after any section refresh
	document.body.addEventListener('submit', function(e){
		const form = e.target;
		if (form.matches('#criteriaCard form[method="post"]')) {
			e.preventDefault();
			submitCriteriaAjax(e);
		}
		if (form.matches('#judgesCard form[method="post"]')) {
			e.preventDefault();
			submitJudgeAjax(e);
		}
	});
		// Initialize real-time polling for Results tab
	initRealTimeUpdates();
});

// Real-time updates functionality
let pollingInterval = null;
let isPolling = false;
let lastUpdateTimestamp = null;
let updateInProgress = false;
let pendingUpdate = false;
let updateDebounceTimer = null;

function initRealTimeUpdates() {
	// Start polling when Results tab is shown
	document.getElementById('tab-results').addEventListener('click', function() {
		console.log('Results tab clicked - starting updates');
		// Load initial results and start polling immediately
		updateResults();
		startPolling(); // Always start polling on Results tab
		checkForUpdates(); // Check status and update
	});
	
	// Stop polling when other tabs are shown
	document.getElementById('tab-setting').addEventListener('click', function() {
		console.log('Setting tab clicked - stopping polling');
		stopPolling();
	});
	
	// Handle page visibility changes
	document.addEventListener('visibilitychange', function() {
		if (document.hidden) {
			// Page is hidden, stop polling to prevent unnecessary requests
			console.log('Page hidden - stopping polling');
			stopPolling();
		} else {
			// Page is visible, check if polling should resume
			console.log('Page visible - checking for updates');
			checkForUpdates(); // This will decide whether to start polling
		}
	});
	
	// Load initial results and start polling immediately
	console.log('Initializing real-time updates');
	updateResults();
	startPolling(); // Always start polling
	checkForUpdates(); // Check status and update
}

    function startPolling() {
	if (isPolling) {
		console.log('Already polling, skipping start');
		return;
	}
	
	console.log('Starting polling interval');
	isPolling = true;
	
	// Check status every 1 second
	pollingInterval = setInterval(() => {
		if (!isPolling) {
			return;
		}
		console.log('Polling interval tick');
		checkForUpdates();
	}, 1000);
}

function toast(message, type) {
    const isHtml = typeof message === 'string' && message.indexOf('<') !== -1;
    const opts = { toast: true, position: 'top-end', timer: 2200, showConfirmButton: false, icon: type || 'success' };
    if (isHtml) { opts.html = message; } else { opts.title = message; }
    Swal.fire(opts);
}

function confirmTopEnd(options) {
    const { title = 'Are you sure?', text = '', icon = 'warning', confirmButtonText = 'Yes', cancelButtonText = 'Cancel' } = options || {};
    return Swal.fire({ position: 'top-end', toast: false, title, text, icon, showCancelButton: true, confirmButtonText, cancelButtonText });
}

function parseJsonSafe(response) {
    return response.text().then(text => {
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text && text.trim().length ? text : 'Invalid server response'); }
    });
}

function refreshCriteriaSection(year) {
    fetch('admin.php?year=' + encodeURIComponent(year) + '&t=' + Date.now(), { cache:'no-cache' })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const fresh = doc.getElementById('criteriaCard');
            const card = document.getElementById('criteriaCard');
            if (fresh && card) card.outerHTML = fresh.outerHTML;
            // rebind nothing needed due to delegated submit handler
        });
}

function refreshJudgesSection(year) {
    fetch('admin.php?year=' + encodeURIComponent(year) + '&t=' + Date.now(), { cache:'no-cache' })
        .then(r => r.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const fresh = doc.getElementById('judgesCard');
            const card = document.getElementById('judgesCard');
            if (fresh && card) card.outerHTML = fresh.outerHTML;
            // delegated submit handler covers new content
        });
}

function criteriaNameExists(name) {
    const rows = document.querySelectorAll('#criteriaCard tbody tr');
    const target = (name || '').trim().toLowerCase();
    for (const row of rows) {
        const nameEl = row.querySelector('.fw-semibold');
        if (!nameEl) continue;
        const cell = nameEl.textContent.trim().toLowerCase();
        if (cell === target) return true;
    }
    return false;
}

function getCriteriaTotalPercent() {
    let total = 0;
    document.querySelectorAll('#criteriaCard tbody span.badge').forEach(b => {
        const t = (b.textContent || '').replace('%','').trim();
        const n = parseFloat(t);
        if (!isNaN(n)) total += n;
    });
    return total;
}

function judgeNumberExistsLocal(judgeNumber) {
    const rows = document.querySelectorAll('#judgesCard tbody tr');
    const target = (judgeNumber || '').trim().toLowerCase();
    for (const row of rows) {
        const el = row.querySelector('td .fw-semibold');
        if (!el) continue;
        const val = el.textContent.trim().toLowerCase();
        if (val === target) return true;
    }
    return false;
}

function submitCriteriaAjax(e) {
	if (e && e.preventDefault) e.preventDefault();
	const form = (e && e.target) ? e.target : (e && e.currentTarget) ? e.currentTarget : document.querySelector('#criteriaCard form');
	if (!form) return false;

	function toast(msg, type = 'success') {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: type,
			title: msg,
			showConfirmButton: false,
			timer: 2200,
			timerProgressBar: true
		});
	}

	if (!validateCriteriaForm()) return false;

	const year = form.querySelector('input[name="criteria_year"]').value;
	const name = form.querySelector('input[name="criteria_name"]').value.trim();
	const pct = parseInt(form.querySelector('input[name="criteria_percentage"]').value, 10) || 0;

	if (criteriaNameExists(name)) {
		toast('Criteria name already exists.', 'error');
		return false;
	}

	const currentTotal = getCriteriaTotalPercent();
	const remaining = Math.max(0, 100 - currentTotal);
	if (pct > remaining) {
		toast(`Adding ${pct}% would exceed 100% (current total: ${currentTotal}%).`, 'error');
		return false;
	}

	const payload = new URLSearchParams(new FormData(form));
	payload.append('ajax', '1');

	fetch('admin.php?year=' + encodeURIComponent(year), {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: payload.toString()
	})
	.then(parseJsonSafe)
	.then(data => {
		if (!data || !data.ok) {
			toast(data?.message || 'Failed to save criteria.', 'error');
			throw new Error(data?.message || 'Failed to save criteria.');
		}

		const newTotal = currentTotal + pct;
		if (newTotal >= 100) {
			toast('Criteria saved. Total reached 100%.', 'success');
		} else {
			toast(`Saved. ${100 - newTotal}% remaining.`, 'success');
		}

		refreshCriteriaSection(year);
		form.reset();
	})
	.catch(err => {
		toast(err.message || 'Error saving criteria.', 'error');
		console.error('submitCriteriaAjax error:', err);
	});

	// return false;
}



function deleteCriteriaAjax(id, name) {
    confirmTopEnd({ title: 'Delete criteria?', text: '"' + name + '" will be removed.', icon: 'warning', confirmButtonText: 'Delete' }).then(result => {
        if (!result.isConfirmed) return;
        const year = getCurrentYear();
        const payload = new URLSearchParams();
        payload.append('action','delete_criteria');
        payload.append('criteria_id', id);
        payload.append('ajax','1');
        fetch('admin.php?year=' + encodeURIComponent(year), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
            .then(parseJsonSafe).then(data => {
                if (!data.ok) throw new Error(data.message || 'Failed');
                toast(data.message || 'Deleted');
                refreshCriteriaSection(year);
            }).catch(err => toast(err.message || 'Error', 'error'));
    });
}

function submitJudgeAjax(e) {
    if (!validateJudgeForm()) { e.preventDefault(); return false; }
    e.preventDefault();
    const form = e.target;
    const year = form.querySelector('input[name="judge_year"]').value;
    const judgeNumber = form.querySelector('input[name="judge_number"]').value.trim();
    if (judgeNumberExistsLocal(judgeNumber)) { toast('Judge number already exists', 'error'); return false; }
    const payload = new URLSearchParams(new FormData(form));
    payload.append('ajax', '1');
    fetch('admin.php?year=' + encodeURIComponent(year), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString()
    }).then(parseJsonSafe).then(data => {
        if (!data.ok) throw new Error(data.message || 'Failed');
        toast(data.message || 'Saved');
        refreshJudgesSection(year);
        form.reset();
    }).catch(err => toast(err.message || 'Error', 'error'));
    return false;
}

function deleteJudgeAjax(id, name) {
    confirmTopEnd({ title: 'Delete judge?', text: '"' + name + '" will be removed.', icon: 'warning', confirmButtonText: 'Delete' }).then(result => {
        if (!result.isConfirmed) return;
        const year = getCurrentYear();
        const payload = new URLSearchParams();
        payload.append('action','delete_judge');
        payload.append('judge_id', id);
        payload.append('ajax','1');
        fetch('admin.php?year=' + encodeURIComponent(year), { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString() })
            .then(parseJsonSafe).then(data => {
                if (!data.ok) throw new Error(data.message || 'Failed');
                toast(data.message || 'Deleted');
                refreshJudgesSection(year);
            }).catch(err => toast(err.message || 'Error', 'error'));
    });
}

function stopPolling() {
	if (!isPolling) {
		console.log('Not polling, skipping stop');
		return;
	}
	
	console.log('Stopping polling interval');
	isPolling = false;
	updateInProgress = false;
	
	if (pollingInterval) {
		clearInterval(pollingInterval);
		pollingInterval = null;
	}
	
	if (updateDebounceTimer) {
		clearTimeout(updateDebounceTimer);
		updateDebounceTimer = null;
	}
}
 
function checkForUpdates() {
	if (updateInProgress) return;
	
	const year = getCurrentYear();
	if (!year) return;
	
	updateInProgress = true;
	
	// Check if there are any scores with status = 0 for this year
	fetch(`check_status.php?year=${encodeURIComponent(year)}&t=${Date.now()}`, {
		method: 'GET',
		cache: 'no-cache',
		headers: {
			'Cache-Control': 'no-cache',
			'Pragma': 'no-cache'
		}
	})
		.then(response => {
			if (!response.ok) {
				throw new Error(`HTTP ${response.status}: ${response.statusText}`);
			}
			return response.json();
		})
		.then(data => {
			// Check if there are any scores with status = 0
			const hasIncompleteScores = data.has_incomplete_scores || false;
			const incompleteCount = data.incomplete_count || 0;
			const completedCount = data.completed_count || 0;
			
			console.log('Status check:', { hasIncompleteScores, incompleteCount, completedCount, isPolling });
			
			// ALWAYS start polling if there are any scores (status = 0 OR status = 1)
			if (incompleteCount > 0 || completedCount > 0) {
				// START polling if there are any scores (incomplete OR completed)
				if (!isPolling) {
					startPolling();
				}
				updateResults(); // Update results
			} else {
				// No scores at all, but still start polling to monitor for new scores
				if (!isPolling) {
					startPolling();
				}
				updateResults(); // Still update results (empty)
			}
			
			updateInProgress = false;
		})
		.catch(error => {
			console.error('Polling error:', error);
			updateInProgress = false;
		});
}

function updateResults() {
	const year = getCurrentYear();
	if (!year) {
		console.log('No year selected, skipping update');
		return;
	}
	
	console.log('Updating results for year:', year);
	
	// Update the results tables
	const container = document.getElementById('resultsTables');
	if (container) {
		// Add visual feedback for updating
		const judgeStatusCard = container.querySelector('#judgeStatusCard');
		if (judgeStatusCard) {
			judgeStatusCard.classList.add('updating');
		}
		
		fetch(`get_resultsTable.php?year=${encodeURIComponent(year)}&t=${Date.now()}`, { cache: 'no-cache' })
			.then(r => {
				if (!r.ok) {
					throw new Error(`HTTP ${r.status}: ${r.statusText}`);
				}
				return r.text();
			})
			.then(html => { 
				container.innerHTML = html;
				
				// Log update for debugging
				console.log('Results updated at', new Date().toLocaleTimeString());
				
				// Remove updating class after a short delay
				setTimeout(() => {
					const newJudgeStatusCard = container.querySelector('#judgeStatusCard');
					if (newJudgeStatusCard) {
						newJudgeStatusCard.classList.remove('updating');
					}
				}, 500);
			})
			.catch(error => {
				console.error('Update error:', error);
				// Remove updating class on error
				const judgeStatusCard = container.querySelector('#judgeStatusCard');
				if (judgeStatusCard) {
					judgeStatusCard.classList.remove('updating');
				}
			});
	} else {
		console.log('Results container not found');
	}
}

function getCurrentYear() {
	// Get year from URL parameter or from the year select dropdown
	const urlParams = new URLSearchParams(window.location.search);
	const yearFromUrl = urlParams.get('year');
	
	if (yearFromUrl) {
		return yearFromUrl;
	}
	
	// Fallback to dropdown value
	const yearSelect = document.getElementById('lockYearSelect');
	return yearSelect ? yearSelect.value : null;
}

function performSmoothUpdate(data, year, cacheBuster) {
	// Clear any pending debounced updates
	if (updateDebounceTimer) {
		clearTimeout(updateDebounceTimer);
	}
	
	// Debounce the update to prevent rapid successive updates
	updateDebounceTimer = setTimeout(() => {
		// Only do a full refresh to avoid DOM manipulation glitches
		const container = document.getElementById('resultsTables');
		if (container) {
			// Add a subtle loading indicator
			container.style.opacity = '0.95';
			
			fetch(`get_resultsTable.php?year=${encodeURIComponent(year)}&t=${cacheBuster}`, { cache: 'no-cache' })
				.then(r => r.text())
				.then(html => { 
					// Use a single DOM update to prevent glitches
					container.innerHTML = html;
					container.style.opacity = '1';
					updateInProgress = false;
				})
				.catch(() => {
					container.style.opacity = '1';
					updateInProgress = false;
				});
		} else {
			updateInProgress = false;
		}
	}, 100); // 100ms debounce
}

function updateResultsTables(data) {
	console.log('Updating results tables with new data...');
	
	// Update male candidates table
	updateMaleCandidatesTable(data);
	
	// Update female candidates table  
	updateFemaleCandidatesTable(data);
}

function updateMaleCandidatesTable(data) {
	const tableBody = document.getElementById('maleCandidatesTable');
	if (!tableBody || !data.male_candidates) return;
	
	// Get current table structure
	const currentRows = Array.from(tableBody.querySelectorAll('tr'));
	
	// Update each row with new scores
	data.male_candidates.forEach(candidate => {
		const candidateId = candidate.id;
		const candidateNumber = candidate.number;
		
		// Find existing row for this candidate
		let existingRow = currentRows.find(row => {
			const numberCell = row.querySelector('.candidate-number');
			return numberCell && numberCell.textContent.trim() === candidateNumber.toString();
		});
		
		if (!existingRow) return;
		
		// Update criteria scores
		data.criteria.forEach((criteria, criteriaIndex) => {
			const criteriaName = criteria.name;
			const scoreCell = existingRow.children[criteriaIndex + 1]; // +1 for candidate number column
			
			if (scoreCell) {
            const rawScore = (data.totals_by_candidate[candidateId] && data.totals_by_candidate[candidateId].criteria && data.totals_by_candidate[candidateId].criteria[criteriaName] !== undefined) ? data.totals_by_candidate[candidateId].criteria[criteriaName] : 0;
            const formattedScore = formatScore(rawScore);
				
				// Update color square and score without transitions
				const colorSquare = getColorSquare(candidateId, criteriaName, data.male_criteria_top3, 0);
				const newContent = `${colorSquare}${formattedScore}`;
				
				// Only update if content has changed
				if (scoreCell.innerHTML !== newContent) {
					scoreCell.innerHTML = newContent;
				}
			}
		});
		
		// Update overall score
		const overallCell = existingRow.querySelector('.overall-score');
		if (overallCell) {
			const overallScore = calculateOverallScore(candidateId, data.totals_by_candidate);
			const formattedOverall = formatScore(overallScore);
			
			// Update color square and score without transitions
			const overallColorSquare = getColorSquare(candidateId, 'overall', data.male_overall_top3, 0);
			const newOverallContent = `${overallColorSquare}${formattedOverall}`;
			
			// Only update if content has changed
			if (overallCell.innerHTML !== newOverallContent) {
				overallCell.innerHTML = newOverallContent;
			}
		}
	});
}

function updateFemaleCandidatesTable(data) {
	const tableBody = document.getElementById('femaleCandidatesTable');
	if (!tableBody || !data.female_candidates) return;
	
	// Get current table structure
	const currentRows = Array.from(tableBody.querySelectorAll('tr'));
	
	// Update each row with new scores
	data.female_candidates.forEach(candidate => {
		const candidateId = candidate.id;
		const candidateNumber = candidate.number;
		
		// Find existing row for this candidate
		let existingRow = currentRows.find(row => {
			const numberCell = row.querySelector('td:first-child');
			return numberCell && numberCell.textContent.trim() === candidateNumber.toString();
		});
		
		if (!existingRow) return;
		
		// Update criteria scores
		data.criteria.forEach((criteria, criteriaIndex) => {
			const criteriaName = criteria.name;
			const scoreCell = existingRow.children[criteriaIndex + 1]; // +1 for candidate number column
			
			if (scoreCell) {
            const rawScore = (data.totals_by_candidate[candidateId] && data.totals_by_candidate[candidateId].criteria && data.totals_by_candidate[candidateId].criteria[criteriaName] !== undefined) ? data.totals_by_candidate[candidateId].criteria[criteriaName] : 0;
            const formattedScore = formatScore(rawScore);
				
				// Update color square and score without transitions
				const colorSquare = getColorSquare(candidateId, criteriaName, data.female_criteria_top3, 0);
				const newContent = `${colorSquare}${formattedScore}`;
				
				// Only update if content has changed
				if (scoreCell.innerHTML !== newContent) {
					scoreCell.innerHTML = newContent;
				}
			}
		});
		
		// Update overall score
		const overallCell = existingRow.querySelector('.overall-score');
		if (overallCell) {
			const overallScore = calculateOverallScore(candidateId, data.totals_by_candidate);
			const formattedOverall = formatScore(overallScore);
			
			// Update color square and score without transitions
			const overallColorSquare = getColorSquare(candidateId, 'overall', data.female_overall_top3, 0);
			const newOverallContent = `${overallColorSquare}${formattedOverall}`;
			
			// Only update if content has changed
			if (overallCell.innerHTML !== newOverallContent) {
				overallCell.innerHTML = newOverallContent;
			}
		}
	});
}

function getColorSquare(candidateId, criteriaName, top3Data, position) {
	if (!top3Data) return '';
	
	// Handle criteria-specific top3 data
	if (criteriaName !== 'overall' && top3Data[criteriaName]) {
		const top3ForCriteria = top3Data[criteriaName];
		const candidateEntry = top3ForCriteria.find(entry => entry.candidate_id === candidateId);
		
		if (candidateEntry) {
			let colorClass = '';
			if (candidateEntry.position === 0) colorClass = 'green';
			else if (candidateEntry.position === 1) colorClass = 'yellow';
			else if (candidateEntry.position === 2) colorClass = 'red';
			
			return colorClass ? `<span class="color-square ${colorClass}"></span>` : '';
		}
	}
	
	// Handle overall top3 data
	if (criteriaName === 'overall' && Array.isArray(top3Data)) {
		const candidateEntry = top3Data.find(entry => entry.candidate_id === candidateId);
		
		if (candidateEntry) {
			let colorClass = '';
			if (candidateEntry.position === 0) colorClass = 'green';
			else if (candidateEntry.position === 1) colorClass = 'yellow';
			else if (candidateEntry.position === 2) colorClass = 'red';
			
			return colorClass ? `<span class="color-square ${colorClass}"></span>` : '';
		}
	}
	
	return '';
}

function calculateOverallScore(candidateId, totalsByCandidate) {
	if (!totalsByCandidate || !totalsByCandidate[candidateId]) return 0;
	
	const candidate = totalsByCandidate[candidateId];
	let total = 0;
	
	for (const criteriaName in candidate.criteria) {
		total += parseFloat(candidate.criteria[criteriaName]) || 0;
	}
	
	return total;
}

function formatScore(score) {
    const n = parseFloat(score);
    if (isNaN(n)) return '0';
    const s = rtrim(rtrim(number_format(n, 2), '0'), '.');
    return s === '' ? '0' : s;
}

function number_format(number, decimals, dec_point, thousands_sep) {
	number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
	var n = !isFinite(+number) ? 0 : +number,
		prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
		sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
		dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
		s = '',
		toFixedFix = function (n, prec) {
			var k = Math.pow(10, prec);
			return '' + Math.round(n * k) / k;
		};
	s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
	if (s[0].length > 3) {
		s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
	}
	if ((s[1] || '').length < prec) {
		s[1] = s[1] || '';
		s[1] += new Array(prec - s[1].length + 1).join('0');
	}
	return s.join(dec);
}

function toggleHighlights() {
	var toggleIcon = document.getElementById('toggleIcon');
	var toggleText = document.getElementById('toggleText');
	var body = document.body;
	
	if (body.classList.contains('highlights-hidden')) {
		body.classList.remove('highlights-hidden');
		toggleIcon.className = 'fas fa-eye';
		toggleText.textContent = 'Hide Highlights';
	} else {
		body.classList.add('highlights-hidden');
		toggleIcon.className = 'fas fa-eye-slash';
		toggleText.textContent = 'Show Highlights';
	}
}

function loadCriteriaForYear(year) {
	if (year) {
		window.location.href = 'admin.php?year=' + encodeURIComponent(year);
	}
}

function selectYearForManagement(year) {
	// Enable/disable buttons based on selection
	toggleLockButton();
	
	if (year) {
		window.location.href = 'admin.php?year=' + encodeURIComponent(year);
	} else {
		window.location.href = 'admin.php';
	}
}

function filterContentByYear(year) {
	if (year) {
		window.location.href = 'admin.php?year=' + encodeURIComponent(year);
	} else {
		window.location.href = 'admin.php';
	}
}

function editCriteria(id, name, percentage) {
	const modal = `
		<div class="modal fade" id="editCriteriaModal" tabindex="-1">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Criteria</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<form id="editCriteriaForm">
							<div class="mb-3">
								<label class="form-label fw-semibold">Criteria Name</label>
                                <input type="text" class="form-control" id="editCriteriaName" value="${name}" required data-original-name="${name}">
							</div>
							<div class="mb-3">
								<label class="form-label fw-semibold">Percentage</label>
                                <input type="number" class="form-control" id="editCriteriaPercentage" value="${Math.round(percentage)}" min="1" max="100" required data-original-percentage="${Math.round(percentage)}">
								<div class="form-text">Enter a whole number between 1 and 100</div>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onclick="saveCriteriaEdit(${id})">Save Changes</button>
					</div>
				</div>
			</div>
		</div>
	`;
	
	// Remove existing modal if any
	const existingModal = document.getElementById('editCriteriaModal');
	if (existingModal) {
		existingModal.remove();
	}
	
	// Add new modal
	document.body.insertAdjacentHTML('beforeend', modal);
	
	// Show modal
	const editModal = new bootstrap.Modal(document.getElementById('editCriteriaModal'));
	editModal.show();
}

function saveCriteriaEdit(id) {
    const name = document.getElementById('editCriteriaName').value.trim();
    const percentage = parseInt(document.getElementById('editCriteriaPercentage').value);
    const originalName = document.getElementById('editCriteriaName').getAttribute('data-original-name') || '';
    const originalPct = parseInt(document.getElementById('editCriteriaPercentage').getAttribute('data-original-percentage'), 10) || 0;
    if (!name) { toast('Please enter a criteria name', 'error'); return; }
    if (isNaN(percentage) || percentage < 1 || percentage > 100) { toast('Percentage must be 1-100', 'error'); return; }
    if (name.toLowerCase() !== originalName.toLowerCase() && criteriaNameExists(name)) { toast('Criteria name already exists', 'error'); return; }
    const currentTotal = getCriteriaTotalPercent();
    const baseTotal = (currentTotal - originalPct);
    const remaining = Math.max(0, 100 - baseTotal);
    if (percentage > remaining) { 
        const currentStr = `${baseTotal}%`;
        const addingStr = `${percentage}%`;
        toast(`Error: <span style="color: red;">Adding ${addingStr}</span> would cause the total (${currentStr}) to exceed 100%. Please adjust the values accordingly.`, 'error'); 
        return; 
    }
    const year = getCurrentYear();
    const payload = new URLSearchParams();
    payload.append('action','edit_criteria');
    payload.append('criteria_id', id);
    payload.append('criteria_name', name);
    payload.append('criteria_percentage', String(percentage));
    payload.append('criteria_year', year || '');
    payload.append('ajax','1');
    fetch('admin.php?year=' + encodeURIComponent(year || ''), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString()
    }).then(parseJsonSafe).then(data => {
        if (!data.ok) throw new Error(data.message || 'Failed');
        const newTotal = (currentTotal - originalPct) + percentage;
        if (newTotal === 100) {
            toast('Total reached 100%', 'success');
        } else {
            const newRemaining = 100 - newTotal;
            toast(`Updated. ${newRemaining}% remaining`, 'success');
        }
        const modalEl = document.getElementById('editCriteriaModal');
        if (modalEl) { const inst = bootstrap.Modal.getInstance(modalEl); if (inst) inst.hide(); }
        refreshCriteriaSection(year);
    }).catch(err => toast(err.message || 'Error', 'error'));
}

function deleteCriteria(id, name) {
	if (confirm(`Are you sure you want to delete the criteria "${name}"? This action cannot be undone and will remove all related scoring data.`)) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.innerHTML = `
			<input type="hidden" name="action" value="delete_criteria">
			<input type="hidden" name="criteria_id" value="${id}">
		`;
		document.body.appendChild(form);
		form.submit();
	}
}

function toggleLockButton() {
    const select = document.getElementById('lockYearSelect');
    const lockBtn = document.getElementById('lockBtn');
    const deleteBtn = document.getElementById('deleteBtn');
    
    if (select && select.value) {
        lockBtn.disabled = false;
        deleteBtn.disabled = false;
        
        // Update button text based on lock status
        const selectedOption = select.options[select.selectedIndex];
        const isLocked = selectedOption && selectedOption.text.includes('(Locked)');
        lockBtn.textContent = isLocked ? 'Unlock' : 'Lock';
    } else {
        lockBtn.disabled = true;
        deleteBtn.disabled = true;
        lockBtn.textContent = 'Lock/Unlock';
    }
}

function deleteYear() {
	const select = document.getElementById('lockYearSelect');
	const year = select.value;
	
	if (year) {
		Swal.fire({
			title: 'Delete Year',
			text: `Are you sure you want to delete the entire year "${year}"? This will delete ALL data including criteria, candidates, judges, and scores. This action cannot be undone!`,
			icon: 'warning',
			showCancelButton: true,
			confirmButtonColor: '#d33',
			cancelButtonColor: '#3085d6',
			confirmButtonText: 'Yes, delete it!',
			cancelButtonText: 'Cancel'
		}).then((result) => {
			if (result.isConfirmed) {
				const form = document.createElement('form');
				form.method = 'POST';
				form.innerHTML = `
					<input type="hidden" name="action" value="delete_year">
					<input type="hidden" name="delete_year" value="${year}">
				`;
				document.body.appendChild(form);
				form.submit();
			}
		});
	}
}

function clearCandidates() {
	if (confirm('Are you sure you want to clear all candidates? This will delete all candidate data for this year.')) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.innerHTML = `
			<input type="hidden" name="action" value="clear_candidates">
			<input type="hidden" name="clear_year" value="<?= h($sel_year) ?>">
		`;
		document.body.appendChild(form);
		form.submit();
	}
}

function updateTotal() {
	const maleCount = parseInt(document.getElementById('male_count').value) || 0;
	const femaleCount = parseInt(document.getElementById('female_count').value) || 0;
	const total = maleCount + femaleCount;
	document.getElementById('totalCandidates').value = total;
}

// Initialize total on page load
document.addEventListener('DOMContentLoaded', function() {
	updateTotal();
});

function validateForm() {
	const maleCount = parseInt(document.getElementById('male_count').value) || 0;
	const femaleCount = parseInt(document.getElementById('female_count').value) || 0;
	
	console.log('Form validation: male_count=' + maleCount + ', female_count=' + femaleCount);
	
	if (maleCount < 0 || femaleCount < 0) {
		alert('Please enter valid numbers (0 or greater)');
		return false;
	}
	
	return true;
}

// Judge Management Functions
function filterJudgesByYear(year) {
	const rows = document.querySelectorAll('#judgesTableBody tr');
	
	rows.forEach(row => {
		const rowYear = row.getAttribute('data-year');
		if (year === '' || rowYear === year) {
			row.style.display = '';
		} else {
			row.style.display = 'none';
		}
	});
	
	// Update the header text
	const headerText = document.querySelector('.card-header small');
	if (year === '') {
		headerText.textContent = 'Manage judges for all years';
	} else {
		headerText.textContent = `Manage judges for ${year}`;
	}
}

function copyToClipboard(text) {
	navigator.clipboard.writeText(text).then(function() {
		// Show success feedback
		const button = event.target.closest('button');
		const originalIcon = button.innerHTML;
		button.innerHTML = '<i class="fas fa-check text-success"></i>';
		button.classList.add('btn-success');
		button.classList.remove('btn-outline-secondary');
		
		setTimeout(() => {
			button.innerHTML = originalIcon;
			button.classList.remove('btn-success');
			button.classList.add('btn-outline-secondary');
		}, 2000);
	}).catch(function(err) {
		alert('Failed to copy access code. Please copy manually: ' + text);
	});
}

function editJudge(id, name, judgeNumber, accessCode, year) {
	const modal = `
		<div class="modal fade" id="editJudgeModal" tabindex="-1">
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Judge</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<form id="editJudgeForm">
							<div class="row g-3">
								<div class="col-12">
									<label class="form-label fw-semibold">Judge Number</label>
                                    <input type="text" class="form-control" id="editJudgeNumber" value="${judgeNumber}" required data-original-number="${judgeNumber}">
								</div>
								<div class="col-12">
									<label class="form-label fw-semibold">Judge Name</label>
									<input type="text" class="form-control" id="editJudgeName" value="${name}" required>
								</div>
								<div class="col-12">
									<label class="form-label fw-semibold">Year</label>
									<div class="form-control-plaintext bg-light p-2 rounded">
										<span class="badge bg-info">${year}</span>
									</div>
								</div>
							</div>
						</form>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
						<button type="button" class="btn btn-primary" onclick="saveJudgeEdit(${id})">Save Changes</button>
					</div>
				</div>
			</div>
		</div>
	`;
	
	// Remove existing modal if any
	const existingModal = document.getElementById('editJudgeModal');
	if (existingModal) {
		existingModal.remove();
	}
	
	// Add new modal
	document.body.insertAdjacentHTML('beforeend', modal);
	
	// Show modal
	const editModal = new bootstrap.Modal(document.getElementById('editJudgeModal'));
	editModal.show();
}

function regenerateAccessCode(id, name) {
	if (confirm(`Are you sure you want to regenerate the access code for "${name}"? This will invalidate the current access code.`)) {
		// Create a form to regenerate access code
		const form = document.createElement('form');
		form.method = 'POST';
		form.innerHTML = `
			<input type="hidden" name="action" value="regenerate_judge_code">
			<input type="hidden" name="judge_id" value="${id}">
		`;
		document.body.appendChild(form);
		form.submit();
	}
}

function saveJudgeEdit(id) {
    const judgeNumber = document.getElementById('editJudgeNumber').value.trim();
    const judgeName = document.getElementById('editJudgeName').value.trim();
    const originalNumber = document.getElementById('editJudgeNumber').getAttribute('data-original-number') || '';
    if (!judgeNumber) { toast('Please enter a judge number', 'error'); return; }
    if (!judgeName) { toast('Please enter a judge name', 'error'); return; }
    if (judgeNumber.toLowerCase() !== originalNumber.toLowerCase() && judgeNumberExistsLocal(judgeNumber)) { toast('Judge number already exists', 'error'); return; }
    const year = getCurrentYear();
    const payload = new URLSearchParams();
    payload.append('action','edit_judge');
    payload.append('judge_id', id);
    payload.append('judge_number', judgeNumber);
    payload.append('judge_name', judgeName);
    payload.append('ajax','1');
    fetch('admin.php?year=' + encodeURIComponent(year || ''), {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: payload.toString()
    }).then(parseJsonSafe).then(data => {
        if (!data.ok) throw new Error(data.message || 'Failed');
        toast(data.message || 'Updated');
        const modalEl = document.getElementById('editJudgeModal');
        if (modalEl) { const inst = bootstrap.Modal.getInstance(modalEl); if (inst) inst.hide(); }
        refreshJudgesSection(year);
    }).catch(err => toast(err.message || 'Error', 'error'));
}

function deleteJudge(id, name) {
	if (confirm(`Are you sure you want to delete judge "${name}"? This action cannot be undone and will remove all their scoring data.`)) {
		const form = document.createElement('form');
		form.method = 'POST';
		form.innerHTML = `
			<input type="hidden" name="action" value="delete_judge">
			<input type="hidden" name="judge_id" value="${id}">
		`;
		document.body.appendChild(form);
		form.submit();
	}
}


function rtrim(str, char) {
	return str.replace(new RegExp(char + '+$'), '');
}

function checkDuplicateCriteriaName(name, year) {
	return false;
}

function checkDuplicateJudgeNumber(judgeNumber, year) {
	return false;
}
function validateCriteriaForm() {
	const name = document.querySelector('input[name="criteria_name"]').value.trim();
	const percentage = parseInt(document.querySelector('input[name="criteria_percentage"]').value);
	
	if (!name) {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'error',
			title: 'Please enter a criteria name',
			showConfirmButton: false,
			timer: 3000
		});
		return false;
	}
	
	if (isNaN(percentage) || percentage < 1 || percentage > 100) {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'error',
			title: 'Percentage must be between 1 and 100',
			showConfirmButton: false,
			timer: 3000
		});
		return false;
	}
	
	return true;
}

function validateJudgeForm() {
	const judgeNumber = document.querySelector('input[name="judge_number"]').value.trim();
	const judgeName = document.querySelector('input[name="judge_name"]').value.trim();
	
	if (!judgeNumber) {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'error',
			title: 'Please enter a judge number',
			showConfirmButton: false,
			timer: 3000
		});
		return false;
	}
	
	if (!judgeName) {
		Swal.fire({
			toast: true,
			position: 'top-end',
			icon: 'error',
			title: 'Please enter a judge name',
			showConfirmButton: false,
			timer: 3000
		});
		return false;
	}
	
	return true;
}

document.addEventListener('DOMContentLoaded', function() {
	toggleLockButton();
});
</script>
</body>
</html>

<?php
require_once __DIR__ . '/db.php';
session_start();
$mysqli = db_connect();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$judge_number = trim($_POST['judge_number'] ?? '');
	$year = trim($_POST['year'] ?? '');
	if ($judge_number !== '' && $year !== '') {
		// Check lock
		$lockRes = $mysqli->query("SELECT is_locked FROM tbl_admin WHERE year='".$mysqli->real_escape_string($year)."'");
		$locked = 0;
		if ($row = $lockRes->fetch_assoc()) { $locked = (int)$row['is_locked']; }
		if ($locked) {
			$message = 'This event year is locked. Scoring is closed.';
		} else {
			// Find judge by judge_number and year
			$stmt = $mysqli->prepare("SELECT id, name FROM tbl_judges WHERE judge_number = ? AND year = ? LIMIT 1");
			$stmt->bind_param('ss', $judge_number, $year);
			$stmt->execute();
			$result = $stmt->get_result();
			$judge = $result->fetch_assoc();
			$stmt->close();

			if ($judge) {
				// Judge found, log them in
				$_SESSION['judge_id'] = (int)$judge['id'];
				$_SESSION['judge_name'] = $judge['name'];
				$_SESSION['judge_year'] = $year;
				$_SESSION['judge_number'] = $judge_number;
				header('Location: pageant_criteria.php');
				exit;
			} else {
				$message = 'Judge number not found for this year. Please contact admin.';
			}
		}
	}
}

$years = get_years($mysqli);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Judge Start - Pageant Criteria System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
	<div class="container">
		<a class="navbar-brand" href="index.php">Judge Portal</a>
	</div>
</nav>

<div class="container py-5">
	<div class="row justify-content-center">
		<div class="col-md-6">
			<div class="card shadow-sm">
				<div class="card-body p-4">
					<h1 class="h4 mb-3">Enter as Judge</h1>
					<?php if ($message): ?><div class="alert alert-warning"><?= h($message) ?></div><?php endif; ?>
					<form method="post" class="vstack gap-3">
						<div>
							<label class="form-label">Judge Number</label>
							<input type="text" name="judge_number" class="form-control" placeholder="e.g. 1, 2, J001" required>
							<small class="text-muted">Enter your assigned judge number</small>
						</div>
						<div>
							<label class="form-label">Event Year</label>
							<select name="year" class="form-select" required>
								<option value="">Select year</option>
								<?php foreach ($years as $y): ?>
									<?php if (!$y['is_locked']): ?>
										<option value="<?= h($y['year']) ?>"><?= h($y['year']) ?></option>
									<?php endif; ?>
								<?php endforeach; ?>
							</select>
							<?php if (empty(array_filter($years, function($y) { return !$y['is_locked']; }))): ?>
								<div class="alert alert-warning mt-2">
									<small>No open events available. All events are currently locked.</small>
								</div>
							<?php endif; ?>
						</div>
						<div>
							<button class="btn btn-success w-100" type="submit">Proceed to Scoring</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



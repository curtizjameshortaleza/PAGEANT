<?php
require_once __DIR__ . '/db.php';
session_start();
$mysqli = db_connect();

if (!isset($_SESSION['judge_id'], $_SESSION['judge_year'])) {
	header('Location: judge_start.php');
	exit;
}

$judge_id = (int)$_SESSION['judge_id'];
$judge_name = $_SESSION['judge_name'] ?? '';
$year = $_SESSION['judge_year'];

// Fetch criteria list in order
$criteria = [];
$res = $mysqli->query("SELECT * FROM tbl_criteria WHERE year='".$mysqli->real_escape_string($year)."' ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $criteria[] = $row;
$res->free();

// If no criteria, show message
if (!$criteria) {
	$no_message = 'No criteria found for this year. Please contact admin.';
}

// Check which criteria are completed for this judge
$completed_criteria = [];
foreach ($criteria as $cr) {
	$crid = (int)$cr['id'];
	$check = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=1 LIMIT 1");
	$completed = 0;
	if ($c = $check->fetch_assoc()) { 
		// If any scores exist with status=1, criteria is completed
		$completed = ((int)$c['count'] > 0) ? 1 : 0; 
	}
	$check->free();
	$completed_criteria[$crid] = $completed;
}

// Fetch candidates for the year
$candidates = [];
if ($criteria) {
	$res = $mysqli->query("SELECT * FROM tbl_candidates WHERE year='".$mysqli->real_escape_string($year)."' ORDER BY number ASC");
	while ($row = $res->fetch_assoc()) $candidates[] = $row;
	$res->free();
}

// Check if all criteria are done (status = 1 means completed)
$all_done = true;
foreach ($criteria as $cr) {
	$crid = (int)$cr['id'];
	$check = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=1 LIMIT 1");
	$count = 0;
	if ($c = $check->fetch_assoc()) { 
		$count = (int)$c['count']; 
	}
	$check->free();
	if ($count === 0) {
		$all_done = false;
		break;
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Judge Scoring - Pageant 2025-2026</title>
	<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<style>
		:root {
			--gold: #D4AF37;
			--gold-light: #F4E4B8;
			--gold-dark: #B8962E;
			--navy: #1a1a2e;
			--navy-light: #2d2d44;
			--white: #ffffff;
			--cream: #faf8f3;
			--text-dark: #2c2c2c;
			--text-muted: #6b7280;
			--success: #10b981;
			--success-light: #d1fae5;
			--shadow-sm: 0 2px 8px rgba(212, 175, 55, 0.08);
			--shadow-md: 0 4px 16px rgba(212, 175, 55, 0.12);
			--shadow-lg: 0 8px 32px rgba(212, 175, 55, 0.16);
			--gradient-gold: linear-gradient(135deg, #D4AF37 0%, #F4E4B8 100%);
			--gradient-navy: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%);
		}

		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
			background: linear-gradient(135deg, #faf8f3 0%, #f5f3ed 100%);
			color: var(--text-dark);
			line-height: 1.6;
			min-height: 100vh;
		}

		.container {
			width: 100%;
			max-width: none;
			margin: 0;
			padding: 30px 40px;
			box-sizing: border-box;
		}

		/* Elegant Header */
		.header-container {
			background: var(--gradient-navy);
			/* make header span the full viewport width */
			position: relative;
			left: 50%;
			right: 50%;
			margin-left: -50vw;
			margin-right: -50vw;
			width: 100vw;
			/* remove top padding coming from .container by shifting up */
			margin-top: -30px;
			padding: 40px 60px;
			box-shadow: var(--shadow-lg);
			/* allow scaled logo to overflow visually without forcing header to resize */
			overflow: visible;
		}

		.header-container::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><path d="M50 10 L60 40 L90 40 L65 60 L75 90 L50 70 L25 90 L35 60 L10 40 L40 40 Z" fill="rgba(212,175,55,0.05)"/></svg>') repeat;
			opacity: 0.3;
		}

		.brand {
			display: flex;
			align-items: center;
			gap: 24px;
			width: 100%;
			max-width: none;
			margin: 0;
			position: relative;
			z-index: 1;
		}

		.brand img {
			/* keep the image's layout box stable but visually scale it larger so the container doesn't resize */
			width: 100px;
			height: 100px;
			will-change: transform;
			transform-origin: left center;
			/* visually enlarge without affecting layout */
			transform: scale(1.18);
			object-fit: contain;
			filter: drop-shadow(0 4px 12px rgba(212, 175, 55, 0.3));
		}

		.brand-text {
			display: flex;
			flex-direction: column;
			gap: 4px;
		}

		.brand-name {
			font-family: 'Playfair Display', serif;
			font-size: 28px;
			font-weight: 700;
			color: var(--white);
			letter-spacing: 1px;
			text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
		}

		.brand-sub {
			font-size: 14px;
			font-weight: 600;
			color: var(--gold);
			letter-spacing: 3px;
			text-transform: uppercase;
		}

		/* Card Styles */
		.card {
			background: var(--white);
			border-radius: 20px;
			box-shadow: var(--shadow-md);
			margin-bottom: 24px;
			padding: 28px;
			border: 1px solid rgba(212, 175, 55, 0.1);
			transition: all 0.3s ease;
		}

		.card:hover {
			box-shadow: var(--shadow-lg);
		}

		/* Tabs Section */
		.tabs-container {
			background: var(--white);
			/* space from header */
			margin-top: 28px;
			border-radius: 20px;
			padding: 20px;
			box-shadow: var(--shadow-md);
			margin-bottom: 24px;
		}

		.cat-title {
			font-family: 'Playfair Display', serif;
			font-size: 18px;
			font-weight: 600;
			color: var(--navy);
			margin-bottom: 16px;
		}

		.tabs {
			display: flex;
			gap: 12px;
			flex-wrap: wrap;
		}

		.btn {
			padding: 12px 24px;
			border: 2px solid var(--gold-light);
			border-radius: 12px;
			background: var(--white);
			color: var(--text-dark);
			font-weight: 600;
			font-size: 14px;
			cursor: pointer;
			transition: all 0.3s ease;
			position: relative;
			overflow: hidden;
			display: flex;
			align-items: center;
			gap: 8px;
		}

		.criteria-weight {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: 32px;
			height: 32px;
			background: rgba(212, 175, 55, 0.15);
			border-radius: 8px;
			font-weight: 700;
			font-size: 13px;
			color: var(--gold-dark);
			padding: 0 8px;
		}

		.btn::before {
			content: '';
			position: absolute;
			top: 50%;
			left: 50%;
			width: 0;
			height: 0;
			border-radius: 50%;
			background: var(--gradient-gold);
			transition: all 0.5s ease;
			transform: translate(-50%, -50%);
			z-index: 0;
		}

		.btn:hover::before {
			width: 300px;
			height: 300px;
		}

		.btn > * {
			position: relative;
			z-index: 1;
		}

		.btn:hover {
			color: var(--white);
			border-color: var(--gold);
			transform: translateY(-2px);
			box-shadow: var(--shadow-md);
		}

		.btn.active {
			background: var(--gradient-gold);
			color: var(--navy);
			border-color: var(--gold);
			box-shadow: 0 4px 20px rgba(212, 175, 55, 0.3);
		}

		.btn.active .criteria-weight {
			background: var(--navy);
			color: var(--gold);
		}

		/* Judge Info */
		.judge-row {
			display: flex;
			align-items: flex-end;
			gap: 20px;
			margin-bottom: 24px;
			flex-wrap: wrap;
		}

		.judge-card, .gender-card {
			background: var(--white);
			border: 1px solid rgba(212, 175, 55, 0.2);
			border-radius: 16px;
			padding: 20px 24px;
			box-shadow: var(--shadow-sm);
			transition: all 0.3s ease;
		}

		.judge-card:hover, .gender-card:hover {
			box-shadow: var(--shadow-md);
			border-color: var(--gold);
		}

		.judge-card {
			flex: 1;
			min-width: 280px;
		}

		.small {
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 1px;
			font-weight: 600;
		}

		.muted {
			color: var(--text-muted);
		}

		input.name {
			width: 100%;
			padding: 12px 16px;
			border: 2px solid rgba(212, 175, 55, 0.2);
			border-radius: 10px;
			font-size: 16px;
			margin-top: 8px;
			background: var(--cream);
			font-weight: 500;
			color: var(--navy);
		}

		.gender-options {
			display: flex;
			gap: 10px;
			margin-top: 12px;
		}

		.gender-options label {
			cursor: pointer;
		}

		.gender-options input {
			display: none;
		}

		.pill {
			display: inline-block;
			padding: 10px 20px;
			border-radius: 50px;
			background: var(--cream);
			border: 2px solid rgba(212, 175, 55, 0.2);
			font-weight: 600;
			font-size: 13px;
			transition: all 0.3s ease;
			color: var(--text-dark);
		}

		.pill:hover {
			border-color: var(--gold);
			transform: scale(1.05);
		}

		.gender-options input:checked + .pill {
			background: var(--gradient-gold);
			color: var(--navy);
			border-color: var(--gold);
			box-shadow: 0 4px 16px rgba(212, 175, 55, 0.3);
		}

		/* Table Styles */
		.section-title {
			font-family: 'Playfair Display', serif;
			font-weight: 700;
			font-size: 20px;
			margin-bottom: 16px;
			color: var(--navy);
			display: flex;
			align-items: center;
			gap: 12px;
		}

		.section-title::before {
			content: '';
			width: 4px;
			height: 24px;
			background: var(--gradient-gold);
			border-radius: 2px;
		}

		.table-wrap {
			overflow-x: auto;
			border-radius: 12px;
			box-shadow: var(--shadow-sm);
		}

		table {
			width: 100%;
			border-collapse: separate;
			border-spacing: 0;
			font-size: 14px;
		}

		thead th {
			background: var(--gradient-navy);
			padding: 16px 12px;
			text-align: left;
			font-weight: 600;
			color: var(--white);
			font-size: 12px;
			text-transform: uppercase;
			letter-spacing: 1px;
			position: sticky;
			top: 0;
			z-index: 10;
		}

		thead th:first-child {
			border-top-left-radius: 12px;
		}

		thead th:last-child {
			border-top-right-radius: 12px;
		}

		tbody tr {
			transition: all 0.2s ease;
		}

		tbody tr:hover {
			background: var(--cream);
		}

		tbody td {
			padding: 16px 12px;
			border-bottom: 1px solid rgba(212, 175, 55, 0.1);
			font-weight: 500;
		}

		tbody tr:last-child td:first-child {
			border-bottom-left-radius: 12px;
		}

		tbody tr:last-child td:last-child {
			border-bottom-right-radius: 12px;
		}

		.score-input {
			width: 100%;
			max-width: 140px;
			padding: 10px 14px;
			border: 2px solid rgba(212, 175, 55, 0.2);
			border-radius: 10px;
			text-align: center;
			font-size: 16px;
			font-weight: 600;
			transition: all 0.3s ease;
			background: var(--white);
			color: var(--navy);
		}

		.score-input:focus {
			outline: none;
			border-color: var(--gold);
			box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
			transform: scale(1.05);
		}

		.score-input::-webkit-outer-spin-button,
		.score-input::-webkit-inner-spin-button {
			-webkit-appearance: none;
			margin: 0;
		}

		.score-input[type=number] {
			-moz-appearance: textfield;
		}

		.score-input:disabled {
			background: #f8f9fa;
			color: #6c757d;
			border-color: #dee2e6;
		}

		.score-input.error {
			border-color: #dc3545;
			background: #fff5f5;
			animation: shake 0.3s ease;
		}

		@keyframes shake {
			0%, 100% { transform: translateX(0); }
			25% { transform: translateX(-5px); }
			75% { transform: translateX(5px); }
		}

		.error-icon {
			position: absolute;
			right: -30px;
			top: 50%;
			transform: translateY(-50%);
			color: #dc3545;
			font-size: 20px;
			display: none;
		}

		.score-wrapper {
			position: relative;
			display: inline-block;
			width: 100%;
		}

		/* Alert Styles */
		.alert {
			padding: 20px 24px;
			border-radius: 16px;
			margin-bottom: 24px;
			border-left: 4px solid;
			font-weight: 500;
		}

		.alert-warning {
			background: linear-gradient(135deg, #fff9e6 0%, #fff3cc 100%);
			border-left-color: #ffc107;
			color: #856404;
		}

		.alert-success {
			background: var(--success-light);
			border-left-color: var(--success);
			color: #065f46;
		}

		.alert h6 {
			margin-bottom: 8px;
			font-weight: 700;
			font-size: 16px;
		}

		/* Submit Button */
		.submit-btn {
			padding: 14px 32px;
			background: var(--gradient-gold);
			color: var(--navy);
			border: none;
			border-radius: 12px;
			font-weight: 700;
			font-size: 15px;
			cursor: pointer;
			transition: all 0.3s ease;
			box-shadow: 0 4px 16px rgba(212, 175, 55, 0.3);
			text-transform: uppercase;
			letter-spacing: 1px;
		}

		.submit-btn:hover:not(:disabled) {
			transform: translateY(-2px);
			box-shadow: 0 8px 24px rgba(212, 175, 55, 0.4);
		}

		.submit-btn:disabled {
			opacity: 0.5;
			cursor: not-allowed;
			transform: none;
		}

		.badge {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 4px 10px;
			border-radius: 50px;
			font-size: 11px;
			font-weight: 700;
			margin-left: 8px;
		}

		.bg-success {
			background: var(--success);
			color: var(--white);
		}

		/* Tab Content */
		.tab-content {
			margin-top: 24px;
		}

		.tab-pane {
			display: none;
		}

		.tab-pane.show.active {
			display: block;
			animation: fadeIn 0.3s ease;
		}

		@keyframes fadeIn {
			from { opacity: 0; transform: translateY(10px); }
			to { opacity: 1; transform: translateY(0); }
		}

		.d-flex {
			display: flex;
		}

		.justify-content-end {
			justify-content: flex-end;
		}

		.mb-3 {
			margin-bottom: 20px;
		}

		.mb-0 {
			margin-bottom: 0;
		}

		/* Responsive */
		@media (max-width: 768px) {
			.container {
				padding: 20px 15px;
			}

			.header-container {
				/* full-bleed on small screens as well */
				position: relative;
				left: 50%;
				right: 50%;
				margin-left: -50vw;
				margin-right: -50vw;
				width: 100vw;
				margin-top: -20px;
				padding: 30px 15px;
			}

			.brand {
				flex-direction: column;
				text-align: center;
			}

			/* keep logo a bit smaller on narrow screens */
			.brand img {
				width: 80px;
				height: 80px;
			}

			.brand-name {
				font-size: 22px;
			}

			.brand-sub {
				font-size: 12px;
			}

			.tabs {
				justify-content: center;
			}

				/* slightly smaller gap on mobile */
				.tabs-container {
					margin-top: 18px;
				}

			.btn {
				padding: 10px 16px;
				font-size: 13px;
			}

			.judge-row {
				flex-direction: column;
			}

			.gender-card {
				width: 100%;
			}

			.card {
				padding: 20px;
			}
		}
	</style>
</head>
<body>
	<div class="container">
		<!-- Header -->
		<div class="header-container">
			<div class="brand">
				<img src="asset/nobgLogo.png" alt="Baguio College Logo" />
				<div class="brand-text">
					<div class="brand-name">BAGUIO COLLEGE OF TECHNOLOGY</div>
					<div class="brand-sub">Pageant <?= htmlspecialchars($year) ?></div>
				</div>
			</div>
		</div>
		<!-- Error/Success messages -->
		<?php if (isset($no_message)): ?>
			<div class="alert alert-warning"><?= htmlspecialchars($no_message) ?></div>
		<?php else: ?>
			<?php if ($all_done): ?>
				<?php 
				// Only show completion message if all criteria have status = 1 (completed)
				$all_truly_done = true;
				foreach ($criteria as $cr) {
					$crid = (int)$cr['id'];
					$check = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=1 LIMIT 1");
					$count = 0;
					if ($c = $check->fetch_assoc()) { 
						$count = (int)$c['count']; 
					}
					$check->free();
					if ($count === 0) {
						$all_truly_done = false;
						break;
					}
				}
				?>
				<?php if ($all_truly_done): ?>
					<div class="alert alert-success">
						<h6>✓ All Criteria Submitted</h6>
						<p class="mb-0">Thank you for your participation as a judge.</p>
					</div>
					<button onclick="window.location.href='judge_start.php'" class="btn">Return to Home</button>
				<?php endif; ?>
			<?php endif; ?>
			
			<!-- Category Tabs -->
			<div class="tabs-container">
				<div class="cat-title">Scoring Categories</div>
				<div class="tabs" id="categoryTabs" role="tablist">
				<?php foreach ($criteria as $index => $cr): ?>
					<?php 
					// Check actual status for this criteria
					$crid = (int)$cr['id'];
					$check = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=1 LIMIT 1");
					$count = 0;
					if ($c = $check->fetch_assoc()) { 
						$count = (int)$c['count']; 
					}
					$check->free();
					$is_completed = ($count > 0);
					?>
					
					<button 
						class="btn tab <?= $index === 0 ? 'active' : '' ?> <?= $is_completed ? 'text-success' : '' ?>" 
						data-bs-toggle="tab" 
						data-bs-target="#criteria-<?= (int)$cr['id'] ?>" 
						type="button" 
						role="tab">
						
						<span><?= htmlspecialchars($cr['name']) ?></span>
						<span class="criteria-weight"><?= rtrim(rtrim($cr['percentage'], '0'), '.') ?>%</span>

						<?php if ($is_completed): ?>
							<!-- ✅ Show badge only if completed -->
							<span class="badge bg-success">✓</span>
						<?php endif; ?>
					</button>
				<?php endforeach; ?>
			</div>

			</div>

			<!-- Judge Info -->
			<div class="judge-row">
				<div class="judge-card">
					<label class="small muted">Judge Name</label>
					<input type="text" class="name" value="<?= htmlspecialchars($judge_name) ?>" readonly>
				</div>
				<div class="gender-card">
					<label class="small muted">Show Table</label>
					<div class="gender-options" role="radiogroup">
						<label><input type="radio" name="judgeGender" value="both" checked><span class="pill">Both</span></label>
						<label><input type="radio" name="judgeGender" value="male"><span class="pill">Male</span></label>
						<label><input type="radio" name="judgeGender" value="female"><span class="pill">Female</span></label>
					</div>
				</div>
			</div>

			<!-- Criteria Content -->
			<div class="tab-content" id="criteriaTabContent">
				<?php foreach ($criteria as $index => $cr): ?>
					<div class="tab-pane fade <?= $index === 0 ? 'show active' : '' ?>" 
						 id="criteria-<?= (int)$cr['id'] ?>" 
						 role="tabpanel">
						<div class="card">
							<div class="mb-3">
								<h6 style="font-size: 20px; font-weight: 700; color: var(--navy); margin-bottom: 8px;">
									<?= htmlspecialchars($cr['name']) ?> - <?= rtrim(rtrim($cr['percentage'], '0'), '.') ?>%
								</h6>
								<p class="muted small">Score range: 1 to <?= rtrim(rtrim($cr['percentage'], '0'), '.') ?></p>
								<?php 
								// Only show completion message if status = 1 (completed)
								$crid = (int)$cr['id'];
								$check = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=1 LIMIT 1");
								$count = 0;
								if ($c = $check->fetch_assoc()) { 
									$count = (int)$c['count']; 
								}
								$check->free();
								?>
								<?php if ($count > 0): ?>
									<div class="alert alert-success" style="margin-top: 16px;">
										<h6>✓ Completed</h6>
										<p class="mb-0">This criteria has been submitted and completed.</p>
									</div>

									<?php 
									// Always show a compact readout of all current scores (autosaved or submitted)
									$allRows = [];
									foreach ($candidates as $cand) {
										$cid = (int)$cand['id'];
										$val = isset($existingScoresMap[$cid]) ? rtrim(rtrim(number_format($existingScoresMap[$cid], 2, '.', ''), '0'), '.') : '';
										$allRows[] = [ 'num' => '#' . htmlspecialchars($cand['number']), 'gender' => $cand['gender'], 'score' => $val ];
									}
									?>
								<?php endif; ?>
							</div>

							<?php 
							$maleList = array_filter($candidates, function($c){ return $c['gender'] === 'Male'; });
							$femaleList = array_filter($candidates, function($c){ return $c['gender'] === 'Female'; });
								// Prefetch existing scores (autosaved or submitted) for this judge & criteria to prefill inputs on reload
								$existingScoresMap = [];
								$score_prefetch = $mysqli->prepare("SELECT candidate_id, score FROM tbl_scores WHERE judge_id = ? AND criteria_id = ?");
								if ($score_prefetch) {
									$score_prefetch->bind_param('ii', $judge_id, $cr['id']);
									$score_prefetch->execute();
									$prefetch_res = $score_prefetch->get_result();
									while ($row = $prefetch_res->fetch_assoc()) {
										$existingScoresMap[(int)$row['candidate_id']] = (float)$row['score'];
									}
									$score_prefetch->close();
								}
							?>

								<?php // Always render the form; disable inputs if locked ?>
								<form method="post" action="submit_scores.php" class="criteria-form" data-criteria-id="<?= (int)$cr['id'] ?>">
									<input type="hidden" name="criteria_id" value="<?= (int)$cr['id'] ?>">

							<!-- Tables Side by Side -->
							<div style="display: flex; gap: 24px; flex-wrap: wrap;">
								<!-- Male Candidates -->
								<div id="maleSection-<?= (int)$cr['id'] ?>" style="flex: 1; min-width: 320px;">
									<h6 class="section-title">Male Candidates</h6>
									<div class="table-wrap">
										<table>
											<thead>
												<tr>
													<th>Candidate #</th>
													<th>Score (1 - <?= rtrim(rtrim($cr['percentage'], '0'), '.') ?>)</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($maleList as $c): ?>
												<tr>
													<td><strong>#<?= htmlspecialchars($c['number']) ?></strong></td>
													<td>
											<?php
											// Single unified input; disabled when completed
											$prefill = isset($existingScoresMap[(int)$c['id']]) ? rtrim(rtrim(number_format($existingScoresMap[(int)$c['id']], 2, '.', ''), '0'), '.') : '';
											?>
											<input type="number" 
											   name="score[<?= (int)$c['id'] ?>]" 
											   class="score-input" 
											   data-candidate-id="<?= (int)$c['id'] ?>" 
											   data-criteria-id="<?= (int)$cr['id'] ?>" 
											   min="1" 
											   max="<?= (int)$cr['percentage'] ?>" 
											   step="0.1" 
											   <?= ($count > 0) ? 'disabled' : 'required' ?>
											   value="<?= $prefill ?>">
													</td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>

								<!-- Female Candidates -->
								<div id="femaleSection-<?= (int)$cr['id'] ?>" style="flex: 1; min-width: 320px;">
									<h6 class="section-title">Female Candidates</h6>
									<div class="table-wrap">
										<table>
											<thead>
												<tr>
													<th>Candidate #</th>
													<th>Score (1 - <?= rtrim(rtrim($cr['percentage'], '0'), '.') ?>)</th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ($femaleList as $c): ?>
												<tr>
													<td><strong>#<?= htmlspecialchars($c['number']) ?></strong></td>
													<td>
											<?php
											$prefill = isset($existingScoresMap[(int)$c['id']]) ? rtrim(rtrim(number_format($existingScoresMap[(int)$c['id']], 2, '.', ''), '0'), '.') : '';
											?>
											<input type="number" 
											   name="score[<?= (int)$c['id'] ?>]" 
											   class="score-input" 
											   data-candidate-id="<?= (int)$c['id'] ?>" 
											   data-criteria-id="<?= (int)$cr['id'] ?>" 
											   min="1" 
											   max="<?= (int)$cr['percentage'] ?>" 
											   step="0.1" 
											   <?= ($count > 0) ? 'disabled' : 'required' ?>
											   value="<?= $prefill ?>">
													</td>
												</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
							</div>

								<?php if (!$all_done): ?>
								<div class="d-flex justify-content-end" style="margin-top: 24px; gap: 10px;">
									<button type="submit" class="submit-btn" <?= ($count > 0) ? 'disabled' : '' ?>>
										Submit <?= htmlspecialchars($cr['name']) ?> Scores
									</button>
								</div>
								<?php endif; ?>
								</form>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<script>
const Toast = Swal.mixin({
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true,
    toast: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

document.addEventListener('DOMContentLoaded', function(){
    const JUDGE_ID = <?= (int)$judge_id ?>;
    const YEAR = <?= json_encode($year) ?>;
    function getDraftKey(criteriaId) { return `draftScores_${YEAR}_${JUDGE_ID}_${criteriaId}`; }
    function getQueueKey() { return `scoreQueue_${YEAR}_${JUDGE_ID}`; }
    function readJSON(key, fallback){ try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback; } catch { return fallback; } }
    function writeJSON(key, val){ try { localStorage.setItem(key, JSON.stringify(val)); } catch {} }
    function enqueueScore(item){ const key = getQueueKey(); const q = readJSON(key, []); q.push(item); writeJSON(key, q); }

    // Flush queue: try all pending items concurrently; keep failures
    async function flushQueue(){
        const key = getQueueKey();
        let q = readJSON(key, []);
        if (!q || !q.length) return;
        const pending = [...q];
        const results = await Promise.allSettled(pending.map(it => {
            const payload = new URLSearchParams();
            payload.append('candidate_id', it.candidate_id);
            payload.append('criteria_id', it.criteria_id);
            payload.append('score', it.score);
            return fetch('autosave_score.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString() })
                .then(async r => {
                    const data = await r.json().catch(()=>({ok:false}));
                    return r.ok && data && data.ok;
                })
                .catch(()=>false);
        }));
        const kept = pending.filter((_, i) => results[i].status !== 'fulfilled' || results[i].value === false);
        writeJSON(key, kept);
    }

    window.addEventListener('online', () => { flushQueue().catch(()=>{}); });

    // Tabs
    const tabButtons = document.querySelectorAll('.tabs .tab');
    const tabPanes = document.querySelectorAll('.tab-pane');

    function getActiveTabMax() {
        const activeTab = document.querySelector('.tab-pane.show.active');
        if (activeTab) {
            const scoreInput = activeTab.querySelector('.score-input:not([disabled])');
            if (scoreInput) return parseFloat(scoreInput.getAttribute('max')) || 120;
        }
        return 120;
    }

    tabButtons.forEach((btn) => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            tabButtons.forEach(t => t.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('show', 'active'));
            this.classList.add('active');
            const targetId = this.getAttribute('data-bs-target');
            const targetPane = document.querySelector(targetId);
            if (targetPane) targetPane.classList.add('show', 'active');
        });
    });

    // Debounce helper
    const debounce = (fn, delay) => { let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn.apply(null, args), delay); }; };

    // Per-input inflight tracking so simultaneous edits don't conflict
    const inflight = {}; // key -> { controller, lastValue }
    function makeKey(criteriaId, candidateId){ return `${criteriaId}_${candidateId}`; }

    // Autosave network function (debounced below). Saves local draft immediately.
    const autosaveNetwork = debounce((el) => {
        const val = el.value;
        const candidateId = el.getAttribute('data-candidate-id');
        const criteriaId = el.getAttribute('data-criteria-id');
        if (val === "" || isNaN(val) || !candidateId || !criteriaId) return;

        // Save draft locally immediately for offline safety
        const draftKey = getDraftKey(criteriaId);
        const draft = readJSON(draftKey, {});
        draft[candidateId] = val;
        writeJSON(draftKey, draft);

        const payload = new URLSearchParams();
        payload.append('candidate_id', candidateId);
        payload.append('criteria_id', criteriaId);
        payload.append('score', val);

        const key = makeKey(criteriaId, candidateId);

        // Abort previous in-flight request for same input so latest wins
        if (inflight[key] && inflight[key].controller) {
            try { inflight[key].controller.abort(); } catch(e) {}
        }

        const controller = new AbortController();
        inflight[key] = { controller, lastValue: val };

        // Visual non-blocking hint
        el.dataset.saving = '1';

        fetch('autosave_score.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString(), signal: controller.signal })
            .then(async r => {
                const data = await r.json().catch(()=>({ ok:false }));
                if (controller.signal.aborted) return; // silently ignore aborted requests
                if (r.ok && data && data.ok) {
                    delete inflight[key];
                    delete el.dataset.saving;
                    Toast.fire({ icon: 'success', title: 'Saved successfully' });
                    // attempt to flush queued items if any
                    flushQueue().catch(()=>{});
                } else {
                    delete inflight[key];
                    delete el.dataset.saving;
                    enqueueScore({ candidate_id: candidateId, criteria_id: criteriaId, score: val, ts: Date.now() });
                    Toast.fire({ icon: 'error', title: 'Error saving data' });
                }
            })
            .catch(err => {
                // Abort is normal when a newer request is sent — don't show error toast
                if (err && err.name === 'AbortError') return;
                delete inflight[key];
                delete el.dataset.saving;
                enqueueScore({ candidate_id: candidateId, criteria_id: criteriaId, score: val, ts: Date.now() });
                Toast.fire({ icon: 'error', title: 'Error saving data' });
            });
    }, 400);

    // Attach to all score inputs per form
    const forms = document.querySelectorAll('.criteria-form');
    forms.forEach(form => {
        function collectInputs() { return Array.from(form.querySelectorAll('.score-input:not([disabled])')); }

        function validateInput(input) {
            const value = parseFloat(input.value);
            const min = parseFloat(input.min) || 1;
            const max = parseFloat(input.getAttribute('max')) || 120;
            if (input.value && (isNaN(value) || value < min || value > max)) {
                input.classList.add('error');
                setTimeout(() => input.classList.remove('error'), 400);
                return false;
            } else {
                input.classList.remove('error');
                return true;
            }
        }

        const inputs = collectInputs();
        inputs.forEach(input => {
            // sanitize on input, debounce autosave
            input.addEventListener('input', function() {
                // only allow digits and single decimal point
                if (this.value && !/^[0-9]*\.?[0-9]*$/.test(this.value)) {
                    this.value = this.value.replace(/[^\d.]/g, '');
                }
                const value = parseFloat(this.value);
                const activeTabMax = getActiveTabMax();
                if (!isNaN(value) && value > activeTabMax) {
                    this.value = activeTabMax.toString();
                    Toast.fire({ icon:'error', title: `Max is ${activeTabMax}` });
                }
                validateInput(this);
                autosaveNetwork(this);
            });

            // ensure save on blur/change
            input.addEventListener('change', function() {
                validateInput(this);
                autosaveNetwork(this);
            });

            // prevent invalid characters at keypress
            input.addEventListener('keypress', function(e) {
                const char = String.fromCharCode(e.which || e.keyCode);
                const currentValue = this.value || '';
                const activeTabMax = getActiveTabMax();
                if (!/[0-9.]/.test(char)) {
                    e.preventDefault();
                    this.classList.add('error');
                    setTimeout(()=>this.classList.remove('error'), 300);
                    return;
                }
                if (char === '.' && currentValue.includes('.')) { e.preventDefault(); return; }
                const newValue = currentValue + char;
                if (!isNaN(parseFloat(newValue)) && parseFloat(newValue) > activeTabMax) {
                    e.preventDefault();
                    this.classList.add('error');
                    setTimeout(()=>this.classList.remove('error'), 300);
                    Toast.fire({ icon:'error', title: `Max is ${activeTabMax}` });
                }
            });

            // Enter moves to next input (don't submit form)
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const all = collectInputs();
                    const idx = all.indexOf(this);
                    if (idx >= 0 && idx < all.length - 1) {
                        all[idx + 1].focus();
                        all[idx + 1].select();
                    }
                }
            });
        });

        // Keep default form submit behavior for final submit button (if used) --
        // the autosave already keeps DB up-to-date with status=0. Final submit should call submit_scores.php to set status=1.
        form.addEventListener('submit', function(e) {
            // validate before allowing final submit
            const now = collectInputs();
            let invalid = null;
            now.forEach(inp => {
                const v = inp.value;
                const min = Number(inp.min) || 1;
                const max = Number(inp.max) || 120;
                if (!v || isNaN(Number(v)) || Number(v) < min || Number(v) > max) invalid = invalid || inp;
            });
            if (invalid) {
                e.preventDefault();
                invalid.focus();
                Toast.fire({ icon:'error', title: 'Please fix invalid scores' });
                return false;
            }
            // allow submit to server for finalizing (server should set status=1)
        });
    });

    // Restore drafts on load from localStorage if inputs empty
    tabPanes.forEach(pane => {
        const criteriaId = pane.id.replace('criteria-','');
        const draft = readJSON(getDraftKey(criteriaId), {});
        if (draft && typeof draft === 'object') {
            Object.entries(draft).forEach(([cid, val]) => {
                const inp = pane.querySelector(`.score-input[data-candidate-id="${cid}"]`);
                if (inp && !inp.disabled && (inp.value === '' || inp.value === null)) inp.value = val;
            });
        }
    });

    // Gender filter
    const radios = document.querySelectorAll('input[name="judgeGender"]');
    function applyGenderFilter() {
        const val = document.querySelector('input[name="judgeGender"]:checked').value;
        const maleSections = document.querySelectorAll('[id^="maleSection-"]');
        const femaleSections = document.querySelectorAll('[id^="femaleSection-"]');
        maleSections.forEach(el => el.style.display = (val === 'both' || val === 'male') ? '' : 'none');
        femaleSections.forEach(el => el.style.display = (val === 'both' || val === 'female') ? '' : 'none');
    }
    radios.forEach(r => r.addEventListener('change', applyGenderFilter));
    applyGenderFilter();
});
</script>
</body>
</html>
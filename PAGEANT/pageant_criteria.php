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
                                    <?php if ($count > 0): ?>
                                        <!-- Completed: show a clickable button that goes to next category -->
                                        <button type="button" class="submit-btn submit-next-btn" data-criteria-id="<?= (int)$cr['id'] ?>">
                                            Completed — Next (<?= htmlspecialchars($cr['name']) ?>)
                                        </button>
                                    <?php else: ?>
                                        <!-- Active submit (will be submitted via AJAX) -->
                                        <button type="submit" class="submit-btn submit-save-btn" data-criteria-id="<?= (int)$cr['id'] ?>">
                                            Submit <?= htmlspecialchars($cr['name']) ?> Scores
                                        </button>
                                    <?php endif; ?>
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
    const DRAFT_PREFIX = `draftScores_${YEAR}_${JUDGE_ID}_`; // append criteria id
    const QUEUE_KEY = `scoreQueue_${YEAR}_${JUDGE_ID}`;

    function readJSON(key, fallback) {
        try { return JSON.parse(localStorage.getItem(key) || 'null') ?? fallback; } catch { return fallback; }
    }
    function writeJSON(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch {}
    }
    function enqueue(item) {
        const q = readJSON(QUEUE_KEY, []);
        q.push(item);
        writeJSON(QUEUE_KEY, q);
    }

    // Accepts either application/json or form format on server; use JSON here.
    async function sendToServer(candidate_id, criteria_id, score) {
        try {
            const body = { candidate_id: candidate_id, criteria_id: criteria_id, score: String(score) };
            const res = await fetch('autosave_score.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                credentials: 'same-origin'
            });
            const data = await res.json().catch(()=>({ok:false}));
            if (res.ok && data && data.ok) return { ok: true };
            return { ok: false, data };
        } catch (err) {
            return { ok: false, error: err };
        }
    }

    // Flush queue sequentially (keeps order). Successful items removed.
    async function flushQueue() {
        const q = readJSON(QUEUE_KEY, []);
        if (!Array.isArray(q) || q.length === 0) return;
        const kept = [];
        for (const item of q) {
            const r = await sendToServer(item.candidate_id, item.criteria_id, item.score);
            if (!r.ok) {
                kept.push(item);
            } else {
                // on success remove local draft for that candidate/criteria if present
                try {
                    const k = DRAFT_PREFIX + item.criteria_id;
                    const draft = readJSON(k, {});
                    if (draft && draft[item.candidate_id] !== undefined) {
                        delete draft[item.candidate_id];
                        writeJSON(k, draft);
                    }
                } catch {}
            }
        }
        writeJSON(QUEUE_KEY, kept);
        if (kept.length === 0) {
            console.debug('Autosave queue flushed');
        } else {
            console.debug('Autosave queue kept', kept);
        }
    }

    window.addEventListener('online', () => { flushQueue().catch(()=>{}); });

    // Simple debounce
    function debounce(fn, wait) {
        let t;
        return function(...a) { clearTimeout(t); t = setTimeout(() => fn.apply(this, a), wait); };
    }

    // Only allow final numeric values like "1" or "1.2" (no trailing dot)
    function isFinalNumber(s) {
        return typeof s === 'string' && /^\d+(\.\d+)?$/.test(s);
    }

    // Attach handlers
    const forms = document.querySelectorAll('.criteria-form');
    forms.forEach(form => {
        const inputs = Array.from(form.querySelectorAll('.score-input:not([disabled])'));

        // Preload draft (if any)
        const criteriaId = form.dataset.criteriaId || form.getAttribute('data-criteria-id') || form.querySelector('input[name="criteria_id"]')?.value;
        if (criteriaId) {
            const draft = readJSON(DRAFT_PREFIX + criteriaId, {});
            if (draft && typeof draft === 'object') {
                Object.entries(draft).forEach(([cid, val]) => {
                    const inp = form.querySelector(`.score-input[data-candidate-id="${cid}"]`);
                    if (inp && !inp.disabled && (inp.value === '' || inp.value === null)) inp.value = val;
                });
            }
        }

        // per-input debounce sender
        const sendCache = {}; // key -> debounce function
        inputs.forEach(inp => {
            const cid = inp.getAttribute('data-candidate-id');
            const crid = inp.getAttribute('data-criteria-id');
            const key = `${crid}_${cid}`;

            // local draft write
            function saveDraft(val) {
                try {
                    const k = DRAFT_PREFIX + crid;
                    const d = readJSON(k, {});
                    d[cid] = val;
                    writeJSON(k, d);
                } catch {}
            }

            // network send wrapper (debounced)
            const trySend = debounce(async function(value) {
                // Only send final numeric values to server
                if (value === '' || !isFinalNumber(value)) {
                    saveDraft(value);
                    return;
                }
                saveDraft(value);

                // Attempt network send
                const r = await sendToServer(cid, crid, value);
                if (r.ok) {
                    // success -> remove draft for this candidate
                    try {
                        const k = DRAFT_PREFIX + crid;
                        const d = readJSON(k, {});
                        if (d && d[cid] !== undefined) {
                            delete d[cid];
                            writeJSON(k, d);
                        }
                    } catch {}
                    Toast.fire({ icon: 'success', title: 'Saved' });
                    // flush queue in case previous items exist
                    flushQueue().catch(()=>{});
                } else {
                    // network/server error -> enqueue for later retry
                    enqueue({ candidate_id: cid, criteria_id: crid, score: value, ts: Date.now() });
                    Toast.fire({ icon: 'info', title: 'Saved locally (will retry)' });
                }
            }, 700);

            sendCache[key] = trySend;

            // input sanitization and live validation
            inp.addEventListener('input', function() {
                // strip invalid chars (allow digits and dot)
                const raw = this.value;
                const clean = raw.replace(/[^0-9.]/g, '');
                if (clean !== raw) this.value = clean;
                // cap to max if numeric
                const v = parseFloat(this.value);
                const max = parseFloat(this.getAttribute('max')) || 120;
                if (!isNaN(v) && v > max) {
                    this.value = max.toString();
                    Toast.fire({ icon:'error', title: `Max is ${max}` });
                }
                // schedule autosave
                trySend(this.value);
            });

            // ensure save on blur/change (immediate)
            inp.addEventListener('change', function() {
                const val = this.value;
                saveDraft(val);
                // immediate attempt
                (async () => {
                    if (val === '' || !isFinalNumber(val)) {
                        Toast.fire({ icon:'warning', title:'Enter a valid number' });
                        return;
                    }
                    const r = await sendToServer(cid, crid, val);
                    if (r.ok) {
                        // Toast.fire({ icon: 'success', title: 'Saved' });
                        // remove draft entry
                        try {
                            const k = DRAFT_PREFIX + crid;
                            const d = readJSON(k, {});
                            if (d && d[cid] !== undefined) {
                                delete d[cid];
                                writeJSON(k, d);
                            }
                        } catch {}
                    } else {
                        enqueue({ candidate_id: cid, criteria_id: crid, score: val, ts: Date.now() });
                        Toast.fire({ icon:'info', title:'Saved locally (will retry)' });
                    }
                })();
            });

            // friendly Enter to move
            inp.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const all = inputs;
                    const idx = all.indexOf(this);
                    if (idx >= 0 && idx < all.length - 1) {
                        all[idx+1].focus();
                        all[idx+1].select();
                    }
                }
            });
        });

        // on form submit, validate all visible inputs
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const visibleInputs = Array.from(form.querySelectorAll('.score-input:not([disabled])')).filter(i => i.offsetParent !== null);
            let invalid = null;
            visibleInputs.forEach(inp => {
                const v = inp.value;
                const min = Number(inp.min) || 1;
                const max = Number(inp.max) || 120;
                if (!v || isNaN(Number(v)) || Number(v) < min || Number(v) > max) invalid = invalid || inp;
            });
            if (invalid) {
                invalid.focus();
                Toast.fire({ icon:'error', title: 'Please fix invalid scores' });
                return false;
            }

            // Try to flush local queue first (best-effort)
            await flushQueue().catch(()=>{});

            // Build FormData (submit_scores.php expects POST)
            const fd = new FormData(form);

            try {
                const res = await fetch(form.action || 'submit_scores.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin'
                });
                // Expect JSON { ok: true } from submit_scores.php — tolerant if it's plain 200
                const data = await res.json().catch(()=>null);
                if (res.ok && (data === null || data.ok)) {
                    // mark inputs disabled (finalized)
                    visibleInputs.forEach(i => i.disabled = true);
                    // update button to Completed — Next
                    const btn = form.querySelector('.submit-btn');
                    if (btn) {
                        btn.textContent = 'Completed — Next';
                        btn.classList.add('submit-next-btn');
                        // ensure it's type=button so it doesn't submit again
                        try { btn.type = 'button'; } catch {}
                    }
                    // mark tab button as completed (add badge if not present)
                    const criteriaId = form.querySelector('input[name="criteria_id"]')?.value;
                    if (criteriaId) {
                        const tabBtn = document.querySelector(`[data-bs-target="#criteria-${criteriaId}"]`);
                        if (tabBtn && !tabBtn.querySelector('.badge')) {
                            const span = document.createElement('span');
                            span.className = 'badge bg-success';
                            span.textContent = '✓';
                            tabBtn.appendChild(span);
                        }
                        // navigate to next tab
                        const tabs = Array.from(document.querySelectorAll('.tabs .tab'));
                        const idx = tabs.findIndex(t => t.getAttribute('data-bs-target') === `#criteria-${criteriaId}`);
                        // find next visible tab after current; fallback to next index
                        let next = null;
                        for (let i = idx + 1; i < tabs.length; i++) {
                            if (tabs[i].offsetParent !== null) { next = tabs[i]; break; }
                        }
                        if (!next) {
                            // wrap to first visible tab
                            next = tabs.find(t => t.offsetParent !== null && t !== tabs[idx]);
                        }
                        if (next) next.click();
                    }
                    Toast.fire({ icon:'success', title: 'Submitted' });
                    return true;
                } else {
                    Toast.fire({ icon:'error', title: 'Submit failed' });
                }
            } catch (err) {
                Toast.fire({ icon:'error', title: 'Network error — try again' });
            }

            // On failure, still attempt to enqueue and let autosave handle retries
            const criteriaId = form.querySelector('input[name="criteria_id"]')?.value;
            visibleInputs.forEach(inp => {
                enqueue({ candidate_id: inp.getAttribute('data-candidate-id'), criteria_id: criteriaId, score: inp.value, ts: Date.now() });
            });
            Toast.fire({ icon:'info', title: 'Saved locally (will retry)' });
        });
    });

    // Restore drafts globally (for inputs that weren't filled)
    document.querySelectorAll('.tab-pane').forEach(pane => {
        const criteriaId = pane.id.replace('criteria-','');
        const draft = readJSON(DRAFT_PREFIX + criteriaId, {});
        if (draft && typeof draft === 'object') {
            Object.entries(draft).forEach(([cid, val]) => {
                const inp = pane.querySelector(`.score-input[data-candidate-id="${cid}"]`);
                if (inp && !inp.disabled && (inp.value === '' || inp.value === null)) inp.value = val;
            });
        }
    });

    // Try to flush queue before unload (best-effort)
    window.addEventListener('beforeunload', function() {
        // flushQueue returns a promise; synchronous flush isn't possible but call it
        flushQueue();
    });

    // allow "Completed — Next" buttons to jump to the next category
    document.addEventListener('click', function(e){
        const btn = e.target.closest('.submit-next-btn');
        if (!btn) return;
        const criteriaId = btn.dataset.criteriaId || btn.getAttribute('data-criteria-id');
        if (!criteriaId) return;
        const tabs = Array.from(document.querySelectorAll('.tabs .tab'));
        const idx = tabs.findIndex(t => t.getAttribute('data-bs-target') === `#criteria-${criteriaId}`);
        let next = null;
        for (let i = idx + 1; i < tabs.length; i++) {
            if (tabs[i].offsetParent !== null) { next = tabs[i]; break; }
        }
        if (!next) next = tabs.find(t => t.offsetParent !== null && t !== tabs[idx]);
        if (next) next.click();
    });
});
</script>
<script>
/* Simple tab switching if Bootstrap JS isn't loaded */
document.addEventListener('DOMContentLoaded', function(){
    const tabButtons = Array.from(document.querySelectorAll('[data-bs-toggle="tab"]'));
    if (!tabButtons.length) return;

    function deactivateAll() {
        // deactivate buttons
        tabButtons.forEach(b => {
            b.classList.remove('active');
            b.setAttribute('aria-selected', 'false');
        });
        // hide panes
        document.querySelectorAll('.tab-pane').forEach(p => {
            p.classList.remove('show','active');
            p.setAttribute('aria-hidden', 'true');
        });
    }

    function activateButton(btn) {
        // deactivate siblings in same tabs container
        const container = btn.closest('.tabs') || document;
        container.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');
    }

    function showPane(selector) {
        if (!selector) return;
        const pane = document.querySelector(selector);
        if (!pane) return;
        // hide others then show target
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('show','active'));
        pane.classList.add('show','active');
        pane.setAttribute('aria-hidden', 'false');
        // // scroll into view a little if needed
        // pane.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // wire clicks
    tabButtons.forEach(btn => {
        btn.addEventListener('click', function(e){
            e.preventDefault();
            activateButton(btn);
            const target = btn.getAttribute('data-bs-target') || btn.getAttribute('href');
            showPane(target);
            // update URL hash without jumping
            try {
                const hash = (target && target.startsWith('#')) ? target : null;
                if (hash) history.replaceState(null, '', hash);
            } catch (err) {}
        });
    });

    // Support hash on load: show tab matching location.hash if present
    const initialHash = window.location.hash;
    if (initialHash) {
        const targetBtn = tabButtons.find(b => (b.getAttribute('data-bs-target') === initialHash || b.getAttribute('href') === initialHash));
        if (targetBtn) {
            activateButton(targetBtn);
            showPane(initialHash);
        }
    } else {
        // ensure first active tab/pane are consistent
        const firstBtn = tabButtons.find(b => b.classList.contains('active')) || tabButtons[0];
        if (firstBtn) {
            activateButton(firstBtn);
            const target = firstBtn.getAttribute('data-bs-target') || firstBtn.getAttribute('href');
            showPane(target);
        }
    }

    // Optional keyboard navigation (left/right)
    document.addEventListener('keydown', function(e){
        if (!['ArrowLeft','ArrowRight'].includes(e.key)) return;
        const visibleTabs = tabButtons.filter(b => b.offsetParent !== null);
        if (!visibleTabs.length) return;
        const activeIndex = visibleTabs.findIndex(b => b.classList.contains('active'));
        if (activeIndex === -1) return;
        let next = activeIndex;
        if (e.key === 'ArrowRight') next = (activeIndex + 1) % visibleTabs.length;
        if (e.key === 'ArrowLeft') next = (activeIndex - 1 + visibleTabs.length) % visibleTabs.length;
        const btn = visibleTabs[next];
        if (btn) { btn.click(); btn.focus(); }
    });
});
</script>
</body>
</html>

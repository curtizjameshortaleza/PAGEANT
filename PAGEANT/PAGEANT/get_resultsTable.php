<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// Accept year from querystring for standalone rendering
$sel_year = $_GET['year'] ?? '';
if (!$sel_year) { $sel_year = ''; }

// Load criteria and candidates for the year when invoked directly
if (!isset($criteria) || !is_array($criteria)) {
    $criteria = [];
    if ($sel_year) {
        $res = $mysqli->query("SELECT * FROM tbl_criteria WHERE year='".$mysqli->real_escape_string($sel_year)."' ORDER BY id ASC");
        while ($row = $res->fetch_assoc()) $criteria[] = $row;
        $res->free();
    }
}
if (!isset($candidates) || !is_array($candidates)) {
    $candidates = [];
    if ($sel_year) {
        $res = $mysqli->query("SELECT * FROM tbl_candidates WHERE year='".$mysqli->real_escape_string($sel_year)."' ORDER BY number ASC");
        while ($row = $res->fetch_assoc()) $candidates[] = $row;
        $res->free();
    }
}
if (!isset($totals_by_candidate)) {
    $totals_by_candidate = [];
    if ($sel_year) {
        $stmt = $mysqli->prepare("SELECT c.id, c.number, c.gender, cr.name, SUM(CAST(s.score AS DECIMAL(10,4))) AS total_score
            FROM tbl_scores s
            JOIN tbl_candidates c ON c.id = s.candidate_id
            JOIN tbl_criteria cr ON cr.id = s.criteria_id
            WHERE s.status = 1 AND s.year = ?
            GROUP BY c.id, cr.id");
        $stmt->bind_param('s', $sel_year);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $cid = (int)$row['id'];
            if (!isset($totals_by_candidate[$cid])) {
                $totals_by_candidate[$cid] = [ 'number' => $row['number'], 'gender' => $row['gender'], 'criteria' => [] ];
            }
            $totals_by_candidate[$cid]['criteria'][$row['name']] = (float)$row['total_score'];
        }
        $stmt->close();
    }
}
?>
<style>
/* Sticky column styles for results tables */
.results-table th {
	position: sticky;
	top: 0;
	z-index: 10;
}

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

.results-table thead th:first-child {
	z-index: 15;
}

.results-table thead th:last-child {
	z-index: 15;
}

.results-table td {
	transition: none;
}

		.score-cell, .overall-score {
			transition: none;
		}
		
		/* Judge Status Update Animation */
		#judgeStatusCard {
			transition: opacity 0.3s ease;
		}
		
		#judgeStatusCard.updating {
			opacity: 0.7;
		}
		
		/* Badge update animation */
		.badge {
			transition: all 0.3s ease;
		}
		
		.badge.updating {
			transform: scale(1.1);
			box-shadow: 0 0 10px rgba(0,0,0,0.3);
		}
	</style>

<?php if ($sel_year && !empty($criteria) && !empty($candidates)): ?>
		<?php
		$male_candidates = array_filter($candidates, function($c) { return $c['gender'] === 'Male'; });
		$female_candidates = array_filter($candidates, function($c) { return $c['gender'] === 'Female'; });
		
		// Get judges and their submission status for this year
		$judges = [];
		$judge_submission_status = [];
		$res = $mysqli->query("SELECT * FROM tbl_judges WHERE year='".$mysqli->real_escape_string($sel_year)."' ORDER BY id ASC");
		while ($row = $res->fetch_assoc()) {
			$judges[] = $row;
			$judge_id = (int)$row['id'];
			
			// Check submission status for this judge
			$total_criteria = count($criteria);
			$submitted_criteria = 0;
			$has_incomplete = false;
			
			foreach ($criteria as $cr) {
				$crid = (int)$cr['id'];
				
				// Check if this judge has submitted this criteria (status = 1)
				$check_submitted = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=1 LIMIT 1");
				$submitted_count = 0;
				if ($c = $check_submitted->fetch_assoc()) { 
					$submitted_count = (int)$c['count']; 
				}
				$check_submitted->free();
				
				// Check if this judge has any incomplete scores for this criteria (status = 0)
				$check_incomplete = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE judge_id={$judge_id} AND criteria_id={$crid} AND status=0 LIMIT 1");
				$incomplete_count = 0;
				if ($c = $check_incomplete->fetch_assoc()) { 
					$incomplete_count = (int)$c['count']; 
				}
				$check_incomplete->free();
				
				if ($submitted_count > 0) {
					$submitted_criteria++;
				}
				
				if ($incomplete_count > 0) {
					$has_incomplete = true;
				}
			}
			
			$judge_submission_status[$judge_id] = [
				'submitted' => $submitted_criteria,
				'total' => $total_criteria,
				'percentage' => $total_criteria > 0 ? round(($submitted_criteria / $total_criteria) * 100) : 0,
				'is_complete' => $submitted_criteria === $total_criteria && $total_criteria > 0 && !$has_incomplete,
				'has_incomplete' => $has_incomplete
			];
		}
		$res->free();
		
		// Calculate top 3 per criteria for color coding - SEPARATE for male and female
		$male_criteria_top3 = [];
		$female_criteria_top3 = [];
		
		foreach ($criteria as $cr) {
			$name = $cr['name'];
			
			// Male scores for this criteria
			$male_scores = [];
			foreach ($male_candidates as $c) {
				$cid = $c['id'];
				$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
				if ($val > 0) {
					$male_scores[] = ['candidate_id' => $cid, 'score' => (float)$val];
				}
			}
			usort($male_scores, function($a, $b) { return $b['score'] <=> $a['score']; });
			
			// Handle ties for male candidates - Show ALL tied candidates for each position
			$male_criteria_top3[$name] = [];
			if (!empty($male_scores)) {
				// Find all unique scores and sort them
				$unique_scores = array_unique(array_column($male_scores, 'score'));
				rsort($unique_scores);
				
				// 1st place (Winner) - ALL candidates with highest score
				$top_score = $unique_scores[0];
				foreach ($male_scores as $score) {
					if ($score['score'] == $top_score) {
						$male_criteria_top3[$name][] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 0];
					}
				}
				
				// 2nd place (First runner-up) - ALL candidates with second highest score
				if (isset($unique_scores[1])) {
					$second_score = $unique_scores[1];
					foreach ($male_scores as $score) {
						if ($score['score'] == $second_score) {
							$male_criteria_top3[$name][] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 1];
						}
					}
				}
				
				// 3rd place (Second runner-up) - ALL candidates with third highest score
				if (isset($unique_scores[2])) {
					$third_score = $unique_scores[2];
					foreach ($male_scores as $score) {
						if ($score['score'] == $third_score) {
							$male_criteria_top3[$name][] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 2];
						}
					}
				}
			}
			
			// Female scores for this criteria
			$female_scores = [];
			foreach ($female_candidates as $c) {
				$cid = $c['id'];
				$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
				if ($val > 0) {
					$female_scores[] = ['candidate_id' => $cid, 'score' => (float)$val];
				}
			}
			usort($female_scores, function($a, $b) { return $b['score'] <=> $a['score']; });
			
			// Handle ties for female candidates - Show ALL tied candidates for each position
			$female_criteria_top3[$name] = [];
			if (!empty($female_scores)) {
				// Find all unique scores and sort them
				$unique_scores = array_unique(array_column($female_scores, 'score'));
				rsort($unique_scores);
				
				// 1st place (Winner) - ALL candidates with highest score
				$top_score = $unique_scores[0];
				foreach ($female_scores as $score) {
					if ($score['score'] == $top_score) {
						$female_criteria_top3[$name][] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 0];
					}
				}
				
				// 2nd place (First runner-up) - ALL candidates with second highest score
				if (isset($unique_scores[1])) {
					$second_score = $unique_scores[1];
					foreach ($female_scores as $score) {
						if ($score['score'] == $second_score) {
							$female_criteria_top3[$name][] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 1];
						}
					}
				}
				
				// 3rd place (Second runner-up) - ALL candidates with third highest score
				if (isset($unique_scores[2])) {
					$third_score = $unique_scores[2];
					foreach ($female_scores as $score) {
						if ($score['score'] == $third_score) {
							$female_criteria_top3[$name][] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 2];
						}
					}
				}
			}
		}
		
		// Calculate top 3 overall scores for male and female
		$male_overall_top3 = [];
		$female_overall_top3 = [];
		
		// Male overall scores
		$male_overall_scores = [];
		foreach ($male_candidates as $c) {
			$cid = $c['id'];
			$overall_score = 0;
			foreach ($criteria as $cr) {
				$name = $cr['name'];
				$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
				$overall_score += (float)$val;
			}
			if ($overall_score > 0) {
				$male_overall_scores[] = ['candidate_id' => $cid, 'score' => (float)$overall_score];
			}
		}
		usort($male_overall_scores, function($a, $b) { return $b['score'] <=> $a['score']; });
		
		// Handle ties for male overall scores - Show ALL tied candidates for each position
		$male_overall_top3 = [];
		if (!empty($male_overall_scores)) {
			// Find all unique scores and sort them
			$unique_scores = array_unique(array_column($male_overall_scores, 'score'));
			rsort($unique_scores);
			
			// 1st place (Winner) - ALL candidates with highest score
			$top_score = $unique_scores[0];
			foreach ($male_overall_scores as $score) {
				if ($score['score'] == $top_score) {
					$male_overall_top3[] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 0];
				}
			}
			
			// 2nd place (First runner-up) - ALL candidates with second highest score
			if (isset($unique_scores[1])) {
				$second_score = $unique_scores[1];
				foreach ($male_overall_scores as $score) {
					if ($score['score'] == $second_score) {
						$male_overall_top3[] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 1];
					}
				}
			}
			
			// 3rd place (Second runner-up) - ALL candidates with third highest score
			if (isset($unique_scores[2])) {
				$third_score = $unique_scores[2];
				foreach ($male_overall_scores as $score) {
					if ($score['score'] == $third_score) {
						$male_overall_top3[] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 2];
					}
				}
			}
		}
		
		// Female overall scores
		$female_overall_scores = [];
		foreach ($female_candidates as $c) {
			$cid = $c['id'];
			$overall_score = 0;
			foreach ($criteria as $cr) {
				$name = $cr['name'];
				$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
				$overall_score += (float)$val;
			}
			if ($overall_score > 0) {
				$female_overall_scores[] = ['candidate_id' => $cid, 'score' => (float)$overall_score];
			}
		}
		usort($female_overall_scores, function($a, $b) { return $b['score'] <=> $a['score']; });
		
		// Handle ties for female overall scores - Show ALL tied candidates for each position
		$female_overall_top3 = [];
		if (!empty($female_overall_scores)) {
			// Find all unique scores and sort them
			$unique_scores = array_unique(array_column($female_overall_scores, 'score'));
			rsort($unique_scores);
			
			// 1st place (Winner) - ALL candidates with highest score
			$top_score = $unique_scores[0];
			foreach ($female_overall_scores as $score) {
				if ($score['score'] == $top_score) {
					$female_overall_top3[] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 0];
				}
			}
			
			// 2nd place (First runner-up) - ALL candidates with second highest score
			if (isset($unique_scores[1])) {
				$second_score = $unique_scores[1];
				foreach ($female_overall_scores as $score) {
					if ($score['score'] == $second_score) {
						$female_overall_top3[] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 1];
					}
				}
			}
			
			// 3rd place (Second runner-up) - ALL candidates with third highest score
			if (isset($unique_scores[2])) {
				$third_score = $unique_scores[2];
				foreach ($female_overall_scores as $score) {
					if ($score['score'] == $third_score) {
						$female_overall_top3[] = ['candidate_id' => $score['candidate_id'], 'score' => $score['score'], 'position' => 2];
					}
				}
			}
		}
		?>


		<!-- Judge Submission Status -->
		<?php if (!empty($judges)): ?>
		<div class="card shadow-sm mb-3" id="judgeStatusCard">
			<div class="card-header text-white" style="background: linear-gradient(45deg, #009688 0%, #4dd0e1 100%);">
				<h6 class="mb-0"><i class="fas fa-gavel me-2"></i> Judge Submission Status</h6>
			</div>
			<div class="card-body">
				<div class="row">
					<?php foreach ($judges as $judge): ?>
						<?php 
						$judge_id = (int)$judge['id'];
						$status = $judge_submission_status[$judge_id];
						
						// Determine status based on percentage and incomplete scores
						if ($status['has_incomplete']) {
							// Any status = 0 → Show 0%
							$status_class = 'warning';
							$status_text = '0%';
						} elseif ($status['percentage'] == 100) {
							// Exactly 100% → Green badge
							$status_class = 'success';
							$status_text = '100%';
						} elseif ($status['percentage'] > 0) {
							// Less than 100% but has some scores → Yellow badge
							$status_class = 'warning';
							$status_text = $status['percentage'] . '%';
						} else {
							// No scores yet
							$status_class = 'secondary';
							$status_text = '0%';
						}
						?>
						<div class="col-md-3 col-sm-6 mb-2">
							<div class="d-flex align-items-center p-2 border rounded">
								<div class="flex-grow-1">
									<div class="fw-bold text-dark"><?= h($judge['name']) ?></div>
									<div class="small text-muted">Judge #<?= h($judge['judge_number']) ?></div>
								</div>
								<div class="ms-2">
									<span class="badge bg-<?= $status_class ?> fs-6"><?= $status_text ?></span>
									<div class="small text-muted text-center"><?= $status['submitted'] ?>/<?= $status['total'] ?></div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- Male Candidates Results -->
		<div class="card shadow-sm results-card">
			<div class="card-header text-white" style="background: linear-gradient(45deg, #2196f3 0%, #21cbf3 100%);">
				<h5 class="mb-0"><i class="fas fa-male me-2"></i> Male Candidates Results</h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover mb-0 results-table">
					<thead class="table-light">
						<tr>
							<th class="text-center" style="width: 80px;">#</th>

							<?php 
							$totalPercentage = 0;
							foreach ($criteria as $cr): 
								$totalPercentage += $cr['percentage']; // accumulate
							?>
								<th class="text-center">
									<?= h($cr['name']) ?><br>
									<small class="text-muted">(<?= h($cr['percentage']) ?>%)</small>
								</th>
							<?php endforeach; ?>

							<th class="text-center bg-light">
								<strong>Overall</strong><br>
								<small class="text-muted">(<?= $totalPercentage ?>%)</small>
							</th>
						</tr>
					</thead>

						<tbody id="maleCandidatesTable">
							<?php foreach ($male_candidates as $c):
								$cid = $c['id'];
								$overall_score = 0;
								foreach ($criteria as $cr) {
									$name = $cr['name'];
									$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
									$overall_score += (float)$val;
								}
							?>
							<tr>
								<td class="text-center candidate-number"><?= h($c['number']) ?></td>
								<?php foreach ($criteria as $cr):
									$name = $cr['name'];
									$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
									
									// Check if this candidate is in top 3 for this criteria (MALE ONLY)
									$color_square = '';
									$top3_for_criteria = $male_criteria_top3[$name] ?? [];
									foreach ($top3_for_criteria as $top_item) {
										if ($top_item['candidate_id'] === $cid) {
											if ($top_item['position'] === 0) $color_square = '<span class="color-square green"></span>'; // 1st place
											elseif ($top_item['position'] === 1) $color_square = '<span class="color-square yellow"></span>'; // 2nd place
											elseif ($top_item['position'] === 2) $color_square = '<span class="color-square red"></span>'; // 3rd place
											break;
										}
									}
								?>
									<td class="text-center score-cell"><?= $color_square ?><?= rtrim(rtrim(number_format((float)$val, 2), '0'), '.') ?></td>
								<?php endforeach; ?>
								<?php
									// Check if this candidate is in top 3 overall (MALE ONLY)
									$overall_color_square = '';
									foreach ($male_overall_top3 as $top_item) {
										if ($top_item['candidate_id'] === $cid) {
											if ($top_item['position'] === 0) $overall_color_square = '<span class="color-square green"></span>'; // 1st place
											elseif ($top_item['position'] === 1) $overall_color_square = '<span class="color-square yellow"></span>'; // 2nd place
											elseif ($top_item['position'] === 2) $overall_color_square = '<span class="color-square red"></span>'; // 3rd place
											break;
										}
									}
									
									// Calculate percentage (assuming max possible score is 100)
									$overall_percentage = $overall_score > 0 ? ($overall_score / 100) * 100 : 0;
								?>
								<td class="text-center overall-score"><?= $overall_color_square ?><?= rtrim(rtrim(number_format($overall_score, 2), '0'), '.') ?>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<!-- Female Candidates Results -->
		<div class="card shadow-sm results-card">
			<div class="card-header text-white" style="background: linear-gradient(45deg, #ff6b9d, #c44569);">
				<h5 class="mb-0"><i class="fas fa-female me-2"></i> Female Candidates Results</h5>
			</div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table table-hover mb-0 results-table">
					<thead class="table-light">
						<tr>
							<th class="text-center" style="width: 80px;">#</th>

							<?php 
							$totalPercentage = 0;
							foreach ($criteria as $cr): 
								$totalPercentage += $cr['percentage']; // accumulate
							?>
								<th class="text-center">
									<?= h($cr['name']) ?><br>
									<small class="text-muted">(<?= h($cr['percentage']) ?>%)</small>
								</th>
							<?php endforeach; ?>

							<th class="text-center bg-light">
								<strong>Overall</strong><br>
								<small class="text-muted">(<?= $totalPercentage ?>%)</small>
							</th>
						</tr>
					</thead>

						<tbody id="femaleCandidatesTable">
							<?php foreach ($female_candidates as $c):
								$cid = $c['id'];
								$overall_score = 0;
								foreach ($criteria as $cr) {
									$name = $cr['name'];
									$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
									$overall_score += (float)$val;
								}
							?>
							<tr>
								<td class="text-center fw-bold"><?= h($c['number']) ?></td>
								<?php foreach ($criteria as $cr):
									$name = $cr['name'];
									$val = $totals_by_candidate[$cid]['criteria'][$name] ?? 0;
									
									// Check if this candidate is in top 3 for this criteria (FEMALE ONLY)
									$color_square = '';
									$top3_for_criteria = $female_criteria_top3[$name] ?? [];
									foreach ($top3_for_criteria as $top_item) {
										if ($top_item['candidate_id'] === $cid) {
											if ($top_item['position'] === 0) $color_square = '<span class="color-square green"></span>'; // 1st place
											elseif ($top_item['position'] === 1) $color_square = '<span class="color-square yellow"></span>'; // 2nd place
											elseif ($top_item['position'] === 2) $color_square = '<span class="color-square red"></span>'; // 3rd place
											break;
										}
									}
								?>
									<td class="text-center score-cell"><?= $color_square ?><?= rtrim(rtrim(number_format((float)$val, 2), '0'), '.') ?></td>
								<?php endforeach; ?>
								<?php
									// Check if this candidate is in top 3 overall (FEMALE ONLY)
									$overall_color_square = '';
									foreach ($female_overall_top3 as $top_item) {
										if ($top_item['candidate_id'] === $cid) {
											if ($top_item['position'] === 0) $overall_color_square = '<span class="color-square green"></span>'; // 1st place
											elseif ($top_item['position'] === 1) $overall_color_square = '<span class="color-square yellow"></span>'; // 2nd place
											elseif ($top_item['position'] === 2) $overall_color_square = '<span class="color-square red"></span>'; // 3rd place
											break;
										}
									}
									
									// Calculate percentage (assuming max possible score is 100)
									$overall_percentage = $overall_score > 0 ? ($overall_score / 100) * 100 : 0;
								?>
								<td class="text-center overall-score"><?= $overall_color_square ?><?= rtrim(rtrim(number_format($overall_score, 2), '0'), '.') ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	<?php elseif ($sel_year): ?>
		<div class="alert alert-info">
			<h5><i class="fas fa-info-circle"></i> Setup Required</h5>
			<p class="mb-0">
				<?php if (empty($criteria)): ?>
					• Please add criteria first
				<?php endif; ?>
				<?php if (empty($candidates)): ?>
					<?= empty($criteria) ? '<br>' : '' ?>• Please generate candidates
				<?php endif; ?>
				<br><small class="text-muted">Results will appear here once both criteria and candidates are set.</small>
			</p>
		</div>
	<?php endif; ?>
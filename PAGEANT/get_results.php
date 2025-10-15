<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

// Set headers for long polling
// Ensure clean JSON output: suppress PHP notices/warnings from leaking into response
@ini_set('display_errors', '0');
@error_reporting(0);
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Get year from request
$year = $_GET['year'] ?? '';
$judgeFilter = isset($_GET['judge_id']) && $_GET['judge_id'] !== '' ? (int)$_GET['judge_id'] : null;

if (!$year) {
    http_response_code(400);
    echo json_encode(['error' => 'Year parameter required']);
    exit;
}

// Fetch data for selected year
$criteria = [];
$res = $mysqli->query("SELECT * FROM tbl_criteria WHERE year='".$mysqli->real_escape_string($year)."' ORDER BY id ASC");
while ($row = $res->fetch_assoc()) $criteria[] = $row;
$res->free();

$candidates = [];
$res = $mysqli->query("SELECT * FROM tbl_candidates WHERE year='".$mysqli->real_escape_string($year)."' ORDER BY number ASC");
while ($row = $res->fetch_assoc()) $candidates[] = $row;
$res->free();

// Results computations
function fetch_totals($mysqli, $year, $judgeFilter) {
    $base = "SELECT c.id as candidate_id, c.number, c.gender, cr.id as criteria_id, cr.name as criteria_name,
        SUM(CAST(s.score AS DECIMAL(10,4))) as total_score
        FROM tbl_scores s
        JOIN tbl_candidates c ON c.id = s.candidate_id
        JOIN tbl_criteria cr ON cr.id = s.criteria_id
        WHERE s.status = 1 AND s.year = ?";
    if ($judgeFilter) { $base .= " AND s.judge_id = ?"; }
    $base .= " GROUP BY c.id, cr.id
        ORDER BY c.number, cr.id";
    $stmt = $judgeFilter
        ? $mysqli->prepare($base)
        : $mysqli->prepare(str_replace(' AND s.judge_id = ?','',$base));
    if ($judgeFilter) { $stmt->bind_param('si', $year, $judgeFilter); }
    else { $stmt->bind_param('s', $year); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}

function fetch_overall($mysqli, $year, $judgeFilter) {
    $base = "SELECT c.id as candidate_id, c.number, c.gender, SUM(CAST(s.score AS DECIMAL(10,4))) as overall_total
        FROM tbl_scores s
        JOIN tbl_candidates c ON c.id = s.candidate_id
        WHERE s.status = 1 AND s.year = ?";
    if ($judgeFilter) { $base .= " AND s.judge_id = ?"; }
    $base .= " GROUP BY c.id
        ORDER BY overall_total DESC";
    $stmt = $judgeFilter
        ? $mysqli->prepare($base)
        : $mysqli->prepare(str_replace(' AND s.judge_id = ?','',$base));
    if ($judgeFilter) { $stmt->bind_param('si', $year, $judgeFilter); }
    else { $stmt->bind_param('s', $year); }
    $stmt->execute();
    $res = $stmt->get_result();
    $data = [];
    while ($row = $res->fetch_assoc()) { $data[] = $row; }
    $stmt->close();
    return $data;
}

$totals = fetch_totals($mysqli, $year, $judgeFilter);
$overall = fetch_overall($mysqli, $year, $judgeFilter);

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

$male_candidates = array_filter($candidates, function($c) { return $c['gender'] === 'Male'; });
$female_candidates = array_filter($candidates, function($c) { return $c['gender'] === 'Female'; });

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

// Return JSON response
echo json_encode([
    'criteria' => $criteria,
    'male_candidates' => array_values($male_candidates),
    'female_candidates' => array_values($female_candidates),
    'totals_by_candidate' => $totals_by_candidate,
    'male_criteria_top3' => $male_criteria_top3,
    'female_criteria_top3' => $female_criteria_top3,
    'male_overall_top3' => $male_overall_top3,
    'female_overall_top3' => $female_overall_top3,
    'timestamp' => time()
]);
?>

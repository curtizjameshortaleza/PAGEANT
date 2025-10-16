<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();

echo "Checking and fixing status values in database...\n\n";

// Check current status distribution
$result = $mysqli->query("SELECT status, COUNT(*) as count FROM tbl_scores GROUP BY status");
echo "Current status distribution:\n";
while ($row = $result->fetch_assoc()) {
    $status_text = $row['status'] == 1 ? 'Completed' : 'Not Completed';
    echo "Status {$row['status']} ({$status_text}): {$row['count']} records\n";
}
echo "\n";

// Check if there are any status = 0 entries
$result = $mysqli->query("SELECT COUNT(*) as count FROM tbl_scores WHERE status = 0");
$count_0 = $result->fetch_assoc()['count'];

if ($count_0 > 0) {
    echo "Found {$count_0} records with status = 0 (Not Completed)\n";
    echo "These should be fixed to status = 1 (Completed) if they represent submitted scores.\n\n";
    
    // Show some examples
    $result = $mysqli->query("SELECT s.*, j.name as judge_name, c.number as candidate_number, c.gender, cr.name as criteria_name 
                              FROM tbl_scores s 
                              JOIN tbl_judges j ON j.id = s.judge_id 
                              JOIN tbl_candidates c ON c.id = s.candidate_id 
                              JOIN tbl_criteria cr ON cr.id = s.criteria_id 
                              WHERE s.status = 0 
                              LIMIT 5");
    
    echo "Sample records with status = 0:\n";
    echo "Judge | Candidate | Criteria | Score | Year\n";
    echo "------|-----------|----------|-------|-----\n";
    while ($row = $result->fetch_assoc()) {
        echo "{$row['judge_name']} | #{$row['candidate_number']} ({$row['gender']}) | {$row['criteria_name']} | {$row['score']} | {$row['year']}\n";
    }
    echo "\n";
    
    // Ask for confirmation to fix
    echo "Do you want to fix all status = 0 records to status = 1? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    fclose($handle);
    
    if (trim(strtolower($line)) === 'y') {
        $update_result = $mysqli->query("UPDATE tbl_scores SET status = 1 WHERE status = 0");
        if ($update_result) {
            $affected = $mysqli->affected_rows;
            echo "Successfully updated {$affected} records from status = 0 to status = 1\n";
        } else {
            echo "Error updating records: " . $mysqli->error . "\n";
        }
    } else {
        echo "No changes made.\n";
    }
} else {
    echo "No records found with status = 0. All scores are already marked as completed.\n";
}

echo "\nFinal status distribution:\n";
$result = $mysqli->query("SELECT status, COUNT(*) as count FROM tbl_scores GROUP BY status");
while ($row = $result->fetch_assoc()) {
    $status_text = $row['status'] == 1 ? 'Completed' : 'Not Completed';
    echo "Status {$row['status']} ({$status_text}): {$row['count']} records\n";
}

$mysqli->close();
?>

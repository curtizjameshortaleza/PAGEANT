<?php
// Database bootstrap and schema auto-creation

// Basic local MySQL config for XAMPP
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'pageant_criteria_system';

// Disable mysqli exceptions to avoid fatal on missing DB; we'll bootstrap instead
mysqli_report(MYSQLI_REPORT_OFF);

function db_connect_server_only() {
	global $DB_HOST, $DB_USER, $DB_PASS;
	$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS);
	if ($mysqli->connect_errno) {
		die('Database connection failed: ' . $mysqli->connect_error);
	}
	$mysqli->set_charset('utf8mb4');
	return $mysqli;
}

function db_connect() {
	global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
	// Ensure DB and tables exist before attempting DB-specific connection
	bootstrap_database();
	$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
	if ($mysqli->connect_errno) {
		die('Database connection failed after bootstrap: ' . $mysqli->connect_error);
	}
	$mysqli->set_charset('utf8mb4');
	return $mysqli;
}

function bootstrap_database() {
	global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
	$server = db_connect_server_only();
	$server->query("CREATE DATABASE IF NOT EXISTS `{$DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
	$server->close();

	$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
	if ($mysqli->connect_errno) {
		die('Failed connecting to freshly created DB: ' . $mysqli->connect_error);
	}
	$mysqli->set_charset('utf8mb4');

	// Create tables if not exists
	$schema = [];

	$schema[] = "CREATE TABLE IF NOT EXISTS tbl_admin (
		id INT AUTO_INCREMENT PRIMARY KEY,
		year VARCHAR(20) NOT NULL UNIQUE,
		is_locked TINYINT(1) NOT NULL DEFAULT 0,
		created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

	$schema[] = "CREATE TABLE IF NOT EXISTS tbl_criteria (
		id INT AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(100) NOT NULL,
		percentage DECIMAL(5,2) NOT NULL,
		year VARCHAR(20) NOT NULL,
		UNIQUE KEY uniq_name_year (name, year)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

	$schema[] = "CREATE TABLE IF NOT EXISTS tbl_judges (
		id INT AUTO_INCREMENT PRIMARY KEY,
		name VARCHAR(100) NOT NULL,
		judge_number VARCHAR(50) NOT NULL,
		access_code VARCHAR(64) NOT NULL,
		year VARCHAR(20) NOT NULL,
		UNIQUE KEY uniq_judge_number_year (judge_number, year),
		UNIQUE KEY uniq_name_year (name, year)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $schema[] = "CREATE TABLE IF NOT EXISTS tbl_candidates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        number INT NOT NULL,
        gender ENUM('Male','Female') NOT NULL,
        year VARCHAR(20) NOT NULL,
        UNIQUE KEY uniq_number_gender_year (number, gender, year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

	$schema[] = "CREATE TABLE IF NOT EXISTS tbl_scores (
		id INT AUTO_INCREMENT PRIMARY KEY,
		judge_id INT NOT NULL,
		candidate_id INT NOT NULL,
		criteria_id INT NOT NULL,
		score DECIMAL(10,4) NOT NULL,
		status TINYINT(1) NOT NULL DEFAULT 0,
		year VARCHAR(20) NOT NULL,
		UNIQUE KEY uniq_score (judge_id, candidate_id, criteria_id),
		KEY idx_year (year),
		FOREIGN KEY (judge_id) REFERENCES tbl_judges(id) ON DELETE CASCADE,
		FOREIGN KEY (candidate_id) REFERENCES tbl_candidates(id) ON DELETE CASCADE,
		FOREIGN KEY (criteria_id) REFERENCES tbl_criteria(id) ON DELETE CASCADE
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";


    foreach ($schema as $sql) {
        if (!$mysqli->query($sql)) {
            die('Schema creation failed: ' . $mysqli->error);
        }
    }

    // Migration: Drop tbl_lock table if it exists (no longer needed)
    @ $mysqli->query("DROP TABLE IF EXISTS tbl_lock");

    // Migration: ensure candidate numbers are unique per gender and year
    // Older versions used UNIQUE (number, year) which prevents separate male/female numbering
    // Try to drop the old index if it exists and create the correct one; ignore errors if already migrated
    @ $mysqli->query("ALTER TABLE tbl_candidates DROP INDEX uniq_number_year");
    @ $mysqli->query("ALTER TABLE tbl_candidates ADD UNIQUE KEY uniq_number_gender_year (number, gender, year)");

    // Migration: add judge_number column if it doesn't exist
    $check_column = $mysqli->query("SHOW COLUMNS FROM tbl_judges LIKE 'judge_number'");
    if (!$check_column || $check_column->num_rows == 0) {
        @ $mysqli->query("ALTER TABLE tbl_judges ADD COLUMN judge_number VARCHAR(50) NOT NULL DEFAULT '' AFTER name");
        @ $mysqli->query("ALTER TABLE tbl_judges ADD UNIQUE KEY uniq_judge_number_year (judge_number, year)");
    }


	// Migration: add status column to tbl_scores if missing
	$check_status_col = $mysqli->query("SHOW COLUMNS FROM tbl_scores LIKE 'status'");
	if (!$check_status_col || $check_status_col->num_rows == 0) {
		@ $mysqli->query("ALTER TABLE tbl_scores ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 0 AFTER score");
	}

	// Migration: ensure score is numeric DECIMAL, not VARCHAR
	$col = $mysqli->query("SHOW COLUMNS FROM tbl_scores LIKE 'score'");
	if ($col && ($c = $col->fetch_assoc())) {
		$type = strtolower($c['Type'] ?? '');
		if (strpos($type, 'decimal') === false) {
			@ $mysqli->query("ALTER TABLE tbl_scores MODIFY COLUMN score DECIMAL(10,4) NOT NULL");
		}
	}

	$mysqli->close();
}

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function get_years($mysqli) {
	$years = [];
	$res = $mysqli->query("SELECT year, is_locked FROM tbl_admin ORDER BY year DESC");
	if ($res) {
		while ($row = $res->fetch_assoc()) { $years[] = $row; }
		$res->free();
	}
	return $years;
}

?>



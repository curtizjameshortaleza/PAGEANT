<?php
require_once __DIR__ . '/db.php';
$mysqli = db_connect();
$years = get_years($mysqli);
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Pageant Criteria System</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
	<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
		<div class="container">
			<a class="navbar-brand" href="index.php">Pageant Criteria System</a>
		</div>
	</nav>

	<div class="container py-5">
		<div class="row justify-content-center">
			<div class="col-lg-8">
				<div class="card shadow-sm">
					<div class="card-body p-4">
						<h1 class="h4 mb-3">Welcome</h1>
						<p class="text-muted">Choose an action below.</p>
						<div class="row g-3">
							<div class="col-md-6">
								<a href="admin.php" class="btn btn-primary w-100 py-3">Admin Panel</a>
							</div>
							<div class="col-md-6">
								<a href="pageant.php" class="btn btn-success w-100 py-3">Judge Portal</a>
							</div>
						</div>
						<hr class="my-4">
						<h2 class="h6">Existing Event Years</h2>
						<div class="small text-muted">
							<?php if (empty($years)): ?>
								<span>No event years yet. Go to Admin Panel to add one.</span>
							<?php else: ?>
								<ul class="mb-0">
									<?php foreach ($years as $y): ?>
										<li><?= h($y['year']) ?> <?= $y['is_locked'] ? '(Locked)' : '' ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



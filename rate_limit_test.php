<?php
require_once 'users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if(!hasPerm(2)){
	die("You do not have permission to access this page.");
}
$message = '';
$messageType = '';

if ($_POST) {
	$action = $_POST['action'] ?? '';
	$testType = $_POST['test_type'] ?? '';

	switch ($testType) {
		case 'check_limit':
			$allowed = checkRateLimit($action, $user->data()->id ?? null, $_POST['email'] ?? null);
			if ($allowed) {
				$message = "✅ Action '$action' is ALLOWED";
				$messageType = 'success';
			} else {
				$message = "❌ Action '$action' is RATE LIMITED";
				$messageType = 'danger';
				
				$message .= "<br><small>" . getRateLimitErrorMessage($action) . "</small>";
			}
			break;

		case 'record_success':
			recordRateLimit($action, true, $user->data()->id ?? null, $_POST['email'] ?? null, [], [
				'test_success' => true,
				'timestamp' => time()
			]);
			$message = "✅ Recorded SUCCESSFUL attempt for '$action'";
			$messageType = 'success';
			break;

		case 'record_failure':
			recordRateLimit($action, false, $user->data()->id ?? null, $_POST['email'] ?? null, [], [
				'test_failure' => true,
				'timestamp' => time()
			]);
			$message = "❌ Recorded FAILED attempt for '$action'";
			$messageType = 'warning';
			break;

		case 'clear_failed':
			clearFailedRateLimit($action, $user->data()->id ?? null, $_POST['email'] ?? null);
			$message = "🧹 Cleared failed attempts for '$action'";
			$messageType = 'info';
			break;

		case 'validate_and_record':
			$allowed = validateRateLimit($action, $user->data()->id ?? null, $_POST['email'] ?? null);
			if ($allowed) {
				$message = "✅ Action '$action' validated and recorded";
				$messageType = 'success';
			} else {
				$message = "❌ Action '$action' blocked by rate limit and recorded";
				$messageType = 'danger';
			}
			break;

		case 'simulate_auth_success':
			handleAuthSuccess($action, $user->data()->id ?? null, $_POST['email'] ?? null, [], [
				'test_auth_success' => true,
				'user_agent' => Server::get('HTTP_USER_AGENT')
			]);
			$message = "🎉 Simulated successful authentication for '$action' - recorded success and cleared failures";
			$messageType = 'success';
			break;

		case 'simulate_auth_failure':
			handleAuthFailure($action, $user->data()->id ?? null, $_POST['email'] ?? null, [], [
				'test_auth_failure' => true,
				'user_agent' => Server::get('HTTP_USER_AGENT')
			]);
			$message = "💥 Simulated failed authentication for '$action'";
			$messageType = 'danger';
			break;
	}
}

// Get current status for all configured actions
$rateLimit = new RateLimit();
$currentUser = $user->data()->id ?? null;
$selectedAction = $_POST['action'] ?? 'login_attempt';
$selectedEmail = $_POST['email'] ?? 'test@example.com';

?>
<div class="container mt-4">
	<div class="row">
		<div class="col-12">
			<h1 class="mb-4">🚦 Rate Limit Testing Dashboard</h1>

			<?php if ($message): ?>
				<div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
					<?= $message ?>
					<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
				</div>
			<?php endif; ?>

			<div class="row">
				<div class="col-md-6">
					<div class="test-section">
						<h3>🧪 Test Actions</h3>
						<p><strong>Current User ID:</strong> <?= $currentUser ?: 'Not logged in' ?><br>
							<strong>Test Email:</strong> <?= $selectedEmail ?><br>
							<strong>Your IP:</strong> <?= Server::get('REMOTE_ADDR'); ?? 'Unknown' ?>
						</p>

						<form method="post" class="mb-3">
							<div class="row">
								<div class="col-md-6">
									<label for="action" class="form-label">Action to Test:</label>
									<select name="action" id="action" class="form-select" required>
										<option value="login_attempt" <?= $selectedAction === 'login_attempt' ? ' selected' : '' ?>>Login Attempt</option>
										<option value="totp_verify" <?= $selectedAction === 'totp_verify' ? ' selected' : '' ?>>TOTP Verify</option>
										<option value="passkey_verify" <?= $selectedAction === 'passkey_verify' ? ' selected' : '' ?>>Passkey Verify</option>
										<option value="passkey_store" <?= $selectedAction === 'passkey_store' ? ' selected' : '' ?>>Passkey Store</option>
										<option value="password_reset_request" <?= $selectedAction === 'password_reset_request' ? ' selected' : '' ?>>Password Reset Request</option>
										<option value="password_reset_submit" <?= $selectedAction === 'password_reset_submit' ? ' selected' : '' ?>>Password Reset Submit</option>
										<option value="registration_attempt" <?= $selectedAction === 'registration_attempt' ? ' selected' : '' ?>>Registration Attempt</option>
										<option value="email_verification" <?= $selectedAction === 'email_verification' ? ' selected' : '' ?>>Email Verification</option>
										<option value="email_change" <?= $selectedAction === 'email_change' ? ' selected' : '' ?>>Email Change</option>
										<option value="password_change" <?= $selectedAction === 'password_change' ? ' selected' : '' ?>>Password Change</option>
									</select>
								</div>
								<div class="col-md-6">
									<label for="email" class="form-label">Test Email (optional):</label>
									<input type="email" name="email" id="email" class="form-control" value="<?= hed($selectedEmail) ?>">
								</div>
							</div>

							<div class="btn-group-vertical d-grid gap-2 mt-3">
								<button type="submit" name="test_type" value="check_limit" class="btn btn-primary">
									🔍 Check if Action is Allowed
								</button>
								<button type="submit" name="test_type" value="validate_and_record" class="btn btn-info">
									✅ Validate & Record (Recommended)
								</button>
								<button type="submit" name="test_type" value="record_success" class="btn btn-success">
									✅ Record Successful Attempt
								</button>
								<button type="submit" name="test_type" value="record_failure" class="btn btn-warning">
									❌ Record Failed Attempt
								</button>
								<button type="submit" name="test_type" value="simulate_auth_success" class="btn btn-success">
									🎉 Simulate Auth Success
								</button>
								<button type="submit" name="test_type" value="simulate_auth_failure" class="btn btn-danger">
									💥 Simulate Auth Failure
								</button>
								<button type="submit" name="test_type" value="clear_failed" class="btn btn-secondary">
									🧹 Clear Failed Attempts
								</button>
							</div>
						</form>
					</div>
				</div>

				<div class="col-md-6">
					<div class="test-section">
						<h3>📊 Current Rate Limit Status</h3>

						<?php
						$actions = ['login_attempt', 'totp_verify', 'passkey_verify', 'password_reset_request', 'registration_attempt'];

						foreach ($actions as $action):
							if (!isRateLimitEnabled($action)) continue;

							$status = getRateLimitStatus($action, $currentUser, $selectedEmail);
							$hasLimits = !empty($status['identifiers']);
							$isLimited = false;

							if ($hasLimits) {
								foreach ($status['identifiers'] as $identifier) {
									if ($identifier['is_limited']) {
										$isLimited = true;
										break;
									}
								}
							}
						?>

							<div class="card status-card <?= $isLimited ? 'rate-limit-exceeded' : 'rate-limit-ok' ?>">
								<div class="card-header">
									<strong><?= ucwords(str_replace('_', ' ', $action)) ?></strong>
									<?= $isLimited ? '🚫 LIMITED' : '✅ OK' ?>
								</div>
								<div class="card-body">
									<?php if ($hasLimits): ?>
										<?php foreach ($status['identifiers'] as $type => $info): ?>
											<div class="mb-2">
												<strong><?= ucfirst($type) ?>:</strong>
												<?= $info['failed_attempts'] ?>/<?= $info['max_allowed'] ?> failed attempts
												(<?= $info['total_attempts'] ?> total in last <?= $info['window_seconds'] ?>s)
												<?php if ($info['is_limited']): ?>
													<span class="badge bg-danger">LIMITED</span>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									<?php else: ?>
										<em>No attempts recorded yet</em>
									<?php endif; ?>
								</div>
							</div>

						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<div class="test-section">
				<h3>📝 Usage Instructions</h3>
				<div class="row">
					<div class="col-md-6">
						<h5>Testing Scenarios:</h5>
						<ol>
							<li><strong>Test Normal Flow:</strong> Use "Validate & Record" - this checks if action is allowed and records the attempt</li>
							<li><strong>Trigger Rate Limit:</strong> Use "Record Failed Attempt" multiple times for the same action</li>
							<li><strong>Clear Rate Limit:</strong> Use "Simulate Auth Success" or "Clear Failed Attempts"</li>
							<li><strong>Check Status:</strong> Use "Check if Action is Allowed" to see current limits</li>
						</ol>
					</div>
					<div class="col-md-6">
						<h5>Rate Limit Configuration:</h5>
						<ul>
							<li><strong>Login:</strong> 5 failed attempts per IP (15 min), 3 per user (5 min)</li>
							<li><strong>TOTP:</strong> 10 per IP (5 min), 5 per user (5 min)</li>
							<li><strong>Password Reset:</strong> 3 per IP (1 hour), 2 per email (1 hour)</li>
							<li><strong>Registration:</strong> 3 per IP (1 hour)</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="alert alert-info">
				<h5>💡 Tips for Testing:</h5>
				<ul class="mb-0">
					<li>Try recording multiple failed attempts for "Login Attempt" to see rate limiting kick in</li>
					<li>Check the status panel to see attempt counts in real-time</li>
					<li>Use "Simulate Auth Success" to clear failed attempts after successful authentication</li>
					<li>Different actions have different limits - experiment with each one</li>
					<li>Rate limits are tracked per IP, user, and email depending on the action</li>
				</ul>
			</div>
		</div>
	</div>
</div>
<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
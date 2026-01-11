<?php
// Mock DB connection for testing environment if real one fails or is not configured
// However, since I need to test if the table exists, I should try to use the real one if possible,
// or at least verify the SQL syntax via dry run?
// Actually, I can just use the provided includes/db_connect.php.
// If it fails (because credentials are placeholders), I will catch it.

// Mock the mail config constants if they are not defined (though functions.php includes mail_config.php)
// functions.php includes mail_config.php using __DIR__, so it should be fine.

// Include the functions file
require_once __DIR__ . '/includes/functions.php';

echo "Testing PHPMailer Class existence...\n";
if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    echo "SUCCESS: PHPMailer class found.\n";
} else {
    echo "FAILURE: PHPMailer class NOT found.\n";
    exit(1);
}

// Test Logging
echo "Testing DB Logging...\n";

// We need a DB connection. Let's try to include db_connect.
// Note: In this sandbox, I might not have a running MySQL with these credentials.
// But I can check if the function *runs* without syntax errors.

// Mock PDO for testing logic if real connection fails
class MockPDO extends PDO {
    public function __construct() {}
    public function prepare($query) { return new MockStmt($query); }
}
class MockStmt {
    public function __construct($query) { echo "SQL Prepared: $query\n"; }
    public function bindValue($param, $value, $type) { echo "Bound $param = $value\n"; }
    public function execute() { echo "Executed.\n"; return true; }
}

$dbh = null;
try {
    // Attempt real connection?
    // Given the environment, it's likely 'localhost' with root/root or similar, or just not available.
    // I'll skip real DB connection and use Mock to verify the *Code Logic* flow.
    $dbh = new MockPDO();
} catch (Exception $e) {
    echo "Real DB failed, using Mock.\n";
    $dbh = new MockPDO();
}

log_email_history($dbh, 'test@example.com', 'Test Subject', 'Test Body', 'success');

echo "SUCCESS: log_email_history executed without fatal error.\n";

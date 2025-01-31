<?php
// Connect to SQLite database
$db = new SQLite3('mobank.db');

// Create users table if not exists (PINs are stored hashed)
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY, 
    phone TEXT UNIQUE, 
    pin TEXT, 
    balance REAL DEFAULT 0.0, 
    failed_attempts INTEGER DEFAULT 0, 
    blocked_until DATETIME DEFAULT NULL
)");

// Create transactions table
$db->exec("CREATE TABLE IF NOT EXISTS transactions (
    id INTEGER PRIMARY KEY, 
    phone TEXT, 
    type TEXT, 
    amount REAL, 
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Capture USSD request
$sessionId   = $_POST["sessionId"];
$serviceCode = $_POST["serviceCode"];
$phoneNumber = $_POST["phoneNumber"];
$text        = $_POST["text"];

$response = "";
$level = explode("*", $text);

// Fetch user details
$user = $db->querySingle("SELECT pin, balance, failed_attempts, blocked_until FROM users WHERE phone = '$phoneNumber'", true);

// Check if user is blocked
if ($user && $user['blocked_until'] && strtotime($user['blocked_until']) > time()) {
    $response = "END Your account is temporarily blocked. Try again later.";
} 
// New user - ask to set PIN
elseif (!$user) {
    if ($text == "") {
        $response = "CON Welcome to MoBank! Set a 4-digit PIN:";
    } elseif (strlen($text) == 4 && ctype_digit($text)) {
        $hashedPin = password_hash($text, PASSWORD_BCRYPT);
        $db->exec("INSERT INTO users (phone, pin) VALUES ('$phoneNumber', '$hashedPin')");
        $response = "END PIN set successfully! Dial again to log in.";
    } else {
        $response = "CON Invalid PIN. Enter a 4-digit PIN:";
    }
} 
// Returning user - ask for PIN
elseif (!isset($_SESSION[$sessionId])) {
    if ($text == "") {
        $response = "CON Enter your 4-digit PIN:";
    } elseif (password_verify($text, $user['pin'])) {
        $_SESSION[$sessionId] = true; // Authenticate session
        $response = "CON Welcome to MoBank\n1. Check Balance\n2. Deposit\n3. Withdraw";
    } else {
        // Track failed login attempts
        $attempts = $user['failed_attempts'] + 1;
        if ($attempts >= 3) {
            $blockTime = date("Y-m-d H:i:s", strtotime("+15 minutes"));
            $db->exec("UPDATE users SET failed_attempts = $attempts, blocked_until = '$blockTime' WHERE phone = '$phoneNumber'");
            $response = "END Too many failed attempts. Account blocked for 15 minutes.";
        } else {
            $db->exec("UPDATE users SET failed_attempts = $attempts WHERE phone = '$phoneNumber'");
            $response = "CON Incorrect PIN. Try again:";
        }
    }
} 
// Authenticated users proceed
elseif ($level[0] == "1") { // Check balance
    $response = "END Your balance is MWK " . number_format($user['balance'], 2);
} elseif ($level[0] == "2" && count($level) == 1) { // Deposit menu
    $response = "CON Enter amount to deposit:";
} elseif ($level[0] == "2" && count($level) == 2) { // Process deposit
    $amount = floatval($level[1]);
    if ($amount > 0) {
        $db->exec("UPDATE users SET balance = balance + $amount WHERE phone = '$phoneNumber'");
        $db->exec("INSERT INTO transactions (phone, type, amount) VALUES ('$phoneNumber', 'Deposit', $amount)");
        $response = "END Deposit successful! New balance updated.";
    } else {
        $response = "END Invalid deposit amount.";
    }
} elseif ($level[0] == "3" && count($level) == 1) { // Withdraw menu
    $response = "CON Enter amount to withdraw:";
} elseif ($level[0] == "3" && count($level) == 2) { // Process withdrawal
    $amount = floatval($level[1]);
    if ($amount > 0 && $user['balance'] >= $amount) {
        $db->exec("UPDATE users SET balance = balance - $amount WHERE phone = '$phoneNumber'");
        $db->exec("INSERT INTO transactions (phone, type, amount) VALUES ('$phoneNumber', 'Withdraw', $amount)");
        $response = "END Withdrawal successful! New balance updated.";
    } else {
        $response = "END Insufficient balance or invalid amount.";
    }
} else {
    $response = "END Invalid option. Try again.";
}

// Return USSD response
header("Content-type: text/plain");
echo $response;
?>

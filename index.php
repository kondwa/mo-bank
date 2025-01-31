<?php
// Connect to SQLite database
$db = new SQLite3('mobank.db');

// Create table if not exists
$db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, phone TEXT UNIQUE, balance REAL DEFAULT 0.0)");
$db->exec("CREATE TABLE IF NOT EXISTS transactions (id INTEGER PRIMARY KEY, phone TEXT, type TEXT, amount REAL, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP)");

// Capture USSD request
$sessionId   = $_POST["sessionId"];
$serviceCode = $_POST["serviceCode"];
$phoneNumber = $_POST["phoneNumber"];
$text        = $_POST["text"];

// Process USSD input
$response = "";
$level = explode("*", $text);

if ($text == "") {
    $response = "CON Welcome to MoBank\n1. Check Balance\n2. Deposit\n3. Withdraw";
} elseif ($level[0] == "1") { // Check balance
    $result = $db->querySingle("SELECT balance FROM users WHERE phone = '$phoneNumber'", true);
    $balance = $result ? $result['balance'] : 0.0;
    $response = "END Your balance is MWK " . number_format($balance, 2);
} elseif ($level[0] == "2" && count($level) == 1) { // Deposit menu
    $response = "CON Enter amount to deposit:";
} elseif ($level[0] == "2" && count($level) == 2) { // Process deposit
    $amount = floatval($level[1]);
    if ($amount > 0) {
        $db->exec("INSERT INTO users (phone, balance) VALUES ('$phoneNumber', $amount) ON CONFLICT(phone) DO UPDATE SET balance = balance + $amount");
        $db->exec("INSERT INTO transactions (phone, type, amount) VALUES ('$phoneNumber', 'Deposit', $amount)");
        $response = "END Deposit successful! New balance updated.";
    } else {
        $response = "END Invalid deposit amount.";
    }
} elseif ($level[0] == "3" && count($level) == 1) { // Withdraw menu
    $response = "CON Enter amount to withdraw:";
} elseif ($level[0] == "3" && count($level) == 2) { // Process withdrawal
    $amount = floatval($level[1]);
    $result = $db->querySingle("SELECT balance FROM users WHERE phone = '$phoneNumber'", true);
    $balance = $result ? $result['balance'] : 0.0;

    if ($amount > 0 && $balance >= $amount) {
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

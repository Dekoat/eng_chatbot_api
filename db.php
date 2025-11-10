<?php
/**6
 * Database Configuration for RMUTP Server
 * Host: shost.rmutp.ac.th
 */

// === PRODUCTION SERVER (RMUTP) ===
$host = "localhost";
$user = "anuwat-kl@shost.rmutp.ac.th";
$pass = "wbmbCmxVlN6mBtU";
$db = "anuwat-kl";

// === LOCAL DEVELOPMENT (XAMPP) - Comment out for production ===
// $host = "localhost";
// $user = "root";
// $pass = "";
// $db = "eng_chatbot";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4"); // รองรับภาษาไทย

if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
?>


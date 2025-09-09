<?php
// db.php
$DB_HOST = "sql100.infinityfree.com";
$DB_USER = "if0_39846404";
$DB_PASS = "GKDFcUQesONv8"; // XAMPP default
$DB_NAME = "if0_39846404_expense_tracker";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset("utf8mb4");

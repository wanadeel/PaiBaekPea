<?php
// รูปแบบ MySQLi เหมือน db.php ต้นฉบับ
$servername = getenv("DB_HOST") ?: "localhost";
$username = getenv("DB_USER") ?: "root";
$password = getenv("DB_PASSWORD") ?: "";
$dbname = getenv("DB_NAME") ?: "paibaekpea_db";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $error) {
    error_log($error->getMessage());
    http_response_code(500);
    exit(
        "เชื่อมต่อฐานข้อมูลไม่ได้ กรุณานำเข้า database/schema.sql และตรวจ db.php"
    );
}
// ไม่สร้างตารางหรือ seed ใหม่ทุกครั้งที่เปิดหน้าเว็บ

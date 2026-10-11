<?php
// ทุกหน้าเรียกไฟล์นี้ก่อนแสดง HTML: เริ่ม Session และเชื่อมต่อ db.php
if (session_status() === PHP_SESSION_NONE) {
    ini_set("session.use_strict_mode", "1");
    session_set_cookie_params([
        "httponly" => true,
        "secure" => !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
        "samesite" => "Lax",
        "path" => "/",
    ]);
    session_start();
}
date_default_timezone_set("Asia/Bangkok");
require_once dirname(__DIR__) . "/db.php";
header("Cache-Control: no-store");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header(
    "Content-Security-Policy: default-src 'self'; img-src 'self'; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'",
);
$_SESSION["csrf"] = $_SESSION["csrf"] ?? bin2hex(random_bytes(32));
// หากติดตั้งใน xampp/htdocs/paibaekpea รูปจะอยู่ใน xampp/paibaekpea-private-uploads
$projectRoot = dirname(__DIR__);
$privatePath =
    getenv("UPLOAD_DIR") ?:
    dirname($projectRoot, 2) . "/paibaekpea-private-uploads";
if (
    !is_dir($privatePath) &&
    !mkdir($privatePath, 0750, true) &&
    !is_dir($privatePath)
) {
    http_response_code(500);
    exit("สร้างโฟลเดอร์เก็บรูปไม่ได้ ตรวจสิทธิ์โฟลเดอร์หรือกำหนด UPLOAD_DIR");
}
define("UPLOAD_DIR", $privatePath);
require_once __DIR__ . "/helpers.php";

require_once __DIR__ . "/admin_support.php";

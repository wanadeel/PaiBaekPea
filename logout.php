<?php
require "includes/init.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("กรุณากดออกจากระบบจากเมนู");
}
check_csrf();
$_SESSION = [];
session_regenerate_id(true);
$_SESSION["csrf"] = bin2hex(random_bytes(32));
flash("ออกจากระบบแล้ว");
go("index.php");

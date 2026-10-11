<?php
require_once dirname(__DIR__) . "/includes/init.php";

// ตรวจทั้งการล็อกอินผ่านหน้าแอดมินและสิทธิ์ล่าสุดในฐานข้อมูล
function admin_current_user()
{
    global $conn;

    $adminId = (int) ($_SESSION["admin_user_id"] ?? 0);

    if ($adminId < 1 || $adminId !== user_id()) {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT user_id, fullname, email, role, account_status
         FROM users
         WHERE user_id = ?"
    );
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
        !$user ||
        $user["role"] !== "admin" ||
        $user["account_status"] !== "active"
    ) {
        unset($_SESSION["admin_user_id"]);
        return null;
    }

    return $user;
}

function require_admin()
{
    $user = admin_current_user();

    if (!$user) {
        header("Location: login.php");
        exit;
    }

    return $user;
}
<?php
require "includes/init.php";
require_login();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("กรุณากดถูกใจผ่านปุ่มในเว็บไซต์");
}

check_csrf();

$tripId = filter_input(INPUT_POST, "trip_id", FILTER_VALIDATE_INT);
$action = $_POST["action"] ?? "";

if (
    !$tripId
    || $tripId < 1
    || !is_string($action)
    || !in_array($action, ["like", "unlike"], true)
) {
    http_response_code(400);
    exit("ข้อมูลการกดถูกใจไม่ถูกต้อง");
}

$uid = (int) user_id();
$transaction = false;

try {
    $conn->begin_transaction();
    $transaction = true;

    // ตรวจสถานะล่าสุด และล็อกทริประหว่างทำรายการ
    $stmt = $conn->prepare(
        "SELECT visibility, moderation_status
         FROM trips
         WHERE trip_id = ?
         FOR UPDATE"
    );

    $stmt->bind_param("i", $tripId);
    $stmt->execute();
    $trip = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$trip || $trip["visibility"] !== "public" || $trip["moderation_status"] !== "visible") {
        $conn->rollback();
        $transaction = false;

        http_response_code(404);
        exit("ไม่พบทริป หรือทริปนี้ไม่ได้เปิดเป็นสาธารณะ");
    }

    if ($action === "like") {
        $stmt = $conn->prepare(
            "INSERT INTO trip_likes (user_id, trip_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)"
        );
    } else {
        $stmt = $conn->prepare(
            "DELETE FROM trip_likes
             WHERE user_id = ? AND trip_id = ?"
        );
    }

    $stmt->bind_param("ii", $uid, $tripId);
    $stmt->execute();
    $stmt->close();

    // อ่านจำนวนถูกใจล่าสุด
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM trip_likes
     WHERE trip_id = ?"
);

$stmt->bind_param("i", $tripId);
$stmt->execute();
$likeCount = (int) $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

$conn->commit();
$transaction = false;

// ส่งผลกลับให้ JavaScript โดยไม่เปลี่ยนหน้า
if (
    strpos($_SERVER["HTTP_ACCEPT"] ?? "", "application/json") !== false
) {
    header("Content-Type: application/json; charset=utf-8");
    header("Cache-Control: no-store");

    echo json_encode([
        "success" => true,
        "liked" => $action === "like",
        "count" => $likeCount
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// ใช้เมื่อส่งฟอร์มปกติ โดยไม่มี JavaScript
flash($action === "like"
    ? "กดถูกใจทริปแล้ว"
    : "ยกเลิกถูกใจแล้ว");

go("index.php");
exit;

} catch (Throwable $error) {
    if ($transaction) {
        $conn->rollback();
    }

    error_log((string) $error);
    http_response_code(500);
    exit("ทำรายการไม่สำเร็จ กรุณาลองใหม่อีกครั้ง");
}

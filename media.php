<?php
require "includes/init.php";
// ไม่มี static URL ไปยังไฟล์ส่วนตัว: ตรวจสิทธิ์ใหม่ทุกครั้ง
$filename = null;
$canReview = has_admin_access() ? 1 : 0;
if (isset($_GET["trip_id"])) {
    $trip_id = (int) $_GET["trip_id"];
    $uid = user_id();
    $stmt = $conn->prepare(
        "SELECT cover AS filename FROM trips WHERE trip_id=? AND ((visibility='public' AND (moderation_status='visible' OR ?=1)) OR user_id=?)",
    );
    $stmt->bind_param("iii", $trip_id, $canReview, $uid);
} elseif (isset($_GET["photo_id"])) {
    $photo_id = (int) $_GET["photo_id"];
    $uid = user_id();
    $stmt = $conn->prepare(
        "SELECT ph.filename FROM place_photos ph JOIN trip_places p ON ph.place_id=p.place_id JOIN trips t ON p.trip_id=t.trip_id WHERE ph.photo_id=? AND ((t.visibility='public' AND (t.moderation_status='visible' OR ?=1)) OR t.user_id=?)",
    );
    $stmt->bind_param("iii", $photo_id, $canReview, $uid);
} elseif (isset($_GET["user_id"])) {
    $uid = (int) $_GET["user_id"];
    $stmt = $conn->prepare(
        "SELECT avatar AS filename FROM users WHERE user_id=?",
    );
    $stmt->bind_param("i", $uid);
} else {
    http_response_code(404);
    exit();
}
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$filename = $row["filename"] ?? null;
if (
    !$filename ||
    !preg_match("/\A[a-f0-9]{48}\.(jpg|png|webp)\z/", $filename)
) {
    http_response_code(404);
    exit();
}
$path = UPLOAD_DIR . "/" . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit();
}
$types = ["jpg" => "image/jpeg", "png" => "image/png", "webp" => "image/webp"];
header("Content-Type: " . $types[pathinfo($filename, PATHINFO_EXTENSION)]);
header("Content-Length: " . filesize($path));
readfile($path);
exit();

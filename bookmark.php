<?php
require "includes/init.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    exit("กรุณากดปุ่มบันทึกทริป");
}
check_csrf();
$trip_id = (int) ($_POST["trip_id"] ?? 0);
$action = $_POST["action"] ?? "add";
if (!user_id()) {
    $_SESSION["pending_bookmark"] = $trip_id;
    $_SESSION["next"] = "bookmarks.php";
    go("login.php");
}
if ($action === "remove") {
    $uid = user_id();
    $stmt = $conn->prepare(
        "DELETE FROM bookmarks WHERE user_id=? AND trip_id=?",
    );
    $stmt->bind_param("ii", $uid, $trip_id);
    $stmt->execute();
    $stmt->close();
    flash("นำออกจากทริปที่สนใจแล้ว");
} else {
    flash(
        bookmark_trip($trip_id)
            ? "บันทึกทริปแล้ว"
            : "บันทึกไม่ได้ ทริปต้องเป็นสาธารณะของผู้อื่น",
    );
}
go("bookmarks.php");

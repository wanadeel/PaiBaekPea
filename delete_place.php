<?php
require "includes/init.php";
require_login();
$place_id = (int) ($_GET["id"] ?? 0);
[$place, $trip] = get_place($place_id, true);
$trip_id = (int) $trip["trip_id"];
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $transaction = false;
    try {
        $conn->begin_transaction();
        $transaction = true;
        // ล็อกทริปขณะตรวจจำนวน เพื่อไม่ให้เหลือ 0 สถานที่เมื่อมีการลบพร้อมกัน
        $stmt = $conn->prepare(
            "SELECT trip_id FROM trips WHERE trip_id=? FOR UPDATE",
        );
        $stmt->bind_param("i", $trip_id);
        $stmt->execute();
        $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM trip_places WHERE trip_id=?",
        );
        $stmt->bind_param("i", $trip_id);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()["total"];
        $stmt->close();
        if ($count <= 1) {
            throw new RuntimeException(
                "ทริปต้องมีอย่างน้อยหนึ่งสถานที่ กรุณาเพิ่มสถานที่ใหม่ก่อนลบ",
            );
        }
        $files = array_column(photos_for($place_id), "filename");
        $stmt = $conn->prepare(
            "DELETE FROM trip_places WHERE place_id=? AND trip_id=?",
        );
        $stmt->bind_param("ii", $place_id, $trip_id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $transaction = false;
        clean_files($files);
        flash("ลบสถานที่แล้ว");
        go("trip_detail.php?id=" . $trip_id);
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }
        $message = error_message($error);
    }
}
$page_title = "ยืนยันลบสถานที่";
require "includes/header.php";
?>
<section class="panel narrow"><h1>ลบสถานที่นี้?</h1><h2><?= h(
    $place["name"],
) ?></h2><p>รูปภาพและเรื่องราวของสถานที่นี้จะถูกลบถาวร</p>
<?php if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?></p><?php endif; ?>
<form method="post"><?php csrf_field(); ?><div class="actions"><a class="btn secondary" href="trip_detail.php?id=<?= $trip_id ?>">ยกเลิก</a><button class="btn danger">ยืนยันการลบ</button></div></form></section>
<?php require "includes/footer.php"; ?>

<?php
require "includes/init.php";
require_login();
$trip_id = (int) ($_GET["id"] ?? 0);
$trip = get_trip($trip_id, true);
$message = "";
// GET แสดงหน้ายืนยันเท่านั้น; ลบจริงด้วย POST + CSRF
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $transaction = false;
    try {
        $stmt = $conn->prepare(
            "SELECT ph.filename FROM place_photos ph JOIN trip_places p ON ph.place_id=p.place_id WHERE p.trip_id=?",
        );
        $stmt->bind_param("i", $trip_id);
        $stmt->execute();
        $files = array_column(
            $stmt->get_result()->fetch_all(MYSQLI_ASSOC),
            "filename",
        );
        $stmt->close();
        $files[] = $trip["cover"];
        $conn->begin_transaction();
        $transaction = true;
        $uid = user_id();
        $stmt = $conn->prepare(
            "DELETE FROM trips WHERE trip_id=? AND user_id=?",
        );
        $stmt->bind_param("ii", $trip_id, $uid);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $transaction = false;
        clean_files($files);
        flash("ลบทริปและรูปภาพแล้ว");
        go("my_trips.php");
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }
        $message = error_message($error);
    }
}
$page_title = "ยืนยันลบทริป";
require "includes/header.php";
?>
<section class="panel narrow"><h1>ลบทริปนี้?</h1><h2><?= h(
    $trip["title"],
) ?></h2><p>สถานที่ รูปภาพ และรายการที่ผู้อื่นบันทึกไว้จะถูกลบตาม ไม่สามารถย้อนกลับได้</p>
<?php if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?></p><?php endif; ?>
<form method="post"><?php csrf_field(); ?><div class="actions"><a class="btn secondary" href="trip_detail.php?id=<?= $trip_id ?>">ยกเลิก</a><button class="btn danger">ยืนยันการลบ</button></div></form></section>
<?php require "includes/footer.php"; ?>

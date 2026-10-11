<?php
require "includes/init.php";
require_login();
$trip_id = (int) ($_GET["id"] ?? 0);
$trip = get_trip($trip_id, true);
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $created = [];
    $transaction = false;
    try {
        [
            $title,
            $description,
            $start,
            $end,
            $visibility,
            $category_id,
        ] = trip_input();
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS total FROM trip_places WHERE trip_id=? AND (visit_date<? OR visit_date>?)",
        );
        $stmt->bind_param("iss", $trip_id, $start, $end);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()["total"]) {
            throw new RuntimeException(
                "ช่วงวันที่ใหม่ไม่ครอบคลุมสถานที่เดิม กรุณาแก้วันที่ของสถานที่ก่อน",
            );
        }
        $stmt->close();
        $new_cover = upload_photo($_FILES["cover"] ?? [], $created);
        $cover = $new_cover ?? $trip["cover"];
        $uid = user_id();
        $conn->begin_transaction();
        $transaction = true;
        $stmt = $conn->prepare(
            "UPDATE trips SET category_id=?,title=?,description=?,start_date=?,end_date=?,visibility=?,cover=? WHERE trip_id=? AND user_id=?",
        );
        $stmt->bind_param(
            "issssssii",
            $category_id,
            $title,
            $description,
            $start,
            $end,
            $visibility,
            $cover,
            $trip_id,
            $uid,
        );
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $transaction = false;
        if ($new_cover) {
            clean_files([$trip["cover"]]);
        }
        flash("แก้ไขทริปแล้ว");
        go("trip_detail.php?id=" . $trip_id);
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }
        clean_files($created);
        $message = error_message($error);
    }
}
$categories = $conn
    ->query("SELECT * FROM categories ORDER BY category_id")
    ->fetch_all(MYSQLI_ASSOC);
$page_title = "แก้ไขทริป";
require "includes/header.php";
?>
<section class="page-heading"><h1>แก้ไขทริป</h1><p class="muted">จัดการสถานที่และรูปสถานที่ได้จากหน้ารายละเอียดทริป</p></section>
<?php if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?></p><?php endif; ?>
<form class="panel" method="post" enctype="multipart/form-data"><?php
csrf_field();
require "includes/trip_fields.php";
?><div class="actions"><a class="btn secondary" href="trip_detail.php?id=<?= $trip_id ?>">ยกเลิก</a><button class="btn">บันทึกการแก้ไข</button></div></form>
<?php require "includes/footer.php"; ?>

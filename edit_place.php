<?php
require "includes/init.php";
require_login();
$place_id = (int) ($_GET["id"] ?? 0);
[$place, $trip] = get_place($place_id, true);
$trip_id = (int) $trip["trip_id"];
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    check_csrf();
    $created = [];
    $removed = [];
    $transaction = false;
    try {
        [$name, $province, $visit, $story, $sort, $country] = place_input($trip);
        $conn->begin_transaction();
        $transaction = true;
        $stmt = $conn->prepare(
    "UPDATE trip_places
     SET name = ?,
         province = ?,
         visit_date = ?,
         story = ?,
         sort_order = ?,
         country_code = ?,
         province_code = NULL
     WHERE place_id = ? AND trip_id = ?"
);

$stmt->bind_param(
    "ssssisii",
    $name,
    $province,
    $visit,
    $story,
    $sort,
    $country,
    $place_id,
    $trip_id
);
        $stmt->execute();
        $stmt->close();
        $delete = $_POST["delete_photos"] ?? [];
        if (!is_array($delete)) {
            throw new RuntimeException("รายการรูปที่ลบไม่ถูกต้อง");
        }
        foreach ($delete as $photo) {
            $photo_id = (int) $photo;
            $stmt = $conn->prepare(
                "SELECT filename FROM place_photos WHERE photo_id=? AND place_id=?",
            );
            $stmt->bind_param("ii", $photo_id, $place_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($row) {
                $removed[] = $row["filename"];
                $stmt = $conn->prepare(
                    "DELETE FROM place_photos WHERE photo_id=? AND place_id=?",
                );
                $stmt->bind_param("ii", $photo_id, $place_id);
                $stmt->execute();
                $stmt->close();
            }
        }
        // รับรูปเดิมที่ผู้ใช้ปรับขนาดแล้ว
$cropped = $_FILES["cropped_photos"] ?? null;

if ($cropped !== null) {
    if (
        !is_array($cropped) ||
        !isset($cropped["error"]) ||
        !is_array($cropped["error"]) ||
        count($cropped["error"]) > 10
    ) {
        throw new RuntimeException("ข้อมูลรูปที่ปรับไม่ถูกต้อง");
    }

    foreach ($cropped["error"] as $id => $uploadError) {
        if (
            !ctype_digit((string) $id) ||
            (int) $id < 1 ||
            !is_int($uploadError)
        ) {
            throw new RuntimeException("ข้อมูลรูปที่ปรับไม่ถูกต้อง");
        }

        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $photo_id = (int) $id;

        // หากเลือกให้ลบรูปนี้ ไม่ต้องบันทึกรูปที่ครอป
        if (in_array((string) $photo_id, array_map("strval", $delete), true)) {
            continue;
        }

        // ตรวจว่ารูปนี้เป็นของสถานที่ที่กำลังแก้ไขจริง
        $stmt = $conn->prepare(
            "SELECT filename
             FROM place_photos
             WHERE photo_id = ? AND place_id = ?
             FOR UPDATE"
        );

        $stmt->bind_param("ii", $photo_id, $place_id);
        $stmt->execute();
        $oldPhoto = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$oldPhoto) {
            throw new RuntimeException("ไม่มีสิทธิ์แก้ไขรูปนี้");
        }

        $file = [];

        foreach (["name", "type", "tmp_name", "error", "size"] as $field) {
            if (
                !isset($cropped[$field]) ||
                !is_array($cropped[$field]) ||
                !array_key_exists($id, $cropped[$field]) ||
                is_array($cropped[$field][$id])
            ) {
                throw new RuntimeException("ข้อมูลไฟล์รูปไม่ถูกต้อง");
            }

            $file[$field] = $cropped[$field][$id];
        }

        // ใช้ฟังก์ชันตรวจสอบและอัปโหลดรูปเดิมของเว็บไซต์
        $filename = upload_photo($file, $created, true);

        $stmt = $conn->prepare(
            "UPDATE place_photos
             SET filename = ?
             WHERE photo_id = ? AND place_id = ?"
        );

        $stmt->bind_param("sii", $filename, $photo_id, $place_id);
        $stmt->execute();
        $stmt->close();

        // ลบไฟล์เก่าหลัง commit ด้วยขั้นตอนเดิม
        $removed[] = $oldPhoto["filename"];
    }
}
        add_photos($place_id, $created);
        $conn->commit();
        $transaction = false;
        clean_files($removed);
        flash("แก้ไขสถานที่แล้ว");
        go("trip_detail.php?id=" . $trip_id);
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }
        clean_files($created);
        $message = error_message($error);
    }
}
$photos = photos_for($place_id);
$page_title = "แก้ไขสถานที่";
require "includes/header.php";
?>
<section class="page-heading"><h1>แก้ไขสถานที่</h1><p class="muted"><?= h(
    $trip["title"],
) ?></p></section>
<?php if ($message): ?><p class="error" role="alert"><?= h(
    $message,
) ?> · กรุณาเลือกไฟล์รูปใหม่อีกครั้ง</p><?php endif; ?>
<form class="panel" method="post" enctype="multipart/form-data"><?php
csrf_field();
require "includes/place_fields.php";
?>
<div class="photo-grid place-photo-grid">
    <?php foreach ($photos as $photo): ?>
        <?php $photoId = (int) $photo["photo_id"]; ?>

        <div class="place-photo-card" data-place-crop>
            <div class="place-photo-frame">
                <img
                    class="place-photo-original"
                    src="media.php?photo_id=<?= $photoId ?>"
                    alt="รูปสถานที่"
                >

                <canvas
                    class="place-photo-preview"
                    width="1200"
                    height="900"
                    role="img"
                    aria-label="รูปสถานที่ที่ปรับแล้ว"
                    hidden
                ></canvas>

                <button
                    class="place-photo-crop-button"
                    type="button"
                    data-crop-open
                    aria-label="ปรับขนาดและตำแหน่งรูป"
                    aria-controls="photo-editor-<?= $photoId ?>"
                    aria-expanded="false"
                    hidden
                >
                    <svg
                        viewBox="0 0 24 24"
                        width="28"
                        height="28"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M6 3v15h15"></path>
                        <path d="M3 6h15v15"></path>
                    </svg>
                </button>
            </div>

            <!-- JavaScript ใส่ไฟล์ที่ครอปแล้วลงในช่องนี้ -->
            <input
                type="file"
                name="cropped_photos[<?= $photoId ?>]"
                accept="image/jpeg"
                data-crop-file
                hidden
            >

            <div
                class="place-photo-editor"
                id="photo-editor-<?= $photoId ?>"
                hidden
            >
                <canvas
                    class="place-photo-canvas"
                    width="1200"
                    height="900"
                    role="img"
                    aria-label="พื้นที่ปรับรูป ลากรูปเพื่อเลื่อนตำแหน่ง"
                ></canvas>
                <label>
                    ขยายรูป
                    <input
                        type="range"
                        min="1"
                        max="3"
                        step="0.01"
                        value="1"
                        data-crop-zoom
                    >
                </label>
                <label>
                    เลื่อนซ้าย–ขวา
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="0.1"
                        value="50"
                        data-crop-x
                    >
                </label>
                <label>
                    เลื่อนขึ้น–ลง
                    <input
                        type="range"
                        min="0"
                        max="100"
                        step="0.1"
                        value="50"
                        data-crop-y
                    >
                </label>

                <div class="place-photo-editor-actions">
                    <button class="btn small" type="button" data-crop-apply>
                        ใช้รูปนี้
                    </button>
                    <button
                        class="btn secondary small"
                        type="button"
                        data-crop-cancel
                    >
                        ยกเลิกการปรับ
                    </button>
                </div>
            </div>

            <p class="muted place-photo-status" role="status"></p>

            <label class="check">
                <input
                    type="checkbox"
                    name="delete_photos[]"
                    value="<?= $photoId ?>"
                    data-crop-delete
                >
                ลบรูปนี้เมื่อบันทึก
            </label>
        </div>
    <?php endforeach; ?>
</div>
<div class="actions"><a class="btn secondary" href="trip_detail.php?id=<?= $trip_id ?>">ยกเลิก</a>
<button class="btn">บันทึกการแก้ไข</button>
</div>
</form>
<!-- ปุ่มยกเลิกและบันทึกเดิมอยู่ด้านบน -->

<script
    src="place-photo-crop.js?v=<?= (int) filemtime(__DIR__ . '/place-photo-crop.js') ?>"
    defer
></script>

<?php require "includes/footer.php"; ?>

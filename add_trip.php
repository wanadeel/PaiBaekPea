<?php
error_reporting(E_ALL);
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");

require "includes/init.php";
require_login();

$message = "";
$trip = [];
$rows = [
    0 => [
        "sort_order" => 1
    ]
];

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
            $category_id
        ] = trip_input();

        $submitted = $_POST["places"] ?? [];

        if (
            !is_array($submitted) ||
            count($submitted) < 1 ||
            count($submitted) > 20
        ) {
            throw new RuntimeException(
                "กรุณาเพิ่มสถานที่อย่างน้อย 1 แห่ง และไม่เกิน 20 แห่ง"
            );
        }

        // ตรวจโครงสร้างก่อนนำกลับไปแสดงในแบบฟอร์ม
        foreach ($submitted as $slot => $row) {
            if (
                !ctype_digit((string) $slot) ||
                (int) $slot > 19 ||
                !is_array($row)
            ) {
                throw new RuntimeException("ข้อมูลสถานที่ไม่ถูกต้อง");
            }

            foreach ($row as $value) {
                if (!is_string($value)) {
                    throw new RuntimeException("ข้อมูลสถานที่ไม่ถูกต้อง");
                }
            }
        }

        $rows = $submitted;
        $validated = [];
        $originalPost = $_POST;

        // ใช้ตัวตรวจสอบเดิมกับสถานที่แต่ละแห่ง
        try {
            $number = 0;

            foreach ($rows as $slot => $row) {
                $number++;
                $_POST = $row;

                try {
                    $validated[$slot] = place_input([
                        "start_date" => $start,
                        "end_date" => $end
                    ]);
                } catch (RuntimeException $error) {
                    throw new RuntimeException(
                        "สถานที่ที่ " . $number . ": " . $error->getMessage()
                    );
                }
            }
        } finally {
            $_POST = $originalPost;
        }

        $cover = upload_photo(
            $_FILES["cover"] ?? [],
            $created,
            true
        );

        $uid = user_id();

        $conn->begin_transaction();
        $transaction = true;

        $stmt = $conn->prepare(
            "INSERT INTO trips
                (user_id, category_id, title, description,
                 start_date, end_date, visibility, cover)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "iissssss",
            $uid,
            $category_id,
            $title,
            $description,
            $start,
            $end,
            $visibility,
            $cover
        );

        $stmt->execute();
        $trip_id = (int) $conn->insert_id;
        $stmt->close();

        foreach ($validated as $slot => $values) {
            [
                $name,
                $province,
                $visit,
                $story,
                $sort,
                $country
            ] = $values;

            $stmt = $conn->prepare(
                "INSERT INTO trip_places
                    (trip_id, name, province, visit_date,
                     story, sort_order, country_code)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "issssis",
                $trip_id,
                $name,
                $province,
                $visit,
                $story,
                $sort,
                $country
            );

            $stmt->execute();
            $place_id = (int) $conn->insert_id;
            $stmt->close();

            // ส่งเฉพาะรูปของสถานที่นี้ให้ฟังก์ชันอัปโหลดเดิม
            $hadPhotos = array_key_exists("photos", $_FILES);
            $originalPhotos = $_FILES["photos"] ?? null;

            $_FILES["photos"] = $_FILES["photos_" . $slot] ?? [
                "name" => [],
                "type" => [],
                "tmp_name" => [],
                "error" => [],
                "size" => []
            ];

            try {
                add_photos($place_id, $created);
            } finally {
                if ($hadPhotos) {
                    $_FILES["photos"] = $originalPhotos;
                } else {
                    unset($_FILES["photos"]);
                }
            }
        }

        $conn->commit();
        $transaction = false;
    } catch (Throwable $error) {
        if ($transaction) {
            $conn->rollback();
        }

        clean_files($created);
        $message = error_message($error);
    }

    if ($message === "") {
        flash("สร้างทริปและบันทึกสถานที่ทั้งหมดแล้ว");
        go("trip_detail.php?id=" . $trip_id);
        exit;
    }
}

$categories = $conn
    ->query("SELECT * FROM categories ORDER BY category_id")
    ->fetch_all(MYSQLI_ASSOC);

$page_title = "สร้างทริป";
require "includes/header.php";
?>

<section class="page-heading">
    <p class="eyebrow">WRITE YOUR JOURNEY</p>
    <h1>บันทึกการเดินทางครั้งใหม่</h1>
    <p class="muted">
        เพิ่มทุกสถานที่ที่ไปเยือน แล้วเก็บความทรงจำไว้ในทริปเดียวกัน
    </p>
</section>

<?php if ($message): ?>
    <p class="error" role="alert">
        <?= h($message) ?>
        · กรุณาเลือกไฟล์รูปใหม่อีกครั้ง
    </p>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="trip-create-form">
    <?php csrf_field(); ?>

    <section class="panel">
        <h2>01 ข้อมูลทริป</h2>
        <?php require "includes/trip_fields.php"; ?>
    </section>

    <section class="panel">
        <div class="places-heading">
            <div>
                <h2>02 สถานที่ในทริป</h2>
                <p class="muted">
                    เพิ่มได้ไม่เกิน 20 แห่ง และเลือกวันที่เดียวกันได้
                </p>
            </div>

            <button
                class="btn add-place-button"
                type="button"
                id="add-place-button"
                aria-controls="places-list"
                hidden
            >
                <span aria-hidden="true">+</span>
                เพิ่มสถานที่
            </button>
        </div>

        <p id="places-status" class="muted" role="status"></p>

        <div id="places-list">
            <?php $placeNumber = 0; ?>

            <?php foreach ($rows as $slot => $row): ?>
                <?php
                $placeNumber++;
                require "includes/trip_place_card.php";
                ?>
            <?php endforeach; ?>
        </div>

        <noscript>
            <p class="muted">
                เปิด JavaScript เพื่อเพิ่มหลายฟอร์มในหน้านี้
                หรือบันทึกทริปก่อนแล้วเพิ่มสถานที่จากหน้ารายละเอียด
            </p>
        </noscript>
    </section>

    <div class="actions">
        <a class="btn secondary" href="my_trips.php">ยกเลิก</a>
        <button class="btn" type="submit">บันทึกทริปทั้งหมด</button>
    </div>
</form>

<template id="place-card-template">
    <?php
    $slot = "__INDEX__";
    $row = [];
    $placeNumber = "";
    require "includes/trip_place_card.php";
    ?>
</template>

<script src="trip-places.js" defer></script>

<?php require "includes/footer.php"; ?>
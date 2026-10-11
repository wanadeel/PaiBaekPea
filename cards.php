<?php
$viewerId = (int) user_id();

$thaiDate = static function ($value) {
    $months = [
        1 => "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.",
        "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.",
        "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];

    $date = DateTimeImmutable::createFromFormat("!Y-m-d", $value);

    if (!$date) {
        return $value;
    }

    return (int) $date->format("j")
        . " " . $months[(int) $date->format("n")]
        . " " . ((int) $date->format("Y") + 543);
};
?>

<?php if (!$trips): ?>

    <section class="panel empty">
        <h2>ยังไม่พบทริป</h2>
        <p>ลองเปลี่ยนคำค้นหา หรือตัวกรองของคุณ</p>
    </section>

<?php else: ?>

    <div class="trip-grid">
        <?php foreach ($trips as $trip): ?>
            <?php
            $cardTripId = (int) $trip["trip_id"];

            // ตรวจสิทธิ์ก่อนแสดงข้อมูลประกอบของทริป
            if (
                $trip["visibility"] !== "public"
                && (int) $trip["user_id"] !== $viewerId
            ) {
                continue;
            }

            if (($trip['moderation_status'] ?? 'visible') === 'hidden' && (int)$trip['user_id'] !== $viewerId) continue;
            // จังหวัดทั้งหมดภายในทริปนี้
            $provinceStmt = $conn->prepare(
                "SELECT DISTINCT province
                 FROM trip_places
                 WHERE trip_id = ? AND province <> ''
                 ORDER BY province"
            );

            $provinceStmt->bind_param("i", $cardTripId);
            $provinceStmt->execute();

            $provinceRows = $provinceStmt
                ->get_result()
                ->fetch_all(MYSQLI_ASSOC);

            $provinceStmt->close();

            $provinceText = implode(
                ", ",
                array_column($provinceRows, "province")
            );

            // จำนวนถูกใจ และสถานะของผู้ใช้ปัจจุบัน
            $likeStmt = $conn->prepare(
                "SELECT
                    COUNT(*) AS total,
                    COALESCE(MAX(user_id = ?), 0) AS liked
                 FROM trip_likes
                 WHERE trip_id = ?"
            );

            $likeStmt->bind_param("ii", $viewerId, $cardTripId);
            $likeStmt->execute();
            $likeData = $likeStmt->get_result()->fetch_assoc();
            $likeStmt->close();

            $liked = (bool) $likeData["liked"];
            $likeCount = (int) $likeData["total"];

            $dateText = $thaiDate($trip["start_date"]);

            if ($trip["start_date"] !== $trip["end_date"]) {
                $dateText .= " – " . $thaiDate($trip["end_date"]);
            }
            ?>

            <article class="trip-card">
                <a href="trip_detail.php?id=<?= $cardTripId ?>">
                    <img
                        class="card-cover"
                        src="media.php?trip_id=<?= $cardTripId ?>"
                        alt="<?= h($trip["title"]) ?>"
                        loading="lazy"
                    >
                </a>

                <div class="card-body">
                    <span class="badge">
                        <?= $trip["visibility"] === "private"
                            ? "ส่วนตัว"
                            : "สาธารณะ" ?>
                    </span>

                    <p class="muted">
                        <?= h($trip["category_name"]) ?>
                    </p>

                    <h2>
                        <a href="trip_detail.php?id=<?= $cardTripId ?>">
                            <?= h($trip["title"]) ?>
                        </a>
                    </h2>

                    <p class="excerpt">
                        <?= h($trip["description"]) ?>
                    </p>

                    <!-- จังหวัด วันที่ และจำนวนถูกใจ -->
                    <div class="trip-card-meta">

                        <span class="trip-meta-item trip-meta-location">
                            <svg viewBox="0 0 24 24"
                                 aria-hidden="true">
                                <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>

                            <span>
                                <?= h($provinceText !== ""
                                    ? $provinceText
                                    : "ยังไม่ระบุจังหวัด") ?>
                            </span>
                        </span>

                        <span class="trip-meta-item trip-meta-date">
                            <svg viewBox="0 0 24 24"
                                 aria-hidden="true">
                                <rect x="3" y="5"
                                      width="18" height="16" rx="2"/>
                                <path d="M16 3v4M8 3v4M3 11h18"/>
                            </svg>

                            <span><?= h($dateText) ?></span>
                        </span>

                        <?php if ($trip["visibility"] === "public"): ?>
                            <?php if ($viewerId): ?>

                                <form
                                    class="trip-like-form"
                                    method="post"
                                    action="like_trip.php"
                                >
                                    <?php csrf_field(); ?>

                                    <input
                                        type="hidden"
                                        name="trip_id"
                                        value="<?= $cardTripId ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="<?= $liked ? "unlike" : "like" ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="trip-like-button <?= $liked ? "is-liked" : "" ?>"
                                        aria-pressed="<?= $liked ? "true" : "false" ?>"
                                        aria-label="<?= $liked ? "ยกเลิกถูกใจ" : "ถูกใจทริป" ?>"
                                    >
                                        <svg viewBox="0 0 24 24"
                                             aria-hidden="true">
                                            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>
                                        </svg>

                                        <span><?= $likeCount ?></span>
                                    </button>
                                </form>

                            <?php else: ?>

                                <a
                                    class="trip-like-button"
                                    href="login.php"
                                    aria-label="เข้าสู่ระบบเพื่อกดถูกใจ"
                                >
                                    <svg viewBox="0 0 24 24"
                                         aria-hidden="true">
                                        <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>
                                    </svg>

                                    <span><?= $likeCount ?></span>
                                </a>

                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <a
                        class="author"
                        href="profile.php?id=<?= (int) $trip["user_id"] ?>"
                    >
                        โดย <?= h($trip["fullname"]) ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

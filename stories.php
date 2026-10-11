<?php
require __DIR__ . '/includes/init.php';

$page_title = 'สตอรี่';

$stmt = $conn->prepare(
    "SELECT s.story_id, s.user_id, s.title, s.caption,
            s.created_at, u.fullname,
            (SELECT COUNT(*) FROM story_likes l
             WHERE l.story_id = s.story_id) AS like_count
     FROM stories s
     JOIN users u ON u.user_id = s.user_id
     WHERE u.account_status = 'active'
     ORDER BY RAND()
     LIMIT 24"
);

$stmt->execute();
$stories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

require __DIR__ . '/includes/header.php';
?>

<section class="stories-page">
    <div class="stories-heading">
        <h1>สตอรี่ระหว่างทาง</h1>
        <p>แบ่งปันช่วงเวลาการเดินทาง ผ่านคลิปวิดีโอของสมาชิก</p>
        <?php if (user_id() > 0): ?>
    <a href="story_upload.php" class="stories-upload-btn">
        + เพิ่มสตอรี่
    </a>
<?php else: ?>
    <a href="login.php" class="stories-upload-btn">
        เข้าสู่ระบบเพื่อเพิ่มสตอรี่
    </a>
<?php endif; ?>
    </div>

    <?php if (!$stories): ?>
        <div class="stories-empty">
            <span aria-hidden="true">▷</span>
            <h2>ยังไม่มีคลิปสตอรี่</h2>
            <p>พื้นที่สำหรับเก็บช่วงเวลาระหว่างทางของคุณ</p>
        </div>
    <?php else: ?>
        <div class="stories-grid">
            <?php foreach ($stories as $story): ?>
                <article class="stories-card">
                    <div class="stories-frame">
                        <video
                            autoplay
                            loop
                            muted
                            playsinline
                            preload="metadata"
                            src="story_video.php?id=<?= (int) $story['story_id'] ?>"
                            aria-label="<?= h($story['title']) ?>"
                            >เบราว์เซอร์นี้ไม่รองรับการเล่นวิดีโอ
                        </video>
                    </div>

                    <div class="stories-info">
                        <h2><?= h($story['title']) ?></h2>

                        <div class="stories-meta">
                            <a href="profile.php?id=<?= (int)$story['user_id'] ?>">
                                <?= h($story['fullname']) ?>
                            </a>

                            <span class="stories-heart">
                                ♡ <?= number_format((int)$story['like_count']) ?>
                            </span>
                        </div>

    <?php if ((int) $story['user_id'] === user_id()): ?>
    <div class="stories-actions">
        <a
            class="btn"
            href="story_edit.php?id=<?= (int) $story['story_id'] ?>"
        >แก้ไข</a>

        <a
            class="btn"
            href="story_edit.php?id=<?= (int) $story['story_id'] ?>&amp;mode=delete"
        >ลบคลิป</a>
    </div>
<?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

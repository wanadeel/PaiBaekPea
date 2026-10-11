<?php
require "includes/init.php";
$profile_id = (int) ($_GET["id"] ?? user_id());
$stmt = $conn->prepare(
    "SELECT user_id,username,fullname,bio,avatar,profile_cover FROM users WHERE user_id=?",
);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$profile) {
    not_found();
}
$viewer = user_id();
$stmt = $conn->prepare(
    "SELECT t.*,u.fullname,c.name AS category_name FROM trips t JOIN users u ON t.user_id=u.user_id JOIN categories c ON t.category_id=c.category_id WHERE t.user_id=? AND ((t.visibility='public' AND t.moderation_status='visible') OR t.user_id=?) ORDER BY t.start_date DESC,t.trip_id DESC",
);
$stmt->bind_param("ii", $profile_id, $viewer);
$stmt->execute();
$trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$tab = ($_GET['view'] ?? '') === 'videos' ? 'videos' : 'photos';
$stmt = $conn->prepare(
    "SELECT s.story_id,s.user_id,s.title,s.caption,s.created_at,
            (SELECT COUNT(*) FROM story_likes l WHERE l.story_id=s.story_id) AS like_count
     FROM stories s JOIN users u ON u.user_id=s.user_id
     WHERE s.user_id=? AND u.account_status='active'
     ORDER BY s.created_at DESC,s.story_id DESC"
);
$stmt->bind_param('i', $profile_id);
$stmt->execute();
$profileStories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$page_title = $profile['fullname'];
require 'includes/header.php';
?>
<link rel="stylesheet" href="assets/profile.css?v=<?= (int) filemtime(__DIR__ . '/assets/profile.css') ?>">
<section class="pbb-profile-page">
    <section class="pbb-profile-hero" aria-label="โปรไฟล์สมาชิก">
        <div class="pbb-profile-banner">
            <?php if (!empty($profile['profile_cover'])): ?>
                <img src="profile_cover.php?id=<?= $profile_id ?>" alt="ภาพปกของ <?= h($profile['fullname']) ?>">
            <?php endif; ?>
            <?php if ($profile_id === $viewer): ?>
                <a class="btn small pbb-profile-cover-link" href="edit_profile.php#profile-cover">เปลี่ยนภาพปก</a>
            <?php endif; ?>
        </div>
        <div class="pbb-profile-details">
            <div class="pbb-profile-identity">
                <?php if ($profile['avatar']): ?>
                    <img class="pbb-profile-avatar" src="media.php?user_id=<?= $profile_id ?>" alt="รูปโปรไฟล์ <?= h($profile['fullname']) ?>">
                <?php else: ?>
                    <span class="pbb-profile-avatar pbb-profile-initial" aria-hidden="true"><?= h(mb_substr($profile['fullname'],0,1)) ?></span>
                <?php endif; ?>
                <div class="pbb-profile-name">
                    <p class="pbb-profile-kicker">TRAVELER PROFILE</p>
                    <h1><?= h($profile['fullname']) ?></h1>
                    <p>@<?= h($profile['username']) ?></p>
                    <?php if (!empty($profile['bio'])): ?><p class="pbb-profile-bio"><?= h($profile['bio']) ?></p><?php endif; ?>
                </div>
                <div class="pbb-profile-buttons">
                    <?php if ($profile_id === $viewer): ?>
                        <a class="btn secondary" href="edit_profile.php">แก้ไขโปรไฟล์</a>
                    <?php elseif ($viewer): ?>
                        <a class="btn secondary small" href="report.php?type=user&amp;id=<?= $profile_id ?>">รายงานสมาชิก</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="pbb-profile-stats">
                <div><strong><?= count($trips) ?></strong><span>ทริป<?= $profile_id === $viewer ? 'ทั้งหมด' : 'สาธารณะ' ?></span></div>
                <div><strong><?= count($profileStories) ?></strong><span>วิดีโอสตอรี่</span></div>
            </div>
        </div>
    </section>

    <nav class="pbb-profile-tabs" aria-label="เลือกดูโพสต์ของสมาชิก">
        <a href="profile.php?id=<?= $profile_id ?>&amp;view=photos" class="<?= $tab === 'photos' ? 'is-active' : '' ?>" <?= $tab === 'photos' ? 'aria-current="page"' : '' ?>>รูปภาพ / ทริป <span><?= count($trips) ?></span></a>
        <a href="profile.php?id=<?= $profile_id ?>&amp;view=videos" class="<?= $tab === 'videos' ? 'is-active' : '' ?>" <?= $tab === 'videos' ? 'aria-current="page"' : '' ?>>วิดีโอ <span><?= count($profileStories) ?></span></a>
    </nav>

    <?php if ($tab === 'photos'): ?>
        <div class="pbb-profile-section-heading"><h2>เรื่องราวการเดินทาง</h2></div>
        <?php require 'includes/cards.php'; ?>
    <?php else: ?>
        <div class="pbb-profile-section-heading">
            <h2>วิดีโอสตอรี่</h2>
            <?php if ($profile_id === $viewer): ?><a class="btn small" href="story_upload.php">+ เพิ่มสตอรี่</a><?php endif; ?>
        </div>
        <?php if (!$profileStories): ?>
            <section class="panel empty"><h2>ยังไม่มีวิดีโอสตอรี่</h2><p>คลิปที่สมาชิกลงในหน้าสตอรี่จะแสดงที่นี่</p></section>
        <?php else: ?>
            <div class="pbb-profile-videos">
    <?php foreach ($profileStories as $story): ?>
        <article class="pbb-profile-video-card">
            <div class="pbb-video-wrap">
    <video
        autoplay
        muted
        loop
        playsinline
        preload="metadata"
        src="story_video.php?id=<?= (int) $story['story_id'] ?>"
        aria-label="<?= h($story['title']) ?>"
    >
        เบราว์เซอร์นี้ไม่รองรับการเล่นวิดีโอ
    </video>

    <button
        type="button"
        class="pbb-video-sound"
        aria-label="เปิดเสียง"
        aria-pressed="false"
        title="เปิดเสียง"
    >
        <span aria-hidden="true">🔇</span>
    </button>
</div>       
                            <div class="pbb-profile-video-info">
                            <h3><?= h($story['title']) ?></h3>
                            <?php if ($story['caption'] !== ''): ?><p><?= h($story['caption']) ?></p><?php endif; ?>
                            <span>♡ <?= number_format((int) $story['like_count']) ?></span>
                            <?php if ($profile_id === $viewer && is_file(__DIR__ . '/story_edit.php')): ?>
                                <div class="pbb-profile-video-actions">
                                    <a class="btn small" href="story_edit.php?id=<?= (int) $story['story_id'] ?>">แก้ไข</a>
                                    <a class="btn secondary small" href="story_edit.php?id=<?= (int) $story['story_id'] ?>&amp;mode=delete">ลบคลิป</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
<script src="assets/profile-videos.js?v=<?= (int) filemtime(__DIR__ . '/assets/profile-videos.js') ?>" defer></script>
<?php require 'includes/footer.php'; ?>

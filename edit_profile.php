<?php
require __DIR__ . '/includes/init.php';
require_login();
$uid = user_id();
$message = '';
$stmt = $conn->prepare('SELECT fullname,bio,avatar,profile_cover FROM users WHERE user_id=?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$profile) not_found();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $created = [];
    $transaction = false;
    try {
        if (empty($_POST) && empty($_FILES)) {
            throw new RuntimeException('ไฟล์อาจใหญ่เกินค่าที่เซิร์ฟเวอร์อนุญาต กรุณาเลือกรูปขนาดเล็กลง');
        }
        check_csrf();
        $fullname = text_value('fullname', 100, true);
        $bio = text_value('bio', 500);
        $new_avatar = upload_photo($_FILES['avatar'] ?? [], $created);
        $new_cover = upload_photo($_FILES['profile_cover'] ?? [], $created);

        $conn->begin_transaction();
        $transaction = true;
        $stmt = $conn->prepare('SELECT avatar,profile_cover FROM users WHERE user_id=? FOR UPDATE');
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $saved = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$saved) throw new RuntimeException('ไม่พบบัญชีผู้ใช้');
        $avatar = $new_avatar ?? (!empty($_POST['remove_avatar']) ? null : $saved['avatar']);
        $cover = $new_cover ?? (!empty($_POST['remove_cover']) ? null : $saved['profile_cover']);
        $stmt = $conn->prepare('UPDATE users SET fullname=?,bio=?,avatar=?,profile_cover=? WHERE user_id=?');
        $stmt->bind_param('ssssi', $fullname, $bio, $avatar, $cover, $uid);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $transaction = false;
        $created = [];
        if ($avatar !== $saved['avatar']) clean_files([$saved['avatar']]);
        if ($cover !== $saved['profile_cover']) clean_files([$saved['profile_cover']]);
        $_SESSION['fullname'] = $fullname;
        flash('แก้ไขโปรไฟล์แล้ว');
        go('profile.php?id=' . $uid);
    } catch (Throwable $error) {
        if ($transaction) $conn->rollback();
        clean_files($created);
        $message = error_message($error);
    }
}
$page_title = 'แก้ไขโปรไฟล์';
require __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="assets/profile.css?v=<?= (int) filemtime(__DIR__ . '/assets/profile.css') ?>">
<section class="panel narrow pbb-profile-edit">
    <h1>แก้ไขโปรไฟล์</h1>
    <?php if ($message): ?><p class="error" role="alert"><?= h($message) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" id="profile-edit-form">
        <?php csrf_field(); ?>
        <label for="fullname">ชื่อที่แสดง</label>
        <input id="fullname" name="fullname" maxlength="100" value="<?= h(is_string($_POST['fullname'] ?? null) ? $_POST['fullname'] : $profile['fullname']) ?>" required>
        <label for="bio">คำแนะนำตัว</label>
        <textarea id="bio" name="bio" maxlength="500" rows="4"><?= h(is_string($_POST['bio'] ?? null) ? $_POST['bio'] : $profile['bio']) ?></textarea>

        <label for="avatar">รูปโปรไฟล์ (JPG / PNG / WebP ไม่เกิน 5 MB)</label>
        <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp">
        <?php if ($profile['avatar']): ?>
            <img class="avatar" src="media.php?user_id=<?= $uid ?>" alt="รูปโปรไฟล์ปัจจุบัน">
            <label class="check"><input name="remove_avatar" value="1" type="checkbox">ลบรูปโปรไฟล์เดิม</label>
        <?php endif; ?>

        <section id="profile-cover" class="pbb-cover-settings">
            <h2>ภาพปกโปรไฟล์</h2>
            <?php if (!empty($profile['profile_cover'])): ?>
                <img class="pbb-cover-preview" id="pbb-cover-current" src="profile_cover.php?id=<?= $uid ?>" alt="ภาพปกปัจจุบัน">
                <button class="btn secondary small" type="button" id="pbb-cover-reopen">จัดตำแหน่งภาพปกเดิม</button>
                <label class="check"><input id="pbb-cover-remove" name="remove_cover" value="1" type="checkbox">ลบภาพปกเดิม</label>
            <?php endif; ?>
            <label for="pbb-cover-file">เลือกภาพปกใหม่</label>
            <input id="pbb-cover-file" type="file" name="profile_cover" accept="image/jpeg,image/png,image/webp">
            <p class="muted">JPG / PNG / WebP ไม่เกิน 5 MB เลือกรูปแล้วลากหรือซูมเพื่อจัดภาพปก</p>
            <div id="pbb-cover-editor" class="pbb-cover-editor" hidden>
                <canvas id="pbb-cover-canvas" width="1600" height="600" aria-label="ตัวอย่างภาพปกโปรไฟล์"></canvas>
                <div class="pbb-cover-controls">
                    <label for="pbb-cover-zoom">ซูม<input id="pbb-cover-zoom" type="range" min="1" max="3" step="0.01" value="1.1"></label>
                    <label for="pbb-cover-x">ตำแหน่งแนวนอน<input id="pbb-cover-x" type="range" min="0" max="100" step="0.1" value="50"></label>
                    <label for="pbb-cover-y">ตำแหน่งแนวตั้ง<input id="pbb-cover-y" type="range" min="0" max="100" step="0.1" value="50"></label>
                </div>
                <div class="actions">
                    <button class="btn secondary" type="button" id="pbb-cover-reset">จัดกึ่งกลาง</button>
                    <button class="btn secondary" type="button" id="pbb-cover-cancel">ยกเลิกการปรับ</button>
                    <button class="btn" type="button" id="pbb-cover-apply">ใช้ภาพนี้</button>
                </div>
            </div>
            <canvas id="pbb-cover-applied" class="pbb-cover-preview" width="1600" height="600" aria-label="ภาพปกที่จัดตำแหน่งแล้ว" role="img" hidden></canvas>
            <p id="pbb-cover-status" role="status" aria-live="polite"></p>
        </section>
        <div class="actions">
            <a class="btn secondary" href="profile.php?id=<?= $uid ?>">ยกเลิก</a>
            <button class="btn" type="submit">บันทึกโปรไฟล์</button>
        </div>
    </form>
</section>
<script src="assets/profile-cover.js?v=<?= (int) filemtime(__DIR__ . '/assets/profile-cover.js') ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>

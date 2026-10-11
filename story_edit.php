<?php
require __DIR__ . '/includes/init.php';
require_login();

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    not_found();
}
$memberId = user_id();

function story_owner_record($conn, $id, $memberId, $lock = false)
{
    $sql = 'SELECT * FROM stories WHERE story_id = ? AND user_id = ?';
    if ($lock) {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id, $memberId);
    $stmt->execute();
    $record = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $record;
}

function story_remove_video_file($filename)
{
    if (!is_string($filename) || !preg_match('/\A[a-f0-9]{48}\.(mp4|webm)\z/', $filename)) {
        return;
    }
    $path = UPLOAD_DIR . '/story-videos/' . $filename;
    if (is_file($path) && !is_link($path) && !@unlink($path)) {
        error_log('Story file cleanup failed: ' . $filename);
    }
}

function story_save_replacement($file)
{
        $video = $file;

        if (!$video || !is_array($video)) {
            throw new RuntimeException('กรุณาเลือกคลิปวิดีโอ');
        }

        $uploadError = $video['error'] ?? null;

        if (!is_int($uploadError)) {
            throw new RuntimeException('ข้อมูลไฟล์ไม่ถูกต้อง');
        }

        if (
            $uploadError === UPLOAD_ERR_INI_SIZE ||
            $uploadError === UPLOAD_ERR_FORM_SIZE
        ) {
            throw new RuntimeException(
                'ไฟล์ใหญ่เกินค่าที่เซิร์ฟเวอร์อนุญาต กรุณาลองคลิปขนาดเล็กก่อน'
            );
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'อัปโหลดไม่สำเร็จ กรุณาเลือกไฟล์แล้วลองใหม่'
            );
        }

        $tmp = $video['tmp_name'] ?? '';

        if (!is_string($tmp) || !is_uploaded_file($tmp)) {
            throw new RuntimeException('ไฟล์อัปโหลดไม่ถูกต้อง');
        }

        $size = filesize($tmp);

        if ($size === false || $size < 1 || $size > 50 * 1024 * 1024) {
            throw new RuntimeException(
                'คลิปต้องมีขนาดมากกว่า 0 และไม่เกิน 50 MB'
            );
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);

        $allowed = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
        ];

        if (!isset($allowed[$mime])) {
            throw new RuntimeException(
                'รองรับเฉพาะ MP4 และ WebM หากเป็น MOV ให้แปลงเป็น MP4 ก่อน'
            );
        }

        $directory = UPLOAD_DIR . '/story-videos';

        if (
            !is_dir($directory) &&
            !mkdir($directory, 0750, true) &&
            !is_dir($directory)
        ) {
            throw new RuntimeException('สร้างโฟลเดอร์เก็บคลิปไม่ได้');
        }

        $filename = bin2hex(random_bytes(24)) . '.' . $allowed[$mime];
        $destination = $directory . '/' . $filename;

        if (!move_uploaded_file($tmp, $destination)) {
            throw new RuntimeException(
                'บันทึกคลิปไม่ได้ กรุณาตรวจสิทธิ์โฟลเดอร์เก็บไฟล์'
            );
        }

        return [$filename, $mime, (int) $size];
}

$story = story_owner_record($conn, $id, $memberId);
if (!$story) {
    not_found();
}
$error = '';
$mode = ($_GET['mode'] ?? '') === 'delete' ? 'delete' : 'edit';
$title = $story['title'];
$caption = $story['caption'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newFilename = null;
    $inTransaction = false;
    try {
        if (empty($_POST) && empty($_FILES)) {
            throw new RuntimeException('ไฟล์อาจใหญ่เกินค่าที่ PHP อนุญาต กรุณาลองคลิปขนาดเล็กก่อน');
        }
        check_csrf();
        $action = $_POST['action'] ?? '';
        if (!is_string($action) || !in_array($action, ['save', 'delete'], true)) {
            throw new RuntimeException('คำสั่งไม่ถูกต้อง');
        }
        $mode = $action === 'delete' ? 'delete' : 'edit';

        if ($action === 'save') {
            $title = text_value('title', 150, true);
            $caption = text_value('caption', 1000);
            $video = $_FILES['video'] ?? null;
            if ($video !== null) {
                if (!is_array($video) || !isset($video['error']) || !is_int($video['error'])) {
                    throw new RuntimeException('ข้อมูลไฟล์ไม่ถูกต้อง');
                }
                if ($video['error'] !== UPLOAD_ERR_NO_FILE) {
                    [$newFilename, $newMime, $newSize] = story_save_replacement($video);
                }
            }
        }

        $conn->begin_transaction();
        $inTransaction = true;
        $current = story_owner_record($conn, $id, $memberId, true);
        if (!$current) {
            throw new RuntimeException('ไม่พบสตอรี่หรือไม่มีสิทธิ์แก้ไข');
        }
        $oldFilename = $current['filename'];

        if ($action === 'delete') {
            $stmt = $conn->prepare('DELETE FROM story_likes WHERE story_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('DELETE FROM stories WHERE story_id = ? AND user_id = ?');
            $stmt->bind_param('ii', $id, $memberId);
        } elseif ($newFilename !== null) {
            $stmt = $conn->prepare(
                'UPDATE stories SET title = ?, caption = ?, filename = ?, mime_type = ?, file_size = ?
                 WHERE story_id = ? AND user_id = ?'
            );
            $stmt->bind_param('ssssiii', $title, $caption, $newFilename, $newMime, $newSize, $id, $memberId);
        } else {
            $stmt = $conn->prepare('UPDATE stories SET title = ?, caption = ? WHERE story_id = ? AND user_id = ?');
            $stmt->bind_param('ssii', $title, $caption, $id, $memberId);
        }
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $inTransaction = false;
        $replacedVideo = $newFilename !== null;
        // ฐานข้อมูลบันทึกแล้ว: เก็บคลิปใหม่ไว้ และลบคลิปเก่าหลัง commit เท่านั้น
        $newFilename = null;
        if ($action === 'delete' || $replacedVideo) {
            story_remove_video_file($oldFilename);
        }
        go('stories.php');
    } catch (Throwable $e) {
        if ($inTransaction) {
            $conn->rollback();
        }
        if ($newFilename !== null) {
            story_remove_video_file($newFilename);
        }
        if ($e instanceof RuntimeException && !($e instanceof mysqli_sql_exception)) {
            $error = $e->getMessage();
        } else {
            error_log('Story management: ' . $e->getMessage());
            $error = 'บันทึกไม่สำเร็จ กรุณาลองอีกครั้ง';
        }
    }
}

$page_title = $mode === 'delete' ? 'ลบสตอรี่' : 'แก้ไขสตอรี่';
require __DIR__ . '/includes/header.php';
?>
<section class="panel">
    <h1><?= h($page_title) ?></h1>
    <?php if ($error !== ''): ?>
        <p role="alert"><?= h($error) ?></p>
    <?php endif; ?>

    <p>คลิปปัจจุบัน: <?= h($story['title']) ?></p>
    <video controls playsinline preload="metadata" width="280"
           src="story_video.php?id=<?= (int) $id ?>">
        เบราว์เซอร์นี้ไม่รองรับการเล่นวิดีโอ
    </video>

    <?php if ($mode === 'delete'): ?>
        <p>ยืนยันลบสตอรี่นี้หรือไม่? คลิปและยอดกดใจของสตอรี่นี้จะถูกลบ และกู้คืนไม่ได้</p>
        <form action="story_edit.php?id=<?= (int) $id ?>&amp;mode=delete" method="post">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn">ยืนยันลบสตอรี่</button>
            <a href="stories.php" class="btn">ยกเลิก</a>
        </form>
    <?php else: ?>
        <form action="story_edit.php?id=<?= (int) $id ?>" method="post" enctype="multipart/form-data">
            <?php csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <p>
                <label for="story-title">ชื่อสตอรี่</label>
                <input id="story-title" type="text" name="title" maxlength="150"
                       value="<?= h($title) ?>" required>
            </p>
            <p>
                <label for="story-caption">คำบรรยาย</label>
                <textarea id="story-caption" name="caption" maxlength="1000" rows="4"><?= h($caption) ?></textarea>
            </p>
            <p>
                <label for="story-video">เปลี่ยนคลิปวิดีโอ</label>
                <input id="story-video" type="file" name="video" accept="video/mp4,video/webm">
            </p>
            <p>ไม่เลือกไฟล์ใหม่จะใช้คลิปเดิม รองรับ MP4 และ WebM ไม่เกิน 50 MB</p>
            <button type="submit" class="btn">บันทึกการแก้ไข</button>
            <a href="stories.php" class="btn">ยกเลิก</a>
        </form>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>

<label for="title">ชื่อทริป *</label><input id="title" name="title" maxlength="150" value="<?= h(
    $_POST["title"] ?? ($trip["title"] ?? ""),
) ?>" required>
<label for="description">คำอธิบาย</label><textarea id="description" name="description" maxlength="5000" rows="4"><?= h(
    $_POST["description"] ?? ($trip["description"] ?? ""),
) ?></textarea>
<div class="form-grid">
<div><label for="start">วันที่เริ่มต้น *</label><input id="start" type="date" name="start_date" min="1900-01-01" max="2200-12-31" value="<?= h(
    $_POST["start_date"] ?? ($trip["start_date"] ?? ""),
) ?>" required></div>
<div><label for="end">วันที่สิ้นสุด *</label><input id="end" type="date" name="end_date" min="1900-01-01" max="2200-12-31" value="<?= h(
    $_POST["end_date"] ?? ($trip["end_date"] ?? ""),
) ?>" required></div>
</div>
<label for="category">ประเภทการท่องเที่ยว</label><select id="category" name="category_id" required><?php foreach (
    $categories
    as $c
): ?><option value="<?= (int) $c["category_id"] ?>" <?= (int) ($_POST[
    "category_id"
] ??
    ($trip["category_id"] ?? 1)) === (int) $c["category_id"]
    ? "selected"
    : "" ?>><?= h($c["name"]) ?></option><?php endforeach; ?></select>

<div class="cover-crop" id="cover-crop">
    <label for="cover">
        <?= empty($trip["trip_id"])
            ? "รูปหน้าปก *"
            : "เปลี่ยนรูปหน้าปก (ไม่เลือกจะใช้รูปเดิม)" ?>
    </label>

    <input
        id="cover"
        type="file"
        name="cover"
        accept="image/jpeg,image/png,image/webp"
        <?= empty($trip["trip_id"]) ? "required" : "" ?>
    >

    <p class="muted">
        JPG / PNG / WebP ไม่เกิน 5 MB ต่อไฟล์
        · เลือกรูปแล้วลากหรือซูมเพื่อจัดหน้าปก
    </p>

    <?php if (!empty($trip["trip_id"])): ?>
        <div class="cover-crop-current">
    <p>รูปปกที่บันทึกไว้ปัจจุบัน</p>

    <div class="cover-image-wrap">
        <img
            class="preview"
            id="cover-current"
            src="media.php?trip_id=<?= (int) $trip["trip_id"] ?>"
            alt="รูปปกที่บันทึกไว้ปัจจุบัน"
        >

        <button
    class="btn secondary cover-edit-icon"
    type="button"
    id="cover-edit-current"
    aria-label="ปรับตำแหน่งรูปปกเดิม"
    title="ปรับตำแหน่งรูปปกเดิม"
>
    <img
        src="images/crop-icon.png"
        alt=""
        aria-hidden="true"
    >
</button>
    </div>
</div>
    <?php endif; ?>

    <div class="cover-crop-editor" id="cover-editor" hidden>
        <p id="cover-help">
            ลากภาพเพื่อจัดตำแหน่ง หรือใช้แถบเลื่อนด้านล่าง
            รูปจะถูกครอปตามกรอบแนวนอน 16:9
        </p>

        <canvas
            id="cover-canvas"
            width="1280"
            height="720"
            aria-label="ตัวอย่างส่วนของรูปที่จะใช้เป็นปก"
            aria-describedby="cover-help"
        ></canvas>

        <div class="cover-crop-controls">
            <label for="cover-zoom">
                ซูม
                <input
                    id="cover-zoom"
                    type="range"
                    min="1"
                    max="3"
                    step="0.01"
                    value="1"
                >
            </label>

            <label for="cover-x">
                ตำแหน่งแนวนอน
                <input
                    id="cover-x"
                    type="range"
                    min="0"
                    max="100"
                    step="0.1"
                    value="50"
                >
            </label>

            <label for="cover-y">
                ตำแหน่งแนวตั้ง
                <input
                    id="cover-y"
                    type="range"
                    min="0"
                    max="100"
                    step="0.1"
                    value="50"
                >
            </label>
        </div>

        <div class="actions">
            <button class="btn secondary" type="button" id="cover-reset">
                จัดกึ่งกลาง
            </button>

            <button class="btn secondary" type="button" id="cover-cancel">
                ยกเลิกการปรับ
            </button>

            <button class="btn" type="button" id="cover-apply">
                ใช้รูปนี้
            </button>
        </div>
    </div>

    <p id="cover-status" role="status" aria-live="polite"></p>
</div>

<script
    src="cover-crop.js?v=<?= (int) filemtime(__DIR__ . '/../cover-crop.js') ?>"
    defer
></script>
<label for="visibility">ความเป็นส่วนตัว</label><select id="visibility" name="visibility"><option value="private" <?= ($_POST[
    "visibility"
] ??
    ($trip["visibility"] ?? "private")) ===
"private"
    ? "selected"
    : "" ?>>ส่วนตัว — เฉพาะฉัน</option><option value="public" <?= ($_POST[
    "visibility"
] ??
    ($trip["visibility"] ?? "private")) ===
"public"
    ? "selected"
    : "" ?>>สาธารณะ — ทุกคนดูได้</option></select>

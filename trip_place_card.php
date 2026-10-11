<?php
$prefix = "place-" . $slot . "-";

$countries = [
    "TH" => "ประเทศไทย",
    "JP" => "ญี่ปุ่น",
    "KR" => "เกาหลีใต้",
    "CN" => "จีน",
    "SG" => "สิงคโปร์",
    "MY" => "มาเลเซีย",
    "VN" => "เวียดนาม",
    "LA" => "ลาว",
    "ID" => "อินโดนีเซีย",
    "FR" => "ฝรั่งเศส",
    "GB" => "สหราชอาณาจักร",
    "US" => "สหรัฐอเมริกา"
];
?>

<article
    class="trip-place-card"
    data-slot="<?= h((string) $slot) ?>"
>
    <div class="place-card-heading">
        <h3 class="place-card-title">
            สถานที่ที่ <?= h((string) $placeNumber) ?>
        </h3>

        <button
            class="btn secondary remove-place-button"
            type="button"
            hidden
        >
            ลบสถานที่นี้
        </button>
    </div>

    <div class="form-grid">
        <div>
            <label for="<?= h($prefix) ?>country">ประเทศ</label>

            <select
                id="<?= h($prefix) ?>country"
                name="places[<?= h((string) $slot) ?>][country_code]"
            >
                <option value="">ยังไม่ระบุประเทศ</option>

                <?php foreach ($countries as $code => $label): ?>
                    <option
                        value="<?= h($code) ?>"
                        <?= ($row["country_code"] ?? "") === $code
                            ? "selected"
                            : "" ?>
                    >
                        <?= h($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="<?= h($prefix) ?>name">ชื่อสถานที่ *</label>

            <input
                id="<?= h($prefix) ?>name"
                name="places[<?= h((string) $slot) ?>][name]"
                maxlength="150"
                value="<?= h($row["name"] ?? "") ?>"
                required
            >
        </div>

        <div>
            <label for="<?= h($prefix) ?>province">จังหวัด *</label>

            <input
                id="<?= h($prefix) ?>province"
                name="places[<?= h((string) $slot) ?>][province]"
                maxlength="100"
                value="<?= h($row["province"] ?? "") ?>"
                required
            >
        </div>

        <div>
            <label for="<?= h($prefix) ?>date">วันที่ไปเยือน *</label>

            <input
                id="<?= h($prefix) ?>date"
                type="date"
                name="places[<?= h((string) $slot) ?>][visit_date]"
                value="<?= h($row["visit_date"] ?? "") ?>"
                required
            >
        </div>

        <div>
            <label for="<?= h($prefix) ?>sort">ลำดับการแสดง</label>

            <input
                id="<?= h($prefix) ?>sort"
                type="number"
                name="places[<?= h((string) $slot) ?>][sort_order]"
                min="1"
                max="1000"
                value="<?= (int) ($row["sort_order"] ?? 1) ?>"
                required
            >
        </div>
    </div>

    <label for="<?= h($prefix) ?>story">
        เรื่องราวของสถานที่นี้
    </label>

    <textarea
        id="<?= h($prefix) ?>story"
        name="places[<?= h((string) $slot) ?>][story]"
        rows="5"
        maxlength="10000"
    ><?= h($row["story"] ?? "") ?></textarea>

    <label for="<?= h($prefix) ?>photos">
        เพิ่มรูปภาพของสถานที่นี้
    </label>

    <input
        id="<?= h($prefix) ?>photos"
        type="file"
        name="photos_<?= h((string) $slot) ?>[]"
        accept="image/jpeg,image/png,image/webp"
        multiple
    >

    <p class="muted">
        ไม่เกิน 10 รูปต่อสถานที่ และไม่เกิน 5 MB ต่อรูป
    </p>
</article>
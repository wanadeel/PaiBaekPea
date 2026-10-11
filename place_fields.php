<div class="form-grid">
<?php
$countryOptions = [
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

$selectedCountry = $_POST["country_code"]
    ?? ($place["country_code"] ?? "");
?>

<div>
    <label for="country_code">ประเทศ</label>

    <select id="country_code" name="country_code">
        <option value="">ยังไม่ระบุประเทศ</option>

        <?php foreach ($countryOptions as $code => $label): ?>
            <option
                value="<?= h($code) ?>"
                <?= $selectedCountry === $code ? "selected" : "" ?>
            >
                <?= h($label) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div><label for="name">ชื่อสถานที่ *</label><input id="name" name="name" maxlength="150" value="<?= h(
    $_POST["name"] ?? ($place["name"] ?? ""),
) ?>" required></div>
<div><label for="province">จังหวัด *</label><input id="province" name="province" maxlength="100" value="<?= h(
    $_POST["province"] ?? ($place["province"] ?? ""),
) ?>" required></div>
<div><label for="visit">วันที่ไปเยือน *</label><input id="visit" type="date" name="visit_date" value="<?= h(
    $_POST["visit_date"] ?? ($place["visit_date"] ?? ""),
) ?>" required></div>
<div><label for="sort">ลำดับการแสดง</label><input id="sort" type="number" min="1" max="1000" name="sort_order" value="<?= (int) ($_POST[
    "sort_order"
] ??
    ($place["sort_order"] ?? 1)) ?>" required></div>
</div>
<label for="story">เรื่องราวของสถานที่นี้</label><textarea id="story" name="story" rows="5" maxlength="10000"><?= h(
    $_POST["story"] ?? ($place["story"] ?? ""),
) ?></textarea>
<label for="photos">เพิ่มรูปภาพ (เลือกหลายรูปได้)</label><input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
<p class="muted">ไม่เกิน 10 รูปต่อสถานที่ และไม่เกิน 5 MB ต่อรูป</p>

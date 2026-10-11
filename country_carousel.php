<?php
// Include from index.php after its search form. No database access in this view.
$mapCountries = [
    '' => ['ทุกประเทศ', 'ALL', 'เลือกจุดหมายที่อยากออกเดินทาง'],
    'TH' => ['ประเทศไทย', 'TH', 'THAILAND'],
    'JP' => ['ญี่ปุ่น', 'JP', 'JAPAN'],
    'KR' => ['เกาหลีใต้', 'KR', 'SOUTH KOREA'],
    'CN' => ['จีน', 'CN', 'CHINA'],
    'SG' => ['สิงคโปร์', 'SG', 'SINGAPORE'],
    'MY' => ['มาเลเซีย', 'MY', 'MALAYSIA'],
    'VN' => ['เวียดนาม', 'VN', 'VIETNAM'],
    'LA' => ['ลาว', 'LA', 'LAOS'],
    'ID' => ['อินโดนีเซีย', 'ID', 'INDONESIA'],
    'FR' => ['ฝรั่งเศส', 'FR', 'FRANCE · พื้นที่ยุโรป'],
    'GB' => ['สหราชอาณาจักร', 'GB', 'UNITED KINGDOM'],
    'US' => ['สหรัฐอเมริกา', 'US', 'USA · 48 รัฐที่ต่อเนื่องกัน']
];
$mapEscape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$mapSelected = $_GET['country'] ?? '';
if (!is_string($mapSelected) || !array_key_exists($mapSelected, $mapCountries)) {
    $mapSelected = '';
}
// Preserve the search and category; discard old province/region when changing country.
$mapQuery = [];
foreach (['q', 'category_id'] as $mapKey) {
    if (isset($_GET[$mapKey]) && is_string($_GET[$mapKey])) {
        $mapQuery[$mapKey] = $_GET[$mapKey];
    }
}
//แผนที่ประเทศ//
?>
<section class="country-carousel" aria-labelledby="country-carousel-title">
    <div class="country-carousel-heading">
        <div>
            <p class="country-carousel-eyebrow">WHERE TO NEXT?</p>
            <h3 id="country-carousel-title">โลกกว้างที่คุณอยากค้นหา</h3>
            <p class="country-carousel-hint">เลื่อนดูประเทศที่ท่านสนใจ</p>
        </div>
        <div class="country-carousel-controls" hidden>
            <button type="button" class="country-carousel-arrow" data-country-prev aria-label="เลื่อนไปประเทศก่อนหน้า" aria-controls="country-carousel-track">&#8592;</button>
            <button type="button" class="country-carousel-arrow" data-country-next aria-label="เลื่อนไปประเทศถัดไป" aria-controls="country-carousel-track">&#8594;</button>
        </div>
    </div>
    <div class="country-carousel-track" id="country-carousel-track" tabindex="0" aria-label="รายการประเทศ เลื่อนด้วยลูกศรซ้ายขวา">
        <?php foreach ($mapCountries as $mapCode => $mapInfo): ?>
            <?php
            $mapParams = $mapQuery;
            if ($mapCode !== '') $mapParams['country'] = $mapCode;
            $mapUrl = 'index.php' . ($mapParams ? '?' . http_build_query($mapParams) : '') . '#country-carousel-title';
            $mapActive = $mapSelected === $mapCode;
            ?>
            <a class="country-map-card<?= $mapActive ? ' is-selected' : '' ?>"
               href="<?= $mapEscape($mapUrl) ?>"
               data-country-code="<?= $mapEscape($mapCode) ?>"
               <?= $mapActive ? 'aria-current="true"' : '' ?>>
                <span class="country-map-label"><?= $mapActive ? 'กำลังเลือก' : 'สำรวจทริป' ?></span>
                <span class="country-map-picture">
                    <img src="assets/country-maps/<?= $mapEscape($mapInfo[1]) ?>.svg" alt="<?= $mapEscape($mapCode === '' ? 'สัญลักษณ์ลูกโลก' : 'แผนที่โดยสังเขปของ' . $mapInfo[0]) ?>" width="420" height="320" decoding="async">
                </span>
                <span class="country-map-name"><?= $mapEscape($mapInfo[0]) ?></span>
                <span class="country-map-caption"><?= $mapEscape($mapInfo[2]) ?></span>
                <span class="country-map-action">ดูทริป <span aria-hidden="true">&#8599;</span></span>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="country-carousel-note"> สามารถปัดจากขวาไปซ้ายเพื่อชมประเทศ</p>
</section>

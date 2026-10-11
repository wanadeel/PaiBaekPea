<?php
require "includes/init.php";
require_login();

$uid = (int) user_id();

$countryNames = [
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
    "US" => "สหรัฐอเมริกา",
];

$monthNames = [
    1 => "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.",
    "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.",
    "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
];

// รับเฉพาะค่าที่ถูกต้อง
$year = filter_var(
    $_GET["year"] ?? date("Y"),
    FILTER_VALIDATE_INT,
    ["options" => ["min_range" => 1900, "max_range" => 2200]]
);

if ($year === false) {
    $year = (int) date("Y");
}

$scope = ($_GET["scope"] ?? "") === "mine" ? "mine" : "all";

$country = $_GET["country"] ?? "";
if (!is_string($country)) {
    $country = "";
}

$from = sprintf("%04d-01-01", $year);
$to = sprintf("%04d-12-31", $year);

// ภาพรวม: เฉพาะสาธารณะ
// ของฉัน: ตรวจ user_id จาก session เท่านั้น
$accessCondition = $scope === "mine"
    ? "t.user_id = ?"
    : "t.visibility = 'public' AND t.moderation_status = 'visible'";

$message = "";
$trips = [];
$countryTrips = [];
$provinceTrips = [];
$categoryCounts = [];
$monthly = array_fill(1, 12, 0);
$tripCountries = [];
$years = [(int) date("Y") => true, $year => true];

try {
    // ปีที่มีข้อมูล ซึ่งผู้ใช้มีสิทธิ์ดู
    $stmt = $conn->prepare(
        "SELECT t.start_date, t.end_date
         FROM trips t
         WHERE $accessCondition"
    );

    if ($scope === "mine") {
        $stmt->bind_param("i", $uid);
    }

    $stmt->execute();
    $dateRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($dateRows as $dateRow) {
        $firstYear = max(
            1900,
            (int) substr($dateRow["start_date"], 0, 4)
        );

        $lastYear = min(
            2200,
            (int) substr($dateRow["end_date"], 0, 4)
        );

        for ($y = $firstYear; $y <= $lastYear; $y++) {
            $years[$y] = true;
        }
    }

    // ทริปที่ช่วงเดินทางทับซ้อนกับปีที่เลือก
    $stmt = $conn->prepare(
        "SELECT t.*, u.fullname, c.name AS category_name
         FROM trips t
         JOIN users u ON u.user_id = t.user_id
         JOIN categories c ON c.category_id = t.category_id
         WHERE $accessCondition
           AND t.start_date <= ?
           AND t.end_date >= ?
         ORDER BY t.start_date DESC, t.trip_id DESC"
    );

    if ($scope === "mine") {
        $stmt->bind_param("iss", $uid, $to, $from);
    } else {
        $stmt->bind_param("ss", $to, $from);
    }

    $stmt->execute();
    $trips = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // สถานที่ที่ไปเยือนจริงในปีนั้น
    $stmt = $conn->prepare(
        "SELECT p.trip_id, p.country_code, p.province
         FROM trip_places p
         JOIN trips t ON t.trip_id = p.trip_id
         WHERE $accessCondition
           AND t.start_date <= ?
           AND t.end_date >= ?
           AND p.visit_date BETWEEN ? AND ?"
    );

    if ($scope === "mine") {
        $stmt->bind_param("issss", $uid, $to, $from, $from, $to);
    } else {
        $stmt->bind_param("ssss", $to, $from, $from, $to);
    }

    $stmt->execute();
    $places = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($places as $placeRow) {
        $id = (int) $placeRow["trip_id"];
        $code = strtoupper(trim((string) $placeRow["country_code"]));
        $province = trim((string) $placeRow["province"]);

        // ใช้ trip_id เป็นคีย์ ป้องกันนับสถานที่ประเทศเดิมซ้ำในทริปเดียว
        if ($code !== "") {
            $countryTrips[$code][$id] = true;
            $tripCountries[$id][$code] = true;
        }

        // ชื่อจังหวัดเดียวกัน แต่คนละประเทศถือเป็นคนละพื้นที่
        if ($province !== "") {
            $provinceKey = $code . "|" . $province;
            $provinceTrips[$provinceKey][$id] = true;
        }
    }

    foreach ($trips as $tripRow) {
        $category = $tripRow["category_name"];
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

        // คร่อมเดือน: นับในทุกเดือนที่ช่วงเดินทางทับซ้อน
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = sprintf("%04d-%02d-01", $year, $month);
            $monthEnd = date("Y-m-t", strtotime($monthStart));

            if (
                $tripRow["start_date"] <= $monthEnd &&
                $tripRow["end_date"] >= $monthStart
            ) {
                $monthly[$month]++;
            }
        }
    }
} catch (Throwable $error) {
    // ไม่แสดงรายละเอียด SQL หรือข้อมูลเชื่อมต่อฐานข้อมูล
    $message = "โหลดข้อมูลแดชบอร์ดไม่สำเร็จ กรุณาลองอีกครั้ง";
}

krsort($years);

$countryCounts = [];
foreach ($countryTrips as $code => $ids) {
    $countryCounts[$code] = count($ids);
}

arsort($countryCounts);
arsort($categoryCounts);

// ประเทศที่เลือกต้องอยู่ในข้อมูลที่ผู้ใช้มีสิทธิ์ดู
if (!array_key_exists($country, $countryCounts)) {
    $country = "";
}

$totalTrips = count($trips);
$totalCountries = count($countryCounts);
$totalProvinces = count($provinceTrips);
$topCode = array_key_first($countryCounts);
$topCountry = $topCode !== null
    ? ($countryNames[$topCode] ?? $topCode)
    : "ยังไม่มีข้อมูล";

$maxCountry = max(1, ...array_values($countryCounts));
$maxMonthly = max(1, ...array_values($monthly));

$knownTripIds = [];
foreach ($countryTrips as $ids) {
    $knownTripIds += $ids;
}

$unknownCountryTrips = $totalTrips - count($knownTripIds);

// ทริปที่เกี่ยวข้อง ใช้เฉพาะชุดข้อมูลที่ตรวจสิทธิ์แล้ว
$relatedTrips = array_values(array_filter(
    $trips,
    function ($tripRow) use ($country, $tripCountries) {
        if ($country === "") {
            return true;
        }

        return isset(
            $tripCountries[(int) $tripRow["trip_id"]][$country]
        );
    }
));

$relatedTotal = count($relatedTrips);
$relatedTrips = array_slice($relatedTrips, 0, 12);

// เตรียมกราฟเส้น SVG
$points = [];
$chartDots = [];

foreach ($monthly as $month => $count) {
    $x = 45 + ($month - 1) * (810 / 11);
    $y = 220 - ($count / $maxMonthly) * 170;

    $points[] = round($x, 2) . "," . round($y, 2);
    $chartDots[$month] = [$x, $y, $count];
}

$page_title = "แดชบอร์ด";
require "includes/header.php";
?>

<section class="page-heading heading-row dashboard-heading">
    <div>
        <p class="eyebrow">YOUR TRAVEL INSIGHTS</p>
        <h1>แดชบอร์ดการเดินทาง</h1>
        <p class="muted">ย้อนดูเส้นทาง และค้นหาแรงบันดาลใจครั้งต่อไป</p>
    </div>

    <div class="dashboard-top-actions">
        <a class="btn" href="add_trip.php">+ สร้างทริปใหม่</a>
        <a class="btn secondary" href="bookmarks.php">ทริปที่บันทึกไว้</a>
    </div>
</section>

<!-- ตัวกรอง -->
<form class="filters dashboard-filters" method="get" action="dashboard.php">
    <label for="dashboard-year">
        ปีที่เดินทาง
        <select id="dashboard-year" name="year">
            <?php foreach ($years as $optionYear => $unused): ?>
                <option
                    value="<?= (int) $optionYear ?>"
                    <?= $year === (int) $optionYear ? "selected" : "" ?>
                >
                    <?= (int) $optionYear + 543 ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label for="dashboard-scope">
        ชุดข้อมูล
        <select id="dashboard-scope" name="scope">
            <option value="all" <?= $scope === "all" ? "selected" : "" ?>>
                ภาพรวมทั้งเว็บ — ทริปสาธารณะ
            </option>
            <option value="mine" <?= $scope === "mine" ? "selected" : "" ?>>
                ทริปของฉัน — สาธารณะและส่วนตัว
            </option>
        </select>
    </label>

    <button class="btn" type="submit">แสดงข้อมูล</button>
</form>

<?php if ($message !== ""): ?>
    <p class="error" role="alert"><?= h($message) ?></p>
<?php else: ?>

    <p class="dashboard-context">
        <?= $scope === "mine" ? "ทริปของฉัน" : "ภาพรวมทริปสาธารณะทั้งเว็บ" ?>
        · ปี <?= $year + 543 ?>
    </p>

    <!-- การ์ดสรุป -->
    <div class="dashboard-stats">
        <article class="dashboard-stat">
            <p>จำนวนทริป</p>
            <strong><?= $totalTrips ?></strong>
            <span>ทริปที่เดินทางในปีนี้</span>
        </article>

        <article class="dashboard-stat">
            <p>ประเทศที่ไปเยือน</p>
            <strong><?= $totalCountries ?></strong>
            <span>ประเทศที่มีรหัสประเทศระบุไว้</span>
        </article>

        <article class="dashboard-stat">
            <p>จังหวัด / พื้นที่</p>
            <strong><?= $totalProvinces ?></strong>
            <span>จากสถานที่ที่ไปเยือนในปีนี้</span>
        </article>

        <article class="dashboard-stat">
            <p>ประเทศที่มีทริปมากที่สุด</p>
            <strong class="dashboard-stat-country">
                <?= h($topCountry) ?>
            </strong>
            <span>
                <?= $topCode !== null ? $countryCounts[$topCode] . " ทริป" : "—" ?>
            </span>
        </article>
    </div>

    <div class="dashboard-chart-grid">
        <!-- ประเทศยอดนิยม -->
        <section class="panel dashboard-panel">
            <h2>ประเทศที่มีทริปมากที่สุด</h2>
            <p class="muted">กดชื่อประเทศเพื่อดูทริปที่เกี่ยวข้องด้านล่าง</p>

            <?php if (!$countryCounts): ?>
                <p class="dashboard-empty">
                    ยังไม่มีข้อมูลประเทศจากสถานที่ที่ไปเยือนในปีนี้
                </p>
            <?php else: ?>
                <div class="dashboard-country-bars">
                    <?php foreach ($countryCounts as $code => $count): ?>
                        <?php
                        $percentage = $totalTrips > 0
                            ? $count / $totalTrips * 100
                            : 0;

                        $width = $count / $maxCountry * 100;

                        $url = "dashboard.php?" . http_build_query([
                            "year" => $year,
                            "scope" => $scope,
                            "country" => $code,
                        ]) . "#dashboard-related";
                        ?>

                        <a
                            class="dashboard-country <?= $country === $code ? "is-selected" : "" ?>"
                            href="<?= h($url) ?>"
                        >
                            <div class="dashboard-bar-label">
                                <strong><?= h($countryNames[$code] ?? $code) ?></strong>
                                <span>
                                    <?= $count ?> ทริป
                                    (<?= number_format($percentage, 1) ?>%)
                                </span>
                            </div>

                            <div class="dashboard-bar-track" aria-hidden="true">
                                <span data-bar-width="<?= round($width, 2) ?>"></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="dashboard-note">
                จัดอันดับที่ผู้คนไปมากที่สุด
            </p>

            <?php if ($unknownCountryTrips > 0): ?>
                <p class="dashboard-note">
                    <?= $unknownCountryTrips ?> ทริปไม่มีสถานที่ที่ระบุประเทศ
                    และวันที่ไปเยือนในปีนี้ จึงไม่อยู่ในแท่งประเทศ
                </p>
            <?php endif; ?>
        </section>

        <!-- สัดส่วนหมวดหมู่ -->
        <section class="panel dashboard-panel">
            <h2>สัดส่วนประเภททริป</h2>
            <p class="muted">ใช้หมวดหมู่ที่มีอยู่จริงในเว็บไซต์</p>

            <?php if (!$categoryCounts): ?>
                <p class="dashboard-empty">ยังไม่มีทริปในปีที่เลือก</p>
            <?php else: ?>
                <?php foreach ($categoryCounts as $name => $count): ?>
                    <?php $percentage = $count / $totalTrips * 100; ?>

                    <div class="dashboard-category">
                        <div class="dashboard-bar-label">
                            <strong><?= h($name) ?></strong>
                            <span>
                                <?= $count ?> ทริป
                                (<?= number_format($percentage, 1) ?>%)
                            </span>
                        </div>

                        <div class="dashboard-bar-track" aria-hidden="true">
                            <span data-bar-width="<?= round($percentage, 2) ?>"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>

    <!-- กราฟรายเดือน -->
    <section class="panel dashboard-panel">
        <h2>จำนวนทริปในแต่ละเดือน</h2>
        <p class="muted">
            ทริปที่คร่อมหลายเดือนนับในทุกเดือนที่เดินทาง
        </p>

        <?php if ($totalTrips === 0): ?>
            <p class="dashboard-empty">ยังไม่มีทริปในปีที่เลือก</p>
        <?php else: ?>
            <div class="dashboard-line-chart">
                <svg
                    viewBox="0 0 900 280"
                    role="img"
                    aria-labelledby="dashboard-line-title dashboard-line-desc"
                >
                    <title id="dashboard-line-title">
                        จำนวนทริปรายเดือน ปี <?= $year + 543 ?>
                    </title>

                    <desc id="dashboard-line-desc">
                        ตัวเลขแต่ละเดือนแสดงในตารางใต้กราฟ
                    </desc>

                    <?php for ($tick = 0; $tick <= 4; $tick++): ?>
                        <?php
                        $gridY = 220 - $tick * 42.5;
                        $gridCount = $maxMonthly * $tick / 4;
                        ?>

                        <line
                            x1="45"
                            y1="<?= $gridY ?>"
                            x2="855"
                            y2="<?= $gridY ?>"
                            class="dashboard-grid-line"
                        />

                        <text
                            x="35"
                            y="<?= $gridY + 5 ?>"
                            text-anchor="end"
                            class="dashboard-axis-text"
                        >
                            <?= number_format($gridCount, 1) ?>
                        </text>
                    <?php endfor; ?>

                    <polyline
                        points="<?= implode(" ", $points) ?>"
                        class="dashboard-line"
                    />

                    <?php foreach ($chartDots as $month => [$x, $y, $count]): ?>
                        <circle
                            cx="<?= round($x, 2) ?>"
                            cy="<?= round($y, 2) ?>"
                            r="5"
                            class="dashboard-dot"
                        >
                            <title>
                                <?= h($monthNames[$month]) ?>: <?= $count ?> ทริป
                            </title>
                        </circle>

                        <text
                            x="<?= round($x, 2) ?>"
                            y="<?= round($y - 12, 2) ?>"
                            text-anchor="middle"
                            class="dashboard-value-text"
                        >
                            <?= $count ?>
                        </text>

                        <text
                            x="<?= round($x, 2) ?>"
                            y="252"
                            text-anchor="middle"
                            class="dashboard-axis-text"
                        >
                            <?= h($monthNames[$month]) ?>
                        </text>
                    <?php endforeach; ?>
                </svg>
            </div>

            <div class="dashboard-table-wrap">
                <table class="dashboard-month-table">
                    <caption>จำนวนทริปแยกตามเดือน</caption>
                    <thead>
                        <tr>
                            <th scope="col">เดือน</th>
                            <?php foreach ($monthNames as $name): ?>
                                <th scope="col"><?= h($name) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <th scope="row">ทริป</th>
                            <?php foreach ($monthly as $count): ?>
                                <td><?= $count ?></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <!-- ทริปที่เกี่ยวข้อง -->
    <section id="dashboard-related">
        <div class="section-heading dashboard-related-heading">
            <h2>
                <?php if ($country !== ""): ?>
                    ทริปใน <?= h($countryNames[$country] ?? $country) ?>
                <?php else: ?>
                    <?= $scope === "mine" ? "ทริปล่าสุดของฉันในปีนี้" : "ทริปที่เกี่ยวข้องในปีนี้" ?>
                <?php endif; ?>
            </h2>

            <?php if ($country !== ""): ?>
                <a
                    class="btn secondary small"
                    href="<?= h("dashboard.php?" . http_build_query([
                        "year" => $year,
                        "scope" => $scope,
                    ]) . "#dashboard-related") ?>"
                >
                    ดูทุกประเทศ
                </a>
            <?php endif; ?>
        </div>

        <p class="dashboard-context">
            พบ <?= $relatedTotal ?> ทริป · แสดงสูงสุด 12 ทริป
        </p>

        <?php if (!$relatedTrips): ?>
            <div class="panel empty">
                <h2>ยังไม่มีทริป</h2>
                <p>ลองเลือกปีอื่น หรือเริ่มบันทึกการเดินทางของคุณ</p>
                <a class="btn" href="add_trip.php">+ สร้างทริป</a>
            </div>
        <?php else: ?>
            <div class="trip-grid">
                <?php foreach ($relatedTrips as $journey): ?>
                    <article class="trip-card">
                        <a href="trip_detail.php?id=<?= (int) $journey["trip_id"] ?>">
                            <img
                                class="card-cover"
                                src="media.php?trip_id=<?= (int) $journey["trip_id"] ?>"
                                alt="<?= h($journey["title"]) ?>"
                                loading="lazy"
                            >
                        </a>

                        <div class="card-body">
                            <span class="badge">
                                <?= $journey["visibility"] === "private"
                                    ? "ส่วนตัว"
                                    : "สาธารณะ" ?>
                            </span>

                            <p class="muted"><?= h($journey["category_name"]) ?></p>

                            <h2>
                                <a href="trip_detail.php?id=<?= (int) $journey["trip_id"] ?>">
                                    <?= h($journey["title"]) ?>
                                </a>
                            </h2>

                            <p class="excerpt"><?= h($journey["description"]) ?></p>

                            <p class="muted">
                                <?= h($journey["start_date"]) ?>
                                — <?= h($journey["end_date"]) ?>
                            </p>

                            <a
                                class="author"
                                href="profile.php?id=<?= (int) $journey["user_id"] ?>"
                            >
                                โดย <?= h($journey["fullname"]) ?>
                            </a>

                            <?php if ((int) $journey["user_id"] === $uid): ?>
                                <div class="actions">
                                    <a
                                        class="btn secondary small"
                                        href="edit_trip.php?id=<?= (int) $journey["trip_id"] ?>"
                                    >
                                        แก้ไขทริป
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

<?php endif; ?>
<script
    src="dashboard-animation.js?v=<?= (int) filemtime(__DIR__ . '/dashboard-animation.js') ?>"
    defer
></script>

<?php require "includes/footer.php"; ?>

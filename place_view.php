<?php
require "includes/init.php";
$place_id = (int) ($_GET["id"] ?? 0);
[$place, $trip] = get_place($place_id);
$photos = photos_for($place_id);
$page_title = $place["name"];
require "includes/header.php";
?>
<a class="back-link" href="trip_detail.php?id=<?= (int) $trip[
    "trip_id"
] ?>">← กลับทริป <?= h($trip["title"]) ?></a>
<section class="panel"><p class="eyebrow"><?= h(
    $place["visit_date"],
) ?></p><h1><?= h($place["name"]) ?></h1><p class="muted"><?= h(
    $place["province"],
) ?></p><p class="story"><?= h(
    $place["story"],
) ?></p><div class="photo-grid"><?php foreach (
    $photos
    as $photo
): ?><a href="media.php?photo_id=<?= (int) $photo[
    "photo_id"
] ?>" target="_blank" rel="noopener"><img src="media.php?photo_id=<?= (int) $photo[
    "photo_id"
] ?>" alt="<?= h($place["name"]) ?>"></a><?php endforeach; ?></div></section>
<?php require "includes/footer.php"; ?>

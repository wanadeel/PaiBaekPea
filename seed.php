<?php
// รันจาก Terminal เท่านั้น: php database/seed.php
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require dirname(__DIR__) . "/includes/init.php";
if ($conn->query("SELECT user_id FROM users LIMIT 1")->num_rows) {
    exit("มีสมาชิกแล้ว ไม่เพิ่มข้อมูลซ้ำหรือเขียนทับข้อมูลเดิม\n");
}
$created = [];
try {
    $conn->begin_transaction();
    $password = password_hash("DemoTrip123!", PASSWORD_DEFAULT);
    foreach (
        [
            [
                "ter_travels",
                "ter@example.com",
                "เตอร์ ระหว่างทาง",
                "เด็กไอทีที่ชอบพักจากหน้าจอแล้วออกไปเที่ยว",
            ],
            [
                "mali_journey",
                "mali@example.com",
                "มะลิ ไปเรื่อย",
                "เก็บเรื่องราวเล็ก ๆ ระหว่างทาง",
            ],
        ]
        as $u
    ) {
        [$username, $email, $fullname, $bio] = $u;
        $stmt = $conn->prepare(
            "INSERT INTO users(username,email,password,fullname,bio) VALUES(?,?,?,?,?)",
        );
        $stmt->bind_param(
            "sssss",
            $username,
            $email,
            $password,
            $fullname,
            $bio,
        );
        $stmt->execute();
        $ids[] = $conn->insert_id;
        $stmt->close();
    }
    $examples = [
        [
            $ids[0],
            1,
            "เชียงใหม่ ในวันที่อยากเดินช้าลง",
            "ฝากความเหนื่อยไว้กับภูเขา",
            "2026-08-30",
            "2026-09-02",
            "public",
            "chiangmai.jpg",
            [
                [
                    "ดอยสุเทพ",
                    "เชียงใหม่",
                    "2026-08-30",
                    "มองแสงสุดท้ายบนภูเขาแล้วรู้สึกเหมือนได้พักจากทุกอย่าง",
                ],
                [
                    "เดินเล่นในเมืองเก่า",
                    "เชียงใหม่",
                    "2026-09-01",
                    "เดินตามตรอกเล็ก ๆ แล้วแวะจดบันทึกในร้านกาแฟ",
                ],
            ],
        ],
        [
            $ids[1],
            2,
            "กระบี่ มีทะเลเป็นเพื่อน",
            "ฟังเสียงคลื่นมากกว่าเสียงแจ้งเตือน",
            "2026-07-18",
            "2026-07-20",
            "public",
            "krabi.jpg",
            [
                [
                    "ชายหาดกระบี่",
                    "กระบี่",
                    "2026-07-18",
                    "เดินบนหาดทราย มองเรือหางยาว แล้วปล่อยเวลาให้ผ่านไปช้า ๆ",
                ],
            ],
        ],
        [
            $ids[0],
            3,
            "หนึ่งวันในเมืองเก่า",
            "ออกไปมองกรุงเทพฯ ในมุมใหม่",
            "2026-06-14",
            "2026-06-14",
            "public",
            "bangkok.jpg",
            [
                [
                    "ย่านเมืองเก่า",
                    "กรุงเทพมหานคร",
                    "2026-06-14",
                    "บางครั้งเราไม่ต้องเดินทางไกลเพื่อพบสิ่งใหม่",
                ],
            ],
        ],
        [
            $ids[0],
            1,
            "ความทรงจำส่วนตัวบนดอย",
            "ใช้ทดสอบสิทธิ์ของเตอร์",
            "2026-01-31",
            "2026-02-02",
            "private",
            "chiangmai.jpg",
            [
                [
                    "มุมสงบบนดอย",
                    "เชียงใหม่",
                    "2026-02-01",
                    "เรื่องราวนี้เฉพาะเจ้าของเท่านั้นที่อ่านได้",
                ],
            ],
        ],
        [
            $ids[1],
            2,
            "วันพักใจริมทะเล",
            "ใช้ทดสอบสิทธิ์ของมะลิ",
            "2026-05-02",
            "2026-05-03",
            "private",
            "krabi.jpg",
            [["ริมทะเลยามเย็น", "กระบี่", "2026-05-02", "ความทรงจำส่วนตัว"]],
        ],
    ];
    $tripIds = [];
    foreach ($examples as $example) {
        [
            $user_id,
            $category_id,
            $title,
            $description,
            $start,
            $end,
            $visibility,
            $asset,
            $places,
        ] = $example;
        $cover = bin2hex(random_bytes(24)) . ".jpg";
        if (
            !copy(
                dirname(__DIR__) . "/assets/" . $asset,
                UPLOAD_DIR . "/" . $cover,
            )
        ) {
            throw new RuntimeException("คัดลอกรูปตัวอย่างไม่ได้");
        }
        $created[] = $cover;
        $stmt = $conn->prepare(
            "INSERT INTO trips(user_id,category_id,title,description,start_date,end_date,visibility,cover) VALUES(?,?,?,?,?,?,?,?)",
        );
        $stmt->bind_param(
            "iissssss",
            $user_id,
            $category_id,
            $title,
            $description,
            $start,
            $end,
            $visibility,
            $cover,
        );
        $stmt->execute();
        $trip_id = $conn->insert_id;
        $tripIds[] = $trip_id;
        $stmt->close();
        foreach ($places as $i => $p) {
            [$name, $province, $visit, $story] = $p;
            $sort = $i + 1;
            $stmt = $conn->prepare(
                "INSERT INTO trip_places(trip_id,name,province,visit_date,story,sort_order) VALUES(?,?,?,?,?,?)",
            );
            $stmt->bind_param(
                "issssi",
                $trip_id,
                $name,
                $province,
                $visit,
                $story,
                $sort,
            );
            $stmt->execute();
            $place_id = $conn->insert_id;
            $stmt->close();
            if ($i === 0) {
                $filename = bin2hex(random_bytes(24)) . ".jpg";
                if (
                    !copy(
                        dirname(__DIR__) . "/assets/" . $asset,
                        UPLOAD_DIR . "/" . $filename,
                    )
                ) {
                    throw new RuntimeException("คัดลอกรูปตัวอย่างไม่ได้");
                }
                $created[] = $filename;
                $stmt = $conn->prepare(
                    "INSERT INTO place_photos(place_id,filename) VALUES(?,?)",
                );
                $stmt->bind_param("is", $place_id, $filename);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
    $stmt = $conn->prepare(
        "INSERT INTO bookmarks(user_id,trip_id) VALUES(?,?)",
    );
    $stmt->bind_param("ii", $ids[0], $tripIds[1]);
    $stmt->execute();
    $stmt->close();
    $conn->commit();
    echo "สร้างสมาชิก 2 บัญชี ทริปสาธารณะ 3 และส่วนตัว 2 สำเร็จ\n";
} catch (Throwable $error) {
    $conn->rollback();
    clean_files($created);
    fwrite(STDERR, "สร้างตัวอย่างไม่สำเร็จ: " . $error->getMessage() . "\n");
    exit(1);
}

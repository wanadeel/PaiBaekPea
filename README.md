# ฐานข้อมูล

users (user_id PK, username/email UNIQUE) → trips (trip_id PK, user_id/category_id FK) → trip_places (place_id PK, trip_id FK) → place_photos (photo_id PK, place_id FK, filename UNIQUE)

categories 1:N trips; users M:N trips ผ่าน bookmarks ซึ่งใช้ composite primary key (user_id,trip_id) ป้องกันซ้ำ มี ON DELETE CASCADE สำหรับทริป สถานที่ รูป และ Bookmark

cover อยู่ใน trips และ avatar อยู่ใน users เป็นชื่อไฟล์สุ่มของระบบ เก็บไฟล์จริงนอก web root ทุกภาพเข้าผ่าน media.php

validation วันและอย่างน้อยหนึ่งสถานที่ตรวจใน PHP ช่วงวันที่ ISO ใช้ start_date <= filter_end AND end_date >= filter_start จึงรวมทริปคร่อมเดือน ไม่มีการอ้าง created_at เพื่อกรองการเดินทาง

schema.sql เป็น schema ใหม่ ไม่ใช่ migration ฐานข้อมูล e_learning_db; ไม่ย้ายบัญชีหรือรหัสผ่านจากเว็บเดิมอัตโนมัติ

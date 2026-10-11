-- เลือกฐานข้อมูล PaiBaekPea ใน phpMyAdmin แล้วรันครั้งเดียว
ALTER TABLE users ADD COLUMN profile_cover VARCHAR(100) NULL DEFAULT NULL AFTER avatar;

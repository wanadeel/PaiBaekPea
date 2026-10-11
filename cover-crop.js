(() => {
    "use strict";

    const root = document.getElementById("cover-crop");
    if (!root) return;

    const input = document.getElementById("cover");
    const form = input.form;
    const editor = document.getElementById("cover-editor");
    const canvas = document.getElementById("cover-canvas");
    const ctx = canvas.getContext("2d");

    const zoom = document.getElementById("cover-zoom");
    const posX = document.getElementById("cover-x");
    const posY = document.getElementById("cover-y");

    const apply = document.getElementById("cover-apply");
    const reset = document.getElementById("cover-reset");
    const cancel = document.getElementById("cover-cancel");
    const status = document.getElementById("cover-status");

    const current = document.getElementById("cover-current");
    const editCurrent = document.getElementById("cover-edit-current");

    const maxBytes = 5 * 1024 * 1024;

    let picture = null;
    let acceptedFiles = input.files;
    let pending = false;
    let busy = false;
    let drag = null;

    function message(text) {
        status.textContent = text;
    }

    function setBusy(value) {
        busy = value;
        input.disabled = value;
        apply.disabled = value;
        reset.disabled = value;
        cancel.disabled = value;

        zoom.disabled = value;
        posX.disabled = value;
        posY.disabled = value;

        if (editCurrent) editCurrent.disabled = value;
    }

    function dimensions() {
        const scale = Math.max(
            canvas.width / picture.width,
            canvas.height / picture.height
        ) * Number(zoom.value);

        const width = picture.width * scale;
        const height = picture.height * scale;

        return {
            width,
            height,
            overflowX: Math.max(0, width - canvas.width),
            overflowY: Math.max(0, height - canvas.height)
        };
    }

    function draw() {
        if (!picture) return;

        const size = dimensions();

        const x = -size.overflowX * Number(posX.value) / 100;
        const y = -size.overflowY * Number(posY.value) / 100;

        // เติมสีขาวสำหรับรูป PNG ที่มีพื้นหลังโปร่งใส
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.drawImage(
            picture,
            x,
            y,
            size.width,
            size.height
        );
    }

    function changed() {
        if (!picture || busy) return;

        pending = true;
        draw();
        message("ปรับตำแหน่งแล้ว กด “ใช้รูปนี้” เพื่อยืนยัน");
    }

    async function loadPicture(getBlob) {
        setBusy(true);
        message("กำลังเปิดรูป…");

        try {
            const blob = await getBlob();

            if (!["image/jpeg", "image/png", "image/webp"].includes(blob.type)) {
                throw new Error("กรุณาเลือกไฟล์ JPG, PNG หรือ WebP");
            }

            if (blob.size > maxBytes) {
                throw new Error("รูปต้องมีขนาดไม่เกิน 5 MB");
            }

            const next = await createImageBitmap(blob);

            if (next.width * next.height > 40000000) {
                next.close();
                throw new Error("รูปมีความละเอียดสูงเกินไป กรุณาย่อรูปก่อน");
            }

            if (picture) picture.close();
            picture = next;

            zoom.value = "1.1";
            posX.value = "50";
            posY.value = "50";

            editor.hidden = false;
            pending = true;

            draw();
            message("ลากรูปหรือปรับแถบเลื่อน แล้วกด “ใช้รูปนี้”");
        } catch (error) {
            message(
                error instanceof Error
                    ? error.message
                    : "เปิดรูปไม่ได้ กรุณาเลือกไฟล์ใหม่"
            );
        } finally {
            setBusy(false);
        }
    }

    input.addEventListener("change", () => {
        const file = input.files[0];

        // เก็บไฟล์ที่ยืนยันไว้ จนกว่ารูปใหม่จะถูกครอปสำเร็จ
        input.files = acceptedFiles;

        if (file) {
            loadPicture(async () => file);
        }
    });

    // หน้าสร้างทริปยังไม่มีปุ่มปรับรูปเดิม จึงเพิ่มปุ่มเปิดอีกครั้ง
let reopenButton = editCurrent;

if (!reopenButton) {
    reopenButton = document.createElement("button");
    reopenButton.type = "button";
    reopenButton.className = "btn secondary";
    reopenButton.textContent = "ปรับรูปปกอีกครั้ง";
    editor.before(reopenButton);
}

reopenButton.addEventListener("click", () => {
    if (busy) return;

    // เปิดรูปที่กำลังปรับอยู่ โดยจำตำแหน่งและระดับซูมไว้
    if (picture) {
        editor.hidden = false;
        draw();
        message("ปรับตำแหน่งได้ แล้วกด “ใช้รูปนี้” เพื่อยืนยัน");
        return;
    }

    // หากเคยยืนยันรูปแล้ว ให้เปิดไฟล์นั้น
    if (acceptedFiles.length) {
        loadPicture(async () => acceptedFiles[0]);
        return;
    }

    // เปิดรูปที่บันทึกอยู่ในเว็บไซต์
    if (current) {
        loadPicture(async () => {
            const response = await fetch(current.src, {
                credentials: "same-origin",
                cache: "no-store"
            });

            if (!response.ok) {
                throw new Error("โหลดรูปปกเดิมไม่ได้ กรุณาลองอีกครั้ง");
            }

            return response.blob();
        });
        return;
    }

    // ยังไม่มีรูป ให้เลือกไฟล์ก่อน
    input.click();
    });

    [zoom, posX, posY].forEach((control) => {
        control.addEventListener("input", changed);
    });

    reset.addEventListener("click", () => {
        zoom.value = "1";
        posX.value = "50";
        posY.value = "50";
        changed();
    });

    // รองรับทั้งเมาส์และนิ้วบนมือถือ
    canvas.addEventListener("pointerdown", (event) => {
    if (!picture || busy || drag) return;
    if (event.pointerType === "mouse" && event.button !== 0) return;

    event.preventDefault();

    const size = dimensions();

    // ถ้ารูปพอดีกรอบ ให้ซูมเล็กน้อยเพื่อมีพื้นที่ลาก
    if (size.overflowX < 1 && size.overflowY < 1) {
        zoom.value = "1.1";
        changed();
    }

    drag = {
        id: event.pointerId,
        x: event.clientX,
        y: event.clientY
    };

    canvas.setPointerCapture(event.pointerId);
});

    canvas.addEventListener("pointermove", (event) => {
        if (!drag || event.pointerId !== drag.id || busy) return;

        const rect = canvas.getBoundingClientRect();
        const size = dimensions();

        const dx = (event.clientX - drag.x) * canvas.width / rect.width;
        const dy = (event.clientY - drag.y) * canvas.height / rect.height;

        if (size.overflowX > 0) {
            posX.value = String(Math.max(0, Math.min(
                100,
                Number(posX.value) - dx / size.overflowX * 100
            )));
        }

        if (size.overflowY > 0) {
            posY.value = String(Math.max(0, Math.min(
                100,
                Number(posY.value) - dy / size.overflowY * 100
            )));
        }

        drag.x = event.clientX;
        drag.y = event.clientY;
        changed();
    });

    function stopDragging(event) {
        if (drag && event.pointerId === drag.id) {
            drag = null;
        }
    }

    canvas.addEventListener("pointerup", stopDragging);
    canvas.addEventListener("pointercancel", stopDragging);
    canvas.addEventListener("lostpointercapture", stopDragging);

    apply.addEventListener("click", async () => {
        if (!picture || busy) return;

        setBusy(true);
        message("กำลังเตรียมรูปปก…");

        try {
            const blob = await new Promise((resolve, reject) => {
                canvas.toBlob((result) => {
                    if (result) resolve(result);
                    else reject(new Error("สร้างรูปไม่สำเร็จ กรุณาลองอีกครั้ง"));
                }, "image/jpeg", 0.9);
            });

            if (blob.size > maxBytes) {
                throw new Error("รูปหลังครอปมีขนาดเกิน 5 MB");
            }

            const file = new File(
                [blob],
                "trip-cover.jpg",
                { type: "image/jpeg" }
            );

            const transfer = new DataTransfer();
            transfer.items.add(file);

            input.files = transfer.files;
acceptedFiles = input.files;
pending = false;

// แสดงรูปที่ครอปแล้วแทนรูปเดิม
let preview = document.getElementById("cover-applied-preview");

if (!preview) {
    preview = document.createElement("canvas");
    preview.id = "cover-applied-preview";
    preview.className = "preview";
    preview.setAttribute("role", "img");
    preview.setAttribute("aria-label", "รูปปกที่ปรับแล้ว");

    if (current) {
        // อยู่ในกรอบเดียวกับปุ่มไอคอนเดิม
        current.before(preview);
    } else {
        editor.before(preview);
    }
}

preview.width = canvas.width;
preview.height = canvas.height;
preview.getContext("2d").drawImage(canvas, 0, 0);

if (current) current.hidden = true;

// ซ่อนกล่องปรับรูป
editor.hidden = true;

message("เลือกรูปปกแล้ว กดบันทึกทริปเพื่อบันทึกลงเว็บไซต์");
        } catch (error) {
            message(
                error instanceof Error
                    ? error.message
                    : "เตรียมรูปไม่สำเร็จ กรุณาลองอีกครั้ง"
            );
        } finally {
            setBusy(false);
        }
    });

    cancel.addEventListener("click", () => {
        input.files = acceptedFiles;
        pending = false;
        editor.hidden = true;
        drag = null;

        if (picture) {
            picture.close();
            picture = null;
        }

        message(
            acceptedFiles.length
                ? "ยกเลิกการปรับล่าสุด ใช้รูปที่ยืนยันไว้ก่อนหน้า"
                : "ยกเลิกการปรับรูปแล้ว"
        );
    });

    form.addEventListener("submit", (event) => {
        if (busy || pending) { 
            event.preventDefault();

            message(
                busy
                    ? "กรุณารอให้เตรียมรูปเสร็จก่อน"
                    : "กรุณากด “ใช้รูปนี้” หรือยกเลิกการปรับก่อนบันทึกทริป"
            );

            if (!busy) apply.focus();
        }
    });

    input.addEventListener("invalid", () => {
        if (pending) {
            message("กรุณากด “ใช้รูปนี้” เพื่อยืนยันรูปปกก่อน");
        }
    });
})();
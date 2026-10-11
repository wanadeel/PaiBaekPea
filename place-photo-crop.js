(() => {
    "use strict";

    document.querySelectorAll("[data-place-crop]").forEach((card) => {
        const original = card.querySelector(".place-photo-original");
        const preview = card.querySelector(".place-photo-preview");
        const editor = card.querySelector(".place-photo-editor");
        const canvas = card.querySelector(".place-photo-canvas");
        const ctx = canvas.getContext("2d");

        const fileInput = card.querySelector("[data-crop-file]");
        const open = card.querySelector("[data-crop-open]");
        const apply = card.querySelector("[data-crop-apply]");
        const cancel = card.querySelector("[data-crop-cancel]");
        const remove = card.querySelector("[data-crop-delete]");

        const zoom = card.querySelector("[data-crop-zoom]");
        const posX = card.querySelector("[data-crop-x]");
        const posY = card.querySelector("[data-crop-y]");
        const status = card.querySelector(".place-photo-status");
        const form = fileInput.form;

        let picture = null;
        let busy = false;
        let drag = null;
        let accepted = ["1", "50", "50"];

        const controls = [open, apply, cancel, zoom, posX, posY];

        function message(text) {
            status.textContent = text;
        }

        function setBusy(value) {
            busy = value;

            controls.forEach((control) => {
                control.disabled = value;
            });
        }

        function showEditor(value) {
            editor.hidden = !value;
            open.setAttribute("aria-expanded", String(value));
        }

        function size() {
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

            const dimensions = size();

            ctx.fillStyle = "#fff";
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            ctx.drawImage(
                picture,
                -dimensions.overflowX * Number(posX.value) / 100,
                -dimensions.overflowY * Number(posY.value) / 100,
                dimensions.width,
                dimensions.height
            );
        }

        function restorePosition() {
            [zoom.value, posX.value, posY.value] = accepted;
            draw();
        }

        open.hidden = false;

        open.addEventListener("click", async () => {
            if (busy) return;

            if (remove.checked) {
                message("เอาเครื่องหมายลบรูปออกก่อนปรับรูปครับ");
                return;
            }

            setBusy(true);
            message("กำลังเปิดรูป…");

            try {
                if (!picture) {
                    const response = await fetch(original.src, {
                        credentials: "same-origin",
                        cache: "no-store"
                    });

                    if (!response.ok) {
                        throw new Error("เปิดรูปไม่ได้ กรุณาลองอีกครั้ง");
                    }

                    const blob = await response.blob();

                    if (
                        !["image/jpeg", "image/png", "image/webp"]
                            .includes(blob.type)
                    ) {
                        throw new Error("ไฟล์ที่ได้รับไม่ใช่รูปที่รองรับ");
                    }

                    if (blob.size > 5 * 1024 * 1024) {
                        throw new Error("รูปต้องมีขนาดไม่เกิน 5 MB");
                    }

                    const next = await createImageBitmap(blob);

                    if (next.width * next.height > 40000000) {
                        next.close();
                        throw new Error("รูปมีความละเอียดสูงเกินไป");
                    }

                    picture = next;
                }

                restorePosition();
                showEditor(true);
                message("ลากรูปหรือปรับแถบเลื่อน แล้วกด “ใช้รูปนี้”");
                cancel.focus();
            } catch (error) {
                message(error.message || "เปิดรูปไม่สำเร็จ");
            } finally {
                setBusy(false);
            }
        });

        [zoom, posX, posY].forEach((control) => {
            control.addEventListener("input", draw);
        });

        canvas.addEventListener("pointerdown", (event) => {
            if (!picture || busy || drag) return;

            if (event.pointerType === "mouse" && event.button !== 0) {
                return;
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
            const dimensions = size();

            const dx = (event.clientX - drag.x) * canvas.width / rect.width;
            const dy = (event.clientY - drag.y) * canvas.height / rect.height;

            if (dimensions.overflowX > 0) {
                posX.value = String(Math.max(0, Math.min(
                    100,
                    Number(posX.value) - dx / dimensions.overflowX * 100
                )));
            }

            if (dimensions.overflowY > 0) {
                posY.value = String(Math.max(0, Math.min(
                    100,
                    Number(posY.value) - dy / dimensions.overflowY * 100
                )));
            }

            drag.x = event.clientX;
            drag.y = event.clientY;
            draw();
        });

        function stopDragging(event) {
            if (drag && event.pointerId === drag.id) {
                drag = null;
            }
        }

        ["pointerup", "pointercancel", "lostpointercapture"]
            .forEach((eventName) => {
                canvas.addEventListener(eventName, stopDragging);
            });

        apply.addEventListener("click", async () => {
            if (!picture || busy) return;

            setBusy(true);
            message("กำลังเตรียมรูป…");

            try {
                const blob = await new Promise((resolve, reject) => {
                    canvas.toBlob((result) => {
                        if (result) {
                            resolve(result);
                        } else {
                            reject(new Error("สร้างรูปไม่สำเร็จ"));
                        }
                    }, "image/jpeg", 0.9);
                });

                if (blob.size > 5 * 1024 * 1024) {
                    throw new Error("รูปหลังปรับต้องไม่เกิน 5 MB");
                }

                const transfer = new DataTransfer();

                transfer.items.add(new File(
                    [blob],
                    "place-photo.jpg",
                    { type: "image/jpeg" }
                ));

                fileInput.files = transfer.files;

                preview.getContext("2d").drawImage(canvas, 0, 0);
                preview.hidden = false;
                original.hidden = true;

                accepted = [zoom.value, posX.value, posY.value];

                showEditor(false);
                drag = null;

                message("ใช้รูปนี้แล้ว กด “บันทึกการแก้ไข” เพื่อบันทึกจริง");
            } catch (error) {
                message(error.message || "เตรียมรูปไม่สำเร็จ");
            } finally {
                setBusy(false);

                if (editor.hidden) {
                    open.focus();
                }
            }
        });

        cancel.addEventListener("click", () => {
            restorePosition();
            showEditor(false);
            drag = null;

            message("ยกเลิกการปรับล่าสุดแล้ว");
            open.focus();
        });

        remove.addEventListener("change", () => {
            if (remove.checked && !busy) {
                restorePosition();
                showEditor(false);
                drag = null;
            }
        });

        form.addEventListener("submit", (event) => {
            if (busy || (!editor.hidden && !remove.checked)) {
                event.preventDefault();

                message(
                    busy
                        ? "กรุณารอให้เตรียมรูปเสร็จก่อน"
                        : "กด “ใช้รูปนี้” หรือ “ยกเลิกการปรับ” ก่อนบันทึก"
                );

                if (!busy) apply.focus();
            }
        });
    });
})();
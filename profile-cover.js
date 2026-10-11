(() => {
    'use strict';
    const form = document.getElementById('profile-edit-form');
    if (!form) return;
    const byId = (id) => document.getElementById(id);
    const input = byId('pbb-cover-file');
    const editor = byId('pbb-cover-editor');
    const canvas = byId('pbb-cover-canvas');
    const ctx = canvas.getContext('2d');
    const zoom = byId('pbb-cover-zoom');
    const posX = byId('pbb-cover-x');
    const posY = byId('pbb-cover-y');
    const apply = byId('pbb-cover-apply');
    const reset = byId('pbb-cover-reset');
    const cancel = byId('pbb-cover-cancel');
    const current = byId('pbb-cover-current');
    const reopen = byId('pbb-cover-reopen');
    const remove = byId('pbb-cover-remove');
    const preview = byId('pbb-cover-applied');
    const status = byId('pbb-cover-status');
    const maxBytes = 5 * 1024 * 1024;
    let acceptedFiles = input.files;
    let picture = null;
    let busy = false;
    let pending = false;
    let drag = null;
    const message = (text) => { status.textContent = text; };
    const controls = [input, zoom, posX, posY, apply, reset, cancel, reopen, remove].filter(Boolean);

    function setBusy(value) {
        busy = value;
        controls.forEach((control) => { control.disabled = value; });
    }
    function dimensions() {
        const scale = Math.max(canvas.width / picture.width, canvas.height / picture.height) * Number(zoom.value);
        const width = picture.width * scale;
        const height = picture.height * scale;
        return { width, height, overflowX: Math.max(0, width - canvas.width), overflowY: Math.max(0, height - canvas.height) };
    }
    function draw() {
        if (!picture) return;
        const size = dimensions();
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(picture, -size.overflowX * Number(posX.value) / 100,
            -size.overflowY * Number(posY.value) / 100, size.width, size.height);
    }
    function changed() {
        if (!picture || busy) return;
        pending = true;
        draw();
        message('ปรับภาพแล้ว กด “ใช้ภาพนี้” ก่อนบันทึกโปรไฟล์');
    }
    async function loadPicture(getBlob) {
        setBusy(true);
        message('กำลังเปิดภาพปก…');
        try {
            const blob = await getBlob();
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(blob.type)) {
                throw new Error('รองรับเฉพาะ JPG, PNG และ WebP');
            }
            if (!blob.size || blob.size > maxBytes) throw new Error('ภาพต้องมีขนาดมากกว่า 0 และไม่เกิน 5 MB');
            const next = await createImageBitmap(blob);
            if (next.width * next.height > 25000000) {
                next.close();
                throw new Error('กรุณาย่อภาพให้ไม่เกิน 25 ล้านพิกเซล');
            }
            if (picture) picture.close();
            picture = next;
            drag = null;
            zoom.value = '1.1';
            posX.value = '50';
            posY.value = '50';
            editor.hidden = false;
            pending = true;
            if (remove) remove.checked = false;
            draw();
            message('ลากภาพหรือใช้แถบเลื่อน แล้วกด “ใช้ภาพนี้”');
        } catch (error) {
            message(error instanceof Error ? error.message : 'เปิดภาพไม่ได้ กรุณาเลือกใหม่');
        } finally {
            setBusy(false);
        }
    }
    input.addEventListener('change', () => {
        const file = input.files[0];
        input.files = acceptedFiles;
        if (file) loadPicture(async () => file);
    });
    if (reopen) reopen.addEventListener('click', () => {
        if (busy) return;
        if (picture) {
            editor.hidden = false;
            if (remove) remove.checked = false;
            draw();
            return;
        }
        if (acceptedFiles.length) {
            loadPicture(async () => acceptedFiles[0]);
        } else if (current) {
            loadPicture(async () => {
                const response = await fetch(current.src, { credentials: 'same-origin', cache: 'no-store' });
                if (!response.ok) throw new Error('เปิดภาพปกเดิมไม่ได้');
                return response.blob();
            });
        }
    });
    [zoom, posX, posY].forEach((control) => control.addEventListener('input', changed));
    reset.addEventListener('click', () => {
        zoom.value = '1.1';
        posX.value = '50';
        posY.value = '50';
        changed();
    });
    canvas.addEventListener('pointerdown', (event) => {
        if (!picture || busy || drag) return;
        if (event.pointerType === 'mouse' && event.button !== 0) return;
        event.preventDefault();
        const size = dimensions();
        if (size.overflowX < 1 && size.overflowY < 1) {
            zoom.value = '1.1';
            changed();
        }
        drag = { id: event.pointerId, x: event.clientX, y: event.clientY };
        canvas.setPointerCapture(event.pointerId);
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!picture || !drag || drag.id !== event.pointerId || busy) return;
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        const size = dimensions();
        const dx = (event.clientX - drag.x) * canvas.width / rect.width;
        const dy = (event.clientY - drag.y) * canvas.height / rect.height;
        if (size.overflowX > 0) posX.value = String(Math.max(0, Math.min(100, Number(posX.value) - dx / size.overflowX * 100)));
        if (size.overflowY > 0) posY.value = String(Math.max(0, Math.min(100, Number(posY.value) - dy / size.overflowY * 100)));
        drag.x = event.clientX;
        drag.y = event.clientY;
        changed();
    });
    const stopDrag = (event) => {
        if (drag && drag.id === event.pointerId) drag = null;
    };
    ['pointerup', 'pointercancel', 'lostpointercapture'].forEach((name) => canvas.addEventListener(name, stopDrag));
    apply.addEventListener('click', async () => {
        if (!picture || busy) return;
        setBusy(true);
        message('กำลังเตรียมภาพปก…');
        try {
            draw();
            const blob = await new Promise((resolve, reject) => canvas.toBlob((result) => {
                if (result) resolve(result);
                else reject(new Error('เตรียมภาพไม่สำเร็จ'));
            }, 'image/jpeg', 0.9));
            if (blob.size > maxBytes) throw new Error('ภาพหลังปรับมีขนาดเกิน 5 MB');
            const file = new File([blob], 'profile-cover.jpg', { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            preview.getContext('2d').drawImage(canvas, 0, 0);
            input.files = transfer.files;
            acceptedFiles = input.files;
            pending = false;
            preview.hidden = false;
            if (current) current.hidden = true;
            if (remove) remove.checked = false;
            editor.hidden = true;
            message('เลือกภาพปกแล้ว กด “บันทึกโปรไฟล์” เพื่อบันทึกลงเว็บไซต์');
        } catch (error) {
            message(error instanceof Error ? error.message : 'เตรียมภาพไม่สำเร็จ');
        } finally {
            setBusy(false);
        }
    });
    function cancelEditing() {
        input.files = acceptedFiles;
        pending = false;
        drag = null;
        editor.hidden = true;
        if (picture) { picture.close(); picture = null; }
        message('ยกเลิกการปรับล่าสุดแล้ว');
    }
    cancel.addEventListener('click', cancelEditing);
    if (remove) remove.addEventListener('change', () => {
        if (!remove.checked) return;
        cancelEditing();
        input.files = new DataTransfer().files;
        acceptedFiles = input.files;
        preview.hidden = true;
        if (current) current.hidden = false;
        message('ภาพปกจะถูกลบเมื่อกดบันทึกโปรไฟล์');
    });
    form.addEventListener('submit', (event) => {
        if (!busy && !pending) return;
        event.preventDefault();
        message(busy ? 'กรุณารอเตรียมภาพให้เสร็จ' : 'กด “ใช้ภาพนี้” หรือยกเลิกการปรับก่อนบันทึก');
        if (!busy) apply.focus();
    });
})();

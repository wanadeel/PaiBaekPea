document.addEventListener("submit", async function (event) {
    const form = event.target;

    // ทำงานเฉพาะฟอร์มหัวใจ
    if (
        !(form instanceof HTMLFormElement) ||
        !form.matches(".trip-like-form")
    ) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector(".trip-like-button");
    const actionInput = form.querySelector('input[name="action"]');
    const countElement = button?.querySelector("span");

    if (!button || !actionInput || !countElement || button.disabled) {
        return;
    }

    // สร้างข้อความสถานะสำหรับฟอร์มนี้
    let status = form.querySelector(".trip-like-status");

    if (!status) {
        status = document.createElement("span");
        status.className = "trip-like-status";
        status.setAttribute("role", "status");
        form.appendChild(status);
    }

    status.textContent = "";

    // ป้องกันการกดซ้ำระหว่างบันทึก
    button.disabled = true;
    button.setAttribute("aria-busy", "true");

    try {
        const response = await fetch(form.getAttribute("action"), {
            method: "POST",
            body: new FormData(form),
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        // เช่น เซสชันหมดอายุแล้วเซิร์ฟเวอร์ส่งไปหน้าเข้าสู่ระบบ
        if (response.redirected) {
            throw new Error(
                "กรุณาเข้าสู่ระบบใหม่ แล้วลองกดหัวใจอีกครั้ง"
            );
        }

        if (!response.ok) {
            if (response.status === 404) {
    const responseText = await response.text();

    if (
        responseText.trim() ===
        "ไม่พบทริป หรือทริปนี้ไม่ได้เปิดเป็นสาธารณะ"
    ) {
        throw new Error(
            "พบทริปไม่ได้ในฐานข้อมูล หรือทริปถูกเปลี่ยนเป็นส่วนตัว กรุณารีเฟรชหน้ารายการ"
        );
    }

    throw new Error(
        "หาไฟล์รับการกดหัวใจไม่พบที่: " + response.url
    );
}

            if (
                response.status === 401 ||
                response.status === 403
            ) {
                throw new Error(
                    "กรุณารีเฟรชหน้าและตรวจสอบการเข้าสู่ระบบ"
                );
            }

            throw new Error("ทำรายการไม่สำเร็จ กรุณาลองใหม่");
        }

        const contentType = response.headers.get("content-type") || "";

        if (!contentType.includes("application/json")) {
            throw new Error(
                "เซิร์ฟเวอร์ส่งข้อมูลไม่ถูกต้อง กรุณาตรวจโค้ด like_trip.php"
            );
        }

        const data = await response.json();

        if (
            data.success !== true ||
            typeof data.liked !== "boolean" ||
            !Number.isInteger(data.count) ||
            data.count < 0
        ) {
            throw new Error("ข้อมูลตอบกลับไม่ถูกต้อง กรุณาลองใหม่");
        }

        // เปลี่ยนสีหัวใจและตัวเลข หลังเซิร์ฟเวอร์บันทึกสำเร็จ
        button.classList.toggle("is-liked", data.liked);
        button.setAttribute("aria-pressed", String(data.liked));
        button.setAttribute(
            "aria-label",
            data.liked ? "ยกเลิกถูกใจ" : "ถูกใจทริป"
        );

        countElement.textContent = String(data.count);

        // กดครั้งถัดไปให้ทำรายการตรงข้าม
        actionInput.value = data.liked ? "unlike" : "like";

    } catch (error) {
        status.textContent = error instanceof Error
            ? error.message
            : "เชื่อมต่อไม่สำเร็จ กรุณาลองใหม่";

    } finally {
        button.disabled = false;
        button.removeAttribute("aria-busy");
    }
});
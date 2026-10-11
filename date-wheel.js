(() => {
    "use strict";

    // ป้องกันการโหลดซ้ำ
    if (window.pbbDateWheelLoaded) return;
    window.pbbDateWheelLoaded = true;

    const months = [
        "มกราคม", "กุมภาพันธ์", "มีนาคม", "เมษายน",
        "พฤษภาคม", "มิถุนายน", "กรกฎาคม", "สิงหาคม",
        "กันยายน", "ตุลาคม", "พฤศจิกายน", "ธันวาคม"
    ];

    const enhanced = new WeakSet();
    const refreshers = new Set();
    const rowHeight = 44;

    let activeInput = null;
    let activeButton = null;
    let selected = { day: 1, month: 1, year: 2026 };

    const dialog = document.createElement("dialog");
    dialog.className = "pbb-date-dialog";
    dialog.setAttribute("aria-labelledby", "pbb-date-title");

    dialog.innerHTML = `
        <h2 id="pbb-date-title">เลือกวันที่</h2>
        <p class="pbb-date-hint">
            เลื่อนขึ้นลง หรือกดตัวเลขเพื่อเลือกวันที่
        </p>

        <div class="pbb-date-headings" aria-hidden="true">
            <span>วัน</span>
            <span>เดือน</span>
            <span>ปี พ.ศ.</span>
        </div>

        <div class="pbb-date-wheels">
            <div class="pbb-date-wheel" data-part="day"
                 role="group" aria-label="เลือกวัน"></div>
            <div class="pbb-date-wheel" data-part="month"
                 role="group" aria-label="เลือกเดือน"></div>
            <div class="pbb-date-wheel" data-part="year"
                 role="group" aria-label="เลือกปี พ.ศ."></div>
        </div>

        <p class="pbb-date-preview" aria-live="polite"></p>
        <p class="pbb-date-error" role="alert"></p>

        <div class="pbb-date-actions">
            <button type="button" class="btn secondary"
                    data-action="cancel">ยกเลิก</button>
            <button type="button" class="btn"
                    data-action="apply">ใช้วันที่นี้</button>
        </div>
    `;

    // เบราว์เซอร์ที่ไม่รองรับ dialog จะยังใช้ช่องวันที่เดิม
    if (typeof dialog.showModal !== "function") return;

    document.body.append(dialog);

    const preview = dialog.querySelector(".pbb-date-preview");
    const error = dialog.querySelector(".pbb-date-error");
    const wheels = {};

    function parseDate(value) {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || "");
        if (!match) return null;

        return {
            year: Number(match[1]),
            month: Number(match[2]),
            day: Number(match[3])
        };
    }

    function isoDate(date) {
        return [
            String(date.year).padStart(4, "0"),
            String(date.month).padStart(2, "0"),
            String(date.day).padStart(2, "0")
        ].join("-");
    }

    function thaiDate(date) {
        return `${date.day} ${months[date.month - 1]} ${date.year + 543}`;
    }

    function daysInMonth() {
        return new Date(selected.year, selected.month, 0).getDate();
    }

    function updatePreview() {
        preview.textContent = thaiDate(selected);
        error.textContent = "";
    }

    function markSelection(state, index) {
        state.buttons.forEach((button, i) => {
            button.setAttribute("aria-pressed", String(i === index));
        });
    }

    function selectIndex(part, index) {
        const state = wheels[part];
        const value = state.values[index];
        if (value === undefined) return;

        const changed = selected[part] !== value;
        selected[part] = value;
        markSelection(state, index);

        // เปลี่ยนเดือนหรือปีแล้วปรับจำนวนวันให้ถูกต้อง
        if (changed && (part === "month" || part === "year")) {
            selected.day = Math.min(selected.day, daysInMonth());
            renderDays();
        }

        updatePreview();
    }

    function settle(part) {
        const state = wheels[part];
        clearTimeout(state.timer);

        const index = Math.max(
            0,
            Math.min(
                state.values.length - 1,
                Math.round(state.element.scrollTop / rowHeight)
            )
        );

        selectIndex(part, index);
    }

    function buildWheel(part, values, label) {
        const state = wheels[part];
        clearTimeout(state.timer);
        state.values = values;

        const fragment = document.createDocumentFragment();

        values.forEach((value, index) => {
            const button = document.createElement("button");
            button.type = "button";
            button.textContent = label(value);
            button.setAttribute("aria-pressed", "false");

            button.addEventListener("click", () => {
                clearTimeout(state.timer);
                state.element.scrollTop = index * rowHeight;
                selectIndex(part, index);
            });

            // ปุ่มลูกศรบนคีย์บอร์ดใช้เลื่อนรายการได้
            button.addEventListener("keydown", (event) => {
                let next = index;

                if (event.key === "ArrowDown") next++;
                else if (event.key === "ArrowUp") next--;
                else if (event.key === "Home") next = 0;
                else if (event.key === "End") next = values.length - 1;
                else return;

                event.preventDefault();
                next = Math.max(0, Math.min(values.length - 1, next));

                state.element.scrollTop = next * rowHeight;
                selectIndex(part, next);
                state.buttons[next].focus({ preventScroll: true });
            });

            fragment.append(button);
        });

        state.element.replaceChildren(fragment);
        state.buttons = Array.from(state.element.children);

        const index = Math.max(0, values.indexOf(selected[part]));
        markSelection(state, index);
        state.element.scrollTop = index * rowHeight;
    }

    function renderDays() {
        buildWheel(
            "day",
            Array.from({ length: daysInMonth() }, (_, i) => i + 1),
            String
        );
    }

    dialog.querySelectorAll("[data-part]").forEach((element) => {
        const part = element.dataset.part;

        wheels[part] = {
            element,
            values: [],
            buttons: [],
            timer: null
        };

        element.addEventListener("scroll", () => {
            clearTimeout(wheels[part].timer);

            wheels[part].timer = setTimeout(() => {
                if (dialog.open) settle(part);
            }, 140);
        }, { passive: true });
    });

    function openPicker(input, button) {
        if (input.disabled || input.readOnly) return;

        activeInput = input;
        activeButton = button;

        const today = new Date();
        selected = parseDate(input.value) || {
            day: today.getDate(),
            month: today.getMonth() + 1,
            year: today.getFullYear()
        };

        const min = parseDate(input.min);
        const max = parseDate(input.max);

        // หากยังไม่มีวันที่ เริ่มต้นภายในช่วงที่อนุญาต
        if (!input.value) {
            if (min && isoDate(selected) < input.min) selected = { ...min };
            if (max && isoDate(selected) > input.max) selected = { ...max };
        }

        const firstYear = Math.min(min?.year ?? 1900, selected.year);
        const lastYear = Math.max(max?.year ?? 2200, selected.year);

        dialog.querySelector("#pbb-date-title").textContent =
            input.labels?.[0]?.textContent.trim() || "เลือกวันที่";

        dialog.showModal();

        buildWheel(
            "year",
            Array.from(
                { length: lastYear - firstYear + 1 },
                (_, i) => firstYear + i
            ),
            year => String(year + 543)
        );

        buildWheel(
            "month",
            Array.from({ length: 12 }, (_, i) => i + 1),
            month => months[month - 1]
        );

        renderDays();
        updatePreview();

        wheels.day.buttons[selected.day - 1].focus({
            preventScroll: true
        });
    }

    dialog.querySelector('[data-action="cancel"]')
        .addEventListener("click", () => dialog.close());

    dialog.querySelector('[data-action="apply"]')
        .addEventListener("click", () => {
            // อ่านตำแหน่งล่าสุด แม้เพิ่งเลื่อนแล้วกดยืนยันทันที
            settle("day");
            settle("month");
            settle("year");

            const value = isoDate(selected);

            if (activeInput.min && value < activeInput.min) {
                error.textContent =
                    `กรุณาเลือกตั้งแต่ ${thaiDate(parseDate(activeInput.min))}`;
                return;
            }

            if (activeInput.max && value > activeInput.max) {
                error.textContent =
                    `กรุณาเลือกไม่เกิน ${thaiDate(parseDate(activeInput.max))}`;
                return;
            }

            activeInput.value = value;

            activeInput.dispatchEvent(
                new Event("input", { bubbles: true })
            );
            activeInput.dispatchEvent(
                new Event("change", { bubbles: true })
            );

            dialog.close();
        });

    dialog.addEventListener("close", () => {
        Object.values(wheels).forEach(state => clearTimeout(state.timer));
        activeButton?.focus();
    });

    function enhanceDateInputs() {
        document.querySelectorAll('input[type="date"]').forEach(input => {
            if (enhanced.has(input)) return;
            enhanced.add(input);

            const button = document.createElement("button");
            button.type = "button";
            button.className = "pbb-date-button";
            button.setAttribute("aria-haspopup", "dialog");

            const label = input.labels?.[0];

            function refresh() {
                const date = parseDate(input.value);
                button.textContent = date ? thaiDate(date) : "เลือกวันที่";
                button.disabled = input.disabled || input.readOnly;
                button.setAttribute(
                    "aria-label",
                    `${label?.textContent.trim() || "วันที่"}: ${button.textContent}`
                );
            }

            input.insertAdjacentElement("afterend", button);

            // เก็บ input เดิมไว้ส่งข้อมูลให้ PHP
            input.hidden = true;

            button.addEventListener("click", () => openPicker(input, button));

            if (label) {
                label.addEventListener("click", event => {
                    event.preventDefault();
                    button.focus();
                });
            }

            input.addEventListener("input", refresh);
            input.addEventListener("change", refresh);

            // ช่อง required ยังตรวจสอบได้ และเปิดตัวเลือกเมื่อกรอกไม่ครบ
            input.addEventListener("invalid", event => {
                event.preventDefault();

                if (!dialog.open) {
                    openPicker(input, button);
                    error.textContent = "กรุณาเลือกวันที่ให้ถูกต้อง";
                }
            });

            input.form?.addEventListener("reset", () => {
                setTimeout(refresh, 0);
            });

            refreshers.add({ input, refresh });
            refresh();
        });
    }

    enhanceDateInputs();

    // สร้างช่องวันที่
        const observer = new MutationObserver(() => {
        enhanceDateInputs();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
})();
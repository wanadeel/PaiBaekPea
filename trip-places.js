(() => {
    "use strict";

    const list = document.getElementById("places-list");
    const addButton = document.getElementById("add-place-button");
    const template = document.getElementById("place-card-template");
    const status = document.getElementById("places-status");

    if (!list || !addButton || !template || !status) return;

    const limit = 20;
    const cards = () => [...list.querySelectorAll(".trip-place-card")];

    function updateCards() {
        const items = cards();

        items.forEach((card, index) => {
            card.querySelector(".place-card-title").textContent =
                `สถานที่ที่ ${index + 1}`;

            const removeButton =
                card.querySelector(".remove-place-button");

            removeButton.hidden = items.length === 1;
            removeButton.setAttribute(
                "aria-label",
                `ลบสถานที่ที่ ${index + 1}`
            );
        });

        addButton.disabled = items.length >= limit;
        status.textContent =
            `มี ${items.length} สถานที่ จากสูงสุด ${limit} แห่ง`;
    }

    addButton.hidden = false;

    addButton.addEventListener("click", () => {
        const items = cards();
        if (items.length >= limit) return;

        const usedSlots = new Set(
            items.map(card => Number(card.dataset.slot))
        );

        let slot = 0;
        while (usedSlots.has(slot)) slot++;

        const holder = document.createElement("template");
        holder.innerHTML = template.innerHTML
            .split("__INDEX__")
            .join(String(slot));

        const card =
            holder.content.querySelector(".trip-place-card");
        const previous = items[items.length - 1];

        // ใช้ประเทศ จังหวัด และวันที่ของสถานที่ก่อนหน้าเป็นค่าเริ่มต้น
        if (previous) {
            ["country_code", "province", "visit_date"].forEach(field => {
                const source = previous.querySelector(
                    `[name$="[${field}]"]`
                );
                const target = card.querySelector(
                    `[name$="[${field}]"]`
                );

                if (source && target) {
                    target.value = source.value;
                }
            });
        }

        const highestSort = Math.max(
            0,
            ...items.map(item =>
                Number(
                    item.querySelector('[name$="[sort_order]"]').value
                ) || 0
            )
        );

        card.querySelector('[name$="[sort_order]"]').value =
            Math.min(highestSort + 1, 1000);

        list.appendChild(card);
        updateCards();

        card.querySelector('[name$="[name]"]').focus();

        card.scrollIntoView({
            behavior: window.matchMedia(
                "(prefers-reduced-motion: reduce)"
            ).matches ? "auto" : "smooth",
            block: "start"
        });
    });

    list.addEventListener("click", event => {
        const button =
            event.target.closest(".remove-place-button");

        if (!button || cards().length <= 1) return;

        const card = button.closest(".trip-place-card");
        const title =
            card.querySelector(".place-card-title").textContent.trim();

        if (!window.confirm(
            `ลบ${title}ออกจากแบบฟอร์มหรือไม่? ข้อมูลและรูปที่เลือกในช่องนี้จะถูกนำออก`
        )) {
            return;
        }

        card.remove();
        updateCards();
        addButton.focus();
    });

    updateCards();
})();
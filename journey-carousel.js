(() => {
    "use strict";

    const root = document.querySelector(".journey-carousel");
    if (!root) return;

    const viewport = root.querySelector(".journey-carousel-window");
    const track = root.querySelector(".journey-carousel-track");
    const group = root.querySelector(".journey-carousel-group");
    const pauseButton = root.querySelector("[data-journey-pause]");

    if (!viewport || !track || !group) return;

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    );

    let groupWidth = 0;
    let looping = false;
    let paused = reducedMotion.matches;
    let hovered = false;
    let focused = false;
    let touching = false;
    let drag = null;
    let blockClick = false;
    let lastTime = null;
    let manualUntil = 0;
    let frame = null;

    // ความเร็ว หน่วยพิกเซลต่อวินาที
    const speed = 38;

    const before = group.cloneNode(true);
    const after = group.cloneNode(true);

    // ชุดสำเนาใช้ต่อภาพให้วนลื่น ๆ
    // ให้คีย์บอร์ดและโปรแกรมอ่านหน้าจอใช้ชุดต้นฉบับ
    [before, after].forEach((copy) => {
        copy.setAttribute("aria-hidden", "true");

        copy.querySelectorAll("a").forEach((link) => {
            link.tabIndex = -1;
        });
    });

    track.prepend(before);
    track.append(after);

    function updateButton() {
        pauseButton.textContent = paused ? "เล่นต่อ" : "หยุดเลื่อน";
    }

    function measure() {
        groupWidth = group.getBoundingClientRect().width;
        looping = groupWidth > viewport.clientWidth + 1;

        before.hidden = !looping;
        after.hidden = !looping;
        pauseButton.hidden = !looping;

        viewport.scrollLeft = looping ? groupWidth : 0;
        updateButton();
    }

    function wrap() {
        if (!looping || groupWidth <= 0) return;

        if (viewport.scrollLeft < groupWidth / 2) {
            viewport.scrollLeft += groupWidth;
        } else if (viewport.scrollLeft >= groupWidth * 1.5) {
            viewport.scrollLeft -= groupWidth;
        }
    }

    function animate(time) {
        const delta = lastTime === null
            ? 0
            : Math.min((time - lastTime) / 1000, 0.05);

        lastTime = time;

        if (
    looping &&
    !paused &&
    !document.hidden
    ) {
    viewport.scrollLeft -= speed * delta;
    wrap();
    }
         {
            // ลด scrollLeft เพื่อให้ภาพเคลื่อนไปทางขวา
            viewport.scrollLeft -= speed * delta;
            wrap();
        }

        frame = requestAnimationFrame(animate);
    }

    pauseButton.addEventListener("click", () => {
        paused = !paused;
        updateButton();
    });

    root.addEventListener("mouseenter", () => {
        hovered = true;
    });

    root.addEventListener("mouseleave", () => {
        hovered = false;
    });

    viewport.addEventListener("focusin", () => {
        focused = true;
    });

    viewport.addEventListener("focusout", (event) => {
        focused = viewport.contains(event.relatedTarget);
    });

    viewport.addEventListener("scroll", wrap, { passive: true });

    viewport.addEventListener("wheel", () => {
        manualUntil = performance.now() + 1500;
    }, { passive: true });

    // เมาส์: กดค้างแล้วลาก
    // มือถือ: ใช้การปัดตามธรรมชาติของเบราว์เซอร์
    viewport.addEventListener("pointerdown", (event) => {
        if (event.pointerType !== "mouse") {
            touching = true;
            return;
        }

        if (event.button !== 0) return;

        drag = {
            id: event.pointerId,
            startX: event.clientX,
            lastX: event.clientX,
            moved: false
        };

        blockClick = false;
    });

    viewport.addEventListener("pointermove", (event) => {
        if (!drag || event.pointerId !== drag.id) return;

        const distance = event.clientX - drag.startX;

        if (!drag.moved && Math.abs(distance) > 5) {
            drag.moved = true;
            viewport.classList.add("is-dragging");
            viewport.setPointerCapture(event.pointerId);
        }

        if (drag.moved) {
            event.preventDefault();

            viewport.scrollLeft -= event.clientX - drag.lastX;
            wrap();
        }

        drag.lastX = event.clientX;
    });

    function finishPointer(event) {
        touching = false;
        manualUntil = performance.now() + 1500;

        if (!drag || event.pointerId !== drag.id) return;

        blockClick = drag.moved;
        drag = null;

        viewport.classList.remove("is-dragging");

        // ป้องกันการเปิดโพสต์หลังเพิ่งลากเสร็จ
        setTimeout(() => {
            blockClick = false;
        }, 0);
    }

    window.addEventListener("pointerup", finishPointer);
    window.addEventListener("pointercancel", finishPointer);

    viewport.addEventListener("lostpointercapture", () => {
        drag = null;
        viewport.classList.remove("is-dragging");
    });

    viewport.addEventListener("click", (event) => {
        if (blockClick) {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    viewport.addEventListener("dragstart", (event) => {
        event.preventDefault();
    });

    viewport.addEventListener("keydown", (event) => {
        if (event.target !== viewport) return;

        if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
            event.preventDefault();

            viewport.scrollLeft += event.key === "ArrowRight" ? 160 : -160;
            wrap();
        }
    });

    reducedMotion.addEventListener("change", (event) => {
        paused = event.matches;
        updateButton();
    });

    new ResizeObserver(measure).observe(viewport);

    measure();
    frame = requestAnimationFrame(animate);

    window.addEventListener("pagehide", () => {
        cancelAnimationFrame(frame);
    });

    window.addEventListener("pageshow", (event) => {
        if (event.persisted) {
            lastTime = null;
            frame = requestAnimationFrame(animate);
        }
    });
})();
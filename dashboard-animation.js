(() => {
    "use strict";

    const reducedMotion = window.matchMedia(
        "(prefers-reduced-motion: reduce)"
    );

    // ระยะเวลาของแอนิเมชัน หน่วยมิลลิวินาที
    const duration = 1500;

    function easeOut(progress) {
        return 1 - Math.pow(1 - progress, 3);
    }

    function createCounter(element) {
        const original = element.textContent.trim();

        // รองรับจำนวนทริปและเปอร์เซ็นต์ โดยเก็บข้อความเดิมไว้
        const parts = [];
        const pattern = /\d[\d,]*(?:\.\d+)?/g;
        let match;

        while ((match = pattern.exec(original)) !== null) {
            const raw = match[0];
            const value = Number(raw.replaceAll(",", ""));

            if (!Number.isFinite(value)) continue;

            parts.push({
                start: match.index,
                end: match.index + raw.length,
                value,
                decimals: raw.includes(".")
                    ? raw.split(".")[1].length
                    : 0
            });
        }

        if (!parts.length) return null;

        // โปรแกรมอ่านหน้าจออ่านค่าจริง โดยไม่ต้องอ่านทุกเฟรม
        element.setAttribute("aria-label", original);

        return (progress) => {
            if (progress >= 1) {
                element.textContent = original;
                return;
            }

            let result = "";
            let cursor = 0;

            parts.forEach((part) => {
                result += original.slice(cursor, part.start);

                const current = part.decimals > 0
                    ? part.value * progress
                    : Math.floor(part.value * progress);

                result += current.toLocaleString("en-US", {
                    minimumFractionDigits: part.decimals,
                    maximumFractionDigits: part.decimals
                });

                cursor = part.end;
            });

            result += original.slice(cursor);
            element.textContent = result;
        };
    }

    function prepareSection(section) {
        const counters = [];

        section.querySelectorAll(
            ".dashboard-stat > strong, " +
            ".dashboard-bar-label > span, " +
            ".dashboard-month-table td"
        ).forEach((element) => {
            // ชื่อประเทศไม่ต้องเปลี่ยนเป็นตัวเลข
            if (element.matches(".dashboard-stat > strong")) {
                if (!/^[\d,]+$/.test(element.textContent.trim())) return;
            }

            const counter = createCounter(element);
            if (counter) counters.push(counter);
        });

        const bars = Array.from(
            section.querySelectorAll("[data-bar-width]")
        ).map((element) => ({
            element,
            target: Math.max(
                0,
                Math.min(100, Number(element.dataset.barWidth) || 0)
            )
        }));

        const line = section.querySelector(".dashboard-line");
        const dots = Array.from(
            section.querySelectorAll(".dashboard-dot")
        );
        const labels = Array.from(
            section.querySelectorAll(".dashboard-value-text")
        );

        const baseline = 220;
        let linePoints = [];

        if (line) {
            const raw = line.getAttribute("points").trim();

            linePoints = raw.split(/\s+/).map((pair) => {
                const [x, y] = pair.split(",").map(Number);
                return { x, y };
            });
        }

        const dotTargets = dots.map((dot) => ({
            dot,
            y: Number(dot.getAttribute("cy"))
        }));

        const labelTargets = labels.map((label) => ({
            label,
            y: Number(label.getAttribute("y")),
            counter: createCounter(label)
        }));

        function render(progress) {
            counters.forEach((counter) => counter(progress));

            bars.forEach(({ element, target }) => {
                element.style.width = `${target * progress}%`;
            });

            if (line) {
                const points = linePoints.map(({ x, y }) => {
                    const animatedY = baseline + (y - baseline) * progress;
                    return `${x},${animatedY}`;
                });

                line.setAttribute("points", points.join(" "));
            }

            dotTargets.forEach(({ dot, y }) => {
                dot.setAttribute(
                    "cy",
                    String(baseline + (y - baseline) * progress)
                );
            });

            labelTargets.forEach(({ label, y, counter }) => {
                // ข้อความเริ่มเหนือเส้นศูนย์ แล้วขยับตามจุด
                label.setAttribute(
                    "y",
                    String(
                        baseline - 12 +
                        (y - (baseline - 12)) * progress
                    )
                );

                if (counter) counter(progress);
            });
        }

        return { section, render, started: false };
    }

    const sections = Array.from(
        document.querySelectorAll(
            ".dashboard-stats, .dashboard-panel"
        )
    ).map(prepareSection);

    if (!sections.length) return;

    if (reducedMotion.matches) {
        sections.forEach((item) => item.render(1));
        return;
    }

    // เตรียมค่าเริ่มต้นเป็นศูนย์
    sections.forEach((item) => item.render(0));

    function start(item) {
        if (item.started) return;
        item.started = true;

        let startTime = null;

        function frame(time) {
            if (startTime === null) startTime = time;

            const progress = Math.min(
                (time - startTime) / duration,
                1
            );

            item.render(easeOut(progress));

            if (progress < 1) {
                requestAnimationFrame(frame);
            }
        }

        requestAnimationFrame(frame);
    }

    // เริ่มเมื่อกราฟหรือการ์ดเลื่อนเข้ามาในจอ
    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                const item = sections.find(
                    (candidate) => candidate.section === entry.target
                );

                if (item) start(item);
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.15
        });

        sections.forEach((item) => {
            observer.observe(item.section);
        });
    } else {
        sections.forEach(start);
    }

    reducedMotion.addEventListener("change", (event) => {
        if (event.matches) {
            sections.forEach((item) => {
                item.started = true;
                item.render(1);
            });
        }
    });
})();
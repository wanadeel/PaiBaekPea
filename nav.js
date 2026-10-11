const nav = document.querySelector(".site-header nav");

if (nav) {
    const links = [...nav.querySelectorAll(".nav-link")];
    const indicator = document.createElement("span");

    indicator.className = "nav-indicator";
    indicator.setAttribute("aria-hidden", "true");
    nav.appendChild(indicator);

    let hoveredLink = null;

    // ย้ายแถบไปอยู่กึ่งกลางใต้เมนู
    function moveTo(link) {
        if (!link) {
            indicator.style.opacity = "0";
            return;
        }

        const navRect = nav.getBoundingClientRect();
        const linkRect = link.getBoundingClientRect();

        const x =
            linkRect.left - navRect.left - nav.clientLeft +
            nav.scrollLeft +
            (linkRect.width - indicator.offsetWidth) / 2;

        const y =
            linkRect.bottom - navRect.top - nav.clientTop +
            nav.scrollTop - 9;

        indicator.style.transform = `translate(${x}px, ${y}px)`;
        indicator.style.opacity = "1";
    }

    // เลือกเมนูที่ชี้อยู่ หรือเมนูของหน้าปัจจุบัน
    function updateIndicator() {
        const focusedLink = links.find(
            link => link === document.activeElement
        );

        const activeLink = nav.querySelector(".nav-link.is-active");

        moveTo(hoveredLink || focusedLink || activeLink);
    }

    links.forEach(link => {
        link.addEventListener("mouseenter", () => {
            hoveredLink = link;
            updateIndicator();
        });

        link.addEventListener("mouseleave", () => {
            hoveredLink = null;
            updateIndicator();
        });

        link.addEventListener("focus", updateIndicator);

        link.addEventListener("blur", () => {
            requestAnimationFrame(updateIndicator);
        });
    });

    // จัดตำแหน่งเมื่อเปิดหน้า
    updateIndicator();

    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            indicator.classList.add("is-ready");
        });
    });

    // ปรับตำแหน่งเมื่อขนาดหน้าจอหรือเมนูเปลี่ยน
    window.addEventListener("resize", updateIndicator);

    const observer = new ResizeObserver(updateIndicator);
    observer.observe(nav);
    links.forEach(link => observer.observe(link));

    document.fonts.ready.then(updateIndicator);
}
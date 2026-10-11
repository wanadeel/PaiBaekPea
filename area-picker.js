(() => {
    const picker = document.querySelector("details.area-picker");
    if (!picker) return;

    function closePicker() {
        picker.open = false;
    }

    // พับเมื่อเปิดหน้า รวมถึงกลับมาด้วยปุ่มย้อนกลับ
    closePicker();
    window.addEventListener("pageshow", closePicker);

    // พับเมื่อกดลิงก์เลือกประเทศ จังหวัด หรือล้างตัวกรอง
    picker.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) return;

        const link = event.target.closest("a[href]");
        if (!link || event.defaultPrevented) return;

        // การเปิดลิงก์ในแท็บใหม่ไม่ต้องพับหน้าปัจจุบัน
        if (
            event.ctrlKey ||
            event.metaKey ||
            event.shiftKey ||
            event.altKey ||
            link.target === "_blank"
        ) return;

        closePicker();
    });

    // พับเมื่อส่งแบบฟอร์มค้นหาในกล่อง
    picker.addEventListener("submit", (event) => {
        if (!event.defaultPrevented) closePicker();
    });
})();
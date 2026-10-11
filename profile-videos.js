(() => {
    'use strict';

    document.querySelectorAll('.pbb-video-wrap').forEach((wrap) => {
        const video = wrap.querySelector('video');
        const button = wrap.querySelector('.pbb-video-sound');
        const icon = button?.querySelector('span');

        if (!video || !button || !icon) return;

        const updateButton = () => {
            const soundOn = !video.muted && video.volume > 0;
            const label = soundOn ? 'ปิดเสียง' : 'เปิดเสียง';

            icon.textContent = soundOn ? '🔊' : '🔇';
            button.setAttribute('aria-label', label);
            button.setAttribute('aria-pressed', String(soundOn));
            button.title = label;
        };

        video.muted = true;
        video.autoplay = true;
        video.loop = true;
        video.playsInline = true;

        button.addEventListener('click', () => {
            const soundOn = !video.muted && video.volume > 0;

            video.muted = soundOn;

            if (!soundOn && video.volume === 0) {
                video.volume = 1;
            }

            updateButton();
            video.play().catch(() => {});
        });

        video.addEventListener('volumechange', updateButton);

        updateButton();
        video.play().catch(() => {});
    });
})
();
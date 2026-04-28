import { onReady } from '../base/init.js';

onReady(() => {
  const cards = document.querySelectorAll('.card-gradient.animation-parallax');
  cards.forEach((card) => {
    const cover = card.querySelector('.card-gradient__cover');
    if (!cover) return;

    let raf = null;
    const onMove = (e) => {
      if (raf) return;
      raf = requestAnimationFrame(() => {
        raf = null;
        const r = card.getBoundingClientRect();
        if (!r.width || !r.height) return;
        const dx = (e.clientX - r.left) / r.width - 0.5; // -0.5..0.5
        const dy = (e.clientY - r.top) / r.height - 0.5;
        // move cover opposite to cursor for parallax depth feel
        cover.style.transform = `translate(${(-dx * 6).toFixed(2)}%, ${(-dy * 12).toFixed(2)}%)`;
      });
    };

    const reset = () => {
      if (raf) cancelAnimationFrame(raf);
      raf = null;
      cover.style.transform = '';
    };

    card.addEventListener('mousemove', onMove);
    card.addEventListener('mouseleave', reset);
  });
});

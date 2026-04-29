import { onReady } from '../base/init.js';

const SPEED_PX_PER_SEC = 60;
const DRAG_THRESHOLD_PX = 4;

onReady(() => {
  initLogolineMarquee();
});

function initLogolineMarquee() {
  const el = document.getElementById('logolineSlider');
  if (!el || el.swiperInstance) return;

  const wrapper = el.querySelector('.swiper-wrapper');
  if (!wrapper) return;

  const slides = Array.from(wrapper.children);
  if (!slides.length) return;

  slides.forEach((slide) => {
    const clone = slide.cloneNode(true);
    clone.setAttribute('aria-hidden', 'true');
    wrapper.appendChild(clone);
  });

  wrapper.querySelectorAll('img').forEach((img) => {
    img.removeAttribute('loading');
    img.setAttribute('decoding', 'async');
  });

  el.classList.add('is-marquee');
  el.swiperInstance = true;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let oneSetWidth = 0;
  let x = 0;
  let dragging = false;
  let hovered = false;
  let dragMoved = false;
  let dragStartPointerX = 0;
  let dragStartX = 0;
  let activePointerId = null;
  let lastTs = 0;

  const measure = () => {
    const w = Math.round(wrapper.scrollWidth / 2);
    if (w) oneSetWidth = w;
  };

  const normalize = (v) => {
    if (!oneSetWidth) return v;
    let r = v % oneSetWidth;
    if (r > 0) r -= oneSetWidth;
    return r;
  };

  const apply = () => {
    wrapper.style.transform = `translate3d(${x}px, 0, 0)`;
  };

  const tick = (ts) => {
    if (!dragging && !hovered && !reducedMotion && oneSetWidth) {
      if (lastTs) {
        const dt = (ts - lastTs) / 1000;
        x = normalize(x - SPEED_PX_PER_SEC * dt);
        apply();
      }
    }
    lastTs = ts;
    requestAnimationFrame(tick);
  };

  measure();
  apply();
  requestAnimationFrame(tick);

  wrapper.querySelectorAll('img').forEach((img) => {
    if (img.complete) return;
    img.addEventListener('load', measure, { once: true });
    img.addEventListener('error', measure, { once: true });
  });

  if (typeof ResizeObserver !== 'undefined') {
    let roRaf = 0;
    const ro = new ResizeObserver(() => {
      if (roRaf) cancelAnimationFrame(roRaf);
      roRaf = requestAnimationFrame(measure);
    });
    ro.observe(wrapper);
  }

  let resizeRaf = 0;
  window.addEventListener('resize', () => {
    if (resizeRaf) cancelAnimationFrame(resizeRaf);
    resizeRaf = requestAnimationFrame(measure);
  });

  el.addEventListener('mouseenter', () => {
    hovered = true;
    lastTs = 0;
  });
  el.addEventListener('mouseleave', () => {
    hovered = false;
    lastTs = 0;
  });

  el.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    dragging = true;
    dragMoved = false;
    activePointerId = e.pointerId;
    dragStartPointerX = e.clientX;
    dragStartX = x;
    lastTs = 0;
    el.classList.add('is-dragging');
    try {
      el.setPointerCapture(e.pointerId);
    } catch {
      /* noop */
    }
  });

  el.addEventListener('pointermove', (e) => {
    if (!dragging || e.pointerId !== activePointerId) return;
    const dx = e.clientX - dragStartPointerX;
    if (Math.abs(dx) > DRAG_THRESHOLD_PX) dragMoved = true;
    x = normalize(dragStartX + dx);
    apply();
  });

  const endDrag = (e) => {
    if (e.pointerId !== activePointerId) return;
    dragging = false;
    activePointerId = null;
    lastTs = 0;
    el.classList.remove('is-dragging');
    try {
      el.releasePointerCapture(e.pointerId);
    } catch {
      /* noop */
    }
  };
  el.addEventListener('pointerup', endDrag);
  el.addEventListener('pointercancel', endDrag);

  el.addEventListener(
    'click',
    (e) => {
      if (dragMoved) {
        e.preventDefault();
        e.stopPropagation();
        dragMoved = false;
      }
    },
    true,
  );
}

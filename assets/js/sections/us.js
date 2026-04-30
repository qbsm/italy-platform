import { onReady } from '../base/init.js';

onReady(() => {
  const section = document.querySelector('.us');
  if (!section) return;

  const video = section.querySelector('.cover-wrap video');
  if (video && video.dataset.srcMobileMp4) {
    const desktopMq = window.matchMedia('(min-width: 1200px)');
    const setSources = () => {
      const tier = desktopMq.matches ? 'desktop' : 'mobile';
      const webm = video.dataset[`src${tier === 'desktop' ? 'Desktop' : 'Mobile'}Webm`];
      const mp4 = video.dataset[`src${tier === 'desktop' ? 'Desktop' : 'Mobile'}Mp4`];
      if (video.dataset.tier === tier) return;
      video.dataset.tier = tier;
      video.innerHTML =
        `<source src="${webm}" type="video/webm">` +
        `<source src="${mp4}" type="video/mp4">`;
      video.load();
    };
    setSources();
    desktopMq.addEventListener('change', setSources);
  }

  const bgTop = section.querySelector('.section__subitem.bg-top');
  const bgBottom = section.querySelector('.section__subitem.bg-bottom');

  if (bgTop || bgBottom) {
    let bgTicking = false;

    function updateBg() {
      const rect = section.getBoundingClientRect();
      const progress = rect.top + rect.height / 2 - window.innerHeight / 2;

      if (bgTop) bgTop.style.transform = `translateY(${progress * 0.06}px)`;
      if (bgBottom) bgBottom.style.transform = `translateY(${progress * -0.06}px)`;

      bgTicking = false;
    }

    window.addEventListener(
      'scroll',
      () => {
        if (bgTicking) return;
        bgTicking = true;
        requestAnimationFrame(updateBg);
      },
      { passive: true }
    );
  }

  const visual = section.querySelector('.section__item.visual-wrap');
  if (!visual) return;

  const cover = visual.querySelector('.section__subitem.cover-wrap');
  const card1 = visual.querySelector('.section__subitem.card-text-1');
  const card2 = visual.querySelector('.section__subitem.card-text-2');
  if (!cover || !card1 || !card2) return;

  const desktopMq = window.matchMedia('(min-width: 1200px)');
  const pointerMq = window.matchMedia('(hover: hover) and (pointer: fine)');
  if (!desktopMq.matches || !pointerMq.matches) return;

  function applyParallax(clientX, clientY) {
    const rect = visual.getBoundingClientRect();
    if (!rect.width || !rect.height) return;

    const nx = (clientX - rect.left) / rect.width - 0.5;
    const ny = (clientY - rect.top) / rect.height - 0.5;

    cover.style.transform = `translate(calc(-50% + ${nx * -8}px), calc(-50% + ${ny * -6}px)) scale(1.02)`;
    card1.style.transform = `translate(${nx * 20}px, ${ny * -14}px)`;
    card2.style.transform = `translate(${nx * -24}px, ${ny * 18}px)`;
  }

  function resetParallax() {
    cover.style.transform = 'translate(-50%, -50%) scale(1)';
    card1.style.transform = 'translate(0, 0)';
    card2.style.transform = 'translate(0, 0)';
  }

  visual.addEventListener('mousemove', (e) => applyParallax(e.clientX, e.clientY));
  visual.addEventListener('mouseleave', resetParallax);
  resetParallax();
});

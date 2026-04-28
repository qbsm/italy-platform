import { onReady } from '../base/init.js';

onReady(() => {
  initLogolineSlider();
});

function initLogolineSlider() {
  if (typeof window.Swiper === 'undefined') {
    setTimeout(initLogolineSlider, 200);
    return;
  }

  const el = document.getElementById('logolineSlider');
  if (!el || el.swiperInstance) return;

  const swiper = new window.Swiper(el, {
    slidesPerView: 'auto',
    spaceBetween: 80,
    loop: true,
    loopedSlides: 17,
    speed: 5000,
    allowTouchMove: true,
    grabCursor: true,
    freeMode: {
      enabled: true,
      momentum: true,
      momentumRatio: 0.5,
    },
    autoplay: {
      delay: 1,
      disableOnInteraction: false,
      pauseOnMouseEnter: true,
    },
  });

  el.swiperInstance = swiper;
}

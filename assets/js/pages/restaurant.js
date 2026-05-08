import { onReady } from '../base/init.js';

onReady(function () {
  if (typeof window.GLightbox !== 'function') return;
  const gallery = document.getElementById('restaurantGallery');
  if (!gallery) return;

  window.GLightbox({
    selector: '#restaurantGallery .glightbox',
    touchNavigation: true,
    loop: true,
    zoomable: true,
  });
});

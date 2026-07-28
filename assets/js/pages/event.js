import { onReady } from '../base/init.js';
import { PhoneMask } from '../components/form-callback/mask.js';

onReady(function () {
  // Спойлер меню: первый раздел виден, остальные — по кнопке
  const menuToggle = document.querySelector('.js-menu-toggle');
  const menuMore = document.querySelector('.js-menu-more');
  if (menuToggle && menuMore) {
    menuToggle.addEventListener('click', function () {
      const opened = !menuMore.hidden;
      menuMore.hidden = opened;
      menuToggle.setAttribute('aria-expanded', String(!opened));
      menuToggle.textContent = opened ? 'Показать всё меню' : 'Свернуть меню';
    });
  }

  // Галерея события — тот же лайтбокс, что на странице ресторана
  if (typeof window.GLightbox === 'function' && document.getElementById('eventGallery')) {
    window.GLightbox({
      selector: '#eventGallery .glightbox',
      touchNavigation: true,
      loop: true,
      zoomable: true,
    });
  }

  // Форма брони: степпер количества мест + пересчёт «Итого»
  document.querySelectorAll('.form-ticket').forEach(function (form) {
    const input = form.querySelector('input[name="tickets"]');
    if (!input) return;
    const total = form.querySelector('.js-ticket-total');
    const minus = form.querySelector('.js-qty-step[data-step="-1"]');
    const price = parseInt(form.dataset.price || '', 10);
    const currency = form.dataset.currency || '₽';

    function update() {
      let v = parseInt(input.value, 10);
      if (!v || v < 1) v = 1;
      input.value = v;
      if (minus) minus.disabled = v <= 1;
      if (total && price) {
        total.textContent = (v * price).toLocaleString('ru-RU') + ' ' + currency;
      }
    }

    form.querySelectorAll('.js-qty-step').forEach(function (btn) {
      btn.addEventListener('click', function () {
        input.value = (parseInt(input.value, 10) || 1) + parseInt(btn.dataset.step, 10);
        update();
      });
    });
    input.addEventListener('input', update);
    update();

    const phone = form.querySelector('input[type="tel"]');
    if (phone) {
      new PhoneMask(phone).init();
    }
  });
});

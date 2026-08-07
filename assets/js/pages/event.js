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

  // Переход на страницу банка занимает секунду-две. Без обратной связи кнопка выглядит
  // сломанной, и покупатель жмёт её повторно — каждый клик создаёт новый заказ.
  document.querySelectorAll('.form-ticket-pay').forEach(function (form) {
    form.addEventListener('submit', function () {
      if (!form.checkValidity()) return;

      const button = form.querySelector('[type="submit"]');
      if (!button || button.dataset.pending === '1') return;

      button.dataset.pending = '1';
      button.dataset.label = button.textContent;
      button.textContent = 'Переходим к оплате';
      setTimeout(function () {
        button.disabled = true;
      }, 0);
    });
  });

  // Возврат «Назад» со страницы банка отдаётся из bfcache — кнопка должна ожить
  window.addEventListener('pageshow', function (event) {
    if (!event.persisted) return;
    document.querySelectorAll('.form-ticket-pay [type="submit"][data-pending="1"]').forEach(function (button) {
      button.disabled = false;
      button.textContent = button.dataset.label || 'Оплатить билет';
      delete button.dataset.pending;
    });
  });

  // Банк возвращает покупателя на страницу события с ?paid=1 или ?pay=failed.
  // Без разбора этих параметров человек после оплаты видит обычную страницу и
  // не понимает, прошёл платёж или нет.
  (function showPaymentResult() {
    const params = new URLSearchParams(window.location.search);
    const paid = params.get('paid') === '1';
    const failed = params.get('pay') === 'failed';
    if (!paid && !failed) return;

    const slot = document.querySelector('.event-buy__form');
    if (!slot) return;

    const order = params.get('order') || '';
    const box = document.createElement('div');
    box.className = 'form-callback__success';
    box.setAttribute('role', 'status');

    const icon = document.createElement('div');
    icon.className = 'form-callback__success-icon' + (failed ? ' form-callback__success-icon--error' : '');
    icon.textContent = paid ? '✓' : '!';

    const title = document.createElement('div');
    title.className = 'form-callback__success-title';
    title.textContent = paid ? 'Оплата прошла' : 'Оплата не прошла';

    const text = document.createElement('div');
    text.className = 'form-callback__success-text';
    text.textContent = paid
      ? 'Билет и детали мы отправили на вашу почту.' + (order ? ' Номер заказа: ' + order + '.' : '')
      : 'Деньги не списаны. Попробуйте оплатить ещё раз или напишите нам, если ошибка повторится.';

    box.append(icon, title, text);

    if (paid) {
      slot.replaceChildren(box);
      const heading = document.querySelector('.event-buy__heading');
      if (heading) heading.textContent = 'Билет оплачен';
      const sub = document.querySelector('.event-buy__sub');
      if (sub) sub.remove();
    } else {
      slot.prepend(box);
    }

    const anchor = document.getElementById('buy');
    if (anchor) anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });

    // Чтобы перезагрузка страницы не показывала результат повторно
    params.delete('paid');
    params.delete('pay');
    params.delete('order');
    const query = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : '') + '#buy');
  })();
});

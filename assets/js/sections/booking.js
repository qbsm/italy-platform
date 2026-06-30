import { onReady } from '../base/init.js';

// Глобальная кнопка «Забронировать» (.js-button-booking) открывает виджет бронирования Remarked
// с выбором ресторана. Виджет (~137 КБ) грузится ЛЕНИВО по первому клику — не тянем на каждой странице.
// Точки ресторанов отдаёт components/remarked-points.twig (#remarked-points).
onReady(() => {
  const pointsEl = document.getElementById('remarked-points');
  let points = [];
  try {
    points = JSON.parse((pointsEl && pointsEl.textContent) || '[]');
  } catch {
    points = [];
  }
  points.forEach((p) => {
    p.point = parseInt(p.point, 10);
  });

  if (!points.length) {
    return;
  }

  let loading = false;
  let ready = false;

  const loadRemarked = () =>
    new Promise((resolve) => {
      if (typeof window.widgetArea === 'function') {
        resolve();
        return;
      }
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://remarked.ru/widget/new/css/stylesheet.css';
      document.head.appendChild(css);

      const script = document.createElement('script');
      script.src = 'https://remarked.ru/widget/new/js/newidget-v2.js';
      script.onload = resolve;
      script.onerror = resolve;
      document.head.appendChild(script);
    });

  document.addEventListener(
    'click',
    async (event) => {
      const button = event.target.closest('.js-button-booking');
      // После инициализации виджета клики обрабатывает он сам — не мешаем.
      if (!button || ready || loading) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      loading = true;

      await loadRemarked();

      if (typeof window.widgetArea === 'function') {
        window.widgetArea({
          booking: points,
          button: '.js-button-booking',
          linkPolicy: '/policy/',
          newSlotsTime: true,
          requiredSelect: true,
        });
        ready = true;
        button.click(); // повторный клик — теперь его перехватит виджет и откроет модалку
      } else {
        loading = false; // не загрузился — позволим повторить попытку
      }
    },
    true
  );
});

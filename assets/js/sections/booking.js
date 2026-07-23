import { onReady } from '../base/init.js';

// Виджет онлайн-бронирования столов Remarked — логика как на старом сайте.
// Кнопки: .js-button-booking (шапка, все рестораны, выбор в модалке) и .widget__point__N (страница
// ресторана и карточки каталога /restaurants — своя точка первой в списке). Грузим виджет ЛЕНИВО по
// первому клику — не тянем ~137 КБ на каждой странице.
// Точки ресторанов (bookingPoint) отдаёт components/remarked-points.twig (#remarked-points).
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

  // Конфиг виджета — как на старом сайте
  const langEn = {
    'en-US': { thanksText: 'Thank you!<br>We are looking forward to your visit!' },
    'ru-RU': {
      messageBusy: 'Oops! Сегодня мы не бронируем онлайн, пожалуйста, позвоните в ресторан и мы поищем свободный стол.',
      errorMessageBusy:
        'Вы уже забронировали стол на сегодня, если нужно внести изменения в ваш резерв, свяжитесь, пожалуйста, с рестораном',
    },
  };
  const selectAdd = {
    name: 'Подтвердить бронь',
    options: [
      { name: 'Выберите способ подтверждения', value: '' },
      { name: 'Звонок', value: 'Позвонить' },
      { name: 'СМС', value: 'Смс' },
    ],
  };
  const changeQtyNumber = (value, modal) => {
    const sel = modal.querySelector('#remarked-add-select');
    const lbl = modal.querySelector('label[for=remarked-add-select]');
    const display = value >= 6 ? 'none' : 'block';
    if (sel) sel.style.display = display;
    if (lbl) lbl.style.display = display;
  };
  const baseCfg = {
    linkPolicy: '/policy',
    newSlotsTime: true,
    requiredSelect: true,
    lang: langEn,
    changeQtyNumber,
    selectAdd,
    selectNoEmpty: true,
  };

  let loaded = false;
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
      script.onload = () => {
        // скрипт формы бронирования (как на старом сайте)
        const form = document.createElement('script');
        form.async = true;
        form.src = 'https://api.remarked.ru/api/v1/js/jquery.remform.v3.min.js';
        document.head.appendChild(form);
        resolve();
      };
      script.onerror = resolve;
      document.head.appendChild(script);
    });

  const initWidgets = () => {
    if (typeof window.widgetArea !== 'function') return;
    // Шапка — все рестораны
    window.widgetArea({ ...baseCfg, booking: points, button: '.js-button-booking' });
    // Кнопки конкретных ресторанов: страница ресторана — одна точка, каталог /restaurants — у каждой
    // карточки своя. Биндим widgetArea на каждую точку, встреченную на странице.
    const seen = new Set();
    document.querySelectorAll('[class*="widget__point__"]').forEach((el) => {
      const m = el.className.match(/widget__point__(\d+)/);
      if (!m) return;
      const pt = parseInt(m[1], 10);
      if (seen.has(pt)) return;
      seen.add(pt);
      const current = points.find((p) => p.point === pt);
      if (!current) return;
      const sorted = [current, ...points.filter((p) => p.point !== pt)];
      // requiredSelect выключен: свой ресторан уже выбран первым (как на старом сайте),
      // плейсхолдер «Выберите ресторан» нужен только в общей модалке шапки
      window.widgetArea({ ...baseCfg, requiredSelect: false, booking: sorted, button: `.widget__point__${pt}` });
    });
    watchSuccess();
  };

  // Кастомное сообщение успеха + цель Метрики form_booking на успешную бронь (как на старом сайте)
  const watchSuccess = () => {
    const apply = () => {
      const msg = document.querySelector('.remarked-primary-widget__success');
      if (msg && !msg.dataset.italyMsg) {
        msg.dataset.italyMsg = '1';
        msg.innerHTML =
          '<div class="remarked-primary-widget__title">Спасибо</div>Спасибо! <br> Ваша заявка на резерв — уже в ресторане. В ближайшее время мы свяжемся с вами для подтверждения';
      }
      const done = document.querySelector('.remarked-primary-widget--success');
      if (done && !done.dataset.italyGoal) {
        done.dataset.italyGoal = '1';
        const id = window.appConfig && window.appConfig.YANDEX_METRIC_ID;
        if (id && typeof window.ym === 'function') {
          window.ym(id, 'reachGoal', 'form_booking');
        }
      }
    };
    const observer = new MutationObserver(apply);
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
  };

  document.addEventListener(
    'click',
    async (event) => {
      const button = event.target.closest('.js-button-booking, [class*="widget__point__"]');
      if (!button || ready || loaded) {
        return;
      }
      event.preventDefault();
      event.stopImmediatePropagation();
      loaded = true;

      await loadRemarked();

      if (typeof window.widgetArea === 'function') {
        initWidgets();
        ready = true;
        button.click(); // повторный клик — теперь его перехватит виджет и откроет модалку
      } else {
        loaded = false; // не загрузился — позволим повторить
      }
    },
    true
  );
});

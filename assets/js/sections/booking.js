import { onReady } from '../base/init.js';

// Виджет онлайн-бронирования столов Remarked — логика как на старом сайте.
// Кнопки: .js-button-booking (шапка, все рестораны, выбор в модалке) и .widget__point__N (страница
// ресторана и карточки каталога /restaurants — своя точка первой в списке). Виджет тянется со
// стороннего домена (~137 КБ + форма), поэтому грузим его не сразу с документом, а по первому
// намерению гостя: наведение, касание, фокус на кнопке или первый скролл. К моменту клика виджет
// обычно уже готов и модалка открывается сразу.
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

  const BUTTON_SELECTOR = '.js-button-booking, [class*="widget__point__"]';
  const LOADING_CLASS = 'is-booking-loading';
  // Сколько ждём стороннего виджета, прежде чем отпустить кнопку: за это время гость успевает
  // понять, что нажатие принято, а мы — не заблокировать бронь навсегда при недоступном Remarked.
  const READY_TIMEOUT = 10000;

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

  let loading = null; // промис загрузки, чтобы параллельные намерения не плодили теги
  let bound = 0; // сколько раз вызван widgetArea: шапка плюс по одной точке на страницу
  let ready = false; // все модалки отрисованы — клики обрабатывает сам виджет

  const setLoading = (button, state) => {
    if (!button) return;
    button.classList.toggle(LOADING_CLASS, state);
    if (state) {
      button.setAttribute('aria-busy', 'true');
    } else {
      button.removeAttribute('aria-busy');
    }
  };

  const preconnect = () => {
    ['https://remarked.ru', 'https://api.remarked.ru'].forEach((href) => {
      if (document.querySelector(`link[rel="preconnect"][href="${href}"]`)) return;
      const link = document.createElement('link');
      link.rel = 'preconnect';
      link.href = href;
      link.crossOrigin = '';
      document.head.appendChild(link);
    });
  };

  // Ждём условие с поллингом: newidget-v2.js объявляет window.widgetArea не в момент onload, а
  // чуть позже, и разметку модалки вешает тоже не сразу — клик по кнопке до этого уходит в пустоту
  // и кнопка выглядит сломанной.
  const waitFor = (check, deadline) =>
    new Promise((resolve) => {
      const tick = () => {
        if (check()) {
          resolve(true);
          return;
        }
        if (Date.now() > deadline) {
          resolve(false);
          return;
        }
        setTimeout(tick, 50);
      };
      tick();
    });

  const widgetAreaReady = () => typeof window.widgetArea === 'function';
  // На каждый вызов widgetArea виджет рисует свою модалку и только тогда начинает слушать свою
  // кнопку. Ждём все: иначе клик по карточке уходит в пустоту, пока готова лишь модалка шапки.
  const widgetDomReady = () => document.querySelectorAll('.remarked-primary-widget__wrap').length >= bound;

  const loadRemarked = () => {
    if (loading) return loading;
    preconnect();
    loading = new Promise((resolve) => {
      if (typeof window.widgetArea === 'function') {
        resolve(true);
        return;
      }
      const deadline = Date.now() + READY_TIMEOUT;

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
        waitFor(widgetAreaReady, deadline).then(resolve);
      };
      script.onerror = () => resolve(false);
      document.head.appendChild(script);
    }).then((ok) => {
      if (!ok) {
        loading = null; // не поднялся — следующее намерение попробует снова
      }
      return ok;
    });
    return loading;
  };

  const initWidgets = () => {
    if (typeof window.widgetArea !== 'function' || bound) return;
    // Шапка — все рестораны
    window.widgetArea({ ...baseCfg, booking: points, button: '.js-button-booking' });
    bound = 1;
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
      bound += 1;
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

  // Предзагрузка по намерению: наведение, касание, фокус на кнопке брони или первый скролл
  // страницы. Ошибку глушим — клик всё равно попробует загрузить виджет ещё раз.
  const warmUp = () => {
    if (ready || loading) return;
    loadRemarked().then(async (ok) => {
      if (!ok) return;
      initWidgets();
      ready = await waitFor(widgetDomReady, Date.now() + READY_TIMEOUT);
    });
  };

  ['pointerenter', 'touchstart', 'focusin'].forEach((type) => {
    document.addEventListener(
      type,
      (event) => {
        const target = event.target;
        if (target && typeof target.closest === 'function' && target.closest(BUTTON_SELECTOR)) {
          warmUp();
        }
      },
      { capture: true, passive: true }
    );
  });
  window.addEventListener('scroll', warmUp, { once: true, passive: true });

  document.addEventListener(
    'click',
    async (event) => {
      const button = event.target.closest(BUTTON_SELECTOR);
      if (!button || ready) {
        return; // виджет уже поднят — клик обрабатывает он сам
      }
      event.preventDefault();
      event.stopImmediatePropagation();

      setLoading(button, true);
      const ok = await loadRemarked();

      if (!ok) {
        setLoading(button, false);
        return; // Remarked недоступен: кнопка остаётся живой, следующий клик попробует снова
      }
      initWidgets();
      // Виджет вешает свои обработчики не в момент widgetArea(), а когда отрисует модалку —
      // кликаем только после этого, иначе нажатие пропадёт и гостю придётся жать второй раз
      ready = await waitFor(widgetDomReady, Date.now() + READY_TIMEOUT);
      setLoading(button, false);
      if (!ready) {
        return; // модалка так и не отрисовалась: кнопка остаётся живой, повтор без зацикливания
      }
      button.click(); // повторный клик — теперь его перехватит виджет и откроет модалку
    },
    true
  );
});

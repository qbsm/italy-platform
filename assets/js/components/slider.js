// JavaScript для slider

// Десктопный брейкпоинт платформы (custom-media --lg в assets/css/base/mq.css)
const DESKTOP_MQ = '(min-width: 1200px)';
const desktopMq = window.matchMedia(DESKTOP_MQ);

// Слайды с data-hide-on="desktop|mobile" показываются только на мобильных/только на десктопе:
// снимаем их из DOM до инициализации Swiper, иначе он считает скрытый слайд в loop и пагинации
// (при effect: fade на его очереди игрок показывал бы пустой кадр). Скрытие до инициализации
// продублировано CSS-правилом в slider.css — чтобы слайд не мелькал до срабатывания скрипта.
function applyDeviceSlides(slider) {
  const removed = slider.deviceSlides || (slider.deviceSlides = []);

  // Возвращаем ранее снятые слайды на свои места (порядок в DOM сохраняем через next)
  removed.forEach(({ slide, next }) => {
    next.parentElement.insertBefore(slide, next);
  });
  removed.length = 0;

  slider.querySelectorAll('.swiper-slide[data-hide-on]').forEach((slide) => {
    const hideOn = slide.dataset.hideOn;
    if ((hideOn === 'desktop' && desktopMq.matches) || (hideOn === 'mobile' && !desktopMq.matches)) {
      removed.push({ slide, next: slide.nextSibling });
      slide.remove();
    }
  });
}

function hasDeviceSlides(slider) {
  return Boolean(
    (slider.deviceSlides && slider.deviceSlides.length) || slider.querySelectorAll('.swiper-slide[data-hide-on]').length
  );
}

export default function setupSliders() {
  // Проверяем наличие Swiper
  if (typeof window.Swiper === 'undefined') {
    console.error('Критическая ошибка: Swiper не найден. Слайдеры не будут инициализированы.');
    return;
  }

  initializeSliders();

  // При пересечении десктопного брейкпоинта набор слайдов меняется — переинициализируем
  // затронутые слайдеры на актуальном наборе
  desktopMq.addEventListener('change', () => {
    document.querySelectorAll('.swiper-container').forEach((slider) => {
      if (!hasDeviceSlides(slider)) return;

      try {
        if (slider.swiperInstance) {
          slider.swiperInstance.destroy(true, true);
          slider.swiperInstance = null;
        }
        applyDeviceSlides(slider);
        initSlider(slider);
      } catch (error) {
        console.error('Ошибка при переинициализации слайдера:', error);
      }
    });
  });
}

function initializeSliders() {
  const sliders = document.querySelectorAll('.swiper-container');
  if (!sliders.length) return;

  // Инициализация каждого слайдера
  sliders.forEach((slider) => {
    // Пропускаем слайдеры, которые инициализируются в своих модулях
    if (slider.id === 'studioSlider' || slider.id === 'introSlider') return;

    try {
      initSlider(slider);
    } catch (error) {
      console.error('Ошибка при инициализации слайдера:', error);
    }
  });
}

function initSlider(slider) {
  // Проверяем, был ли слайдер уже инициализирован
  if (slider.swiperInstance) return;

  // Снимаем слайды, скрытые на текущем устройстве, до создания Swiper
  applyDeviceSlides(slider);

  // Получаем настройки, переданные через data-атрибут
  let settings = {};

  try {
    const dataSettings = slider.getAttribute('data-settings');
    if (dataSettings) {
      settings = JSON.parse(dataSettings);
    }
  } catch (error) {
    console.error(`Ошибка при парсинге настроек слайдера ${slider.id || 'безымянный'}:`, error);
  }

  // Объединяем пользовательские настройки с дефолтными
  const mergedSettings = Object.assign(
    {
      autoplay: false,
      pagination: { enabled: false },
      navigation: { enabled: false },
      effect: 'slide',
      speed: 300,
      loop: false,
    },
    settings
  );

  // Формируем объект настроек для Swiper
  const swiperOptions = {
    slidesPerView: mergedSettings.slidesPerView || 1,
    spaceBetween: mergedSettings.spaceBetween || 30,
    speed: mergedSettings.speed || 300,
    loop: mergedSettings.loop || false,
  };

  // Добавляем настройки breakpoints для адаптивности
  if (mergedSettings.breakpoints) {
    swiperOptions.breakpoints = mergedSettings.breakpoints;
  }

  // Настройка эффекта
  if (mergedSettings.effect) {
    swiperOptions.effect = mergedSettings.effect;
  }

  // Настройка автопрокрутки
  if (mergedSettings.autoplay) {
    swiperOptions.autoplay = {
      delay: mergedSettings.autoplay.delay || 3000,
      disableOnInteraction:
        mergedSettings.autoplay.disableOnInteraction !== undefined
          ? mergedSettings.autoplay.disableOnInteraction
          : true,
    };
  }

  // Настройка пагинации
  if (mergedSettings.pagination && mergedSettings.pagination.enabled) {
    swiperOptions.pagination = {
      el: slider.querySelector('.swiper-pagination'),
      clickable: mergedSettings.pagination.clickable !== undefined ? mergedSettings.pagination.clickable : true,
    };
  }

  // Настройка навигации
  if (mergedSettings.navigation && mergedSettings.navigation.enabled) {
    swiperOptions.navigation = {
      nextEl: slider.querySelector('.swiper-button-next'),
      prevEl: slider.querySelector('.swiper-button-prev'),
      clickable: mergedSettings.navigation.clickable !== undefined ? mergedSettings.navigation.clickable : true,
    };
  }

  // Инициализация Swiper
  try {
    const swiperInstance = new window.Swiper(slider, swiperOptions);
    // Добавляем экземпляр Swiper в data-атрибут для доступа извне
    slider.swiperInstance = swiperInstance;
  } catch (error) {
    console.error(`Ошибка при инициализации слайдера ${slider.id || 'безымянный'}:`, error);
  }
}

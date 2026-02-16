// JavaScript для slider
export default function setupSliders() {
  // Проверяем наличие Swiper
  if (typeof window.Swiper === 'undefined') {
    console.error('Критическая ошибка: Swiper не найден. Слайдеры не будут инициализированы.');
    return;
  }
  
  console.log('Инициализируем слайдеры');
  initializeSliders();
}

function initializeSliders() {
  const sliders = document.querySelectorAll('.swiper-container');
  if (!sliders.length) {
    console.log('Слайдеры не найдены на странице');
    return;
  }

  console.log(`Найдено ${sliders.length} слайдеров для инициализации`);
  
  // Инициализация каждого слайдера
  sliders.forEach(slider => {
    // Пропускаем слайдеры, которые инициализируются в своих модулях
    if (slider.id === 'studioSlider' || slider.id === 'introSlider') {
      console.log(`Слайдер ${slider.id} инициализируется в отдельном модуле, пропускаем`);
      return;
    }
    
    try {
      initSlider(slider);
    } catch (error) {
      console.error('Ошибка при инициализации слайдера:', error);
    }
  });
}

function initSlider(slider) {
  // Проверяем, был ли слайдер уже инициализирован
  if (slider.swiperInstance) {
    console.log(`Слайдер ${slider.id || 'безымянный'} уже был инициализирован`);
    return;
  }
  
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
  const mergedSettings = Object.assign({
    autoplay: false,
    pagination: { enabled: false },
    navigation: { enabled: false },
    effect: 'slide',
    speed: 300,
    loop: false
  }, settings);

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
    console.log(`Добавлены настройки адаптивности для слайдера ${slider.id || 'безымянный'}:`, mergedSettings.breakpoints);
  }

  // Настройка эффекта
  if (mergedSettings.effect) {
    swiperOptions.effect = mergedSettings.effect;
  }

  // Настройка автопрокрутки
  if (mergedSettings.autoplay) {
    swiperOptions.autoplay = {
      delay: mergedSettings.autoplay.delay || 3000,
      disableOnInteraction: mergedSettings.autoplay.disableOnInteraction !== undefined 
        ? mergedSettings.autoplay.disableOnInteraction 
        : true
    };
  }

  // Настройка пагинации
  if (mergedSettings.pagination && mergedSettings.pagination.enabled) {
    swiperOptions.pagination = {
      el: slider.querySelector('.swiper-pagination'),
      clickable: mergedSettings.pagination.clickable !== undefined 
        ? mergedSettings.pagination.clickable 
        : true
    };
  }

  // Настройка навигации
  if (mergedSettings.navigation && mergedSettings.navigation.enabled) {
    swiperOptions.navigation = {
      nextEl: slider.querySelector('.swiper-button-next'),
      prevEl: slider.querySelector('.swiper-button-prev'),
      clickable: mergedSettings.navigation.clickable !== undefined 
        ? mergedSettings.navigation.clickable 
        : true
    };
  }

  // Инициализация Swiper
  try {
    console.log(`Инициализация слайдера ${slider.id || 'безымянный'} с настройками:`, swiperOptions);
    const swiperInstance = new window.Swiper(slider, swiperOptions);
    console.log(`Слайдер ${slider.id || 'безымянный'} успешно инициализирован:`, swiperInstance);
    
    // Добавляем экземпляр Swiper в data-атрибут для доступа извне
    slider.swiperInstance = swiperInstance;
  } catch (error) {
    console.error(`Ошибка при инициализации слайдера ${slider.id || 'безымянный'}:`, error);
  }
}

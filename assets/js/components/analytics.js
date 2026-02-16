document.addEventListener('DOMContentLoaded', function () {
  // console.log('Кастомный analytics.js (только Яндекс) загружен');

  const YANDEX_METRIC_ID = window.appConfig && window.appConfig.YANDEX_METRIC_ID;

  if (!YANDEX_METRIC_ID) {
    console.warn("ID для Яндекс.Метрики (YANDEX_METRIC_ID) не определен в window.appConfig. Цели Яндекса не будут отправляться.");
  }

  /**
   * Отправляет цель в Яндекс.Метрику.
   * @param {string} goalName - Имя цели.
   * @param {object} [params={}] - Дополнительные параметры цели.
   */
  function sendYandexGoal(goalName, params = {}) {
    if (!YANDEX_METRIC_ID || typeof window.ym !== 'function') {
      if (!YANDEX_METRIC_ID) console.warn(`Яндекс.Метрика: ID не установлен, цель "${goalName}" не отправлена.`);
      if (typeof window.ym !== 'function' && YANDEX_METRIC_ID) console.warn(`Яндекс.Метрика: Функция ym() недоступна, цель "${goalName}" не отправлена.`);
      return;
    }
    try {
      window.ym(YANDEX_METRIC_ID, 'reachGoal', goalName, params);
      // console.log(`Яндекс.Метрика: Отправлена цель "${goalName}"`, params);
    } catch (e) {
      console.error(`Яндекс.Метрика: Ошибка при отправке цели "${goalName}"`, e);
    }
  }

  /**
   * Инициализирует обработчики для динамических целей по клику.
   * Ищет элементы с классами вида .js-goal-ИМЯ_ЦЕЛИ
   */
  function setupDynamicGoals() {
    document.body.addEventListener('click', function (event) {
      let targetElement = event.target;

      while (targetElement && targetElement !== document.body) {
        if (targetElement.classList && typeof targetElement.classList.forEach === 'function') {
          targetElement.classList.forEach(className => {
            if (className.startsWith('js-goal-')) {
              let goalName = className.substring('js-goal-'.length);

              // Очистка от устаревших суффиксов для обратной совместимости
              if (goalName.endsWith('-yandex')) {
                goalName = goalName.substring(0, goalName.length - '-yandex'.length);
              } else if (goalName.endsWith('-topmail')) {
                goalName = goalName.substring(0, goalName.length - '-topmail'.length);
              }

              if (goalName) {
                const goalParams = {};
                if (targetElement.dataset) {
                  for (const dataKey in targetElement.dataset) {
                    if (dataKey.startsWith('goalParam')) {
                      let paramName = dataKey.substring('goalParam'.length);
                      if (paramName.length > 0) {
                        paramName = paramName.charAt(0).toLowerCase() + paramName.slice(1).replace(/-([a-z])/g, g => g[1].toUpperCase());
                        goalParams[paramName] = targetElement.dataset[dataKey];
                      }
                    }
                  }
                }
                sendYandexGoal(goalName, goalParams);
              }
            }
          });
        }
        targetElement = targetElement.parentElement;
      }
    });
  }

  function handleFormSubmissionSuccess(event) {
    // console.log('Analytics (JS): Получено событие formSubmissionSuccess', event.detail);
    sendYandexGoal('zayavka');
  }

  setupDynamicGoals();
  document.addEventListener('formSubmissionSuccess', handleFormSubmissionSuccess);
});
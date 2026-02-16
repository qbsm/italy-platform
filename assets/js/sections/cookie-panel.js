// JavaScript для cookie-panel
document.addEventListener('DOMContentLoaded', function() {
  // Код для cookie-panel

  const cookiePanel = document.querySelector('.cookie-panel');
  const acceptButton = document.querySelector('.js-cookie');
  
  if (!cookiePanel || !acceptButton) {
    return;
  }

  // Функция для работы с cookies
  const cookieUtils = {
    // Установить cookie
    set: function(name, value, days) {
      const expires = new Date();
      expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
      document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Lax`;
    },
    
    // Получить cookie
    get: function(name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(';');
      for (let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
      }
      return null;
    },
    
    // Проверить существование cookie
    exists: function(name) {
      return this.get(name) !== null;
    }
  };

  // Функция показа панели
  function showPanel() {
    cookiePanel.style.display = 'block';
    cookiePanel.style.opacity = '0';
    cookiePanel.style.transform = 'translateY(100%)';
    cookiePanel.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    
    // Анимация появления
    setTimeout(() => {
      cookiePanel.style.opacity = '1';
      cookiePanel.style.transform = 'translateY(0)';
    }, 10);
  }

  // Функция скрытия панели
  function hidePanel() {
    cookiePanel.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    cookiePanel.style.opacity = '0';
    cookiePanel.style.transform = 'translateY(100%)';
    
    setTimeout(() => {
      cookiePanel.style.display = 'none';
    }, 300);
  }

  // Проверяем, есть ли уже согласие
  if (cookieUtils.exists('cookie_consent')) {
    // Согласие уже дано - скрываем панель
    cookiePanel.style.display = 'none';
  } else {
    // Согласия нет - показываем панель
    showPanel();
  }

  // Обработчик клика на кнопку "Принимаю"
  acceptButton.addEventListener('click', function(e) {
    e.preventDefault();
    
    // Устанавливаем cookie на 60 дней
    cookieUtils.set('cookie_consent', 'accepted', 60);
    
    // Скрываем панель
    hidePanel();
    
    // Сообщаем системе, что пользователь дал согласие (для отложенной загрузки аналитики)
    try {
      const event = new CustomEvent('cookieConsentAccepted', { detail: { source: 'cookie-panel', ts: Date.now() } });
      window.dispatchEvent(event);
      document.dispatchEvent(event);
    } catch (_) {}
  });

  // Добавляем стили для анимации, если их нет
  if (!document.querySelector('#cookie-panel-styles')) {
    const style = document.createElement('style');
    style.id = 'cookie-panel-styles';
    style.textContent = `
      .cookie-panel {
        transition: opacity 0.3s ease, transform 0.3s ease;
      }
    `;
    document.head.appendChild(style);
  }
});

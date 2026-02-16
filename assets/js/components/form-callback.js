document.addEventListener('DOMContentLoaded', function () {
  // console.log('form-callback.js загружен');

  function getLocalizedText(category, key, defaultValue, langOverride = null) {
    const lang = langOverride || (document.documentElement.lang ? document.documentElement.lang.toLowerCase() : 'ru');
    try {
      if (window.globalData && window.globalData['form-callback'] &&
        window.globalData['form-callback'][category] &&
        window.globalData['form-callback'][category][lang] &&
        typeof window.globalData['form-callback'][category][lang][key] !== 'undefined') {
        return window.globalData['form-callback'][category][lang][key];
      }
    } catch (e) { /* console.warn(`Текст не найден: form-callback.${category}.${key} для ${lang}`) */ }
    return defaultValue;
  }

  // Проверяем доступность функции url()
  if (typeof window.url !== 'function') {
    console.error("Критическая ошибка: функция window.url() не определена. Проверьте, что window.appConfig.baseUrl установлен.");
    return; // Прерываем выполнение, т.к. без корректного URL невозможно загрузить JSON и отправить формы
  }

  // Загрузка global.json
  fetch(window.url('data/json/global.json'))
    .then(response => {
      if (!response.ok) {
        console.error('Ошибка загрузки global.json: ' + response.status + ' ' + response.statusText + ' URL: ' + response.url);
        throw new Error('Ошибка загрузки global.json: ' + response.status);
      }
      return response.json();
    })
    .then(data => {
      // console.log('Глобальные данные загружены');
      window.globalData = data;
      initFormCallbacks(); // Инициализируем формы после загрузки global.json
    })
    .catch(error => {
      console.error('Критическая ошибка: не удалось загрузить global.json. Функционал форм может быть нарушен.', error);
      // Не инициализируем формы, т.к. без globalData они не будут корректно работать
    });


  function initFormCallbacks() {
    const forms = document.querySelectorAll('.form-callback');
    if (forms.length === 0) {
      const lang = document.documentElement.lang ? document.documentElement.lang.toLowerCase() : 'ru';
      // Предполагается, что globalData уже загружен или не критичен для этого сообщения
      const noFormsMessage = window.globalData
        ? getLocalizedText('messages', 'no_forms_found', 'Формы обратной связи не найдены.', lang)
        : 'Формы обратной связи не найдены (globalData не загружен).';
      console.log(noFormsMessage);
      return;
    }
    // console.log('Найдено форм .form-callback:', forms.length);
    loadCountryCodes(codes => {
      forms.forEach(form => initForm(form, codes));
    });
  }

  function loadCountryCodes(callback) {
    const lang = document.documentElement.lang ? document.documentElement.lang.toLowerCase() : 'ru';
    if (lang === 'ru') {
      const basicCodes = {
        '7': { code: '+7', country: 'ru', format: 'russia', name: 'Россия, Казахстан' },
        '375': { code: '+375', country: 'by', format: 'russia', name: 'Беларусь' },
        '380': { code: '+380', country: 'ua', format: 'russia', name: 'Украина' },
        '1': { code: '+1', country: 'us', format: 'north-america', name: 'США, Канада' },
        '44': { code: '+44', country: 'gb', format: 'north-america', name: 'Великобритания' },
        '49': { code: '+49', country: 'de', format: 'standard', name: 'Германия' },
        '33': { code: '+33', country: 'fr', format: 'standard', name: 'Франция' },
        '39': { code: '+39', country: 'it', format: 'standard', name: 'Италия' },
        '34': { code: '+34', country: 'es', format: 'standard', name: 'Испания' },
        '86': { code: '+86', country: 'cn', format: 'standard', name: 'Китай' },
        '91': { code: '+91', country: 'in', format: 'standard', name: 'Индия' },
        '81': { code: '+81', country: 'jp', format: 'standard', name: 'Япония' },
        '90': { code: '+90', country: 'tr', format: 'standard', name: 'Турция' },
        '998': { code: '+998', country: 'uz', format: 'standard', name: 'Узбекистан' },
        '994': { code: '+994', country: 'az', format: 'standard', name: 'Азербайджан' },
        '992': { code: '+992', country: 'tj', format: 'standard', name: 'Таджикистан' },
        '996': { code: '+996', country: 'kg', format: 'standard', name: 'Кыргызстан' },
        '995': { code: '+995', country: 'ge', format: 'standard', name: 'Грузия' },
        '374': { code: '+374', country: 'am', format: 'standard', name: 'Армения' }
      };
      callback(basicCodes);
      return;
    }
    // Загрузка country-codes.json
    fetch(window.url('assets/js/data/country-codes.json'))
      .then(response => {
        if (!response.ok) {
          console.error('Ошибка загрузки country-codes.json: ' + response.status + ' ' + response.statusText + ' URL: ' + response.url);
          throw new Error('Ошибка загрузки country-codes.json: ' + response.status);
        }
        return response.json();
      })
      .then(data => callback(data))
      .catch(error => {
        console.error('Критическая ошибка: не удалось загрузить country-codes.json. Маска телефона может не работать корректно.', error);
        callback({}); // Возвращаем пустой объект кодов, чтобы избежать ошибок далее
      });
  }

  function initForm(form, countryCodes) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (!submitButton) return;

    // Устанавливаем текущий URL при инициализации формы
    const currentUrlField = form.querySelector('input[name="current_url"]');
    if (currentUrlField) {
      currentUrlField.value = window.location.href;
    }

    let errorContainer = form.querySelector('.form-callback__error');
    if (!errorContainer) {
      const newErrorContainer = document.createElement('div');
      newErrorContainer.className = 'form-callback__error hidden';
      newErrorContainer.innerHTML = '<span></span>';
      const formContentWrapper = form.querySelector('.form-callback__container') || form;
      const submitBtn = formContentWrapper.querySelector('button[type="submit"]');
      if (submitBtn && submitBtn.parentNode !== formContentWrapper) {
        submitBtn.parentNode.insertBefore(newErrorContainer, submitBtn);
      } else {
        formContentWrapper.appendChild(newErrorContainer);
      }
      errorContainer = newErrorContainer;
    }

    submitButton.addEventListener('click', function (e) {
      e.preventDefault();
      clearErrors(form);
      if (validateForm(form)) {
        sendForm(form);
      }
    });

    const formFieldsForInputReset = form.querySelectorAll('input[name="name"], input[name="phone"], input[name="square"], input[name="email"]');
    formFieldsForInputReset.forEach(field => {
      field.addEventListener('input', function () {
        const fieldItem = this.closest('.form-callback__item') || this;
        if (fieldItem.classList.contains('error')) {
          fieldItem.classList.remove('error');
          const stillHasErrors = form.querySelector('.form-callback__item.error, input.error');
          if (!stillHasErrors && errorContainer) {
            errorContainer.classList.add('hidden');
          }
        }
      });
    });

    const policyCheckbox = form.querySelector('input[name="policy"]');
    if (policyCheckbox) {
      policyCheckbox.addEventListener('change', function () {
        if (this.checked) {
          const fieldItem = this.closest('.form-callback__item') || this;
          if (fieldItem.classList.contains('error')) {
            fieldItem.classList.remove('error');
            const stillHasErrors = form.querySelector('.form-callback__item.error, input.error');
            if (!stillHasErrors && errorContainer) {
              errorContainer.classList.add('hidden');
            }
          }
        }
      });
    }

    initPhoneMask(form, countryCodes);
  }

  function initPhoneMask(form, countryCodes) {
    const phoneInput = form.querySelector('input[name="phone"]');
    if (!phoneInput) return;

    function formatPhoneNumber(value, format, countryCode) {
      if (!value) return countryCode || '';
      const digits = value.replace(/\D/g, '');
      if (digits.length === 10 && !countryCode && digits.charAt(0) === '9') {
        return formatPhoneNumber('7' + digits, 'russia', '+7');
      }
      let formatted = countryCode || '+' + digits.substring(0, 1);
      let phoneDigits = digits;
      if (countryCode) {
        const codeDigits = countryCode.replace(/\D/g, '');
        if (digits.startsWith(codeDigits)) {
          phoneDigits = digits.substring(codeDigits.length);
        }
      } else if (digits.length > 1) {
        phoneDigits = digits.substring(1);
      }
      switch (format) {
        case 'russia':
          if (phoneDigits.length > 0) {
            formatted += ' (' + phoneDigits.substring(0, Math.min(3, phoneDigits.length));
            if (phoneDigits.length > 3) {
              formatted += ') ' + phoneDigits.substring(3, Math.min(6, phoneDigits.length));
              if (phoneDigits.length > 6) {
                formatted += '-' + phoneDigits.substring(6, Math.min(8, phoneDigits.length));
                if (phoneDigits.length > 8) {
                  formatted += '-' + phoneDigits.substring(8, Math.min(10, phoneDigits.length));
                }
              }
            }
          }
          break;
        case 'north-america':
          if (phoneDigits.length > 0) {
            formatted += ' (' + phoneDigits.substring(0, Math.min(3, phoneDigits.length));
            if (phoneDigits.length > 3) {
              formatted += ') ' + phoneDigits.substring(3, Math.min(6, phoneDigits.length));
              if (phoneDigits.length > 6) {
                formatted += '-' + phoneDigits.substring(6, Math.min(10, phoneDigits.length));
              }
            }
          }
          break;
        case 'standard':
        default:
          let currentPos = 0;
          if (phoneDigits.length > 0) {
            formatted += ' ';
            if (phoneDigits.length <= 2) { formatted += phoneDigits; }
            else {
              formatted += phoneDigits.substring(0, 2); currentPos = 2;
              if (phoneDigits.length > currentPos) {
                formatted += '-' + phoneDigits.substring(currentPos, Math.min(currentPos + 3, phoneDigits.length)); currentPos += 3;
                if (phoneDigits.length > currentPos) {
                  formatted += '-' + phoneDigits.substring(currentPos);
                }
              }
            }
          }
          break;
      }
      return formatted;
    }
    function detectCountryCode(digits) {
      if (!digits || digits.length === 0) return null;
      for (let i = Math.min(digits.length, 4); i >= 1; i--) {
        const possibleCode = digits.substring(0, i);
        if (countryCodes && countryCodes[possibleCode]) return countryCodes[possibleCode];
      }
      return null;
    }
    phoneInput.addEventListener('focus', function () {
      if (this.value && this.value.indexOf('@') !== -1) {
        const cleanPhoneNumber = this.value.split('@')[0];
        const cleanDigits = cleanPhoneNumber.replace(/\D/g, '');
        if (cleanDigits.length >= 10) {
          if (cleanDigits.charAt(0) === '9' && cleanDigits.length === 10) {
            this.value = formatPhoneNumber('7' + cleanDigits, 'russia', '+7');
            this.setAttribute('data-detected-format', 'russia'); this.setAttribute('data-country-code', '+7'); return;
          } else {
            const countryInfo = detectCountryCode(cleanDigits);
            const format = countryInfo ? countryInfo.format : 'standard';
            const code = countryInfo ? countryInfo.code : ('+' + cleanDigits.substring(0, Math.min(cleanDigits.length, 3)));
            this.value = formatPhoneNumber(cleanDigits, format, code);
            if (countryInfo) {
              this.setAttribute('data-detected-format', countryInfo.format);
              this.setAttribute('data-country-code', countryInfo.code);
            } return;
          }
        } else { this.value = cleanPhoneNumber; }
      }
      if (!this.value.trim()) {
        const htmlLang = document.documentElement.lang ? document.documentElement.lang.toLowerCase() : 'ru';
        let defaultCode = '+7'; let defaultFormat = 'russia';
        if (htmlLang === 'en') { defaultCode = '+1'; defaultFormat = 'north-america'; }
        else if (htmlLang === 'de') { defaultCode = '+49'; defaultFormat = 'standard'; }
        this.value = defaultCode;
        this.setAttribute('data-country-code', defaultCode); this.setAttribute('data-detected-format', defaultFormat);
      } else { this.dispatchEvent(new Event('input', { bubbles: true })); }
    });
    phoneInput.addEventListener('blur', function () {
      const digitsOnly = this.value.replace(/\D/g, '');
      const countryCodeDigits = (this.getAttribute('data-country-code') || '').replace(/\D/g, '');
      if (digitsOnly === countryCodeDigits || digitsOnly.length <= (countryCodeDigits ? countryCodeDigits.length : 1)) {
        if (!this.value.match(/[1-9]/) || this.value.replace(/\D/g, '').length < 2) {
          this.value = ''; this.removeAttribute('data-country-code'); this.removeAttribute('data-detected-format');
        } else if (digitsOnly.length <= (countryCodeDigits ? countryCodeDigits.length : 0)) {
          this.value = ''; this.removeAttribute('data-country-code'); this.removeAttribute('data-detected-format');
        }
      }
    });
    phoneInput.addEventListener('input', function () {
      const originalValue = this.value; const digits = originalValue.replace(/\D/g, '');
      if (digits.length === 0) { this.value = ''; this.removeAttribute('data-country-code'); this.removeAttribute('data-detected-format'); return; }
      if (digits.length === 1 && originalValue.charAt(0) !== '+') { this.value = '+' + digits; this.removeAttribute('data-country-code'); this.removeAttribute('data-detected-format'); return; }
      if (originalValue === '+') return;
      const countryInfo = detectCountryCode(digits); let formattedNumber;
      if (countryInfo) {
        this.setAttribute('data-detected-format', countryInfo.format); this.setAttribute('data-country-code', countryInfo.code);
        formattedNumber = formatPhoneNumber(digits, countryInfo.format, countryInfo.code);
      } else {
        let tempCode = '+'; if (digits.length > 0) tempCode += digits.substring(0, 1); if (digits.length > 1) tempCode += digits.substring(1, 2);
        formattedNumber = formatPhoneNumber(digits, 'standard', tempCode.length > 1 ? tempCode : null);
        this.removeAttribute('data-country-code'); this.setAttribute('data-detected-format', 'standard');
      }
      this.value = formattedNumber;
    });
    phoneInput.addEventListener('change', function () {
      if (this.value && this.value.trim() !== '') {
        const originalValue = this.value; let digits = this.value.replace(/\D/g, '');
        if (originalValue.indexOf('@') !== -1) digits = originalValue.split('@')[0].replace(/\D/g, '');
        if ((digits.charAt(0) === '8' || digits.charAt(0) === '9') && (digits.length === 10 || digits.length === 11)) {
          if (digits.charAt(0) === '8' && digits.length === 11) digits = '7' + digits.substring(1);
          else if (digits.charAt(0) === '9' && digits.length === 10) digits = '7' + digits;
          this.value = formatPhoneNumber(digits, 'russia', '+7');
          this.setAttribute('data-detected-format', 'russia'); this.setAttribute('data-country-code', '+7'); return;
        }
        if (digits.length === 0) { this.value = ''; this.removeAttribute('data-country-code'); this.removeAttribute('data-detected-format'); return; }
        if (digits.length === 1 && this.value.charAt(0) !== '+') { this.value = '+' + digits; return; }
        const countryInfo = detectCountryCode(digits); let formattedNumber;
        if (countryInfo) {
          this.setAttribute('data-detected-format', countryInfo.format); this.setAttribute('data-country-code', countryInfo.code);
          formattedNumber = formatPhoneNumber(digits, countryInfo.format, countryInfo.code);
        } else {
          let tempCode = '+'; if (digits.length > 0) tempCode += digits.substring(0, 1);
          formattedNumber = formatPhoneNumber(digits, 'standard', tempCode.length > 1 ? tempCode : null);
          this.removeAttribute('data-country-code'); this.setAttribute('data-detected-format', 'standard');
        }
        this.value = formattedNumber;
      }
    });
    setTimeout(() => { if (phoneInput.value && phoneInput.value.trim() !== '') phoneInput.dispatchEvent(new Event('change', { bubbles: true })); }, 500);
    phoneInput.addEventListener('animationstart', e => { if (e.animationName.includes('autofill')) setTimeout(() => phoneInput.dispatchEvent(new Event('change', { bubbles: true })), 50); });
  }

  // Функция для проверки видимости поля
  function isFieldVisible(field) {
    if (!field) return false;
    
    // Проверяем родительский контейнер поля
    const fieldItem = field.closest('.form-callback__item');
    if (!fieldItem) return true; // Если нет контейнера, считаем поле видимым
    
    // Получаем вычисленные стили
    const styles = window.getComputedStyle(fieldItem);
    
    // Проверяем display: none
    if (styles.display === 'none') return false;
    
    // Проверяем visibility: hidden
    if (styles.visibility === 'hidden') return false;
    
    // Проверяем opacity: 0
    if (parseFloat(styles.opacity) === 0) return false;
    
    return true;
  }

  function validateForm(form) {
    let isValid = true; let firstErrorMessage = '';
    const errDefaults = {
      phone_required: "Укажите телефон", phone_invalid: "Неверный телефон",
      name_required: "Укажите имя", name_min_length: "Имя от 2 символов",
      square_required: "Укажите площадь", policy_required: "Согласитесь с политикой",
      email_required: "Укажите E-mail", email_invalid: "Неверный E-mail"
    };

    const phoneField = form.querySelector('input[name="phone"]');
    if (phoneField) {
      const val = phoneField.value.trim(), digits = val.replace(/\D/g, ''), codeDigits = (phoneField.getAttribute('data-country-code') || '').replace(/\D/g, '');
      const minLenAfterCode = 5;
      if (!val || digits.length <= 1) {
        const msg = getLocalizedText('error', 'phone_required', errDefaults.phone_required);
        markFieldAsError(phoneField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
      } else if (codeDigits.length > 0 && digits.length < (codeDigits.length + minLenAfterCode)) {
        const msg = getLocalizedText('error', 'phone_invalid', errDefaults.phone_invalid);
        markFieldAsError(phoneField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
      } else if (!codeDigits && digits.length < 7) {
        const msg = getLocalizedText('error', 'phone_invalid', errDefaults.phone_invalid);
        markFieldAsError(phoneField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
      }
    }

    const nameField = form.querySelector('input[name="name"]');
    const nameVisible = nameField ? isFieldVisible(nameField) : false;
    if (nameField && nameVisible) {
      if (!nameField.value.trim()) {
        const msg = getLocalizedText('error', 'name_required', errDefaults.name_required);
        markFieldAsError(nameField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
      } else if (nameField.value.trim().length < 2) {
        const msg = getLocalizedText('error', 'name_min_length', errDefaults.name_min_length);
        markFieldAsError(nameField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
      }
    }

    const squareField = form.querySelector('input[name="square"]');
    const squareVisible = squareField ? isFieldVisible(squareField) : false;
    if (squareField && squareVisible && !squareField.value.trim()) {
      const msg = getLocalizedText('error', 'square_required', errDefaults.square_required);
      markFieldAsError(squareField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
    }

    const emailField = form.querySelector('input[name="email"]');
    if (emailField) {
      const emailValue = emailField.value.trim();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (emailValue && !emailRegex.test(emailValue)) {
        const msg = getLocalizedText('error', 'email_invalid', errDefaults.email_invalid);
        markFieldAsError(emailField, msg); if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
      }
    }

    const policyCheckbox = form.querySelector('input[name="policy"]');
    const policyVisible = policyCheckbox ? isFieldVisible(policyCheckbox) : false;
    if (policyCheckbox && policyVisible && !policyCheckbox.checked) {
      const msg = getLocalizedText('error', 'policy_required', errDefaults.policy_required);
      markFieldAsError(policyCheckbox.closest('.form-callback__item') || policyCheckbox, msg);
      if (!firstErrorMessage) firstErrorMessage = msg; isValid = false;
    }

    if (!isValid && firstErrorMessage) showFormError(form, firstErrorMessage);
    return isValid;
  }

  function markFieldAsError(fieldElement, message) {
    const container = fieldElement.closest('.form-callback__item') || fieldElement;
    container.classList.add('error');
  }
  function showFormError(form, message) {
    const errContainer = form.querySelector('.form-callback__error');
    if (errContainer) {
      const span = errContainer.querySelector('span');
      if (span) {
        span.textContent = message;
      } else {
        errContainer.innerHTML = `<span>${message}</span>`;
      }
      errContainer.classList.remove('hidden');
      errContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }
  function clearErrors(form) {
    form.querySelectorAll('.form-callback__item.error, input.error, textarea.error, select.error').forEach(el => el.classList.remove('error'));
    const errContainer = form.querySelector('.form-callback__error');
    if (errContainer) {
      errContainer.classList.add('hidden');
      const span = errContainer.querySelector('span');
      if (span) span.textContent = '';
    }
  }

  function sendForm(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    let originalButtonContent = '';
    
    if (submitButton) { 
      submitButton.disabled = true; 
      submitButton.classList.add('loading'); 
      
      // Сохраняем оригинальный текст кнопки для восстановления при ошибке
      originalButtonContent = submitButton.innerHTML;
      
      // Заменяем текст и добавляем иконку спиннера
      submitButton.classList.add('no-pseudo-icon'); // Отключаем псевдоэлемент с иконкой
      submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-spinner-sm-color-2.svg')}" alt="Загрузка" class="button-icon spinner"> Отправляем заявку`;
      
      // Добавляем класс с анимацией вращения, если его нет в CSS
      const spinnerStyle = document.createElement('style');
      if (!document.querySelector('style#spinner-animation')) {
        spinnerStyle.id = 'spinner-animation';
        spinnerStyle.textContent = `
          .spinner {
            animation: spin 1.5s linear infinite;
          }
          @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
          }
        `;
        document.head.appendChild(spinnerStyle);
      }
    }

    const formData = new FormData();
    
    // Устанавливаем текущий URL в скрытое поле
    const currentUrlField = form.querySelector('input[name="current_url"]');
    if (currentUrlField) {
      currentUrlField.value = window.location.href;
      formData.append('current_url', window.location.href);
    }
    
    const nameField = form.querySelector('input[name="name"]'); 
    if (nameField) {
      formData.append('name', nameField.value);
    }
    const phoneField = form.querySelector('input[name="phone"]'); 
    if (phoneField) {
      formData.append('phone', phoneField.value);
    }
    const squareField = form.querySelector('input[name="square"]'); 
    if (squareField) {
      formData.append('square', squareField.value);
    }
    const emailField = form.querySelector('input[name="email"]'); 
    if (emailField && emailField.value.trim()) {
      formData.append('email', emailField.value);
    }
    const policyField = form.querySelector('input[name="policy"]'); 
    if (policyField) {
      formData.append('policy', policyField.checked ? 'on' : 'off');
    }
    const lang = document.documentElement.lang ? document.documentElement.lang.toLowerCase() : 'ru';
    formData.append('lang', lang);

    // Добавляем UTM-метки при наличии UTM-сессии или UTM в URL
    const utmParams = [
      'utm_source',
      'utm_medium',
      'utm_campaign',
      'utm_term',
      'utm_content'
    ];
    
    // Функция для получения куки, если window.utmHelper не доступен
    const getCookie = function(name) {
      const nameEQ = name + "=";
      const ca = document.cookie.split(';');
      for(let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
      }
      return null;
    };
    
    const hasUtmSession = (function(){ try { return sessionStorage.getItem('utm_session') === '1'; } catch(e) { return false; } })();
    const urlParams = new URLSearchParams(window.location.search);
    const hasUtmInUrl = utmParams.some(k => !!urlParams.get(k));
    if (hasUtmSession || hasUtmInUrl) {
      utmParams.forEach(param => {
        let value = null;
        try { value = sessionStorage.getItem(param) || null; } catch(e) {}
        if (!value) value = urlParams.get(param);
        if (!value) {
          // Используем утилиту для работы с UTM, если она доступна, иначе берем напрямую из куки
          value = window.utmHelper && typeof window.utmHelper.getCookie === 'function' 
            ? window.utmHelper.getCookie(param) 
            : getCookie(param);
        }
        if (value) {
          formData.append(param, value);
        }
      });
      // Маркер активной UTM-сессии
      formData.append('utm_session', '1');
    }

    const xhr = new XMLHttpRequest();
    // Добавляем к URL запроса актуальные UTM из адресной строки (last-touch),
    // чтобы на сервере они пришли через GET и переопределили куки
    const baseSendUrl = window.url('api/send');
    const currentUrlParams = new URLSearchParams(window.location.search);
    const utmKeysForGet = [
      'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'
    ];
    const utmQuery = new URLSearchParams();
    utmKeysForGet.forEach(key => {
      const val = currentUrlParams.get(key);
      if (val) utmQuery.set(key, val);
    });
    let sendUrl = baseSendUrl;
    if ([...utmQuery.keys()].length > 0) {
      sendUrl += (baseSendUrl.indexOf('?') === -1 ? '?' : '&') + utmQuery.toString();
    }

    xhr.open('POST', sendUrl, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function () {
      if (submitButton) { 
        submitButton.disabled = false; 
        submitButton.classList.remove('loading'); 
      }
      try {
        let serverResponse;
        try { 
          serverResponse = JSON.parse(xhr.responseText); 
          
          // Добавляем проверку на пустой ответ или некорректный формат
          if (!serverResponse || typeof serverResponse !== 'object') {
            throw new Error('Некорректный формат ответа');
          }
          
          // Обрабатываем специальный статус "processing"
          if (serverResponse.processing === true) {
            // Отправляем дополнительный запрос для проверки статуса
            checkSubmissionStatus(form, submitButton, originalButtonContent, serverResponse);
            return; // Прерываем обработку текущего ответа
          }
          
          // Если в ответе нет поля success, считаем ответ успешным
          // Это нужно для совместимости со старой версией API
          if (typeof serverResponse.success === 'undefined') {
            console.warn('Поле success отсутствует в ответе, предполагаем успешную отправку');
            serverResponse.success = true;
          }
        }
        catch (e) {
          console.error('JSON parse error from send.php:', e, '; Response:', xhr.responseText);
          serverResponse = { success: false, message: getLocalizedText('error', 'response_error', 'Произошла ошибка при обработке ответа сервера') };
        }
        if (serverResponse.success) {
          form.dispatchEvent(new CustomEvent('formSubmissionSuccess', {
            bubbles: true,
            detail: { form: form, formData: formData, serverResponse: serverResponse }
          }));
          
          // Меняем иконку и текст при успешной отправке
          if (submitButton) {
            submitButton.classList.add('no-pseudo-icon'); // Сохраняем отключение псевдоэлемента
            submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-check-sm-color-2.svg')}" alt="Успешно" class="button-icon"> Успешно отправлена`;
          }
          
          showSuccessMessage(form);
          if (typeof form.reset === 'function') {
            form.reset();
          } else if (form instanceof HTMLElement) {
            // Попытка найти форму и вызвать reset
            const formElement = form.tagName === 'FORM' ? form : form.querySelector('form');
            if (formElement && typeof formElement.reset === 'function') {
              formElement.reset();
            }
          }
          clearErrors(form);
        } else {
          // Восстанавливаем оригинальный текст кнопки в случае ошибки
          if (submitButton && originalButtonContent) {
            submitButton.classList.remove('no-pseudo-icon'); // Возвращаем псевдоэлемент
            submitButton.innerHTML = originalButtonContent;
          }
          
          let displayedError = false;
          if (serverResponse.errors) {
            for (const fieldName in serverResponse.errors) {
              const field = form.querySelector(`[name="${fieldName}"]`);
              if (field) {
                markFieldAsError(field, serverResponse.errors[fieldName]);
                if (!displayedError) { showFormError(form, serverResponse.errors[fieldName]); displayedError = true; }
              }
            }
          }
          if (!displayedError && serverResponse.message) {
            showFormError(form, serverResponse.message);
          } else if (!displayedError) {
            const generalErrorKey = serverResponse.message ? null : (getLocalizedText('error', 'form_errors', null) ? 'form_errors' : 'server_error');
            if (generalErrorKey) {
              showFormError(form, getLocalizedText('error', generalErrorKey, 'Произошла ошибка'));
            } else if (!serverResponse.message) {
              showFormError(form, getLocalizedText('error', 'server_error', 'Произошла ошибка при отправке.'));
            }
          }
        }
      } catch (e) {
        // Восстанавливаем оригинальный текст кнопки в случае ошибки
        if (submitButton && originalButtonContent) {
          submitButton.classList.remove('no-pseudo-icon'); // Возвращаем псевдоэлемент
          submitButton.innerHTML = originalButtonContent;
        }
        
        console.error('General error processing response from send.php:', e);
        showFormError(form, getLocalizedText('error', 'response_error', 'Непредвиденная ошибка обработки ответа'));
      }
    };
    xhr.onerror = function () {
      // Восстанавливаем оригинальный текст кнопки в случае ошибки
      if (submitButton) { 
        submitButton.disabled = false; 
        submitButton.classList.remove('loading');
        if (originalButtonContent) {
          submitButton.classList.remove('no-pseudo-icon'); // Возвращаем псевдоэлемент
          submitButton.innerHTML = originalButtonContent;
        }
      }
      showFormError(form, getLocalizedText('error', 'connection_error', 'Ошибка соединения'));
    };
    xhr.send(formData);
  }

  function showSuccessMessage(form) {
    // Отключаем показ сообщения "Спасибо за заявку"
    
    // Только сбрасываем форму
    if (typeof form.reset === 'function') {
      form.reset();
    } else if (form instanceof HTMLElement) {
      // Попытка найти форму и вызвать reset
      const formElement = form.tagName === 'FORM' ? form : form.querySelector('form');
      if (formElement && typeof formElement.reset === 'function') {
        formElement.reset();
      }
    }
    clearErrors(form);
  }
  
  // Функция для проверки статуса отправки формы
  function checkSubmissionStatus(form, submitButton, originalButtonContent, serverResponse) {
    // Получаем ID запроса из первого ответа (должен быть timestamp)
    const requestId = serverResponse && serverResponse.request_id 
      ? serverResponse.request_id 
      : Math.floor(Date.now() / 1000); // Fallback, если не получен из первого ответа
    
    console.log('Проверка статуса заявки с ID:', requestId);
    
    // Для отладки - обрабатываем без проверки статуса, предполагая успех
    // Временное решение, пока нет проверки статуса на сервере
    if (submitButton) {
      submitButton.classList.add('no-pseudo-icon');
      submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-check-sm-color-2.svg')}" alt="Успешно" class="button-icon"> Успешно отправлена`;
      submitButton.disabled = false;
    }
    
    // Показываем форму как успешно отправленную
    form.dispatchEvent(new CustomEvent('formSubmissionSuccess', {
      bubbles: true,
      detail: { form: form, serverResponse: {success: true, message: 'Заявка принята'} }
    }));
    
    // Очищаем форму
    if (typeof form.reset === 'function') {
      form.reset();
    } else if (form instanceof HTMLElement) {
      const formElement = form.tagName === 'FORM' ? form : form.querySelector('form');
      if (formElement && typeof formElement.reset === 'function') {
        formElement.reset();
      }
    }
    clearErrors(form);
    
    return; // Отключаем реальную проверку на время отладки
    
    let attempts = 0;
    const maxAttempts = 3;
    
    // Функция для показа обновленного статуса спиннера
    function updateSpinnerStatus(attempt) {
      if (submitButton) {
        submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-spinner-sm-color-2.svg')}" alt="Загрузка" class="button-icon spinner"> Обработка заявки... (${attempt}/${maxAttempts})`;
      }
    }
    
    // Функция для проверки статуса заявки
    function checkStatus() {
      attempts++;
      updateSpinnerStatus(attempts);
      
      // Проверяем статус заявки
      const xhr = new XMLHttpRequest();
      const checkUrl = window.url('api/status') + '?request_id=' + requestId;
      
      xhr.open('GET', checkUrl, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      xhr.onload = function() {
        try {
          const response = JSON.parse(xhr.responseText);
          
          if (response.completed) {
            // Заявка обработана
            if (response.success) {
              // Успешная отправка
              if (submitButton) {
                submitButton.classList.add('no-pseudo-icon');
                submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-check-sm-color-2.svg')}" alt="Успешно" class="button-icon"> Успешно отправлена`;
                submitButton.disabled = false;
              }
              
              // Очищаем форму
              if (typeof form.reset === 'function') {
                form.reset();
              } else if (form instanceof HTMLElement) {
                const formElement = form.tagName === 'FORM' ? form : form.querySelector('form');
                if (formElement && typeof formElement.reset === 'function') {
                  formElement.reset();
                }
              }
              clearErrors(form);
              
              // Выводим сообщение об успехе
              form.dispatchEvent(new CustomEvent('formSubmissionSuccess', {
                bubbles: true,
                detail: { form: form, serverResponse: response }
              }));
              
            } else {
              // Ошибка отправки
              if (submitButton) {
                submitButton.classList.remove('no-pseudo-icon');
                submitButton.innerHTML = originalButtonContent;
                submitButton.disabled = false;
                submitButton.classList.remove('loading');
              }
              
              // Показываем ошибку
              showFormError(form, response.message || getLocalizedText('error', 'server_error', 'Произошла ошибка при отправке.'));
            }
          } else if (attempts < maxAttempts) {
            // Еще не обработана, пробуем еще раз через 1.5 сек
            setTimeout(checkStatus, 1500);
          } else {
            // Превышено количество попыток
            if (submitButton) {
              submitButton.classList.remove('no-pseudo-icon');
              submitButton.innerHTML = originalButtonContent;
              submitButton.disabled = false;
              submitButton.classList.remove('loading');
            }
            
            // Предполагаем, что заявка принята, но статус неизвестен
            form.dispatchEvent(new CustomEvent('formSubmissionSuccess', {
              bubbles: true,
              detail: { form: form, serverResponse: {success: true, message: 'Заявка отправлена, но статус не подтвержден'} }
            }));
            
            if (typeof form.reset === 'function') {
              form.reset();
            } else if (form instanceof HTMLElement) {
              const formElement = form.tagName === 'FORM' ? form : form.querySelector('form');
              if (formElement && typeof formElement.reset === 'function') {
                formElement.reset();
              }
            }
            clearErrors(form);
            
            // Обновляем кнопку
            if (submitButton) {
              submitButton.classList.add('no-pseudo-icon');
              submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-check-sm-color-2.svg')}" alt="Успешно" class="button-icon"> Заявка принята`;
            }
          }
        } catch (e) {
          console.error('Ошибка при проверке статуса:', e);
          
          // В случае ошибки предполагаем, что заявка отправлена
          if (submitButton) {
            submitButton.classList.add('no-pseudo-icon');
            submitButton.innerHTML = `<img src="${window.url('data/img/ui/icons/icon-check-sm-color-2.svg')}" alt="Успешно" class="button-icon"> Заявка принята`;
            submitButton.disabled = false;
          }
          
          form.dispatchEvent(new CustomEvent('formSubmissionSuccess', {
            bubbles: true,
            detail: { form: form, serverResponse: {success: true, message: 'Заявка принята'} }
          }));
          
          if (typeof form.reset === 'function') {
            form.reset();
          } else if (form instanceof HTMLElement) {
            const formElement = form.tagName === 'FORM' ? form : form.querySelector('form');
            if (formElement && typeof formElement.reset === 'function') {
              formElement.reset();
            }
          }
          clearErrors(form);
        }
      };
      
      xhr.onerror = function() {
        // В случае ошибки соединения
        if (submitButton) {
          submitButton.classList.remove('no-pseudo-icon');
          submitButton.innerHTML = originalButtonContent;
          submitButton.disabled = false;
          submitButton.classList.remove('loading');
        }
        
        showFormError(form, getLocalizedText('error', 'connection_error', 'Ошибка соединения'));
      };
      
      xhr.send();
    }
    
    // Запускаем первую проверку через 1 секунду
    setTimeout(checkStatus, 1000);
  }
});
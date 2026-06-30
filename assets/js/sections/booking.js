import { onReady } from '../base/init.js';

// Кнопка «Забронировать» (.js-button-booking) ведёт к форме обратной связи (.js-form-callback)
// в подвале: прокрутка к форме + фокус первого поля. Делегирование — ловит кнопку в любом месте.
onReady(() => {
  document.addEventListener('click', (event) => {
    const button = event.target.closest('.js-button-booking');
    if (!button) {
      return;
    }
    event.preventDefault();

    const form = document.querySelector('.js-form-callback');
    if (!form) {
      return;
    }

    form.scrollIntoView({ behavior: 'smooth', block: 'center' });

    const field = form.querySelector('input, select, textarea');
    if (field) {
      window.setTimeout(() => {
        try {
          field.focus({ preventScroll: true });
        } catch {
          field.focus();
        }
      }, 500);
    }
  });
});

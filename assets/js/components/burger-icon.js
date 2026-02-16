// Обработка клика по иконке бургера
document.addEventListener('DOMContentLoaded', function() {
  const burgerIcon = document.getElementById('burgerIcon');
  let scrollPosition = 0;
  
  if (burgerIcon) {
    burgerIcon.addEventListener('click', function(e) {
      this.classList.toggle('active');
      
      const burgerMenu = document.getElementById('burgerMenu');
      if (burgerMenu) {
        burgerMenu.classList.toggle('active');
        
        // Блокировка/разблокировка прокрутки страницы
        if (burgerMenu.classList.contains('active')) {
          // Сохраняем текущую позицию прокрутки
          scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
          document.body.style.overflow = 'hidden'; // Блокируем прокрутку
          document.body.style.position = 'fixed';
          document.body.style.top = `-${scrollPosition}px`;
          document.body.style.width = '100%';
        } else {
          // Восстанавливаем прокрутку
          document.body.style.overflow = '';
          document.body.style.position = '';
          document.body.style.top = '';
          document.body.style.width = '';
          window.scrollTo(0, scrollPosition);
        }
      }
    });
  }

  // Обработка клика по меню
  const burgerMenu = document.getElementById('burgerMenu');
  if (burgerMenu) {
    burgerMenu.addEventListener('click', function (e) {
      // Не закрываем меню при клике на сам контейнер меню, но не блокируем ссылки
      if (e.target === burgerMenu || e.target.classList.contains('container')) {
        e.stopPropagation();
      }
    });
  }

  // Закрытие меню при клике на пункты меню
  const menuItems = document.querySelectorAll('#burgerMenu a');
  if (menuItems.length > 0) {
    menuItems.forEach(item => {
      item.addEventListener('click', function () {
        if (burgerIcon) {
          burgerIcon.classList.remove('active');
        }
        if (burgerMenu) {
          burgerMenu.classList.remove('active');
          // Восстанавливаем прокрутку
          document.body.style.overflow = '';
          document.body.style.position = '';
          document.body.style.top = '';
          document.body.style.width = '';
          window.scrollTo(0, scrollPosition);
        }
      });
    });
  }

  // Закрытие меню при клике вне меню
  document.addEventListener('click', function (e) {
    if (burgerIcon && burgerMenu) {
      if (!burgerIcon.contains(e.target) && !burgerMenu.contains(e.target)) {
        burgerIcon.classList.remove('active');
        burgerMenu.classList.remove('active');
        // Восстанавливаем прокрутку
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, scrollPosition);
      }
    }
  });
});
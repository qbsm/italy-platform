// JavaScript для accordion
document.addEventListener('DOMContentLoaded', function() {
  console.log('accordion загружен');
  const accordions = document.querySelectorAll('.accordion');
  
  accordions.forEach(accordion => {
    const items = accordion.querySelectorAll('.accordion__item.question-wrap');
    const showAllItem = accordion.querySelector('.accordion__item.show-all');
    const showAllButton = showAllItem ? showAllItem.querySelector('.accordion__show-all') : null;
    const initialItemsCount = 10;
    
    // Если элементов больше 10, скрываем лишние и показываем кнопку "Показать все"
    if (items.length > initialItemsCount) {
      items.forEach((item, index) => {
        if (index >= initialItemsCount) {
          item.style.display = 'none';
        }
      });
      
      // Показываем кнопку "Показать все"
      if (showAllItem) {
        showAllItem.style.display = 'flex';
      }
      
      // Добавляем обработчик клика на кнопку "Показать все"
      if (showAllButton) {
        showAllButton.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Показываем все скрытые элементы
          items.forEach((item, index) => {
            if (index >= initialItemsCount) {
              item.style.display = 'flex';
            }
          });
          
          // Скрываем кнопку после показа всех элементов
          showAllItem.style.display = 'none';
        });
      }
    } else {
      // Если элементов меньше или равно 10, скрываем кнопку "Показать все"
      if (showAllItem) {
        showAllItem.style.display = 'none';
      }
    }
    
    // Инициализация функционала аккордеона
    items.forEach(item => {
      const titleWrap = item.querySelector('.title-wrap');
      const descWrap = item.querySelector('.desc-wrap');
      const iconWrap = titleWrap.querySelector('.accordion__icon-wrap');
      
      // Изначально скрываем описание
      descWrap.style.maxHeight = '0';
      descWrap.style.opacity = '0';
      item.classList.remove('active');
      
      titleWrap.addEventListener('click', () => {
        const isActive = item.classList.contains('active');
        
        // Закрываем все открытые элементы в текущем аккордеоне
        items.forEach(otherItem => {
          if (otherItem !== item) {
            const otherDescWrap = otherItem.querySelector('.desc-wrap');
            otherItem.classList.remove('active');
            otherDescWrap.style.maxHeight = '0';
            otherDescWrap.style.opacity = '0';
          }
        });
        
        // Переключаем текущий элемент
        if (!isActive) {
          item.classList.add('active');
          descWrap.style.maxHeight = descWrap.scrollHeight + 'px';
          descWrap.style.opacity = '1';
        } else {
          item.classList.remove('active');
          descWrap.style.maxHeight = '0';
          descWrap.style.opacity = '0';
        }
      });
    });
  });
});

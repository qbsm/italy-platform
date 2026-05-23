import { onReady } from '../base/init.js';

onReady(() => {
  const cards = document.querySelectorAll('.card-number__item.title-wrap');
  if (!cards.length) return;

  cards.forEach((card) => {
    const titleEl = card.querySelector('.card-number__title');
    if (!titleEl) return;

    const labelWrap = card.parentElement.querySelector('.card-number__item.label-wrap');
    const plusEl = titleEl.querySelector('.card-number__plus');

    const target = parseInt(titleEl.textContent.replace(/\D/g, ''), 10);
    if (isNaN(target)) return;

    const duration = parseFloat(card.dataset.time || '1') * 1000;

    let numberNode = titleEl.firstChild;
    if (!numberNode || numberNode.nodeType !== Node.TEXT_NODE) {
      numberNode = document.createTextNode('');
      titleEl.insertBefore(numberNode, titleEl.firstChild);
    }
    numberNode.nodeValue = '0';

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          observer.unobserve(card);
          animateCount(numberNode, target, duration, plusEl, labelWrap);
        });
      },
      { threshold: 0.3 }
    );

    observer.observe(card);
  });

  function animateCount(node, target, duration, plusEl, labelWrap) {
    const start = performance.now();

    function tick(now) {
      const elapsed = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3);
      node.nodeValue = String(Math.round(eased * target));

      if (progress < 1) {
        requestAnimationFrame(tick);
      } else {
        if (plusEl) plusEl.classList.add('visible');
        if (labelWrap) labelWrap.classList.add('visible');
      }
    }

    requestAnimationFrame(tick);
  }
});

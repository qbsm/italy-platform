document.addEventListener('DOMContentLoaded', function () {
  const buttons = document.querySelectorAll('[data-tag]');
  const cards = document.querySelectorAll('.card-restaurant-wrap[data-tags]');

  if (!buttons.length || !cards.length) return;

  let activeTag = null;

  buttons.forEach(function (btn) {
    btn.addEventListener('click', function () {
      const tag = btn.dataset.tag;

      if (activeTag === tag) {
        activeTag = null;
        buttons.forEach(function (b) { b.classList.remove('active'); });
        cards.forEach(function (card) { card.style.display = ''; });
        return;
      }

      activeTag = tag;

      buttons.forEach(function (b) {
        b.classList.toggle('active', b.dataset.tag === tag);
      });

      cards.forEach(function (card) {
        const cardTags = card.dataset.tags.split(',');
        card.style.display = cardTags.indexOf(tag) !== -1 ? '' : 'none';
      });
    });
  });
});

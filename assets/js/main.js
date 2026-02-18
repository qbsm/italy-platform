// assets/js/main.js

/**
 * Генерирует полный URL на основе базового URL и относительного пути.
 * @param {string} path Относительный путь
 * @returns {string} Полный URL
 */
function url(path) {
  if (!window.appConfig || typeof window.appConfig.baseUrl === 'undefined') {
    console.error('window.appConfig.baseUrl не определен');
    return '/' + (path.startsWith('/') ? path.substring(1) : path);
  }
  const trimmedPath = path.startsWith('/') ? path.substring(1) : path;
  const baseUrl = window.appConfig.baseUrl.endsWith('/') ? window.appConfig.baseUrl : window.appConfig.baseUrl + '/';
  return baseUrl + trimmedPath;
}
window.url = url;

// --- Vendor ---
import './vendor.js';
import './base/expose-vendors.js';

// --- Sections ---
import './sections/logoline.js';
import './sections/content.js';
import './sections/intro.js';
import './sections/footer.js';
import './sections/burger-menu.js';
import './sections/header.js';
import './sections/cookie-panel.js';
import './sections/contacts.js';
import './sections/headline.js';
import './sections/us.js';

// --- Components ---
import './components/button.js';
import './components/analytics.js';
import './components/form-callback.js';
import './components/heading.js';
import './components/accordion.js';
import './components/spoiler.js';
import './components/custom-list.js';
import './components/numbered-list.js';
import './components/mini-table.js';
import './components/blockquote.js';
import './components/cover.js';
import './components/features-list.js';
import './components/card-number.js';
import setupSliders from './components/slider.js';
import './components/burger-icon.js';

// --- Pages ---
import './pages/404.js';
import './pages/contacts.js';
import './pages/index.js';

// --- Init ---
document.addEventListener('DOMContentLoaded', () => {
  if (typeof setupSliders === 'function') {
    setupSliders();
  }
});

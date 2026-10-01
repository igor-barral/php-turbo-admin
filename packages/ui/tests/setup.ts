import '@testing-library/jest-dom/vitest';

beforeEach(() => {
  window.location.hash = '';
  document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT';
});

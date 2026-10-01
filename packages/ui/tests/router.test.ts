import { href, navigate, parseRoute, type Route } from '../src/lib/router';
import { createRouter } from '../src/lib/router.svelte';

describe('parseRoute', () => {
  it.each<[string, Route]>([
    ['', { name: 'home' }],
    ['#/', { name: 'home' }],
    ['#/posts/new', { name: 'create', entity: 'posts' }],
    ['#/posts/7', { name: 'show', entity: 'posts', id: '7' }],
    ['#/posts/a%2Fb/edit', { name: 'edit', entity: 'posts', id: 'a/b' }],
    ['#/posts/7/delete', { name: 'notFound' }],
    ['#/a/b/c/d', { name: 'notFound' }],
  ])('parses %s', (hash, expected) => {
    expect(parseRoute(hash)).toEqual(expected);
  });

  it('parses list query strings', () => {
    const route = parseRoute('#/posts?sort=-id&q=ol%C3%A1');

    expect(route.name).toBe('list');
    expect(route.name === 'list' && route.query.get('q')).toBe('olá');
  });
});

describe('href', () => {
  it.each<[Route, string]>([
    [{ name: 'home' }, '#/'],
    [{ name: 'notFound' }, '#/'],
    [{ name: 'list', entity: 'posts', query: new URLSearchParams() }, '#/posts'],
    [{ name: 'list', entity: 'posts', query: new URLSearchParams({ sort: '-id' }) }, '#/posts?sort=-id'],
    [{ name: 'create', entity: 'posts' }, '#/posts/new'],
    [{ name: 'show', entity: 'posts', id: 'a/b' }, '#/posts/a%2Fb'],
    [{ name: 'edit', entity: 'posts', id: '1' }, '#/posts/1/edit'],
  ])('builds %o', (route, expected) => {
    expect(href(route)).toBe(expected);
  });
});

describe('router', () => {
  it('tracks hash changes until destroyed', () => {
    const router = createRouter();
    expect(router.current).toEqual({ name: 'home' });

    navigate({ name: 'show', entity: 'posts', id: '3' });
    window.dispatchEvent(new HashChangeEvent('hashchange'));
    expect(router.current).toEqual({ name: 'show', entity: 'posts', id: '3' });

    router.destroy();
    window.location.hash = '#/users';
    window.dispatchEvent(new HashChangeEvent('hashchange'));
    expect(router.current).toEqual({ name: 'show', entity: 'posts', id: '3' });
  });
});

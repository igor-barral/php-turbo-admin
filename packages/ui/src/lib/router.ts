// Roteamento por hash (#/posts/1): funciona sob qualquer prefixo (/admin, contêiner na raiz)
// sem rota catch-all no servidor e com assets relativos.

export type Route =
  | { name: 'home' }
  | { name: 'list'; entity: string; query: URLSearchParams }
  | { name: 'create'; entity: string }
  | { name: 'show'; entity: string; id: string }
  | { name: 'edit'; entity: string; id: string }
  | { name: 'notFound' };

export function parseRoute(hash: string): Route {
  const [path = '', search = ''] = hash.replace(/^#/, '').split('?', 2);
  const parts = path.split('/').filter(Boolean).map(decodeURIComponent);
  const [entity, id, action] = parts;

  if (entity === undefined) return { name: 'home' };
  if (parts.length === 1) return { name: 'list', entity, query: new URLSearchParams(search) };
  if (parts.length === 2 && id === 'new') return { name: 'create', entity };
  if (parts.length === 2 && id !== undefined) return { name: 'show', entity, id };
  if (parts.length === 3 && id !== undefined && action === 'edit') return { name: 'edit', entity, id };

  return { name: 'notFound' };
}

export function href(route: Route): string {
  const e = (value: string) => encodeURIComponent(value);

  switch (route.name) {
    case 'home':
    case 'notFound':
      return '#/';
    case 'list': {
      const query = route.query.toString();
      return `#/${e(route.entity)}${query ? '?' + query : ''}`;
    }
    case 'create':
      return `#/${e(route.entity)}/new`;
    case 'show':
      return `#/${e(route.entity)}/${e(route.id)}`;
    case 'edit':
      return `#/${e(route.entity)}/${e(route.id)}/edit`;
  }
}

export function navigate(route: Route): void {
  window.location.hash = href(route);
}

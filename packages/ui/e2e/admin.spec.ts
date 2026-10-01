import { expect, test, type Page } from '@playwright/test';

/** Falha o teste em qualquer erro de console — inclusive violações da CSP estrita. */
function watchConsole(page: Page): string[] {
  const problems: string[] = [];
  page.on('console', (message) => {
    // 4xx de validação/autorização são respostas esperadas da API, não erros da página.
    if (message.type() === 'error' && !/status of 4\d\d/.test(message.text())) problems.push(message.text());
  });
  page.on('pageerror', (error) => problems.push(error.message));
  return problems;
}

async function login(page: Page): Promise<void> {
  await page.goto('/admin');
  await expect(page).toHaveURL(/\/login$/);
  await page.getByLabel('Senha').fill('password');
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page.getByRole('heading', { name: 'Início' })).toBeVisible();
}

test('navigates, filters, creates, edits and deletes under a strict CSP', async ({ page }) => {
  const problems = watchConsole(page);
  await login(page);

  // Listagem com relações carregadas e ordenação.
  await page.getByRole('navigation', { name: 'Entidades' }).getByRole('link', { name: 'Publicações' }).click();
  await expect(page.getByRole('heading', { name: 'Publicações' })).toBeVisible();
  await expect(page.locator('tbody tr')).toHaveCount(25);
  await page.getByRole('button', { name: /Título/ }).click();
  await expect(page).toHaveURL(/sort=title/);
  await expect(page.getByRole('columnheader', { name: /Título/ })).toHaveAttribute('aria-sort', 'ascending');

  // Filtro por enum e paginação por cursor.
  await page.getByRole('combobox', { name: 'Status' }).selectOption('published');
  await expect(page).toHaveURL(/filter%5Bstatus%5D=published|filter\[status\]=published/);
  await page.getByRole('combobox', { name: 'Status' }).selectOption('');
  await page.getByRole('button', { name: 'Próxima' }).click();
  await expect(page).toHaveURL(/cursor=/);
  await page.getByRole('button', { name: 'Anterior' }).click();
  await expect(page).not.toHaveURL(/cursor=/);

  // Criação com validação do servidor.
  await page.getByRole('navigation', { name: 'Entidades' }).getByRole('link', { name: 'Categorias' }).click();
  await page.getByRole('link', { name: 'Novo' }).click();
  await page.getByRole('button', { name: 'Salvar' }).click();
  await expect(page.getByText('Campo obrigatório.')).toBeVisible();

  const name = `E2E ${Date.now()}`;
  await page.getByLabel(/Nome/).fill(name);
  await page.getByLabel('Descrição').fill('Criada pelo teste E2E');
  await page.getByRole('button', { name: 'Salvar' }).click();
  await expect(page.getByRole('heading', { name })).toBeVisible();
  await expect(page.getByRole('status')).toHaveText(/Registro salvo/);

  // Edição envia só o que mudou.
  await page.getByRole('link', { name: 'Editar' }).click();
  await page.getByLabel(/Nome/).fill(`${name} editada`);
  await page.getByRole('button', { name: 'Salvar' }).click();
  await expect(page.getByRole('heading', { name: `${name} editada` })).toBeVisible();

  // Exclusão com confirmação.
  await page.getByRole('button', { name: 'Excluir' }).click();
  await page.getByRole('alertdialog').getByRole('button', { name: 'Excluir' }).click();
  await expect(page.getByRole('heading', { name: 'Categorias' })).toBeVisible();
  await expect(page.getByText(`${name} editada`)).toHaveCount(0);

  expect(problems).toEqual([]);
});

test('edits a belongsTo relation through the lookup picker', async ({ page }) => {
  const problems = watchConsole(page);
  await login(page);

  await page.goto('/admin/#/posts');
  await page.locator('tbody tr').first().getByRole('link', { name: 'Editar' }).click();
  const picker = page.getByRole('combobox', { name: 'Categoria' });
  await picker.fill('Segu');
  await page.getByRole('option').getByRole('button', { name: 'Segurança' }).click();
  await page.getByRole('button', { name: 'Salvar' }).click();

  await expect(page.getByRole('link', { name: 'Segurança' })).toBeVisible();
  expect(problems).toEqual([]);
});

test('logs out with a CSRF protected POST', async ({ page }) => {
  await login(page);

  await page.getByRole('button', { name: 'Sair' }).click();

  await expect(page).toHaveURL(/\/login$/);
});

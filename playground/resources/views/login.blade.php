<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Playground</title>
    <style>
        body { font: 15px/1.5 system-ui, sans-serif; display: grid; place-items: center; min-height: 100vh; margin: 0; background: #f6f7f9; }
        form { background: #fff; padding: 2rem; border-radius: 8px; border: 1px solid #dde1e7; display: grid; gap: .75rem; width: min(22rem, 90vw); }
        input { padding: .5rem; border: 1px solid #dde1e7; border-radius: 6px; font: inherit; }
        button { padding: .55rem; border: 0; border-radius: 6px; background: #3b5bdb; color: #fff; font: inherit; cursor: pointer; }
        .error { color: #c92a2a; margin: 0; }
        .hint { color: #5f6b7a; font-size: .85rem; margin: 0; }
    </style>
</head>
<body>
<form method="post" action="/login">
    @csrf
    <h1 style="margin:0;font-size:1.3rem">php-turbo-admin · playground</h1>
    @error('email') <p class="error">{{ $message }}</p> @enderror
    <label>E-mail <input name="email" type="email" value="{{ old('email', 'admin@example.com') }}" required autofocus style="width:100%"></label>
    <label>Senha <input name="password" type="password" value="" required style="width:100%"></label>
    <p class="hint">Usuário de exemplo: admin@example.com / password</p>
    <button type="submit">Entrar</button>
</form>
</body>
</html>

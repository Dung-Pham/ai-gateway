<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Cổng AI</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root { color-scheme: light dark; --bg:#f4f7fb; --card:#fff; --ink:#0b1b33; --soft:#4a5a73; --line:#d9e2ef; --primary:#1e5bc6; }
        @media (prefers-color-scheme: dark) { :root { --bg:#0a1220; --card:#101a2e; --ink:#e8eef8; --soft:#9fb0c9; --line:#233250; --primary:#6ea2ff; } }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:grid; place-items:center; padding:1rem; background:var(--bg); color:var(--ink);
               font-family:'Be Vietnam Pro',system-ui,-apple-system,'Segoe UI',sans-serif; line-height:1.5; }
        main { width:100%; max-width:30rem; background:var(--card); border:1px solid var(--line); border-radius:.75rem; padding:2rem; }
        .code { font-family:ui-monospace,'IBM Plex Mono',monospace; color:var(--soft); font-size:.875rem; letter-spacing:.04em; }
        h1 { margin:.25rem 0 .75rem; font-size:1.5rem; }
        p { margin:0 0 1.25rem; color:var(--soft); }
        a { display:inline-flex; align-items:center; min-height:44px; padding:0 1rem; border-radius:.5rem; background:var(--primary); color:#fff; text-decoration:none; font-weight:600; }
        @media (prefers-color-scheme: dark) { a { color:#0a1220; } }
        a:focus-visible { outline:3px solid var(--ink); outline-offset:2px; }
    </style>
</head>
<body>
    <main>
        <div class="code">Lỗi @yield('code')</div>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <a href="/">Về trang chính</a>
    </main>
</body>
</html>

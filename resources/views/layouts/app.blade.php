<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'MCA Patient Document Generator') }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f1f5f9; color: #111827; font-size: 14px; line-height: 1.5; }
        h1, h2, h3, h4 { margin: 0 0 .5rem; line-height: 1.25; }
        p { margin: 0 0 .5rem; }
        a { color: #1d4ed8; }
        /* Design tokens: one source of truth shared by the shell (navbar,
           sidebar) and the data views, so analytics, tables and links cannot
           drift away from the sidebar's look. */
        :root {
            --nav-h: 54px;
            --brand: #2563eb;
            --brand-2: #4f46e5;
            --violet: #7c3aed;
            --brand-rgb: 37, 99, 235;
            --grad-brand: linear-gradient(135deg, #2563eb, #7c3aed);
            --grad-shell: linear-gradient(180deg, #0f172a 0%, #1e1b4b 100%);
            --ink: #0f172a;
            --muted: #64748b;
            --muted-2: #94a3b8;
            --side-text: #cbd5e1;
            --line: #e8edf3;
            --line-2: #eef2f7;
            --surface: #f8fafc;
            --danger: #dc2626;
            --success: #16a34a;
            --radius: 14px;
            --radius-sm: 10px;
            --shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px -12px rgba(15,23,42,.12);
        }
        .hidden { display: none !important; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

        /* Top navbar: viewport-pinned, fixed height, single row, never wraps */
        .navbar { position: fixed; top: 0; left: 0; right: 0; height: var(--nav-h); z-index: 30; background: #fff; border-bottom: 1px solid #e5e7eb; }
        .app { padding-top: var(--nav-h); }
        .navbar-inner { display: flex; align-items: center; gap: .9rem; height: 100%; padding: 0 1.1rem; flex-wrap: nowrap; }
        .navbar .brand { display: flex; align-items: center; gap: .5rem; font-size: 16px; font-weight: 800; color: #0f172a; text-decoration: none; white-space: nowrap; }
        .navbar .brand-mark { width: 26px; height: 26px; border-radius: 7px; font-size: 12px; }
        .navbar .crumb { color: #64748b; font-size: 12.5px; margin-right: auto; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .navbar .avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--grad-brand); color: #fff; font-weight: 700; font-size: 12px; display: flex; align-items: center; justify-content: center; }
        .navbar .whoami { line-height: 1.15; text-align: right; }
        .navbar .whoami div:first-child { font-size: 12.5px; font-weight: 600; color: #0f172a; }
        .navbar .whoami div:last-child { font-size: 11px; color: #64748b; text-transform: capitalize; }
        /* Scoped under .navbar on purpose: the generic button[type=button] and
           button[type=submit] rules below would otherwise win on specificity
           and turn the avatar button into an inline-block, which wraps the
           name/role/avatar onto extra lines and pushes the avatar out of the bar. */
        .navbar .user-menu { position: relative; margin-left: auto; flex-shrink: 0; }
        .navbar .user-btn { display: flex; align-items: center; gap: .45rem; background: none; border: 1px solid transparent; border-radius: 999px; padding: .18rem .5rem .18rem .22rem; cursor: pointer; font-family: inherit; font-size: 14px; line-height: 1.15; color: #0f172a; white-space: nowrap; }
        .navbar .user-btn:hover, .navbar .user-menu.open .user-btn { background: #f1f5f9; border-color: #e2e8f0; }
        .navbar .user-btn .caret { color: #94a3b8; font-size: 10px; }
        .navbar .user-drop { position: absolute; right: 0; top: calc(100% + 8px); min-width: 212px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 12px 32px -12px rgba(15,23,42,.28); padding: .35rem; display: none; z-index: 60; }
        .navbar .user-menu.open .user-drop { display: block; }
        .navbar .user-drop .meta { padding: .5rem .6rem; border-bottom: 1px solid #eef2f7; margin-bottom: .3rem; }
        .navbar .user-drop .meta strong { display: block; font-size: 13px; color: #0f172a; }
        .navbar .user-drop .meta span { font-size: 12px; color: #64748b; text-transform: capitalize; }
        .navbar .user-drop a, .navbar .user-drop button { display: block; width: 100%; text-align: left; padding: .5rem .6rem; border-radius: 6px; font-size: 13px; font-weight: 500; color: #334155; text-decoration: none; background: none; border: 0; box-shadow: none; cursor: pointer; font-family: inherit; }
        .navbar .user-drop a:hover, .navbar .user-drop button:hover { background: #f1f5f9; color: #0f172a; }
        .navbar .user-drop button.danger { color: #b91c1c; }
        .navbar .user-drop button.danger:hover { background: #fef2f2; }
        @media (max-width: 639.98px) { .navbar .crumb, .navbar .whoami, .navbar .user-btn .caret { display: none; } }
        .navbar .hamburger { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: .3rem .55rem; font-size: 15px; cursor: pointer; color: #0f172a; line-height: 1; }

        /* Shell: normal-flow flex row. Sidebar first, content second.
           No fixed/sticky positioning anywhere, so nothing can overlap
           or transfer out of order on any screen size. */
        .app { display: block; }
        .sidebar { display: none; }
        .drawer-veil { position: fixed; inset: 0; background: rgba(2,6,23,.55); z-index: 40; }
        @media (min-width: 1024px) {
            .navbar .hamburger, .drawer-veil { display: none !important; }
            .app { display: flex; align-items: flex-start; padding-top: var(--nav-h); }
            .sidebar {
                display: flex; flex-direction: column; flex-shrink: 0;
                position: fixed; top: var(--nav-h); bottom: 0; left: 0; width: 260px; z-index: 30;
                overflow-y: auto;
                background: var(--grad-shell);
                color: #cbd5e1; padding: 1.25rem .9rem;
            }
            .main { flex: 1; min-width: 0; margin-left: 260px; }
        }

        .brand-mark { width: 40px; height: 40px; border-radius: 10px; background: var(--grad-brand); color: #fff; font-weight: 800; font-size: 18px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .nav-label { font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: var(--muted-2); padding: 0 .6rem; margin-bottom: .4rem; }
        .side-nav { display: flex; flex-direction: column; gap: .25rem; flex: 1; }
        .side-nav a { display: flex; align-items: center; gap: .7rem; padding: .6rem .75rem; border-radius: 8px; color: var(--side-text); text-decoration: none; font-size: 14px; }
        .side-nav a:hover { background: rgba(148,163,184,.12); color: #fff; }
        .side-nav a.active { background: var(--brand); color: #fff; font-weight: 600; box-shadow: 0 4px 12px rgba(var(--brand-rgb),.4); }
        .side-nav svg { flex-shrink: 0; }

        /* Mobile drawer reuses the sidebar look */
        .drawer { position: fixed; top: 0; bottom: 0; left: 0; width: 270px; z-index: 50; background: var(--grad-shell); color: #cbd5e1; padding: 1.25rem .9rem; display: flex; flex-direction: column; }
        .drawer .close { align-self: flex-end; background: none; border: 1px solid #334155; color: #fff; border-radius: 6px; padding: .25rem .55rem; cursor: pointer; margin-bottom: .5rem; }
        .navbar .hamburger { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px; padding: .35rem .6rem; font-size: 16px; cursor: pointer; color: #0f172a; }

        .page { width: 100%; max-width: none; margin: 0 auto; padding: 1.25rem 1.5rem 3rem; }
        .page-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .actions { display: flex; gap: .5rem; flex-wrap: wrap; }
        .page-actions { justify-content: flex-end; margin-bottom: 1rem; }
        th.align-right, td.align-right { text-align: right; }
        .page-title { font-size: 20px; font-weight: 600; }
        .card-head { margin-bottom: .25rem; }
        .meta-line { color: var(--muted); font-size: 13px; margin-bottom: .5rem; }
        /* Bulk selection: a narrow checkbox column plus a bar that reports the
           live selection. The column is fixed-width so it never steals room
           from the data columns. */
        th.pick-col, td.pick-col { width: 1%; white-space: nowrap; padding-left: .9rem; padding-right: 0; }
        th.pick-col input, td.pick-col input { width: 16px; height: 16px; accent-color: var(--brand); cursor: pointer; vertical-align: middle; }
        td.pick-col input:disabled { cursor: not-allowed; opacity: .45; }
        .bulk-bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; padding: .6rem .9rem; background: #fff; border: 1px solid var(--line); border-radius: var(--radius); }
        .bulk-actions { display: flex; align-items: center; gap: .5rem; margin-left: auto; flex-wrap: wrap; }
        .bulk-count { font-size: 13px; color: var(--muted); font-variant-numeric: tabular-nums; }
        .bulk-count strong { color: #0f172a; font-size: 14px; }
        .bulk-selectall { display: inline-flex; align-items: center; gap: .45rem; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; }
        .bulk-selectall input { width: 16px; height: 16px; accent-color: var(--brand); cursor: pointer; }
        .bulk-skip { display: inline-flex; align-items: center; gap: .4rem; font-size: 13px; font-weight: 600; color: #374151; cursor: pointer; white-space: nowrap; }
        .bulk-skip input { width: 16px; height: 16px; accent-color: var(--brand); cursor: pointer; }
        body.is-busy .bulk-bar { opacity: .75; pointer-events: none; cursor: progress; }

        /* ---- Login ---- */
        /* Deliberately top-aligned rather than vertically centred: centring on a
           tall monitor pushes the form below the fold of a short window. */
        .login-wrap { max-width: 26rem; margin: .5rem auto 0; }
        .login-card .login-head { text-align: center; margin-bottom: 1.25rem; padding-bottom: 1rem; border-bottom: 1px solid var(--line); }
        .login-head h2 { margin: 0 0 .3rem; font-size: 19px; font-weight: 600; }
        .login-head p { margin: 0; color: var(--muted); font-size: 13.5px; }
        .login-foot { margin: 1.25rem 0 0; text-align: center; font-size: 13px; }
        .login-foot a { color: var(--brand); font-weight: 600; text-decoration: none; }
        .login-foot a:hover { text-decoration: underline; }

        /* ---- Documentation ---- */
        .docs { max-width: 52rem; }
        .docs-nav { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1.25rem; }
        .docs-nav a { font-size: 13px; font-weight: 600; color: var(--brand); text-decoration: none; padding: .35rem .7rem; border: 1px solid var(--line); border-radius: 999px; background: #fff; }
        .docs-nav a:hover { border-color: var(--brand); background: #f8fafc; }
        .docs section { margin-bottom: 2rem; scroll-margin-top: calc(var(--nav-h) + 1rem); }
        .docs h2 { font-size: 18px; font-weight: 600; margin: 0 0 .75rem; padding-bottom: .4rem; border-bottom: 1px solid var(--line); }
        .docs h3 { font-size: 14.5px; font-weight: 600; margin: 1.25rem 0 .4rem; }
        .docs p, .docs li { font-size: 13.5px; line-height: 1.65; color: #374151; }
        .docs ul, .docs ol { margin: .4rem 0; padding-left: 1.25rem; }
        .docs li { margin-bottom: .3rem; }
        .docs code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; background: #f1f5f9; padding: .1rem .35rem; border-radius: 4px; }
        .docs pre { background: #0f172a; color: #e2e8f0; padding: .8rem .95rem; border-radius: var(--radius); overflow-x: auto; margin: .5rem 0; }
        .docs pre code { background: none; color: inherit; padding: 0; font-size: 12.5px; line-height: 1.6; white-space: pre; }
        .docs pre code .hint { color: #94a3b8; }
        .docs h4 { font-size: 13.5px; font-weight: 600; margin: 1.1rem 0 .35rem; color: #0f172a; }
        .docs .hint { color: var(--muted); font-size: 12px; }
        .docs table { width: 100%; border-collapse: collapse; margin: .5rem 0; font-size: 13px; }
        .docs th, .docs td { text-align: left; padding: .5rem .6rem; border-bottom: 1px solid var(--line); vertical-align: top; }
        .docs th { font-weight: 600; color: #0f172a; background: #f8fafc; }
        .docs td code { word-break: break-word; }
        @media (max-width: 700px) {
            .docs table { display: block; width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        }
        .docs .docs-note { border-left: 3px solid var(--brand); background: #f8fafc; padding: .7rem .9rem; border-radius: 0 var(--radius) var(--radius) 0; margin: .75rem 0; }
        .credits-list { list-style: none; padding-left: 0; }
        .credits-list li { display: flex; flex-wrap: wrap; align-items: baseline; gap: .5rem; padding: .45rem 0; border-bottom: 1px solid var(--line); }
        .credits-list .cr-name { font-weight: 600; min-width: 12rem; }
        .credits-list .cr-meta { color: var(--muted); font-size: 12.5px; }
        .credits-todo { border: 1px dashed #f59e0b; background: #fffbeb; color: #92400e; padding: .7rem .9rem; border-radius: var(--radius); font-size: 13px; margin: .5rem 0 0; }
        .bulk-result { margin-bottom: 1rem; padding: .9rem 1.1rem; border-radius: var(--radius); border: 1px solid; font-size: 13.5px; }
        .bulk-result.is-ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
        .bulk-result.is-partial { background: #fffbeb; border-color: #fde68a; color: #92400e; }
        .bulk-result.is-error { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
        .bulk-result-head { margin: 0; font-weight: 600; }
        .bulk-result-sub { margin: .5rem 0 .2rem; font-weight: 600; font-size: 12.5px; text-transform: uppercase; letter-spacing: .04em; opacity: .8; }
        .bulk-result ul { margin: 0; padding-left: 1.1rem; }
        .bulk-result li { margin-bottom: .15rem; }
        .bulk-result-more { opacity: .7; font-style: italic; }

        .card { background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 1.4rem; margin-bottom: 1.25rem; box-shadow: var(--shadow); }
        .flash { border-radius: 6px; padding: .6rem .8rem; margin-bottom: 1rem; }
        .flash-success { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
        .flash-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .flash-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .flash ul { margin: 0; padding-left: 1.2rem; }

        /* Record forms: two columns on desktop, stacked on narrow screens.
           Cards stretch so both columns end level, and their inner content
           distributes instead of leaving dead space at the bottom. */
        .form-wrap { max-width: 84rem; margin: 0 auto; }
        .form-wrap.narrow { max-width: 46rem; }
        .form-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 1.25rem; align-items: stretch; }
        .form-grid.single { grid-template-columns: minmax(0, 1fr); }
        .form-grid > .card, .form-col > .card { margin-bottom: 0; display: flex; flex-direction: column; }
        .form-col { display: flex; flex-direction: column; gap: 1.25rem; }
        .form-col > .card { flex: 1 1 0; }
        .fields { flex: 1; display: flex; flex-direction: column; justify-content: space-evenly; }
        .fields > .field { margin-bottom: 0; }
        .image-upload { flex: 1; display: flex; flex-direction: column; }
        .image-upload .dropzone { flex: 1; display: flex; align-items: center; justify-content: center; text-align: center; }
        @media (max-width: 900px) {
            .form-grid { grid-template-columns: minmax(0, 1fr); }
            .fields { justify-content: flex-start; gap: 1rem; }
            .fields > .field { margin-bottom: 0; }
            .image-upload .dropzone { flex: none; }
        }

        /* Uniform data tables: same tokens, same hover, same action pills and the
           same paginator on every list in the app. */
        table { border-collapse: separate; border-spacing: 0; width: 100%; background: #fff; font-size: 14px; }
        th, td { border-bottom: 1px solid var(--line-2); padding: .8rem 1rem; text-align: left; vertical-align: middle; }
        tbody tr { transition: background .12s; }
        tbody tr:last-child th, tbody tr:last-child td { border-bottom: 0; }
        tbody tr:nth-child(even) td { background: var(--surface); }
        tbody tr:hover td { background: #eff6ff; }
        thead th { position: sticky; top: 0; z-index: 1; background: var(--surface); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: var(--muted); border: 0; border-bottom: 1px solid var(--line); white-space: nowrap; }
        .table-wrap { overflow-x: auto; background: #fff; border: 1px solid var(--line); border-radius: var(--radius-sm); }
        .mono, .table-wrap code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 13px; }
        .table-empty { text-align: center; color: var(--muted); padding: 2rem 1rem; }
        /* Row actions: pill links, evenly spaced, no pipe separators */
        .row-actions { display: inline-flex; align-items: center; gap: .35rem; flex-wrap: wrap; justify-content: flex-end; }
        .row-actions a, .row-actions button, .row-actions .btn-danger { display: inline-flex; align-items: center; padding: .3rem .7rem; border-radius: 999px; font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap; cursor: pointer; }
        .row-actions a { background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe; }
        .row-actions a:hover { background: #dbeafe; border-color: #bfdbfe; }
        .row-actions .btn-danger { background: #fff; border: 1px solid #fecaca; color: #b91c1c; }
        .row-actions .btn-danger:hover { background: #fef2f2; }
        /* Pagination. Custom view at resources/views/vendor/pagination/default.blade.php
           because Laravel's default emits Tailwind classes and this app
           deliberately ships no Tailwind build. */
        .pager { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-top: 1rem; }
        .pager-info { font-size: 13px; color: var(--muted); font-variant-numeric: tabular-nums; }
        .pager-nav { display: flex; align-items: center; gap: .3rem; flex-wrap: wrap; }
        .pager-nav a, .pager-nav span { display: inline-flex; align-items: center; justify-content: center; min-width: 34px; height: 34px; padding: 0 .6rem; border-radius: 8px; border: 1px solid var(--line); background: #fff; color: #334155; font-size: 13.5px; font-weight: 600; text-decoration: none; }
        .pager-nav a:hover { background: var(--surface); border-color: #cbd5e1; }
        .pager-nav .is-current { background: var(--grad-brand); border-color: transparent; color: #fff; box-shadow: 0 4px 12px rgba(var(--brand-rgb),.3); }
        .pager-nav .is-disabled { color: #cbd5e1; background: var(--surface); }
        .pager-nav .ellipsis { border: 0; background: none; color: var(--muted-2); min-width: 20px; padding: 0; }

        /* Record detail page */
        .record-hero { display: flex; align-items: flex-start; gap: 1.25rem; flex-wrap: wrap; }
        .record-hero .avatar-lg { width: 56px; height: 56px; border-radius: 16px; background: var(--grad-brand); color: #fff; font-weight: 800; font-size: 20px; display: grid; place-items: center; flex-shrink: 0; }
        .record-hero .who { min-width: 0; flex: 1 1 280px; }
        .record-hero .who h3 { font-size: 22px; margin: 0 0 .25rem; letter-spacing: -.01em; }
        .record-hero .sub { color: var(--muted); font-size: 13.5px; display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
        .record-hero .hero-actions { display: flex; gap: .5rem; flex-wrap: wrap; align-items: center; }
        .pill { display: inline-flex; align-items: center; gap: .3rem; padding: .2rem .6rem; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .pill-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
        .pill-slate { background: #f1f5f9; color: #475569; }
        .pill-blue { background: #dbeafe; color: #1d4ed8; }
        .pill-green { background: #dcfce7; color: #166534; }
        .pill-red { background: #fee2e2; color: #991b1b; }
        .pill-amber { background: #fef3c7; color: #92400e; }
        .kv { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1.1rem 1.25rem; }
        .kv.wide { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        @media (max-width: 1100px) { .kv.wide { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .kv > div { min-width: 0; }
        .kv .k { font-size: 11.5px; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 700; margin-bottom: .2rem; }
        .kv .v { font-size: 14.5px; color: var(--ink); font-weight: 500; word-break: break-word; }
        .evidence-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; align-items: start; }
        @media (max-width: 900px) { .evidence-row { grid-template-columns: minmax(0, 1fr); } }
        /* Evidence shots use contain and size to the image's own aspect ratio,
           capped so a long portrait ID cannot dominate the page. Cropping an
           evidence photo would hide the very detail it is meant to prove, and
           upscaling a small capture would only blur it. */
        .shot { display: block; position: relative; border: 1px solid var(--line); border-radius: var(--radius-sm); overflow: hidden; background: var(--surface); padding: .5rem; }
        .shot img { display: block; max-width: 100%; max-height: 320px; width: auto; height: auto; margin: 0 auto; }
        .shot-empty { display: grid; place-items: center; height: 150px; color: var(--muted-2); font-size: 13px; text-align: center; padding: 1rem; border: 1px dashed var(--line); border-radius: var(--radius-sm); background: var(--surface); }
        .shot .zoom { position: absolute; right: .5rem; top: .5rem; background: rgba(15,23,42,.78); color: #fff; font-size: 11.5px; font-weight: 600; padding: .2rem .55rem; border-radius: 999px; opacity: 0; transition: opacity .15s; }
        .shot:hover .zoom { opacity: 1; }

        label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 4px; }
        input[type=text], input[type=date], input[type=email], input[type=password], select, textarea { width: 100%; padding: .55rem .7rem; border: 1px solid #dbe1ea; border-radius: 8px; font-size: 14px; background: #f8fafc; font-family: inherit; transition: border-color .15s, box-shadow .15s, background .15s; }
        input:focus, select:focus, textarea:focus { outline: 0; border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
        .field { margin-bottom: 1rem; }
        .filter-row { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: .75rem; }
        .filter-row:last-child { margin-bottom: 0; }
        .filter-row .field { flex: 1; min-width: 150px; margin-bottom: 0; }
        .filter-row .field.grow { flex: 2.5; min-width: 220px; }
        /* Page-size picker holds a 2-3 digit value, so it must not stretch to
           fill a wrapped flex line the way the other fields do. */
        .filter-row .field.rows { flex: 0 0 150px; }
        /* Record-list filter bar sticks directly below the fixed navbar, so
           search, the date range and the page-size picker stay reachable while
           scrolling a 25-100 row list. z-index sits under the navbar's 30 so it
           slides underneath instead of covering it. Static below the table/card
           breakpoint, where the controls already sit at the top of a short list. */
        .sticky-toolbar {
            position: sticky; top: var(--nav-h); z-index: 20;
            /* A sticky element may not be taller than it is useful. Tightened
               padding/gaps/field floors keep every control on ONE line at any
               width where the sidebar is shown (>=1024px), which caps the stuck
               height at ~88px instead of letting it grow to 187px on wrap. */
            padding: .85rem 1rem;
        }
        .sticky-toolbar.is-stuck { box-shadow: 0 10px 26px -16px rgba(15,23,42,.45); }
        .sticky-toolbar .filter-row { gap: .5rem; }
        .sticky-toolbar .filter-row .field { flex: 1 1 108px; min-width: 0; }
        .sticky-toolbar .filter-row .field.grow { flex: 3 1 150px; min-width: 0; }
        .sticky-toolbar .filter-row .field.rows { flex: 0 0 88px; }
        .sticky-toolbar label { font-size: 12px; margin-bottom: 3px; }
        .sticky-toolbar input, .sticky-toolbar select { padding: .45rem .6rem; }
        /* align-items:flex-end stops the buttons stretching up to the
           label+input stack height. Padding is deliberately NOT reduced here so
           toolbar buttons keep the same 42px height as every other button. */
        .sticky-toolbar .toolbar-actions { flex: 0 0 auto; margin-left: auto; align-items: flex-end; }
        @media (max-width: 767.98px) {
            .sticky-toolbar { position: static; }
            /* Below the table/card breakpoint flex-wrapping produced ragged
               rows of uneven height, so use an explicit grid instead: search
               spans full width, the rest pair up, buttons get their own row. */
            .sticky-toolbar .filter-row { display: grid; grid-template-columns: 1fr 1fr; align-items: end; gap: .6rem; }
            .sticky-toolbar .field.grow { grid-column: 1 / -1; }
            /* Pin Rows + buttons to one row explicitly. With only grid-column
               set, auto-placement put the buttons in row 3 and pushed Rows to
               row 4, stranding a hole beside them. */
            .sticky-toolbar .field.rows { grid-column: 1; grid-row: 3; }
            .sticky-toolbar .toolbar-actions { grid-column: 2; grid-row: 3; margin-left: 0; justify-content: flex-end; }
            /* Narrow columns: trim horizontal padding only, so the buttons keep
               the standard 42px height but stop wrapping to two lines. */
            .sticky-toolbar .toolbar-actions .btn-primary, .sticky-toolbar .toolbar-actions .btn-secondary { padding: .6rem .6rem; }
            .sticky-toolbar .filter-row .field, .sticky-toolbar .filter-row .field.rows { flex: none; min-width: 0; }
        }
        .hint { font-size: 12px; color: #6b7280; margin-top: 4px; }
        .fielderror { font-size: 13px; color: #b91c1c; margin-top: 4px; }

        /* Shared metrics for every action button. Buttons do not inherit
           font-family or line-height from the body the way links do, so a
           <button> used to render in the UA default font at line-height:normal
           and came out ~7px shorter than the <a> beside it in the same group. */
        button[type=submit], button[type=button], .btn-primary, .btn-secondary { font-family: inherit; font-size: 14px; font-weight: 600; line-height: 1.5; border-radius: 8px; padding: .6rem 1.2rem; cursor: pointer; text-decoration: none; display: inline-block; }
        button[type=submit], .btn-primary { background: linear-gradient(135deg, #2563eb, #4f46e5); color: #fff; border: 1px solid transparent; box-shadow: 0 4px 12px rgba(37,99,235,.3); }
        button[type=submit]:hover, .btn-primary:hover { filter: brightness(1.07); }
        button[type=submit]:disabled { opacity: .6; cursor: not-allowed; }
        button[type=button], .btn-secondary { background: #fff; border: 1px solid #dbe1ea; color: #334155; }
        button[type=button]:hover, .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }
        button[type=submit]:focus-visible, button[type=button]:focus-visible, .btn-primary:focus-visible, .btn-secondary:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .btn-danger { background: #fff; border: 1px solid #fecaca; color: #b91c1c; border-radius: 6px; padding: .4rem .8rem; font-size: 13px; font-family: inherit; line-height: 1.5; cursor: pointer; }

        .badge { display: inline-block; padding: .15rem .6rem; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f3f4f6; color: #374151; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        @media (max-width: 1279.98px) { .hide-below-xl { display: none; } }

        /* Analytics: KPI tiles drawn from the same tokens as the sidebar, so the
           dashboard reads as part of the same product rather than a bolted-on
           panel. */
        .kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .kpi { position: relative; background: #fff; border: 1px solid var(--line); border-radius: var(--radius); padding: 1.15rem 1.25rem 1rem; box-shadow: var(--shadow); overflow: hidden; }
        .kpi::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--grad-brand); }
        .kpi[data-tone="error"]::before { background: linear-gradient(180deg, #ef4444, var(--danger)); }
        .kpi[data-tone="success"]::before { background: linear-gradient(180deg, #22c55e, var(--success)); }
        .kpi-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .75rem; }
        .kpi-label { font-size: 11.5px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--muted); }
        .kpi-icon { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; color: #fff; flex-shrink: 0; background: var(--grad-brand); }
        .kpi[data-tone="error"] .kpi-icon { background: linear-gradient(135deg, #ef4444, var(--danger)); }
        .kpi[data-tone="success"] .kpi-icon { background: linear-gradient(135deg, #22c55e, var(--success)); }
        .kpi-value { font-size: 34px; font-weight: 800; line-height: 1.05; letter-spacing: -.02em; color: var(--ink); margin: .55rem 0 .3rem; font-variant-numeric: tabular-nums; }
        .kpi-value small { font-size: 15px; font-weight: 700; color: var(--muted); }
        .kpi-foot { display: flex; align-items: center; gap: .45rem; flex-wrap: wrap; font-size: 12.5px; color: var(--muted); }
        .delta { display: inline-flex; align-items: center; gap: .2rem; padding: .08rem .45rem; border-radius: 999px; font-size: 11.5px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .delta-up { background: #dcfce7; color: #166534; }
        .delta-down { background: #fee2e2; color: #991b1b; }
        .delta-flat { background: #f1f5f9; color: #475569; }
        /* Sparkline: real buckets, no smoothing, so the shape stays honest */
        .spark { display: flex; align-items: flex-end; gap: 2px; height: 40px; margin-top: .85rem; }
        .spark span { flex: 1 1 0; min-width: 2px; border-radius: 2px; background: var(--grad-brand); opacity: .3; }
        .spark span.on { opacity: 1; }
        .kpi[data-tone="error"] .spark span { background: linear-gradient(180deg, #ef4444, var(--danger)); }
        .kpi[data-tone="success"] .spark span { background: linear-gradient(180deg, #22c55e, var(--success)); }
        .spark-axis { display: flex; justify-content: space-between; gap: .5rem; font-size: 10.5px; color: var(--muted-2); margin-top: .3rem; }
        .mix { height: 10px; border-radius: 999px; overflow: hidden; display: flex; background: var(--line-2); margin-top: .85rem; }
        .mix span { display: block; height: 100%; }
        .mix .mix-error { background: linear-gradient(90deg, #ef4444, var(--danger)); }
        .mix .mix-success { background: linear-gradient(90deg, #22c55e, var(--success)); }
        @media (max-width: 1279.98px) { .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 639.98px) { .kpis { grid-template-columns: minmax(0, 1fr); } }
        .k { font-size: 13px; color: var(--muted); font-weight: 600; }
        .only-desktop { display: none; }
        @media (min-width: 768px) {
            .only-desktop { display: block; }
            .only-mobile { display: none !important; }
        }

        .dropzone { border: 2px dashed #d1d5db; border-radius: 8px; background: #f9fafb; padding: 1.5rem; text-align: center; cursor: pointer; }
        .dropzone.armed { border-color: #2563eb; background: #eff6ff; }
        .dropzone.locked { opacity: .5; cursor: not-allowed; }
        .dropzone img.preview { max-width: 100%; max-height: 220px; border: 1px solid #e5e7eb; border-radius: 6px; }
        kbd { padding: .1rem .4rem; font-size: 12px; background: #fff; border: 1px solid #d1d5db; border-radius: 4px; }
        code { font-family: ui-monospace, monospace; font-size: 13px; }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="navbar-inner">
            @auth
            <button type="button" class="hamburger" onclick="document.getElementById('drawer').classList.remove('hidden');document.getElementById('drawer-veil').classList.remove('hidden');" aria-label="Open menu">&#9776;</button>
            @endauth
            <a class="brand" href="{{ auth()->check() ? route('dashboard') : route('login') }}">
                <span class="brand-mark">M</span>
                <span>{{ config('app.name', 'MCA Patient Docs') }}</span>
            </a>
            @auth
            <div class="crumb">{{ now()->format('l, F j, Y') }}</div>
            <div class="user-menu" id="user-menu">
                <button type="button" class="user-btn" id="user-btn" aria-haspopup="true" aria-expanded="false">
                    <span class="whoami">
                        <div>{{ auth()->user()->name }}</div>
                        <div>{{ auth()->user()->role }}</div>
                    </span>
                    <span class="avatar">{{ auth()->user()->name[0] }}</span>
                    <span class="caret">&#9662;</span>
                </button>
                <div class="user-drop" role="menu">
                    <div class="meta">
                        <strong>{{ auth()->user()->name }}</strong>
                        <span>{{ auth()->user()->role }}</span>
                    </div>
                    @can('viewAny', \App\Models\Setting::class)
                        <a href="{{ route('settings.index') }}" role="menuitem">Settings</a>
                    @endcan
                    <form action="{{ route('logout') }}" method="POST" style="margin:0;">
                        @csrf
                        <button type="submit" class="danger" role="menuitem">Logout</button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </header>

    <div class="app">
        @auth
        <!-- Sidebar (desktop) -->
        <aside class="sidebar" aria-label="Sidebar">
            <div style="display:flex;flex-direction:column;height:100%;">
                <div class="nav-label" style="margin-top:.25rem;">Menu</div>
                <nav class="side-nav" aria-label="Main">
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('records.error') }}" class="{{ request()->routeIs('records.error') ? 'active' : '' }}">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        PCU Error Records
                    </a>
                    <a href="{{ route('records.success') }}" class="{{ request()->routeIs('records.success') ? 'active' : '' }}">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        PCU Success Records
                    </a>
                    @can('viewAny', \App\Models\Setting::class)
                        <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings*') ? 'active' : '' }}">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            Settings
                        </a>
                    @endcan
                    <a href="{{ route('docs') }}" class="{{ request()->routeIs('docs') ? 'active' : '' }}">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"></path><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"></path></svg>
                        Documentation
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Mobile drawer -->
        <div id="drawer-veil" class="drawer-veil hidden" onclick="document.getElementById('drawer').classList.add('hidden');document.getElementById('drawer-veil').classList.add('hidden');"></div>
        <aside id="drawer" class="drawer hidden" aria-label="Sidebar">
            <button type="button" class="close" onclick="document.getElementById('drawer').classList.add('hidden');document.getElementById('drawer-veil').classList.add('hidden');" aria-label="Close menu">&times;</button>
            <div class="nav-label">Menu</div>
            <nav class="side-nav" aria-label="Main">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('records.error') }}" class="{{ request()->routeIs('records.error') ? 'active' : '' }}">PCU Error Records</a>
                <a href="{{ route('records.success') }}" class="{{ request()->routeIs('records.success') ? 'active' : '' }}">PCU Success Records</a>
                @can('viewAny', \App\Models\Setting::class)
                    <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings*') ? 'active' : '' }}">Settings</a>
                @endcan
                <a href="{{ route('docs') }}" class="{{ request()->routeIs('docs') ? 'active' : '' }}">Documentation</a>
            </nav>
        </aside>
        @endauth

        <div class="main">
            <main class="page">
                <div class="page-head">
                    @yield('page-title')
                </div>

                @if (session('success'))
                    <div class="flash flash-success">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="flash flash-error">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (session('generation_warnings'))
                    <div class="flash flash-warn">
                        <ul>
                            @foreach ((array) session('generation_warnings') as $warning)
                                <li>{{ $warning }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    @stack('scripts')
    <script>
        // Reset scroll positions on load so tables never open scrolled aside.
        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
        window.scrollTo(0, 0);
        document.querySelectorAll('.overflow-x-auto, .table-wrap').forEach(function (el) {
            el.scrollLeft = 0;
        });

        // Avatar menu: one place for identity, Settings and Logout.
        (function () {
            var menu = document.getElementById('user-menu');
            var btn = document.getElementById('user-btn');
            if (!menu || !btn) return;
            function close() {
                menu.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var open = menu.classList.toggle('open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
            document.addEventListener('click', function (e) {
                if (!menu.contains(e.target)) close();
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') close();
            });
        })();
    </script>

    <script>
        /* PhilHealth PIN: 12 digits, auto-formatted to 2-9-1 as the user types. */
        (function () {
            var input = document.getElementById('philhealth_id');
            if (!input) return;
            input.setAttribute('inputmode', 'numeric');
            input.setAttribute('maxlength', '14');
            function format() {
                var digits = input.value.replace(/\D/g, '').slice(0, 12);
                var out = digits.slice(0, 2);
                if (digits.length > 2) out += '-' + digits.slice(2, 11);
                if (digits.length > 11) out += '-' + digits.slice(11, 12);
                input.value = out;
            }
            input.addEventListener('input', format);
            input.addEventListener('blur', format);
            input.addEventListener('paste', function () { setTimeout(format, 0); });
            format();
        })();
    </script>
</body>
</html>

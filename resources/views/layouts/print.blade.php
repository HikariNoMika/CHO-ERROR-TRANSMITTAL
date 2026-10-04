<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        :root {
            --brand: #2563eb;
            --brand-2: #4f46e5;
            --brand-rgb: 37, 99, 235;
            --muted: #64748b;
            --line: #e8edf3;
            --line-2: #eef2f7;
            --radius: 14px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'DejaVu Sans', Arial, sans-serif;
            background: #f1f5f9;
            color: #111827;
            font-size: 14px;
            line-height: 1.5;
        }
        h1, h2, h3 { margin: 0 0 .5rem; }
        p { margin: 0 0 .5rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: .5rem .75rem; text-align: left; vertical-align: top; }
        .hidden { display: none !important; }

        /* ---------- Screen page ---------- */
        .print-page { max-width: 900px; margin: 0 auto; padding: 20px 16px 40px; }
        .sheet {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(15, 23, 42, .06);
            padding: 24px;
        }

        /* ---------- Toolbar ---------- */
        .print-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
            padding: .7rem .9rem;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: var(--radius);
        }
        .pt-meta { font-size: 12.5px; color: var(--muted); min-width: 0; }
        .pt-meta strong { display: block; color: #0f172a; font-size: 14.5px; font-weight: 600; }
        .pt-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            line-height: 1.5;
            border-radius: 8px;
            padding: .6rem 1.1rem;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .btn-secondary { background: #fff; border-color: #dbe1ea; color: #334155; }
        .btn-secondary:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-primary { background: linear-gradient(135deg, #2563eb, #4f46e5); color: #fff; box-shadow: 0 4px 12px rgba(37, 99, 235, .3); }
        .btn-primary:hover { filter: brightness(1.07); }
        .btn:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

        /* ---------- Alerts ---------- */
        .alert { margin-bottom: 16px; padding: .75rem 1rem; border-radius: 8px; font-size: 13.5px; }
        .alert-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
        .alert ul { margin: .25rem 0 0; padding-left: 1.1rem; }

        /* ---------- Faithful Excel preview ---------- */
        .xlsx-preview { position: relative; width: 100%; background: #fff; overflow: hidden; }

        /* ---------- Generic summary ---------- */
        .doc-head { text-align: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #111827; }
        .doc-head h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .doc-head p { margin: 0; color: var(--muted); }
        .doc-section { margin-bottom: 24px; }
        .doc-section h2 { font-size: 16px; font-weight: 600; margin: 0 0 12px; padding-bottom: 6px; border-bottom: 1px solid #d1d5db; }
        .doc-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px 20px; }
        .field label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 2px; }
        .field .val { font-size: 14px; color: #0f172a; font-weight: 500; overflow-wrap: anywhere; }
        .field .val.mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .img-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 24px; }
        .img-block h3 { font-size: 14px; font-weight: 600; margin: 0 0 8px; padding-bottom: 4px; border-bottom: 1px solid #d1d5db; }
        .img-block img { width: 100%; max-height: 256px; object-fit: contain; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .img-empty { height: 256px; border: 2px dashed #d1d5db; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 13px; }
        .doc-foot {
            margin-top: 16px;
            padding: 0 4px;
            display: flex;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 12.5px;
            color: var(--muted);
        }

        @media (max-width: 640px) {
            .print-page { padding: 12px 10px 28px; }
            .sheet { padding: 16px; }
            .doc-grid, .img-grid { grid-template-columns: 1fr; }
            .print-toolbar { flex-direction: column; align-items: stretch; }
            .pt-actions .btn { flex: 1 1 auto; }
        }

        /* ---------- Print ----------
           Zero page margins suppress the browser's own header/footer
           (date, document title, URL, page numbers). Content padding
           below re-adds safe printable margins. */
        @page { margin: 0; size: A4; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .print-page { max-width: none; margin: 0; padding: 0; }
            .sheet { border: 0; border-radius: 0; box-shadow: none; padding: 0; }
            .print-document { padding: 12mm; }
            .page-break { page-break-after: always; }
            img { max-width: 100%; height: auto; }
        }
    </style>
</head>
<body>
    @yield('content')

    <script>
        window.onload = () => {
            if (new URLSearchParams(window.location.search).has('print')) {
                window.print();
            }
        };
    </script>
</body>
</html>
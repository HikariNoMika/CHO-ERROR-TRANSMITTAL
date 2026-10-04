<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Document' }}</title>
    <style>
        body { margin: 0; font-family: 'DejaVu Sans', Arial, sans-serif; background: #fff; color: #111827; font-size: 14px; line-height: 1.5; }
        h1, h2, h3 { margin: 0 0 .5rem; }
        p { margin: 0 0 .5rem; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d1d5db; padding: .5rem .75rem; text-align: left; vertical-align: top; }
        .hidden { display: none !important; }
        /* Zero page margins suppress the browser's own header/footer
           (date, document title, URL, page numbers). Content padding
           below re-adds safe printable margins. */
        @page { margin: 0; size: A4; }
        @media print {
            .no-print { display: none !important; }
            .print-document { padding: 12mm; }
            .page-break { page-break-after: always; }
            img { max-width: 100%; height: auto; }
        }
    </style>
</head>
<body class="bg-white">
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
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 9px; color: #0d1b2a; margin: 0; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { font-size: 8px; color: #55677d; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th {
            background: #184f95; color: #fff; text-align: left;
            padding: 5px 6px; font-size: 8px; text-transform: uppercase;
        }
        td { padding: 4px 6px; border-bottom: 1px solid #e2ecf8; }
        tr:nth-child(even) td { background: #f4f8fd; }
        .footer { margin-top: 12px; font-size: 7px; color: #8fa1b6; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">
        Dicetak {{ $generatedAt }}
        @if (! empty($filters))
            &middot;
            @foreach ($filters as $key => $value)
                {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}@if (! $loop->last), @endif
            @endforeach
        @endif
    </p>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headings) }}">Tidak ada data pada filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">{{ count($rows) }} baris &middot; HRIS &amp; ATS Workforce Platform</p>
</body>
</html>

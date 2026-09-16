@php
    $documentLocale = app()->getLocale();
    $documentDirection = $documentLocale === 'ar' ? 'rtl' : 'ltr';
    $startAlignment = $documentDirection === 'rtl' ? 'right' : 'left';
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $documentLocale) }}" dir="{{ $documentDirection }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $programLabel }}</title>
    <style>
        body { font-family: DejaVu Sans, Tahoma, Arial, sans-serif; color: #172033; background: #f4f6fb; margin: 0; padding: 24px; }
        .card { background: #ffffff; border-radius: 12px; padding: 24px; max-width: 720px; margin: 0 auto; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .period { color: #556; font-size: 13px; margin-bottom: 20px; }
        h2 { font-size: 15px; margin: 24px 0 8px; border-{{ $startAlignment }}: 4px solid #2f6fed; padding-{{ $startAlignment }}: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 13px; }
        th, td { text-align: {{ $startAlignment }}; padding: 6px 8px; border-bottom: 1px solid #e4e8f2; vertical-align: top; }
        th { color: #667; font-weight: 600; }
        .empty { color: #889; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $programLabel }}</h1>
        <div class="period">{{ __('reporting::mail.monthly_program_digest.period', ['from' => $periodFromLabel, 'to' => $periodToLabel]) }}</div>

        @forelse ($entriesByStudentName as $studentName => $entries)
            <h2>{{ $studentName }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.date') }}</th>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.topics') }}</th>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.homework') }}</th>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.participation') }}</th>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.performance') }}</th>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.commitment') }}</th>
                        <th>{{ __('reporting::mail.monthly_program_digest.columns.note') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $entry)
                        <tr>
                            <td>{{ $entry['submitted_at'] }}</td>
                            <td>{{ $entry['topics_covered'] ?? '-' }}</td>
                            <td>{{ $entry['homework_assigned'] ?? '-' }}</td>
                            <td>{{ $entry['participation'] ?? '-' }}</td>
                            <td>{{ $entry['performance'] ?? '-' }}</td>
                            <td>{{ $entry['commitment'] ?? '-' }}</td>
                            <td>{{ $entry['note'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <p class="empty">{{ __('reporting::mail.monthly_program_digest.empty') }}</p>
        @endforelse
    </div>
</body>
</html>

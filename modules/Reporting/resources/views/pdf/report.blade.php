@php
    $documentDirection = ($direction ?? 'rtl') === 'ltr' ? 'ltr' : 'rtl';
    $requestedLocale = (string) ($locale ?? 'ar');
    $documentLocale = preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*$/', $requestedLocale)
        ? $requestedLocale
        : 'ar';
    $reportRows = is_array($rows ?? null)
        ? $rows
        : (is_iterable($rows ?? null) ? iterator_to_array($rows) : []);
    $reportSummary = is_array($summary ?? null) ? $summary : [];
    $appliedFilters = is_array($filterLabels ?? null) ? $filterLabels : [];
    $missing = __('reporting::pdf.labels.not_available', [], $documentLocale);
    $listSeparator = __('reporting::operational.separators.list', [], $documentLocale);
    $startAlignment = $documentDirection === 'rtl' ? 'right' : 'left';
    $endAlignment = $documentDirection === 'rtl' ? 'left' : 'right';
    $startBorder = $documentDirection === 'rtl' ? 'border-right' : 'border-left';
    $display = static function (mixed $value) use ($listSeparator, $missing): string {
        if ($value === null || $value === '') {
            return $missing;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            $flattened = \Illuminate\Support\Arr::flatten($value);
            $printable = array_filter($flattened, static fn (mixed $item): bool => is_scalar($item));

            return $printable === [] ? $missing : implode($listSeparator, array_map('strval', $printable));
        }

        return $missing;
    };
    $documentTitle = filled($title ?? null)
        ? (string) $title
        : __('reporting::pdf.document.sessions', [], $documentLocale);
    $documentOrganization = filled($organizationName ?? null)
        ? (string) $organizationName
        : __('reporting::pdf.document.organization_fallback', [], $documentLocale);
    $documentGeneratedAt = $display($generatedAt ?? null);
    $documentTimezone = $display($timezone ?? null);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', $documentLocale) }}" dir="{{ $documentDirection }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documentTitle }}</title>
    <style>
        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            color: #172033;
            background: #ffffff;
            font-family: dejavusans, sans-serif;
            font-size: 9px;
            line-height: 1.55;
        }

        body { padding-bottom: 8mm; }

        .report-header {
            display: table;
            width: 100%;
            margin-bottom: 4mm;
            padding-bottom: 3.5mm;
            border-bottom: 2px solid #2563eb;
        }

        .report-header__identity,
        .report-header__meta {
            display: table-cell;
            vertical-align: bottom;
        }

        .report-header__meta {
            width: 40%;
            text-align: {{ $endAlignment }};
            color: #526078;
            font-size: 8px;
        }

        .organization {
            margin-bottom: 1mm;
            color: #2563eb;
            font-size: 9px;
            font-weight: 700;
        }

        h1 {
            margin: 0;
            color: #111827;
            font-size: 20px;
            line-height: 1.25;
            font-weight: 800;
        }

        h2 {
            margin: 0 0 2mm;
            color: #263247;
            font-size: 11px;
            font-weight: 800;
        }

        .meta-line { margin-top: .6mm; }
        .meta-label { font-weight: 700; color: #344258; }

        .panel {
            margin-bottom: 3mm;
            padding: 2.6mm 3mm;
            border: 1px solid #dbe3ef;
            border-radius: 2.2mm;
            background: #f8fafc;
            page-break-inside: avoid;
        }

        .filters { color: #3f4d63; }

        .filter {
            display: inline-block;
            margin: .6mm 0 .6mm 1.6mm;
            padding: 1mm 1.8mm;
            border: 1px solid #d6dfed;
            border-radius: 1.2mm;
            background: #ffffff;
        }

        .filter__label { color: #64748b; font-weight: 700; }

        .summary-grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 1.5mm;
        }

        .summary-item {
            padding: 2mm 2.4mm;
            {{ $startBorder }}: 2px solid #2563eb;
            background: #ffffff;
            vertical-align: top;
        }

        .summary-item__label {
            color: #66748b;
            font-size: 7.5px;
            font-weight: 700;
        }

        .summary-item__value {
            margin-top: .5mm;
            color: #14213d;
            font-size: 13px;
            font-weight: 800;
        }

        .table-heading {
            display: table;
            width: 100%;
            margin: 1mm 0 2mm;
        }

        .table-heading h2,
        .table-heading__period {
            display: table-cell;
            vertical-align: bottom;
        }

        .table-heading__period {
            text-align: {{ $endAlignment }};
            color: #667085;
            font-size: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        thead { display: table-header-group; }
        tfoot { display: table-footer-group; }
        tr { page-break-inside: avoid; }

        th {
            padding: 1.8mm 1.4mm;
            border: 1px solid #243b62;
            background: #1f3760;
            color: #ffffff;
            text-align: {{ $startAlignment }};
            font-size: 7.4px;
            font-weight: 800;
            vertical-align: middle;
        }

        td {
            padding: 1.7mm 1.4mm;
            border: 1px solid #dce3ed;
            color: #273449;
            text-align: {{ $startAlignment }};
            vertical-align: top;
            word-wrap: break-word;
        }

        tbody tr:nth-child(even) td { background: #f7f9fc; }
        .primary { color: #172033; font-weight: 750; }
        .secondary { margin-top: .5mm; color: #64748b; font-size: 7.4px; }
        .compact-line + .compact-line { margin-top: .5mm; }

        .badge {
            display: inline-block;
            margin: 0 0 .7mm .7mm;
            padding: .6mm 1.3mm;
            border-radius: 99px;
            background: #e9eef6;
            color: #3d4c63;
            font-size: 7px;
            font-weight: 800;
            white-space: nowrap;
        }

        .badge--success { background: #dcfce7; color: #166534; }
        .badge--danger { background: #fee2e2; color: #991b1b; }
        .badge--warning { background: #fef3c7; color: #92400e; }
        .badge--info { background: #dbeafe; color: #1e40af; }

        .empty-state {
            padding: 12mm;
            border: 1px dashed #bdc8d8;
            border-radius: 2mm;
            color: #64748b;
            text-align: center;
            font-size: 10px;
        }

        .page-footer {
            width: 100%;
            border-collapse: collapse;
            padding-top: 2mm;
            border-top: 1px solid #dbe3ef;
            color: #7a8699;
            font-size: 7px;
        }

        .page-footer td { border: 0; padding-top: 2mm; }
        .page-footer__organization { text-align: {{ $startAlignment }}; }
        .page-footer__page { text-align: {{ $endAlignment }}; }

        .w-session { width: 18%; }
        .w-schedule { width: 14%; }
        .w-group { width: 9%; }
        .w-teacher { width: 12%; }
        .w-students { width: 14%; }
        .w-attendance { width: 10%; }
        .w-status { width: 11%; }
        .w-reason { width: 12%; }
    </style>
</head>
<body>
    <htmlpagefooter name="report-footer">
        <table class="page-footer">
            <tr>
                <td class="page-footer__organization">{{ $documentOrganization }}</td>
                <td class="page-footer__page">{{ __('reporting::pdf.document.page', [], $documentLocale) }} {PAGENO}</td>
            </tr>
        </table>
    </htmlpagefooter>
    <sethtmlpagefooter name="report-footer" value="on" />

    <header class="report-header">
        <div class="report-header__identity">
            <div class="organization">{{ $documentOrganization }}</div>
            <h1>{{ $documentTitle }}</h1>
        </div>
        <div class="report-header__meta">
            @if (! empty($periodLabel))
                <div class="meta-line"><span class="meta-label">{{ __('reporting::pdf.document.period', [], $documentLocale) }}:</span> {{ $periodLabel }}</div>
            @endif
            <div class="meta-line"><span class="meta-label">{{ __('reporting::pdf.document.generated_at', [], $documentLocale) }}:</span> {{ $documentGeneratedAt }}</div>
            <div class="meta-line"><span class="meta-label">{{ __('reporting::pdf.document.timezone', [], $documentLocale) }}:</span> {{ $documentTimezone }}</div>
        </div>
    </header>

    @if ($appliedFilters !== [])
        <section class="panel filters">
            <h2>{{ __('reporting::pdf.document.filters', [], $documentLocale) }}</h2>
            @foreach ($appliedFilters as $filterLabel => $filterValue)
                <span class="filter">
                    @if (! is_int($filterLabel))
                        <span class="filter__label">{{ $filterLabel }}:</span>
                    @endif
                    {{ $display($filterValue) }}
                </span>
            @endforeach
        </section>
    @endif

    @if ($reportSummary !== [])
        <section class="panel">
            <h2>{{ __('reporting::pdf.document.summary', [], $documentLocale) }}</h2>
            <table class="summary-grid">
                <tbody>
                    @foreach (array_chunk($reportSummary, 5, true) as $summaryRow)
                        <tr>
                            @foreach ($summaryRow as $summaryLabel => $summaryValue)
                                <td class="summary-item" width="20%">
                                    <div class="summary-item__label">{{ $summaryLabel }}</div>
                                    <div class="summary-item__value">{{ $display($summaryValue) }}</div>
                                </td>
                            @endforeach
                            @for ($emptyCell = count($summaryRow); $emptyCell < 5; $emptyCell++)
                                <td width="20%"></td>
                            @endfor
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <div class="table-heading">
        <h2>{{ __('reporting::pdf.document.sessions', [], $documentLocale) }}</h2>
        @if (! empty($periodLabel))
            <div class="table-heading__period">{{ $periodLabel }}</div>
        @endif
    </div>

    @if ($reportRows === [])
        <div class="empty-state">{{ __('reporting::pdf.document.no_results', [], $documentLocale) }}</div>
    @else
        <table>
            <thead>
                <tr>
                    <th class="w-session">{{ __('reporting::pdf.columns.session', [], $documentLocale) }}</th>
                    <th class="w-schedule">{{ __('reporting::pdf.columns.schedule', [], $documentLocale) }}</th>
                    <th class="w-group">{{ __('reporting::pdf.columns.group', [], $documentLocale) }}</th>
                    <th class="w-teacher">{{ __('reporting::pdf.columns.teacher', [], $documentLocale) }}</th>
                    <th class="w-students">{{ __('reporting::pdf.columns.students', [], $documentLocale) }}</th>
                    <th class="w-attendance">{{ __('reporting::pdf.columns.attendance', [], $documentLocale) }}</th>
                    <th class="w-status">{{ __('reporting::pdf.columns.status', [], $documentLocale) }}</th>
                    <th class="w-reason">{{ __('reporting::pdf.columns.cancellation_reason', [], $documentLocale) }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reportRows as $row)
                    @php
                        $tone = match ((string) data_get($row, 'status_color', '')) {
                            'success', 'green' => 'success',
                            'danger', 'red' => 'danger',
                            'warning', 'yellow', 'amber' => 'warning',
                            'info', 'primary', 'blue' => 'info',
                            default => 'neutral',
                        };
                    @endphp
                    <tr>
                        <td>
                            <div class="primary">{{ $display(data_get($row, 'title')) }}</div>
                            @if (filled(data_get($row, 'course')))
                                <div class="secondary">{{ __('reporting::pdf.labels.course', [], $documentLocale) }}: {{ $display(data_get($row, 'course')) }}</div>
                            @endif
                            @if (filled(data_get($row, 'session_type_label')))
                                <div class="secondary">{{ __('reporting::pdf.labels.type', [], $documentLocale) }}: {{ $display(data_get($row, 'session_type_label')) }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="compact-line primary">{{ $display(data_get($row, 'scheduled_start_display')) }}</div>
                            @if (filled(data_get($row, 'scheduled_end_display')))
                                <div class="compact-line">{{ $display(data_get($row, 'scheduled_end_display')) }}</div>
                            @endif
                            @if (filled(data_get($row, 'duration_minutes')))
                                <div class="secondary">{{ __('reporting::pdf.labels.duration', [], $documentLocale) }}: {{ __('reporting::pdf.labels.minutes', ['count' => data_get($row, 'duration_minutes')], $documentLocale) }}</div>
                            @endif
                        </td>
                        <td>{{ $display(data_get($row, 'group')) }}</td>
                        <td>
                            <div class="primary">{{ $display(data_get($row, 'actual_teacher')) }}</div>
                            @if (filled(data_get($row, 'original_teacher')) && data_get($row, 'original_teacher') !== data_get($row, 'actual_teacher'))
                                <div class="secondary">{{ __('reporting::pdf.labels.original_teacher', [], $documentLocale) }}: {{ $display(data_get($row, 'original_teacher')) }}</div>
                            @endif
                        </td>
                        <td>{{ $display(data_get($row, 'students_display')) }}</td>
                        <td>{{ $display(data_get($row, 'attendance_summary')) }}</td>
                        <td>
                            <span class="badge badge--{{ $tone }}">{{ $display(data_get($row, 'status_label')) }}</span>
                            @if (filled(data_get($row, 'report_status_label')))
                                <div class="secondary">{{ __('reporting::pdf.labels.report_status', [], $documentLocale) }}: {{ $display(data_get($row, 'report_status_label')) }}</div>
                            @endif
                        </td>
                        <td>{{ $display(data_get($row, 'cancellation_reason')) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>

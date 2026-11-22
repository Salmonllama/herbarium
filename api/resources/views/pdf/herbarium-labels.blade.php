<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $herbarium->name }} — Herbarium Labels</title>
    <style>
        @page {
            margin: {{ $template === 'single_4x3' ? '8pt' : '24pt' }};
        }
        body {
            font-family: "DejaVu Serif", Georgia, serif;
            color: #161816;
            font-size: 8.5pt;
            line-height: 1.32;
            margin: 0;
            padding: 0;
        }
        .sheet-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10pt;
        }
        .label-cell {
            width: 50%;
            vertical-align: top;
        }
        .label-box {
            border: 1pt solid #2a332e;
            padding: 9pt 10pt;
            min-height: {{ $template === 'single_4x3' ? '178pt' : '220pt' }};
            position: relative;
            page-break-inside: avoid;
        }
        .label-header {
            text-align: center;
            border-bottom: 0.75pt solid #4c5951;
            padding-bottom: 4pt;
            margin-bottom: 6pt;
        }
        .herbarium-title {
            font-size: 9pt;
            font-weight: bold;
            letter-spacing: 0.8pt;
            text-transform: uppercase;
        }
        .family-row {
            font-size: 7.5pt;
            letter-spacing: 1pt;
            text-transform: uppercase;
            color: #3f4b44;
            margin-bottom: 2pt;
        }
        .taxon-name {
            font-size: 11pt;
            margin-bottom: 5pt;
        }
        .taxon-name em {
            font-style: italic;
            font-weight: bold;
        }
        .authorship {
            font-style: normal;
            font-weight: normal;
            font-size: 9.5pt;
        }
        .type-badge {
            display: inline-block;
            border: 0.75pt solid #8b1e1e;
            color: #8b1e1e;
            font-size: 7pt;
            font-weight: bold;
            padding: 1pt 4pt;
            text-transform: uppercase;
            margin-left: 4pt;
        }
        .meta-block {
            margin-bottom: 5pt;
        }
        .geo-line {
            font-weight: bold;
        }
        .mono {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 7.5pt;
        }
        .footer-table {
            width: 100%;
            margin-top: 8pt;
            border-top: 0.5pt solid #9ea7a0;
            padding-top: 5pt;
            border-collapse: collapse;
        }
        .footer-left {
            vertical-align: bottom;
            font-size: 8pt;
        }
        .footer-qr {
            width: 48pt;
            text-align: right;
            vertical-align: bottom;
        }
        .footer-qr img {
            width: 42pt;
            height: 42pt;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
@if($template === 'single_4x3')
    @foreach($labels as $index => $label)
        <div class="label-box {{ $index < count($labels) - 1 ? 'page-break' : '' }}">
            <div class="label-header">
                <div class="herbarium-title">
                    {{ $label['herbarium_name'] }}
                    @if($label['institution_code']) ({{ $label['institution_code'] }}) @endif
                </div>
            </div>
            <div class="family-row">{{ $label['family'] }}</div>
            <div class="taxon-name">
                @if($label['qualifier']) <span>{{ $label['qualifier'] }}</span> @endif
                <em>{{ $label['scientific_name'] }}</em>
                @if($label['authorship']) <span class="authorship">{{ $label['authorship'] }}</span> @endif
                @if($label['type_status']) <span class="type-badge">{{ $label['type_status'] }}</span> @endif
            </div>
            <div class="meta-block">
                @if($label['geo_header']) <div class="geo-line">{{ $label['geo_header'] }}</div> @endif
                @if($label['locality']) <div>{{ $label['locality'] }}</div> @endif
                @if($label['coordinates'] || $label['elevation'])
                    <div class="mono">
                        {{ $label['coordinates'] }}
                        @if($label['coordinates'] && $label['elevation']) · @endif
                        @if($label['elevation']) Elev. {{ $label['elevation'] }} @endif
                    </div>
                @endif
                @if($label['habitat']) <div>{{ $label['habitat'] }}</div> @endif
            </div>
            <table class="footer-table">
                <tr>
                    <td class="footer-left">
                        @if($label['recorded_by'])
                            <div><strong>Leg.</strong> {{ $label['recorded_by'] }} @if($label['field_number']) #{{ $label['field_number'] }} @endif</div>
                        @endif
                        @if($label['event_date']) <div>{{ $label['event_date'] }}</div> @endif
                        @if($label['identified_by']) <div>Det. {{ $label['identified_by'] }} {{ $label['date_identified'] }}</div> @endif
                        <div class="mono" style="margin-top: 2pt;"><strong>{{ $label['catalog_number'] }}</strong></div>
                    </td>
                    <td class="footer-qr">
                        <img src="{{ $label['qr_data_uri'] }}" alt="QR">
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
@else
    @foreach(array_chunk($labels, 4) as $pageIndex => $pageLabels)
        <table class="sheet-table {{ $pageIndex < count(array_chunk($labels, 4)) - 1 ? 'page-break' : '' }}">
            @foreach(array_chunk($pageLabels, 2) as $rowLabels)
                <tr>
                    @foreach($rowLabels as $label)
                        <td class="label-cell">
                            <div class="label-box">
                                <div class="label-header">
                                    <div class="herbarium-title">
                                        {{ $label['herbarium_name'] }}
                                        @if($label['institution_code']) ({{ $label['institution_code'] }}) @endif
                                    </div>
                                </div>
                                <div class="family-row">{{ $label['family'] }}</div>
                                <div class="taxon-name">
                                    @if($label['qualifier']) <span>{{ $label['qualifier'] }}</span> @endif
                                    <em>{{ $label['scientific_name'] }}</em>
                                    @if($label['authorship']) <span class="authorship">{{ $label['authorship'] }}</span> @endif
                                    @if($label['type_status']) <span class="type-badge">{{ $label['type_status'] }}</span> @endif
                                </div>
                                <div class="meta-block">
                                    @if($label['geo_header']) <div class="geo-line">{{ $label['geo_header'] }}</div> @endif
                                    @if($label['locality']) <div>{{ $label['locality'] }}</div> @endif
                                    @if($label['coordinates'] || $label['elevation'])
                                        <div class="mono">
                                            {{ $label['coordinates'] }}
                                            @if($label['coordinates'] && $label['elevation']) · @endif
                                            @if($label['elevation']) Elev. {{ $label['elevation'] }} @endif
                                        </div>
                                    @endif
                                    @if($label['habitat']) <div>{{ $label['habitat'] }}</div> @endif
                                </div>
                                <table class="footer-table">
                                    <tr>
                                        <td class="footer-left">
                                            @if($label['recorded_by'])
                                                <div><strong>Leg.</strong> {{ $label['recorded_by'] }} @if($label['field_number']) #{{ $label['field_number'] }} @endif</div>
                                            @endif
                                            @if($label['event_date']) <div>{{ $label['event_date'] }}</div> @endif
                                            @if($label['identified_by']) <div>Det. {{ $label['identified_by'] }} {{ $label['date_identified'] }}</div> @endif
                                            <div class="mono" style="margin-top: 3pt;"><strong>{{ $label['catalog_number'] }}</strong></div>
                                        </td>
                                        <td class="footer-qr">
                                            <img src="{{ $label['qr_data_uri'] }}" alt="QR">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    @endforeach
                    @if(count($rowLabels) === 1)
                        <td class="label-cell"></td>
                    @endif
                </tr>
            @endforeach
        </table>
    @endforeach
@endif
</body>
</html>

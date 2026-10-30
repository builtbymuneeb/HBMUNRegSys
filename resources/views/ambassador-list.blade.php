@php
    $fileName = 'Homebridge_MUN26_'.($type ? $type->label().'_' : '').'Campus_Ambassadors'.($range ? '_'.str_replace(' – ', '-', $range) : '');
    $countLabel = trans_choice(':count '.($type ? $type->label().' ' : '').'Campus Ambassador|:count '.($type ? $type->label().' ' : '').'Campus Ambassadors', $count);
    $star = '<svg class="star" viewBox="0 0 12 12" aria-hidden="true"><path d="M6 0 5.1 5.1 0 6l5.1.9L6 12l1-5.1L12 6 7 5.1Z"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    {{-- The browser uses the title as the file name when saving as PDF. --}}
    <title>{{ $fileName }}</title>
    @include('partials.icons')
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=cinzel:400,600,700|eb-garamond:400,400i,500&display=block" />
    <style>
        /* Sizes are in points to match the A4 design (595 × 842 pt). */
        @page { size: A4; margin: 0; }

        :root {
            --cream: #efe8d6;
            --mist: #b9aecb;
            --gold: #c9a45c;
            --frame: #d6d2de;
            --rule: #cfcbd6;
            --royal: #3b0a6b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        body { background: #18141d; font-family: 'EB Garamond', Georgia, serif; color: var(--cream); }

        .toolbar {
            position: sticky; top: 0; z-index: 10;
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px;
            padding: 12px 16px; background: #0a080d; border-bottom: 1px solid #2e2a35;
            font: 14px/1.4 ui-sans-serif, system-ui, sans-serif; color: #d6d2dc;
        }
        .toolbar a { color: #c9b6ea; text-decoration: none; }
        .toolbar .hint { color: #a8a3ae; font-size: 13px; flex: 1 1 260px; }
        .toolbar button {
            font: 600 14px ui-sans-serif, system-ui, sans-serif; color: #18141d; background: #c9b6ea;
            border: 0; border-radius: 8px; padding: 8px 14px; cursor: pointer;
        }

        .pages { display: flex; flex-direction: column; align-items: center; gap: 24px; padding: 24px 0 48px; }

        .page {
            position: relative; width: 595.28pt; height: 841.89pt; overflow: hidden; flex: none;
            background: #22093f url('{{ asset('images/paper-purple.webp') }}') center / cover no-repeat;
            box-shadow: 0 10px 40px rgb(0 0 0 / .5);
        }

        .frame { position: absolute; border: 0 solid var(--frame); }
        .frame.left, .frame.right { top: 85.3pt; height: 671.3pt; width: 4.2pt; border-left-width: .9pt; border-right-width: .9pt; }
        .frame.left { left: 19.15pt; }
        .frame.right { left: 571.95pt; }
        .frame.top, .frame.bottom { left: 71.4pt; width: 452.5pt; height: 4.2pt; border-top-width: .9pt; border-bottom-width: .9pt; }
        .frame.top { top: 33.85pt; }
        .frame.bottom { top: 803.85pt; }

        .corner { position: absolute; width: 96pt; height: 119pt; }
        .corner.tl { left: 14pt; top: 14pt; }
        .corner.tr { left: 485.3pt; top: 14pt; transform: scaleX(-1); }
        .corner.bl { left: 14pt; top: 708.9pt; transform: scaleY(-1); }
        .corner.br { left: 485.3pt; top: 708.9pt; transform: scale(-1); }

        .crest { position: absolute; left: 235pt; top: 70pt; width: 126pt; height: 158pt; object-fit: contain; }

        .centered { position: absolute; left: 0; right: 0; text-align: center; white-space: nowrap; }
        .title { font-family: 'Cinzel', serif; font-weight: 700; color: var(--cream); line-height: 1.35; }
        .title.first { top: 236.7pt; font-size: 30pt; }
        .title.continued { top: 68.1pt; font-size: 20pt; }
        .subtitle { font-style: italic; color: var(--mist); line-height: 1.3; }
        .subtitle.first { top: 273.9pt; font-size: 13pt; }
        .subtitle.continued { top: 94pt; font-size: 11.5pt; }

        .divider { display: flex; align-items: center; justify-content: center; gap: 6pt; }
        .divider::before, .divider::after { content: ''; height: .5pt; background: var(--rule); }
        .divider.first::before, .divider.first::after { width: 138pt; }
        .divider.continued::before, .divider.continued::after { width: 98pt; }
        .divider.closing::before, .divider.closing::after { width: 58pt; }
        .divider.first { top: 299pt; }
        .divider.continued { top: 115.6pt; }
        .star { width: 12pt; height: 12pt; fill: var(--rule); flex: none; }

        .sheet { position: absolute; left: 62pt; width: 471.3pt; }
        .sheet.first { top: 323pt; }
        .sheet.continued { top: 141.6pt; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { text-align: left; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; padding: 0 8pt 0 0; vertical-align: middle; }
        th:first-child, td:first-child { padding-left: 16pt; }
        col.code { width: 86pt; }
        col.name { width: 200pt; }
        thead tr { height: 26pt; background: var(--royal); }
        th { font-family: 'Cinzel', serif; font-weight: 700; font-size: 9pt; color: var(--gold); border-top: .6pt solid var(--gold); border-bottom: .6pt solid var(--gold); }
        tbody tr { height: 20.5pt; border-bottom: .4pt solid rgb(207 201 214 / .18); }
        tbody tr:nth-child(odd) { background: rgb(255 255 255 / .045); }
        td.code { font-family: 'Cinzel', serif; font-weight: 600; font-size: 9.5pt; color: var(--gold); }
        td.name { font-weight: 500; font-size: 11pt; color: var(--cream); }
        td.campus { font-size: 10.5pt; color: var(--mist); }
        td.empty { text-align: center; font-style: italic; color: var(--mist); padding: 0; }

        .closing { margin-top: 34pt; text-align: center; }
        .closing .motto { margin-top: 7pt; font-style: italic; font-size: 13pt; color: var(--cream); }
        .closing .pillars { margin-top: 6pt; font-family: 'Cinzel', serif; font-size: 8pt; color: var(--gold); }

        .footer { top: 785pt; font-family: 'Cinzel', serif; font-size: 7.5pt; color: var(--mist); }

        @media print {
            body { background: none; }
            .toolbar { display: none; }
            .pages { display: block; padding: 0; }
            .page { box-shadow: none; break-after: page; }
            .page:last-child { break-after: auto; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('staff.ambassadors') }}">← {{ __('Campus ambassadors') }}</a>
        <strong>{{ $countLabel }}{{ $range ? ' · '.$range : '' }} · {{ trans_choice(':count page|:count pages', count($pages)) }}</strong>
        <span class="hint">{{ __('In the print window, choose “Save as PDF”. If you see the options, set Margins to None and turn Background graphics on.') }}</span>
        <button type="button" onclick="window.print()">{{ __('Save as PDF') }}</button>
    </div>

    <div class="pages">
        @foreach ($pages as $index => $rows)
            @php($variant = $loop->first ? 'first' : 'continued')
            <section class="page">
                <div class="frame left"></div><div class="frame right"></div><div class="frame top"></div><div class="frame bottom"></div>
                @foreach (['tl', 'tr', 'bl', 'br'] as $corner)
                    <img class="corner {{ $corner }}" src="{{ asset('images/ornament-corner.webp') }}" alt="" />
                @endforeach

                @if ($loop->first)
                    <img class="crest" src="{{ asset('images/ambassador-crest.webp') }}" alt="Homebridge MUN ’26" />
                    <div class="centered title first">Campus Ambassadors</div>
                    <div class="centered subtitle first">HBMUN 2.0 &nbsp;·&nbsp; {!! $range ? e($range).' &nbsp;·&nbsp; ' : '' !!}{{ $countLabel }}</div>
                @else
                    <div class="centered title continued">Campus Ambassadors</div>
                    <div class="centered subtitle continued">continued</div>
                @endif
                <div class="centered divider {{ $variant }}">{!! $star !!}</div>

                <div class="sheet {{ $variant }}">
                    <table>
                        <colgroup><col class="code" /><col class="name" /><col /></colgroup>
                        <thead>
                            <tr><th>CODE</th><th>NAME</th><th>CAMPUS / CITY</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $ambassador)
                                <tr>
                                    <td class="code">{{ $ambassador->code }}</td>
                                    <td class="name">{{ $ambassador->name }}</td>
                                    <td class="campus">{{ collect([$ambassador->institution, $ambassador->city])->filter()->unique()->implode(', ') }}</td>
                                </tr>
                            @empty
                                <tr><td class="empty" colspan="3">{{ __('No ambassadors in this range.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>

                    @if ($loop->last)
                        <div class="closing">
                            <div class="divider closing">{!! $star !!}</div>
                            <div class="motto">Better dialogue. Bigger perspectives.</div>
                            <div class="pillars">DIPLOMACY &nbsp;·&nbsp; LEADERSHIP &nbsp;·&nbsp; DISCUSSION &nbsp;·&nbsp; IMPACT</div>
                        </div>
                    @endif
                </div>

                <div class="centered footer">HOMEBRIDGE MUN ’26 &nbsp; · &nbsp; BY BEACONHOUSE &nbsp; · &nbsp; PAGE {{ $loop->iteration }} OF {{ $loop->count }}</div>
            </section>
        @endforeach
    </div>

    <script>
        // Shrink the A4 pages to fit narrow screens; printing always uses full size.
        (() => {
            const fit = () => {
                const zoom = Math.min(1, (window.innerWidth - 32) / (595.28 * 4 / 3));
                document.querySelectorAll('.page').forEach((page) => page.style.zoom = zoom);
            };
            fit();
            window.addEventListener('resize', fit);
            window.addEventListener('beforeprint', () => document.querySelectorAll('.page').forEach((page) => page.style.zoom = 1));
            window.addEventListener('afterprint', fit);

            @if ($autoPrint)
                window.addEventListener('load', () => document.fonts.ready.then(() => setTimeout(() => window.print(), 300)));
            @endif
        })();
    </script>
</body>
</html>

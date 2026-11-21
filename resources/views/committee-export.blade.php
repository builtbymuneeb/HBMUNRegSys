@php
    $total = $committees->sum(fn ($committee) => $committee->allocatedDelegates->count());
    $wide = count($columns) > 6;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex" />
    {{-- The browser uses the title as the file name when saving as PDF. --}}
    <title>{{ $title }}</title>
    @include('partials.icons')
    <link rel="preconnect" href="https://fonts.bunny.net" />
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=cinzel:600,700|eb-garamond:400,500&display=block" />
    <style>
        @page { size: A4 {{ $wide ? 'landscape' : 'portrait' }}; margin: 14mm 12mm; }

        :root { --royal: #3b0a6b; --ink: #1d1724; --muted: #6b6475; --rule: #ddd6e6; --tint: #f6f2fa; }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { background: #ece8f1; color: var(--ink); font: 10.5pt/1.35 'EB Garamond', Georgia, serif; }

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

        .sheet {
            width: min(100% - 32px, {{ $wide ? '297mm' : '210mm' }}); margin: 24px auto 48px; padding: 14mm 12mm;
            background: #fff; box-shadow: 0 10px 40px rgb(0 0 0 / .15);
        }

        header.doc { display: flex; align-items: center; gap: 14px; padding-bottom: 10px; border-bottom: 2px solid var(--royal); margin-bottom: 14px; }
        header.doc img { width: 44px; height: 44px; object-fit: contain; }
        header.doc .kicker { font: 600 8pt 'Cinzel', serif; letter-spacing: .14em; color: var(--muted); text-transform: uppercase; }
        header.doc h1 { font: 700 17pt/1.15 'Cinzel', serif; color: var(--royal); }
        header.doc .meta { margin-left: auto; text-align: right; font-size: 9.5pt; color: var(--muted); }

        section.committee + section.committee { margin-top: 22px; }
        section.committee.break { break-before: page; }
        section.committee h2 { font: 700 12.5pt 'Cinzel', serif; color: var(--royal); margin-bottom: 2px; }
        section.committee .sub { font-size: 9.5pt; color: var(--muted); margin-bottom: 8px; }

        table { width: 100%; border-collapse: collapse; font-size: {{ $wide ? '9pt' : '10pt' }}; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        th { text-align: left; font: 600 7.5pt 'Cinzel', serif; letter-spacing: .06em; text-transform: uppercase; color: #fff; background: var(--royal); padding: 5px 6px; }
        td { padding: 4px 6px; border-bottom: 1px solid var(--rule); vertical-align: top; overflow-wrap: anywhere; }
        tbody tr:nth-child(even) td { background: var(--tint); }
        td.num, th.num { width: 1%; white-space: nowrap; color: var(--muted); }
        .empty { padding: 10px 6px; color: var(--muted); font-style: italic; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ url()->previous(route('staff.committees')) }}">← {{ __('Back') }}</a>
        <strong>{{ trans_choice(':count delegate|:count delegates', $total) }} · {{ trans_choice(':count committee|:count committees', $committees->count()) }}</strong>
        <span class="hint">{{ __('In the print window, choose “Save as PDF” as the destination. Turn Background graphics on to keep the purple headers.') }}</span>
        <button type="button" onclick="window.print()">{{ __('Save as PDF') }}</button>
    </div>

    <main class="sheet">
        <header class="doc">
            <img src="{{ asset('images/ambassador-crest.webp') }}" alt="" />
            <div>
                <div class="kicker">{{ config('app.name') }}</div>
                <h1>{{ $heading }}</h1>
            </div>
            <div class="meta">
                {{ trans_choice(':count delegate|:count delegates', $total) }}<br />
                {{ now()->timezone('Asia/Karachi')->format('j M Y, g:i a') }}
            </div>
        </header>

        @foreach ($committees as $committee)
            <section @class(['committee', 'break' => ! $loop->first && $committee->allocatedDelegates->count() > 12])>
                @if ($committees->count() > 1)
                    <h2>{{ $committee->label() }}</h2>
                @endif
                <div class="sub">
                    {{ trans_choice(':count delegate allocated|:count delegates allocated', $committee->allocatedDelegates->count()) }}
                    @if ($committee->agenda)
                        · {{ __('Agenda: :agenda', ['agenda' => \Illuminate\Support\Str::limit($committee->agenda, 160)]) }}
                    @endif
                </div>

                @if ($committee->allocatedDelegates->isEmpty())
                    <div class="empty">{{ __('No delegates allocated yet.') }}</div>
                @else
                    <table>
                        <thead>
                            <tr>
                                <th class="num">#</th>
                                @foreach ($columns as $index => $column)
                                    <th>{{ $column === 'portfolio' ? $committee->portfolioLabel() : $labels[$index] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($committee->allocatedDelegates as $delegate)
                                <tr>
                                    <td class="num">{{ $loop->iteration }}</td>
                                    @foreach ($columns as $column)
                                        <td>{{ \App\Support\CommitteeExport::value($column, $delegate, $committee) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        @endforeach
    </main>

    <script>
        window.addEventListener('load', () => document.fonts.ready.then(() => setTimeout(() => window.print(), 300)));
    </script>
</body>
</html>

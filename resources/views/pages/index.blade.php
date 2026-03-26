<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="exception-viewer-assets-path" content="{{ $assetsPathUrl }}">
    <title>Exception Viewer</title>
    @php
        $faviconSvg = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="18" fill="#e0f2fe"/><path d="M20 12h18l10 10v30a4 4 0 0 1-4 4H20a4 4 0 0 1-4-4V16a4 4 0 0 1 4-4Z" fill="#ffffff" stroke="#0f172a" stroke-width="3"/><path d="M38 12v12h12" fill="#dbeafe" stroke="#0f172a" stroke-width="3"/><path d="M24 32h16M24 40h16" stroke="#0ea5e9" stroke-width="4" stroke-linecap="round"/></svg>');
    @endphp
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ $faviconSvg }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        html {
            color-scheme: light;
            font-family: 'Geist', ui-sans-serif, system-ui, sans-serif;
        }

        summary::-webkit-details-marker {
            display: none;
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.8);
            border: 2px solid transparent;
            border-radius: 9999px;
            background-clip: padding-box;
        }
    </style>
</head>
<body class="h-full overflow-hidden bg-slate-100 text-slate-900 antialiased" data-assets-path="{{ $assetsPathUrl }}">
    @php
        $persistentFilters = array_filter([
            'sort' => $currentSort !== 'newest' ? $currentSort : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $environment = (string) app()->environment();
        $environmentLabel = strtoupper($environment);
        $environmentTone = match ($environment) {
            'local' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'staging' => 'border-amber-200 bg-amber-50 text-amber-700',
            'production' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-100 text-slate-600',
        };
    @endphp

    <div class="relative isolate h-full overflow-hidden bg-slate-100">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(14,165,233,0.10),transparent_28%),radial-gradient(circle_at_bottom_right,rgba(59,130,246,0.08),transparent_24%),linear-gradient(to_bottom,rgba(248,250,252,0.92),rgba(241,245,249,0.96))]"></div>

        <div class="mx-auto box-border h-full w-full max-w-[1760px] px-3 py-3 sm:px-5 sm:py-5">
            <main class="grid h-full overflow-hidden rounded-[28px] border border-slate-200/80 bg-white/85 shadow-[0_28px_90px_rgba(148,163,184,0.18)] backdrop-blur lg:grid-cols-[18rem,minmax(0,1fr)]">
                <aside class="flex min-h-0 flex-col border-b border-slate-200 bg-slate-50/85 lg:border-b-0 lg:border-r">
                    <div class="flex h-16 items-center border-b border-slate-200 px-4 sm:px-5">
                        <div class="flex min-w-0 flex-nowrap items-center gap-3">
                            <div class="h-2.5 w-2.5 rounded-full bg-sky-500"></div>
                            <h1 class="truncate text-base font-semibold text-slate-900">Exception Viewer</h1>
                            <span class="inline-flex shrink-0 whitespace-nowrap rounded-full border px-1.5 py-[3px] text-[8px] font-semibold uppercase tracking-[0.06em] sm:px-1.5 sm:text-[9px] {{ $environmentTone }}">{{ $environmentLabel }}</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-4 py-3 text-[11px] uppercase tracking-[0.24em] text-slate-500 sm:px-5">
                        <span>Group</span>
                        <span class="tabular-nums">{{ $groups->count() + 1 }}</span>
                    </div>

                    <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto px-3 pb-3 sm:px-4">
                        <a
                            href="{{ route('exception-viewer.index', $persistentFilters) }}"
                            class="{{ $selectedGroup === 'all' ? 'border-sky-200 bg-white text-slate-900 shadow-sm shadow-sky-100/80' : 'border-transparent bg-transparent text-slate-600 hover:border-slate-200 hover:bg-white/90 hover:text-slate-900' }} flex items-center justify-between rounded-xl border px-3 py-3 transition"
                        >
                            <span class="truncate text-sm font-medium">All</span>
                            <span class="tabular-nums rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-500">{{ $totalRows }}</span>
                        </a>

                        @foreach ($groups as $group)
                            <a
                                href="{{ route('exception-viewer.index', ['group' => $group['name']] + $persistentFilters) }}"
                                class="{{ $selectedGroup === $group['name'] ? 'border-sky-200 bg-white text-slate-900 shadow-sm shadow-sky-100/80' : 'border-transparent bg-transparent text-slate-600 hover:border-slate-200 hover:bg-white/90 hover:text-slate-900' }} flex items-center justify-between rounded-xl border px-3 py-3 transition"
                            >
                                <span class="truncate pr-3 text-sm font-medium">{{ $group['name'] }}</span>
                                <span class="tabular-nums rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-500">{{ $group['row_count'] }}</span>
                            </a>
                        @endforeach
                    </nav>
                </aside>

                <section class="flex min-h-0 min-w-0 flex-col bg-white/55">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-3 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                <p class="truncate text-sm font-medium text-slate-900">
                                    {{ $selectedGroup === 'all' ? 'All' : $selectedGroup }}
                                </p>
                                <span class="tabular-nums rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-xs text-slate-500">
                                    {{ $exceptions->count() }}
                                </span>
                            </div>

                            <form
                                method="POST"
                                action="{{ route('exception-viewer.purge') }}"
                                onsubmit="return confirm('Delete all exception logs?');"
                            >
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                <button
                                    type="submit"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-600 transition hover:border-rose-300 hover:bg-rose-100 hover:text-rose-700"
                                    aria-label="Delete all exception logs"
                                    title="Delete all exception logs"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.1 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.108 48.108 0 0 0-3.48-.4m-12 .4c.34-.07.68-.12 1.02-.17m0 0A48.11 48.11 0 0 1 8.25 5.4m7.5 0V4.5A2.25 2.25 0 0 0 13.5 2.25h-3A2.25 2.25 0 0 0 8.25 4.5v.9m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        </div>

                        <form method="GET" action="{{ route('exception-viewer.index') }}" class="flex flex-wrap justify-end gap-3">
                            @if ($selectedGroup !== 'all')
                                <input type="hidden" name="group" value="{{ $selectedGroup }}">
                            @endif

                            <button
                                type="button"
                                data-copy-label="Copy all exception export link"
                                onclick="copyButtonText(event, '{{ base64_encode(route('exception-viewer.all')) }}')"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:border-sky-200 hover:text-sky-700"
                                aria-label="Copy all exception export link"
                                title="Copy all exception export link"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6a2.25 2.25 0 0 1 2.25-2.25h6L21 8.25v9.75A2.25 2.25 0 0 1 18.75 20.25H15" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V8.25H21" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75A2.25 2.25 0 0 1 5.25 7.5h6a2.25 2.25 0 0 1 2.25 2.25v8.25a2.25 2.25 0 0 1-2.25 2.25h-6A2.25 2.25 0 0 1 3 18V9.75Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12.75h3M6.75 15.75h3.75" />
                                </svg>
                                <span class="sr-only">Copy all exception export link</span>
                            </button>

                            <label class="sr-only" for="viewer-sort">Sort exceptions</label>
                            <select
                                id="viewer-sort"
                                name="sort"
                                onchange="this.form.requestSubmit()"
                                class="h-11 rounded-xl border border-slate-200 bg-white px-4 text-sm text-slate-700 outline-none transition focus:border-sky-300"
                            >
                                <option value="newest" @selected($currentSort === 'newest')>Newest</option>
                                <option value="count" @selected($currentSort === 'count')>Count</option>
                                <option value="oldest" @selected($currentSort === 'oldest')>Oldest</option>
                            </select>
                        </form>
                    </div>

                    @if ($exceptions->isEmpty())
                        <div class="flex flex-1 items-center justify-center px-6">
                            <p class="text-sm text-slate-500">No entries.</p>
                        </div>
                    @else
                        <div class="hidden border-b border-slate-200 px-4 py-3 text-[11px] uppercase tracking-[0.24em] text-slate-500 lg:grid lg:grid-cols-[7.5rem,minmax(0,1fr),9rem,4.5rem,4rem,4rem] lg:gap-4 lg:px-6">
                            <span>Key</span>
                            <span>Name</span>
                            <span class="text-right">Date</span>
                            <span class="text-center">Count</span>
                            <span class="text-center">Copy</span>
                            <span class="text-center">Link</span>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto">
                            @foreach ($exceptions as $exception)
                                <details id="{{ $exception['dom_id'] }}" class="group border-b border-slate-200/90 last:border-b-0">
                                    <summary class="list-none cursor-pointer px-4 py-4 transition hover:bg-slate-50 sm:px-6">
                                        <div class="grid gap-3 lg:grid-cols-[7.5rem,minmax(0,1fr),9rem,4.5rem,4rem,4rem] lg:items-center lg:gap-4">
                                            <div class="flex items-center gap-3">
                                                <svg class="h-4 w-4 shrink-0 text-slate-400 transition group-open:rotate-90 group-open:text-sky-600" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M7.28 4.97a.75.75 0 0 1 1.06.03l4.25 4.5a.75.75 0 0 1 0 1.03l-4.25 4.5a.75.75 0 1 1-1.09-1.02l3.76-3.98-3.76-3.98a.75.75 0 0 1 .03-1.08Z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="tabular-nums inline-flex rounded-md border border-slate-200 bg-white px-2.5 py-1 font-mono text-xs font-medium tracking-[0.2em] text-sky-700">
                                                    {{ $exception['short_key'] }}
                                                </span>
                                            </div>

                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-slate-900">{{ $exception['name'] }}</p>
                                            </div>

                                            <div class="flex items-center lg:justify-end">
                                                <p class="tabular-nums text-xs text-slate-500">{{ $exception['latest_at'] }}</p>
                                            </div>

                                            <div class="flex items-center lg:justify-center">
                                                <span class="tabular-nums inline-flex min-w-12 items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-sm font-semibold text-slate-700">
                                                    {{ $exception['count'] }}
                                                </span>
                                            </div>

                                            <div class="flex items-center lg:justify-center">
                                                <button
                                                    type="button"
                                                    data-copy-label="Copy exception markdown"
                                                    onclick="copyButtonText(event, '{{ base64_encode($exception['copy_markdown']) }}')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition duration-150 hover:border-sky-200 hover:text-sky-700"
                                                    aria-label="Copy exception markdown"
                                                    title="Copy exception markdown"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                                    </svg>
                                                    <span class="sr-only">Copy exception markdown</span>
                                                </button>
                                            </div>

                                            <div class="flex items-center lg:justify-center">
                                                <button
                                                    type="button"
                                                    data-copy-label="Copy exception detail link"
                                                    onclick="copyButtonText(event, '{{ base64_encode($exception['detail_url']) }}')"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition duration-150 hover:border-sky-200 hover:text-sky-700"
                                                    aria-label="Copy exception detail link"
                                                    title="Copy exception detail link"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                                                    </svg>
                                                    <span class="sr-only">Copy exception detail link</span>
                                                </button>
                                            </div>
                                        </div>
                                    </summary>

                                    <div class="bg-slate-50/80 px-4 pb-4 pt-1 sm:px-6 sm:pb-5">
                                        <div class="grid gap-3 xl:grid-cols-[8rem,minmax(0,1.35fr),minmax(0,1fr),minmax(0,1fr)]">
                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Method</p>
                                                <p class="mt-3 text-sm font-semibold text-slate-900">{{ $exception['request_method'] }}</p>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Endpoint</p>
                                                <p class="mt-3 break-all text-sm leading-6 text-slate-700">{{ $exception['request_endpoint'] }}</p>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Headers</p>
                                                <pre class="mt-3 max-h-64 overflow-auto text-xs leading-6 text-slate-700">{{ $exception['request_headers'] }}</pre>
                                            </div>

                                            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                                <p class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Payload</p>
                                                <pre class="mt-3 max-h-64 overflow-auto text-xs leading-6 text-slate-700">{{ $exception['request_payload'] }}</pre>
                                            </div>
                                        </div>

                                        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
                                            <p class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Location</p>
                                            <p class="mt-3 break-all font-mono text-xs leading-6 text-slate-700">{{ $exception['location'] }}</p>
                                        </div>

                                        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-4">
                                            <pre class="max-h-[36rem] overflow-auto whitespace-pre-wrap break-words text-xs leading-6 text-slate-700">{{ $exception['raw_exception'] }}</pre>
                                        </div>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                </section>
            </main>
        </div>
    </div>

    <script>
        function decodeBase64Utf8(encoded) {
            const binary = window.atob(encoded);
            const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));

            return new TextDecoder().decode(bytes);
        }

        async function copyButtonText(event, encodedText) {
            event.preventDefault();
            event.stopPropagation();

            const button = event.currentTarget;
            const defaultLabel = button.dataset.copyLabel || button.getAttribute('aria-label') || 'Copy';
            const transientClasses = [
                'border-emerald-200',
                'bg-emerald-50',
                'text-emerald-700',
                'border-rose-200',
                'bg-rose-50',
                'text-rose-700',
            ];

            try {
                await navigator.clipboard.writeText(decodeBase64Utf8(encodedText));
                button.animate([
                    { transform: 'scale(1)' },
                    { transform: 'scale(0.92)' },
                    { transform: 'scale(1)' },
                ], {
                    duration: 180,
                    easing: 'ease-out',
                });
                button.classList.remove(...transientClasses);
                button.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
                button.setAttribute('aria-label', 'Copied');
                button.setAttribute('title', 'Copied');
            } catch (error) {
                button.animate([
                    { transform: 'translateX(0)' },
                    { transform: 'translateX(-2px)' },
                    { transform: 'translateX(2px)' },
                    { transform: 'translateX(0)' },
                ], {
                    duration: 180,
                    easing: 'ease-out',
                });
                button.classList.remove(...transientClasses);
                button.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
                button.setAttribute('aria-label', 'Copy failed');
                button.setAttribute('title', 'Copy failed');
            }

            window.clearTimeout(button.copyResetTimer);
            button.copyResetTimer = window.setTimeout(() => {
                button.classList.remove(...transientClasses);
                button.setAttribute('aria-label', defaultLabel);
                button.setAttribute('title', defaultLabel);
            }, 1200);
        }
    </script>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Review grouped Laravel exception logs and copy markdown context for investigation.">
    <meta name="exception-viewer-assets-path" content="{{ $assetsPathUrl }}">
    <title>Exception Viewer</title>
    @php
        $faviconSvg = rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="12" fill="#f8fafc"/><path d="M19 10h18l8 8v34a4 4 0 0 1-4 4H19a4 4 0 0 1-4-4V14a4 4 0 0 1 4-4Z" fill="#ffffff" stroke="#0f172a" stroke-width="3"/><path d="M37 10v10h10" fill="#e2e8f0" stroke="#0f172a" stroke-width="3"/><path d="M23 31h18M23 40h13" stroke="#0284c7" stroke-width="4" stroke-linecap="round"/></svg>');
    @endphp
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,{{ $faviconSvg }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html,
        body {
            height: 100%;
            overflow: hidden;
        }

        html {
            color-scheme: light;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        summary::-webkit-details-marker {
            display: none;
        }

        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.78);
            border: 2px solid transparent;
            border-radius: 9999px;
            background-clip: padding-box;
        }

        [data-source-nav] {
            cursor: grab;
            overscroll-behavior-inline: contain;
            user-select: none;
        }

        [data-source-nav].is-dragging {
            cursor: grabbing;
            scroll-behavior: auto;
        }

        [data-source-nav] a {
            -webkit-user-drag: none;
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body class="h-full overflow-hidden bg-slate-50 text-slate-900 antialiased" data-assets-path="{{ $assetsPathUrl }}">
    @php
        $environment = (string) app()->environment();
        $environmentLabel = strtoupper($environment);
        $environmentTone = match ($environment) {
            'local' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'staging' => 'border-amber-200 bg-amber-50 text-amber-700',
            'production' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-slate-200 bg-slate-50 text-slate-600',
        };
        $selectedSourceLabel = $selectedSource === null
            ? 'Local App'
            : ($sources->firstWhere('key', $selectedSource)['label'] ?? $selectedSource);
        $currentSourceKey = $selectedSource ?? $localSourceKey;
        $selectedSourceIsLocal = $currentSourceKey === $localSourceKey;
        $exportFilters = array_filter([
            'source' => $selectedSourceIsLocal ? null : $selectedSource,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    @endphp

    <div class="h-full overflow-hidden p-2 sm:p-4">
        <main class="mx-auto flex h-full max-w-[1720px] overflow-hidden rounded-xl border border-slate-200 bg-slate-50 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
            <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                <header class="border-b border-slate-200 bg-white">
                    <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex min-w-0 flex-wrap items-center gap-2.5">
                                <h1 class="truncate text-lg font-semibold leading-7 text-slate-950 sm:text-xl">Exception Viewer</h1>
                                <span class="inline-flex h-7 shrink-0 items-center rounded-md border px-2 text-[11px] font-medium tabular-nums {{ $environmentTone }}">{{ $environmentLabel }}</span>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <form method="GET" action="{{ route('exception-viewer.index') }}" class="flex items-center gap-2">
                                @if ($selectedSource !== null && ! $selectedSourceIsLocal)
                                    <input type="hidden" name="source" value="{{ $selectedSource }}">
                                @endif

                                <button
                                    type="button"
                                    data-copy-label="Copy source export link"
                                    onclick="copyButtonText(event, '{{ base64_encode(route('exception-viewer.all', $exportFilters)) }}')"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 transition-colors hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 disabled:cursor-not-allowed disabled:opacity-70"
                                    aria-label="Copy source export link"
                                    title="Copy source export link"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 7.5V6a2.25 2.25 0 0 1 2.25-2.25h6L21 8.25v9.75A2.25 2.25 0 0 1 18.75 20.25H15" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 3.75V8.25H21" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75A2.25 2.25 0 0 1 5.25 7.5h6a2.25 2.25 0 0 1 2.25 2.25v8.25a2.25 2.25 0 0 1-2.25 2.25h-6A2.25 2.25 0 0 1 3 18V9.75Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12.75h3M6.75 15.75h3.75" />
                                    </svg>
                                    <span class="sr-only">Copy source export link</span>
                                </button>

                                <label class="sr-only" for="viewer-sort">Sort exceptions</label>
                                <select
                                    id="viewer-sort"
                                    name="sort"
                                    onchange="this.form.requestSubmit()"
                                    class="h-9 min-w-28 rounded-md border border-slate-200 bg-white px-3 text-sm text-slate-700 outline-none transition-colors focus:border-sky-400 focus:ring-2 focus:ring-sky-100"
                                >
                                    <option value="newest" @selected($currentSort === 'newest')>Newest</option>
                                    <option value="count" @selected($currentSort === 'count')>Count</option>
                                    <option value="oldest" @selected($currentSort === 'oldest')>Oldest</option>
                                </select>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('exception-viewer.purge') }}"
                                onsubmit="return confirm('Delete exception logs for the current source?');"
                            >
                                @csrf
                                <input type="hidden" name="source" value="{{ $currentSourceKey }}">
                                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                <button
                                    type="submit"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-rose-200 bg-white text-rose-600 transition-colors hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500"
                                    aria-label="Delete current source exception logs"
                                    title="Delete {{ $selectedSourceLabel }} exception logs"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.1 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.108 48.108 0 0 0-3.48-.4m-12 .4c.34-.07.68-.12 1.02-.17m0 0A48.11 48.11 0 0 1 8.25 5.4m7.5 0V4.5A2.25 2.25 0 0 0 13.5 2.25h-3A2.25 2.25 0 0 0 8.25 4.5v.9m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>

                            <form
                                method="POST"
                                action="{{ route('exception-viewer.purge') }}"
                                onsubmit="return confirm('Delete all exception logs from every source?');"
                            >
                                @csrf
                                <input type="hidden" name="scope" value="all">
                                <input type="hidden" name="redirect_to" value="{{ request()->getRequestUri() }}">
                                <button
                                    type="submit"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-rose-200 bg-rose-50 text-lg leading-none text-rose-700 transition-colors hover:border-rose-300 hover:bg-rose-100 hover:text-rose-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rose-500"
                                    aria-label="Delete all exception logs"
                                    title="Delete all exception logs"
                                >
                                    <span aria-hidden="true">🚛</span>
                                </button>
                            </form>
                        </div>
                    </div>

                    @if ($sources->isNotEmpty())
                        <nav class="flex max-w-full gap-5 overflow-x-auto border-t border-slate-100 px-4 sm:px-6" aria-label="Exception sources" data-source-nav>
                            @foreach ($sources as $source)
                                @php
                                    $sourceFilters = array_filter([
                                        'source' => $source['is_local'] ? null : $source['key'],
                                        'sort' => $currentSort !== 'newest' ? $currentSort : null,
                                    ], static fn (mixed $value): bool => $value !== null && $value !== '');
                                @endphp
                                <a
                                    href="{{ route('exception-viewer.index', $sourceFilters) }}"
                                    class="{{ $selectedSource === $source['key'] ? 'border-slate-950 text-slate-950' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' }} inline-flex h-11 shrink-0 items-center gap-2 border-b-2 text-sm font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-sky-500"
                                    aria-current="{{ $selectedSource === $source['key'] ? 'page' : 'false' }}"
                                >
                                    <span>{{ $source['label'] }}</span>
                                    <span class="{{ $selectedSource === $source['key'] ? 'border-slate-300 bg-slate-100 text-slate-700' : 'border-slate-200 bg-white text-slate-500' }} inline-flex min-w-6 items-center justify-center rounded-md border px-1.5 py-0.5 text-xs tabular-nums">{{ number_format($source['row_count']) }}</span>
                                </a>
                            @endforeach
                        </nav>
                    @endif
                </header>

                @if ($exceptions->isEmpty())
                    <div class="flex flex-1 items-center justify-center bg-slate-50 px-6 py-12">
                        <div class="max-w-sm rounded-lg border border-slate-200 bg-white px-6 py-5 text-center shadow-sm">
                            <p class="text-base font-semibold text-slate-900">No exception logs found.</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500">{{ $selectedSourceLabel }} has no recorded exceptions yet.</p>
                        </div>
                    </div>
                @else
                    <div class="hidden border-b border-slate-200 bg-slate-50/90 px-4 py-2 text-xs font-medium text-slate-500 lg:grid lg:grid-cols-[8rem,minmax(0,1fr),11rem,4.5rem,3rem,3rem] lg:gap-4 lg:px-6">
                        <span>Key</span>
                        <span>Name</span>
                        <span class="text-right">Date</span>
                        <span class="text-center">Count</span>
                        <span class="text-center">Copy</span>
                        <span class="text-center">Link</span>
                    </div>

                    <div class="min-h-0 flex-1 overflow-x-hidden overflow-y-auto bg-slate-50">
                        @foreach ($exceptions as $exception)
                            <details id="{{ $exception['dom_id'] }}" class="group min-w-0 border-b border-slate-100 bg-white last:border-b-0">
                                <summary class="list-none cursor-pointer px-4 py-3 transition-colors hover:bg-slate-50 group-open:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-inset focus-visible:outline-sky-500 sm:px-6">
                                    <div class="grid gap-3 lg:grid-cols-[8rem,minmax(0,1fr),11rem,4.5rem,3rem,3rem] lg:items-center lg:gap-4">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90 group-open:text-sky-700 motion-reduce:transition-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.28 4.97a.75.75 0 0 1 1.06.03l4.25 4.5a.75.75 0 0 1 0 1.03l-4.25 4.5a.75.75 0 1 1-1.09-1.02l3.76-3.98-3.76-3.98a.75.75 0 0 1 .03-1.08Z" clip-rule="evenodd" />
                                            </svg>
                                            <span class="rounded-md border border-slate-200 bg-white px-2 py-1 font-mono text-xs font-medium tabular-nums text-slate-700">
                                                {{ $exception['short_key'] }}
                                            </span>
                                        </div>

                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-950">{{ $exception['name'] }}</p>
                                            <p class="mt-1 truncate text-sm text-slate-500">{{ $exception['message'] }}</p>
                                        </div>

                                        <div class="flex items-center lg:justify-end">
                                            <p class="text-xs tabular-nums text-slate-500">{{ $exception['latest_at'] }}</p>
                                        </div>

                                        <div class="flex items-center lg:justify-center">
                                            <span class="inline-flex min-w-9 items-center justify-center rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-sm font-semibold tabular-nums text-slate-700">
                                                {{ number_format($exception['count']) }}
                                            </span>
                                        </div>

                                        <div class="flex items-center gap-2 lg:contents">
                                            <div class="flex lg:justify-center">
                                                <button
                                                    type="button"
                                                    data-copy-label="Copy exception markdown"
                                                    onclick="copyButtonText(event, '{{ base64_encode($exception['copy_markdown']) }}')"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 disabled:cursor-not-allowed disabled:opacity-70"
                                                    aria-label="Copy exception markdown"
                                                    title="Copy exception markdown"
                                                >
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                                    </svg>
                                                    <span class="sr-only">Copy exception markdown</span>
                                                </button>
                                            </div>

                                            <div class="flex lg:justify-center">
                                                <button
                                                    type="button"
                                                    data-copy-label="Copy exception detail link"
                                                    onclick="copyButtonText(event, '{{ base64_encode($exception['detail_url']) }}')"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 transition-colors hover:border-sky-300 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-500 disabled:cursor-not-allowed disabled:opacity-70"
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
                                    </div>
                                </summary>

                                <div class="min-w-0 overflow-hidden border-t border-slate-200 bg-slate-50/80 px-4 py-4 sm:px-6">
                                    <div class="grid min-w-0 gap-4 rounded-md border border-slate-200 bg-white px-4 py-3 sm:grid-cols-[8rem,minmax(0,1fr)]">
                                        <div class="min-w-0">
                                            <h3 class="text-xs font-medium text-slate-500">Method</h3>
                                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $exception['request_method'] }}</p>
                                        </div>

                                        <div class="min-w-0">
                                            <h3 class="text-xs font-medium text-slate-500">Endpoint</h3>
                                            <p class="mt-1 break-all text-sm leading-6 text-slate-700">{{ $exception['request_endpoint'] }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
                                        <section class="min-w-0">
                                            <h3 class="text-xs font-medium text-slate-500">Headers</h3>
                                            <pre class="mt-2 max-h-64 min-w-0 overflow-auto whitespace-pre-wrap break-words rounded-md border border-slate-200 bg-white p-3 text-xs leading-6 text-slate-700">{{ $exception['request_headers'] }}</pre>
                                        </section>

                                        <section class="min-w-0">
                                            <h3 class="text-xs font-medium text-slate-500">Payload</h3>
                                            <pre class="mt-2 max-h-64 min-w-0 overflow-auto whitespace-pre-wrap break-words rounded-md border border-slate-200 bg-white p-3 text-xs leading-6 text-slate-700">{{ $exception['request_payload'] }}</pre>
                                        </section>
                                    </div>

                                    <section class="mt-4 min-w-0">
                                        <h3 class="text-xs font-medium text-slate-500">Location</h3>
                                        <p class="mt-2 break-all rounded-md border border-slate-200 bg-white p-3 font-mono text-xs leading-6 text-slate-700">{{ $exception['location'] }}</p>
                                    </section>

                                    <section class="mt-4 min-w-0">
                                        <h3 class="text-xs font-medium text-slate-500">Stack trace</h3>
                                        <pre class="mt-2 max-h-[36rem] min-w-0 overflow-auto whitespace-pre-wrap break-words rounded-md bg-slate-950 p-4 text-xs leading-6 text-slate-100">{{ $exception['raw_exception'] }}</pre>
                                    </section>
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>
        </main>
    </div>

    <script>
        function decodeBase64Utf8(encoded) {
            const binary = window.atob(encoded);
            const bytes = Uint8Array.from(binary, (character) => character.charCodeAt(0));

            return new TextDecoder().decode(bytes);
        }

        function setCopyState(button, state, label) {
            const stateClasses = [
                'border-emerald-200',
                'bg-emerald-50',
                'text-emerald-700',
                'border-rose-200',
                'bg-rose-50',
                'text-rose-700',
            ];

            button.classList.remove(...stateClasses);

            if (state === 'success') {
                button.classList.add('border-emerald-200', 'bg-emerald-50', 'text-emerald-700');
            }

            if (state === 'error') {
                button.classList.add('border-rose-200', 'bg-rose-50', 'text-rose-700');
            }

            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
        }

        async function copyButtonText(event, encodedText) {
            event.preventDefault();
            event.stopPropagation();

            const button = event.currentTarget;
            const defaultLabel = button.dataset.copyLabel || button.getAttribute('aria-label') || 'Copy';

            if (button.disabled) {
                return;
            }

            button.disabled = true;

            try {
                await navigator.clipboard.writeText(decodeBase64Utf8(encodedText));
                setCopyState(button, 'success', 'Copied');
            } catch (error) {
                setCopyState(button, 'error', 'Copy failed');
            } finally {
                window.clearTimeout(button.copyResetTimer);
                button.copyResetTimer = window.setTimeout(() => {
                    setCopyState(button, 'default', defaultLabel);
                    button.disabled = false;
                }, 1200);
            }
        }

        function setupSourceNavScrollMemory() {
            const nav = document.querySelector('[data-source-nav]');

            if (!nav) {
                return;
            }

            const storageKey = `exception-viewer:source-nav-scroll-left:${window.location.pathname}`;

            const readScrollLeft = () => {
                try {
                    const value = window.sessionStorage.getItem(storageKey);

                    return value === null ? null : Number(value);
                } catch (error) {
                    return null;
                }
            };

            const writeScrollLeft = () => {
                try {
                    window.sessionStorage.setItem(storageKey, String(nav.scrollLeft));
                } catch (error) {
                    // Ignore storage failures so navigation still behaves like a normal link.
                }
            };

            let activePointerId = null;
            let startX = 0;
            let startScrollLeft = 0;
            let didDrag = false;
            let suppressClick = false;
            const dragThreshold = 4;

            window.requestAnimationFrame(() => {
                const scrollLeft = readScrollLeft();

                if (Number.isFinite(scrollLeft)) {
                    nav.scrollLeft = scrollLeft;

                    return;
                }

                nav.querySelector('[aria-current="page"]')?.scrollIntoView({
                    block: 'nearest',
                    inline: 'nearest',
                });
            });

            nav.addEventListener('scroll', writeScrollLeft, { passive: true });
            nav.addEventListener('pointerdown', (event) => {
                if (event.button !== 0) {
                    return;
                }

                suppressClick = false;

                if (nav.scrollWidth <= nav.clientWidth) {
                    return;
                }

                activePointerId = event.pointerId;
                startX = event.clientX;
                startScrollLeft = nav.scrollLeft;
                didDrag = false;
            });
            nav.addEventListener('pointermove', (event) => {
                if (activePointerId !== event.pointerId) {
                    return;
                }

                if ((event.buttons & 1) !== 1) {
                    activePointerId = null;
                    nav.classList.remove('is-dragging');

                    return;
                }

                const deltaX = event.clientX - startX;

                if (!didDrag && Math.abs(deltaX) < dragThreshold) {
                    return;
                }

                if (!didDrag) {
                    didDrag = true;
                    nav.classList.add('is-dragging');
                    nav.setPointerCapture?.(event.pointerId);
                }

                event.preventDefault();
                nav.scrollLeft = startScrollLeft - deltaX;
            });
            nav.addEventListener('pointerup', (event) => {
                if (activePointerId !== event.pointerId) {
                    return;
                }

                if (nav.hasPointerCapture?.(event.pointerId)) {
                    nav.releasePointerCapture(event.pointerId);
                }

                nav.classList.remove('is-dragging');
                activePointerId = null;

                if (didDrag) {
                    suppressClick = true;
                    writeScrollLeft();
                    window.setTimeout(() => {
                        suppressClick = false;
                    }, 0);
                }
            });
            nav.addEventListener('pointercancel', (event) => {
                if (activePointerId !== event.pointerId) {
                    return;
                }

                if (nav.hasPointerCapture?.(event.pointerId)) {
                    nav.releasePointerCapture(event.pointerId);
                }

                nav.classList.remove('is-dragging');
                activePointerId = null;
                didDrag = false;
            });
            nav.addEventListener('dragstart', (event) => {
                event.preventDefault();
            });
            nav.addEventListener('click', (event) => {
                if (suppressClick) {
                    suppressClick = false;
                    event.preventDefault();
                    event.stopPropagation();

                    return;
                }

                if (event.target.closest('a[href]')) {
                    writeScrollLeft();
                }
            });
        }

        setupSourceNavScrollMemory();
    </script>
</body>
</html>

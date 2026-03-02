{{-- resources/views/filament/pages/shopify-import-test.blade.php --}}
<x-filament::page>
    @push('styles')
        <style>
            /* =========================================================================
             *  Shopify Import Page – “Ops dashboard” (no Tailwind required)
             *  - Dark-mode aware
             *  - Fixes: include_images vs download_images naming mismatch
             *  - Matches your backend: include_images, strict_image_check, update_existing, chunk_size, currency, queue
             * ========================================================================= */

            /* ==========================
             * FORCE FULL WIDTH (Filament)
             * ========================== */
            .fi-main,
            .fi-page,
            .fi-page-content,
            .fi-section-content,
            .fi-page-header,
            .fi-page-header-content { max-width: none !important; }

            .fi-page-content {
                padding-left: 18px !important;
                padding-right: 18px !important;
            }

            [x-cloak] { display: none !important; }

            /* ==========================
             * TOKENS
             * ========================== */
            .shp-root {
                --shp-bg: #f6f8fb;
                --shp-panel: rgba(255,255,255,.92);
                --shp-panel-2: rgba(248,250,252,.92);
                --shp-text: #0f172a;
                --shp-muted: #64748b;
                --shp-border: rgba(148, 163, 184, .35);
                --shp-border-2: rgba(148, 163, 184, .55);

                --shp-primary: #0ea5e9;
                --shp-primary-2: #6366f1;
                --shp-success: #22c55e;
                --shp-warn: #f59e0b;
                --shp-danger: #ef4444;

                --shp-shadow: 0 18px 55px rgba(2, 6, 23, .10);
                --shp-shadow-soft: 0 10px 25px rgba(15, 23, 42, 0.06);

                --shp-r: 16px;
                --shp-r2: 12px;
                --shp-r3: 10px;

                --shp-mono: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono","Courier New", monospace;
                --shp-sans: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", sans-serif;

                --shp-hero: linear-gradient(120deg, #eff6ff, #ffffff, #eef2ff);
                --shp-progress: linear-gradient(90deg, var(--shp-primary), var(--shp-primary-2), var(--shp-success));
                --shp-console-bg: #020617;
                --shp-console-fg: #bbf7d0;

                width: 100%;
                max-width: none;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                gap: 18px;
                font-family: var(--shp-sans);
                color: var(--shp-text);
                font-size: 14px;
            }

            /* Filament uses .dark on html/body in many setups; support both */
            html.dark .shp-root,
            body.dark .shp-root,
            .dark .shp-root {
                --shp-bg: #0b1220;
                --shp-panel: rgba(2, 6, 23, .62);
                --shp-panel-2: rgba(15, 23, 42, .55);
                --shp-text: #e5e7eb;
                --shp-muted: rgba(226,232,240,.72);
                --shp-border: rgba(148, 163, 184, .22);
                --shp-border-2: rgba(148, 163, 184, .28);

                --shp-shadow: 0 22px 70px rgba(0,0,0,.35);
                --shp-shadow-soft: 0 16px 40px rgba(0,0,0,.25);

                --shp-hero: radial-gradient(circle at 0% 0%, rgba(14,165,233,.18), rgba(2,6,23,.55) 46%, rgba(2,6,23,.75) 100%);
                --shp-console-bg: #000814;
                --shp-console-fg: #b7f7cf;
            }

            /* Use the token background without touching Filament base too much */
            html, body { background: transparent; }

            /* ==========================
             * LAYOUT PRIMITIVES
             * ========================== */
            .shp-hero {
                border-radius: var(--shp-r);
                padding: 16px 18px;
                background: var(--shp-hero);
                box-shadow: var(--shp-shadow);
                border: 1px solid var(--shp-border);
                backdrop-filter: blur(6px);
            }

            .shp-hero-inner {
                display: flex;
                flex-direction: column;
                gap: 14px;
            }

            @media (min-width: 860px) {
                .shp-hero-inner {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                    gap: 18px;
                }
            }

            .shp-hero-main { display: flex; flex-direction: column; gap: 8px; max-width: 980px; }

            .shp-title-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
            .shp-title { font-size: 18px; font-weight: 700; letter-spacing: -0.015em; }

            .shp-subtitle { font-size: 12px; color: var(--shp-muted); line-height: 1.55; }

            .shp-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }

            .shp-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border-radius: 999px;
                padding: 3px 10px;
                font-size: 11px;
                font-weight: 600;
                background: rgba(255, 255, 255, 0.72);
                border: 1px solid var(--shp-border);
                color: var(--shp-text);
                backdrop-filter: blur(6px);
            }

            html.dark .shp-pill,
            body.dark .shp-pill,
            .dark .shp-pill {
                background: rgba(2, 6, 23, .45);
            }

            .shp-dot { width: 7px; height: 7px; border-radius: 999px; }
            .shp-dot-pulse { animation: shp-pulse 1.8s ease-out infinite; }
            @keyframes shp-pulse {
                0% { transform: scale(1); opacity: 1; }
                55% { transform: scale(1.55); opacity: 0.55; }
                100% { transform: scale(1); opacity: 1; }
            }

            .shp-code {
                font-family: var(--shp-mono);
                background: rgba(15, 23, 42, 0.06);
                border-radius: 7px;
                padding: 2px 6px;
                border: 1px solid var(--shp-border);
                font-size: 11px;
                color: var(--shp-text);
            }

            html.dark .shp-code,
            body.dark .shp-code,
            .dark .shp-code {
                background: rgba(148, 163, 184, .12);
            }

            .shp-actions { display: flex; flex-direction: row; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }

            .shp-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                border-radius: 999px;
                padding: 7px 12px;
                font-size: 12px;
                font-weight: 700;
                border: 1px solid var(--shp-border);
                cursor: pointer;
                transition: transform .12s ease, box-shadow .12s ease, background-color .12s ease;
                white-space: nowrap;
                user-select: none;
            }

            .shp-btn:focus-visible { outline: 2px solid var(--shp-primary); outline-offset: 2px; }

            .shp-btn-ghost { background: rgba(255,255,255,.75); color: var(--shp-text); }
            .shp-btn-ghost:hover { background: rgba(255,255,255,.92); transform: translateY(-1px); box-shadow: var(--shp-shadow-soft); }

            html.dark .shp-btn-ghost,
            body.dark .shp-btn-ghost,
            .dark .shp-btn-ghost { background: rgba(2,6,23,.42); }
            html.dark .shp-btn-ghost:hover,
            body.dark .shp-btn-ghost:hover,
            .dark .shp-btn-ghost:hover { background: rgba(2,6,23,.60); }

            .shp-btn-primary {
                border: none;
                background: linear-gradient(90deg, var(--shp-success), #16a34a);
                color: #032018;
                box-shadow: 0 14px 34px rgba(22,163,74,.30);
            }
            .shp-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 18px 44px rgba(22,163,74,.38); }

            .shp-btn-warn {
                border: none;
                background: linear-gradient(90deg, #fbbf24, var(--shp-warn));
                color: #2a1601;
                box-shadow: 0 14px 34px rgba(245,158,11,.28);
            }
            .shp-btn-warn:hover { transform: translateY(-1px); box-shadow: 0 18px 44px rgba(245,158,11,.36); }

            .shp-btn-mini {
                padding: 6px 10px;
                font-size: 11px;
                font-weight: 700;
            }

            /* Main grid */
            .shp-grid { display: grid; grid-template-columns: 1fr; gap: 14px; }
            @media (min-width: 1040px) { .shp-grid { grid-template-columns: 2.05fr 1fr; } }

            .shp-card {
                border-radius: var(--shp-r);
                background: var(--shp-panel);
                border: 1px solid var(--shp-border);
                box-shadow: var(--shp-shadow-soft);
                padding: 14px 14px;
                backdrop-filter: blur(6px);
            }

            .shp-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 10px;
                margin-bottom: 10px;
            }

            .shp-card-title { font-size: 13px; font-weight: 800; }
            .shp-card-sub { font-size: 12px; color: var(--shp-muted); line-height: 1.45; margin-top: 2px; }

            /* Status */
            .shp-status-top { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; text-align: right; }
            .shp-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 4px 10px;
                border-radius: 999px;
                border: 1px solid var(--shp-border);
                background: rgba(255,255,255,.70);
                font-size: 11px;
                font-weight: 800;
                backdrop-filter: blur(6px);
            }
            html.dark .shp-status-pill, body.dark .shp-status-pill, .dark .shp-status-pill { background: rgba(2,6,23,.42); }

            .shp-progressbar { width: 100%; height: 9px; border-radius: 999px; background: rgba(148,163,184,.22); overflow: hidden; margin-top: 8px; }
            .shp-progress-inner { height: 9px; border-radius: 999px; background: var(--shp-progress); width: 0; transition: width .42s ease-out; }

            .shp-meta-row {
                display: flex;
                justify-content: space-between;
                gap: 10px;
                align-items: center;
                margin-top: 8px;
                font-size: 11px;
                color: var(--shp-muted);
            }

            .shp-meta-row strong { color: var(--shp-text); font-variant-numeric: tabular-nums; }

            .shp-kv {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                margin-top: 10px;
            }

            .shp-kv-item {
                border-radius: var(--shp-r2);
                border: 1px solid var(--shp-border);
                background: rgba(255,255,255,.62);
                padding: 8px 10px;
            }
            html.dark .shp-kv-item, body.dark .shp-kv-item, .dark .shp-kv-item { background: rgba(2,6,23,.38); }

            .shp-kv-label { font-size: 10px; text-transform: uppercase; letter-spacing: .10em; color: rgba(100,116,139,.95); }
            html.dark .shp-kv-label, body.dark .shp-kv-label, .dark .shp-kv-label { color: rgba(226,232,240,.62); }

            .shp-kv-val { margin-top: 4px; font-size: 12px; font-weight: 900; font-variant-numeric: tabular-nums; color: var(--shp-text); }
            .shp-kv-sub { margin-top: 2px; font-size: 11px; color: var(--shp-muted); font-family: var(--shp-mono); }

            /* Alerts */
            .shp-alert {
                margin-top: 10px;
                border-radius: var(--shp-r);
                padding: 10px 12px;
                border: 1px solid var(--shp-border);
                background: rgba(255,255,255,.72);
            }
            html.dark .shp-alert, body.dark .shp-alert, .dark .shp-alert { background: rgba(2,6,23,.45); }

            .shp-alert-err { border-color: rgba(239,68,68,.35); background: rgba(254,242,242,.78); }
            html.dark .shp-alert-err, body.dark .shp-alert-err, .dark .shp-alert-err { background: rgba(239,68,68,.12); }

            .shp-alert-title { font-size: 12px; font-weight: 900; }
            .shp-alert-body { margin-top: 6px; font-size: 11px; color: var(--shp-muted); line-height: 1.45; }
            .shp-trace {
                margin-top: 10px;
                border-radius: var(--shp-r2);
                background: var(--shp-console-bg);
                color: #e5e7eb;
                padding: 10px 12px;
                font-size: 11px;
                font-family: var(--shp-mono);
                white-space: pre-wrap;
                max-height: 240px;
                overflow: auto;
                border: 1px solid rgba(148,163,184,.28);
            }

            /* Console */
            .shp-console-wrap { margin-top: 10px; display: flex; flex-direction: column; gap: 6px; }
            .shp-console-head { display:flex; align-items:center; justify-content:space-between; gap: 10px; font-size: 11px; color: var(--shp-muted); }
            .shp-console-title { font-weight: 900; color: var(--shp-text); }

            .shp-console-tools { display:flex; gap: 8px; align-items:center; }
            .shp-linkbtn {
                border: none;
                background: none;
                padding: 0;
                cursor: pointer;
                font-size: 11px;
                font-weight: 800;
                color: var(--shp-muted);
            }
            .shp-linkbtn:hover { color: var(--shp-text); text-decoration: underline; }

            .shp-console {
                margin-top: 2px;
                border-radius: var(--shp-r);
                background: var(--shp-console-bg);
                padding: 10px 10px;
                font-size: 11px;
                font-family: var(--shp-mono);
                color: var(--shp-console-fg);
                max-height: 240px;
                overflow: auto;
                border: 1px solid rgba(148,163,184,.28);
            }

            .shp-console-line { display:flex; gap: 8px; padding: 3px 0; white-space: pre-wrap; }
            .shp-console-time { color: rgba(148,163,184,.75); min-width: 64px; }
            .shp-console-dot { width: 7px; height: 7px; border-radius: 999px; margin-top: 5px; flex: 0 0 auto; }
            .shp-console-msg { flex: 1 1 auto; }

            .shp-console-empty { color: rgba(148,163,184,.80); }

            /* Recent list */
            .shp-recent { margin-top: 12px; border-top: 1px solid var(--shp-border); padding-top: 12px; }
            .shp-recent-head { display:flex; align-items:center; justify-content:space-between; gap: 10px; margin-bottom: 8px; }
            .shp-recent-title { font-size: 11px; font-weight: 900; color: var(--shp-text); }
            .shp-recent-note { font-size: 10px; color: var(--shp-muted); font-family: var(--shp-mono); }

            .shp-recent-list { display:flex; flex-direction:column; gap: 8px; }
            .shp-recent-item {
                border-radius: var(--shp-r);
                border: 1px solid var(--shp-border);
                background: rgba(255,255,255,.72);
                padding: 10px 10px;
            }
            html.dark .shp-recent-item, body.dark .shp-recent-item, .dark .shp-recent-item { background: rgba(2,6,23,.40); }

            .shp-row { display:flex; align-items:flex-start; justify-content:space-between; gap: 10px; }
            .shp-name { font-weight: 900; font-size: 12px; line-height: 1.2; color: var(--shp-text); }
            .shp-handle { margin-top: 4px; font-size: 11px; color: var(--shp-muted); font-family: var(--shp-mono); overflow:hidden; text-overflow: ellipsis; }

            .shp-meta {
                margin-top: 8px;
                font-size: 11px;
                color: var(--shp-muted);
                line-height: 1.45;
            }

            .shp-recent-img-wrap {
                width: 52px;
                height: 52px;
                border-radius: 13px;
                overflow: hidden;
                flex: 0 0 auto;
                border: 1px solid rgba(148,163,184,.35);
                background: rgba(148,163,184,.08);
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .shp-recent-img-wrap img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .shp-meta-tags {
                margin-top: 3px;
                font-size: 10px;
                font-family: var(--shp-mono);
                opacity: .8;
            }

            /* Chips */
            .shp-chip {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 3px 10px;
                border-radius: 999px;
                border: 1px solid var(--shp-border);
                font-size: 11px;
                font-weight: 900;
                background: rgba(255,255,255,.68);
            }
            html.dark .shp-chip, body.dark .shp-chip, .dark .shp-chip { background: rgba(2,6,23,.45); }

            .shp-chip .shp-dot { width: 7px; height: 7px; }
            .shp-chip-create { border-color: rgba(34,197,94,.35); }
            .shp-chip-update { border-color: rgba(14,165,233,.35); }
            .shp-chip-skip { border-color: rgba(245,158,11,.35); }
            .shp-chip-error { border-color: rgba(239,68,68,.35); }

            /* Sections below (parse/plan/import result) */
            .shp-section {
                border-radius: var(--shp-r);
                background: var(--shp-panel);
                border: 1px solid var(--shp-border);
                box-shadow: var(--shp-shadow-soft);
                padding: 14px 14px 16px;
                backdrop-filter: blur(6px);
            }

            .shp-section-head { display:flex; align-items:flex-end; justify-content:space-between; gap: 12px; margin-bottom: 10px; }
            .shp-section-title { font-size: 13px; font-weight: 900; }
            .shp-section-sub { font-size: 11px; color: var(--shp-muted); margin-top: 2px; }

            .shp-tiles {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin-top: 6px;
            }
            @media (min-width: 820px) { .shp-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
            @media (min-width: 1180px) { .shp-tiles { grid-template-columns: repeat(6, minmax(0, 1fr)); } }

            .shp-tile {
                border-radius: var(--shp-r2);
                border: 1px solid var(--shp-border);
                background: rgba(255,255,255,.62);
                padding: 9px 10px;
            }
            html.dark .shp-tile, body.dark .shp-tile, .dark .shp-tile { background: rgba(2,6,23,.38); }

            .shp-tile-l { font-size: 10px; text-transform: uppercase; letter-spacing: .10em; color: rgba(100,116,139,.95); }
            html.dark .shp-tile-l, body.dark .shp-tile-l, .dark .shp-tile-l { color: rgba(226,232,240,.62); }

            .shp-tile-v { margin-top: 5px; font-size: 18px; font-weight: 950; font-variant-numeric: tabular-nums; }

            /* Table */
            .shp-table-wrap { margin-top: 10px; border-radius: var(--shp-r); border: 1px solid var(--shp-border); overflow: hidden; }
            .shp-table { width: 100%; border-collapse: collapse; font-size: 12px; }
            .shp-table th, .shp-table td { padding: 8px 10px; border-bottom: 1px solid rgba(148,163,184,.20); vertical-align: top; }
            .shp-table thead { background: rgba(148,163,184,.12); }
            .shp-table th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: var(--shp-muted); }
            .shp-table tbody tr:hover { background: rgba(148,163,184,.10); }
            .shp-mono { font-family: var(--shp-mono); }

            .shp-mini { font-size: 11px; color: var(--shp-muted); }
            .shp-details-summary { display:inline-flex; align-items:center; gap: 8px; cursor:pointer; font-size: 12px; color: var(--shp-muted); }
            .shp-details-summary:hover { color: var(--shp-text); }
            .shp-pre {
                margin-top: 10px;
                border-radius: var(--shp-r);
                background: var(--shp-console-bg);
                color: #e5e7eb;
                padding: 12px 12px;
                font-size: 11px;
                font-family: var(--shp-mono);
                white-space: pre-wrap;
                max-height: 340px;
                overflow: auto;
                border: 1px solid rgba(148,163,184,.28);
            }

            /* Modals */
            .shp-backdrop {
                position: fixed;
                inset: 0;
                z-index: 60;
                background: rgba(15,23,42,.55);
                backdrop-filter: blur(6px);
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 18px;
            }
            html.dark .shp-backdrop, body.dark .shp-backdrop, .dark .shp-backdrop { background: rgba(0,0,0,.62); }

            .shp-modal {
                width: 100%;
                max-width: 520px;
                border-radius: 18px;
                background: rgba(255,255,255,.92);
                border: 1px solid var(--shp-border);
                box-shadow: var(--shp-shadow);
                padding: 14px 14px;
            }
            html.dark .shp-modal, body.dark .shp-modal, .dark .shp-modal { background: rgba(2,6,23,.82); }

            .shp-modal-title { font-size: 14px; font-weight: 950; }
            .shp-modal-body { margin-top: 6px; font-size: 12px; color: var(--shp-muted); line-height: 1.55; }

            .shp-modal-callout {
                margin-top: 10px;
                border-radius: var(--shp-r);
                border: 1px solid var(--shp-border);
                background: rgba(14,165,233,.10);
                padding: 10px 10px;
                font-size: 11px;
                color: var(--shp-text);
            }

            .shp-modal-kvs { margin-top: 10px; display:grid; grid-template-columns: 1fr 1fr; gap: 8px; }
            .shp-modal-kv { border-radius: var(--shp-r2); border: 1px solid var(--shp-border); padding: 8px 10px; background: rgba(255,255,255,.72); }
            html.dark .shp-modal-kv, body.dark .shp-modal-kv, .dark .shp-modal-kv { background: rgba(2,6,23,.45); }

            .shp-modal-k { font-size: 10px; letter-spacing: .10em; text-transform: uppercase; color: rgba(100,116,139,.95); }
            html.dark .shp-modal-k, body.dark .shp-modal-k, .dark .shp-modal-k { color: rgba(226,232,240,.62); }
            .shp-modal-v { margin-top: 4px; font-size: 12px; font-weight: 900; font-family: var(--shp-mono); }

            .shp-modal-footer { margin-top: 12px; display:flex; justify-content:flex-end; gap: 8px; flex-wrap: wrap; }
        </style>
    @endpush

    <div
        id="top"
        wire:poll.1500ms="refreshProgress"
        x-data="shopifyImportPage({
            progress: @entangle('importProgress'),
            consoleLines: @entangle('importConsole'),
            recentProducts: @entangle('recentProducts'),
            importResult: @entangle('importResult'),
            runId: @entangle('currentRunId'),
            batchId: @entangle('currentBatchId'),
        })"
        class="shp-root"
    >
        {{-- HERO --}}
        <section class="shp-hero">
            <div class="shp-hero-inner">
                <div class="shp-hero-main">
                    <div class="shp-title-row">
                        <div class="shp-title">Shopify CSV → Stripe Import</div>

                        <div class="shp-pill">
                            <span class="shp-dot shp-dot-pulse" :style="{ background: statusDotColor() }"></span>
                            <span x-text="statusLabel()"></span>
                        </div>

                        <template x-if="runId">
                            <button type="button" class="shp-pill" @click="copy(runId, 'Run id kopiert')">
                                <span class="shp-dot" style="background: var(--shp-primary)"></span>
                                Run: <span class="shp-code" x-text="runId"></span>
                                <span style="opacity:.75;">(copy)</span>
                            </button>
                        </template>

                        <template x-if="progress?.batch_id">
                            <button type="button" class="shp-pill" @click="copy(progress.batch_id, 'Batch id kopiert')">
                                <span class="shp-dot" style="background: var(--shp-primary-2)"></span>
                                Batch: <span class="shp-code" x-text="progress.batch_id"></span>
                                <span style="opacity:.75;">(copy)</span>
                            </button>
                        </template>
                    </div>

                    <div class="shp-subtitle">
                        Last opp Shopify CSV, velg Stripe-konto og importer produkter + priser (variantbasert).
                        <strong>Ingen bilder lagres lokalt</strong> – vi sender kun validerte HTTPS-bilde-URLer til Stripe (valgfritt).
                    </div>

                    <div class="shp-pills">
                        <div class="shp-pill">
                            <span class="shp-dot" style="background: rgba(99,102,241,.95)"></span>
                            Queue: <span class="shp-code" x-text="progress?.queue || 'shopify-import'"></span>
                        </div>

                        <div class="shp-pill">
                            <span class="shp-dot" style="background: rgba(14,165,233,.95)"></span>
                            Currency: <span class="shp-code" x-text="(progress?.currency || 'nok').toUpperCase()"></span>
                        </div>

                        <div class="shp-pill">
                            <span class="shp-dot" :style="{ background: (progress?.include_images ? 'var(--shp-success)' : 'var(--shp-warn)') }"></span>
                            Images: <span class="shp-code" x-text="progress?.include_images ? 'ON (URLs)' : 'OFF'"></span>
                        </div>

                        <div class="shp-pill">
                            <span class="shp-dot" :style="{ background: (progress?.strict_image_check ? 'var(--shp-success)' : 'var(--shp-warn)') }"></span>
                            Strict: <span class="shp-code" x-text="progress?.strict_image_check ? 'ON' : 'OFF'"></span>
                        </div>

                        <div class="shp-pill">
                            <span class="shp-dot" :style="{ background: (progress?.update_existing ? 'var(--shp-success)' : 'var(--shp-warn)') }"></span>
                            Update existing: <span class="shp-code" x-text="progress?.update_existing ? 'ON' : 'OFF'"></span>
                        </div>

                        <div class="shp-pill">
                            <span class="shp-dot" style="background: rgba(148,163,184,.95)"></span>
                            Chunk: <span class="shp-code" x-text="String(progress?.chunk_size ?? 10)"></span>
                        </div>
                    </div>
                </div>

                <div class="shp-actions">
                    <button type="button" class="shp-btn shp-btn-ghost" @click="openParseModal()">
                        <span>🔎</span> Analyser CSV
                    </button>
                    <button type="button" class="shp-btn shp-btn-primary" @click="openImportModal()">
                        <span>⚡</span> Kjør import
                    </button>
                    <button type="button" class="shp-btn shp-btn-ghost shp-btn-mini" @click="scrollTo('shp-section-plan')">
                        Plan
                    </button>
                    <button type="button" class="shp-btn shp-btn-ghost shp-btn-mini" @click="scrollTo('shp-section-result')">
                        Resultat
                    </button>
                </div>
            </div>
        </section>

        {{-- FORM + STATUS --}}
        <section class="shp-grid">
            {{-- LEFT: FORM --}}
            <div class="shp-card">
                <div class="shp-card-header">
                    <div>
                        <div class="shp-card-title">CSV + Stripe-oppsett</div>
                        <div class="shp-card-sub">
                            Eksporter fra <span class="shp-code">Shopify → Products → Export as CSV</span>.
                            Last opp filen, fyll inn Stripe account id (<span class="shp-code">acct_…</span>) og velg toggles.
                        </div>
                    </div>

                    <div class="shp-actions">
                        <button type="button" class="shp-btn shp-btn-ghost shp-btn-mini" @click="copy(workerCmd(), 'Kopierte worker-kommando')">
                            Copy worker cmd
                        </button>
                    </div>
                </div>

                <div>
                    {{ $this->form }}

                    <div class="shp-alert" style="margin-top: 12px;">
                        <div class="shp-alert-title">Worker</div>
                        <div class="shp-alert-body">
                            Importen kjører i kø. Hvis “Kjører…” står lenge uten endring: start/verify worker på serveren.
                        </div>
                        <div class="shp-modal-kvs" style="margin-top: 10px;">
                            <div class="shp-modal-kv">
                                <div class="shp-modal-k">Queue</div>
                                <div class="shp-modal-v" x-text="progress?.queue || 'shopify-import'"></div>
                            </div>
                            <div class="shp-modal-kv">
                                <div class="shp-modal-k">Command</div>
                                <div class="shp-modal-v" style="font-size: 11px;" x-text="workerCmd()"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: STATUS + CONSOLE + RECENT --}}
            <div class="shp-card">
                <div class="shp-card-header">
                    <div>
                        <div class="shp-card-title">Importstatus</div>
                        <div class="shp-card-sub">Polling cache. Viser fremdrift, rate og ETA.</div>
                    </div>

                    <div class="shp-status-top">
                        <div class="shp-status-pill" :style="{ borderColor: statusBorderColor() }">
                            <span class="shp-dot shp-dot-pulse" :style="{ background: statusDotColor() }"></span>
                            <span x-text="statusLabel()"></span>
                        </div>

                        <div class="shp-mini shp-mono">
                            <span x-text="progress?.current ?? 0"></span> /
                            <span x-text="progress?.total ?? 0"></span>
                        </div>
                    </div>
                </div>

                <div class="shp-progressbar">
                    <div class="shp-progress-inner" :style="`width:${clampPercent(progress?.percent ?? 0)}%`"></div>
                </div>

                <div class="shp-meta-row">
                    <span class="shp-mono"><strong x-text="clampPercent(progress?.percent ?? 0)"></strong>%</span>
                    <span>
                        Created <strong x-text="progress?.created ?? 0"></strong> ·
                        Updated <strong x-text="progress?.updated ?? 0"></strong> ·
                        Skipped <strong x-text="progress?.skipped ?? 0"></strong> ·
                        Errors <strong :style="{ color: (progress?.errors ?? 0) ? 'var(--shp-danger)' : 'inherit' }" x-text="progress?.errors ?? 0"></strong>
                    </span>
                </div>

                <div class="shp-kv">
                    <div class="shp-kv-item">
                        <div class="shp-kv-label">Rate</div>
                        <div class="shp-kv-val">
                            <span x-text="progress?.rate_per_min ?? 0"></span><span style="font-size:11px; opacity:.8;"> / min</span>
                        </div>
                        <div class="shp-kv-sub" x-text="progress?.started_at ? ('started ' + progress.started_at) : '—'"></div>
                    </div>

                    <div class="shp-kv-item">
                        <div class="shp-kv-label">ETA</div>
                        <div class="shp-kv-val" x-text="formatEta(progress?.eta_seconds)"></div>
                        <div class="shp-kv-sub" x-text="progress?.last_tick_at ? ('last tick ' + progress.last_tick_at) : '—'"></div>
                    </div>

                    <div class="shp-kv-item">
                        <div class="shp-kv-label">Images</div>
                        <div class="shp-kv-val">
                            <span x-text="progress?.images_valid ?? 0"></span>
                            <span style="opacity:.65;">/</span>
                            <span x-text="progress?.images_total ?? 0"></span>
                        </div>
                        <div class="shp-kv-sub">
                            invalid <span x-text="progress?.images_invalid ?? 0"></span>
                        </div>
                    </div>

                    <div class="shp-kv-item">
                        <div class="shp-kv-label">Variants / Prices</div>
                        <div class="shp-kv-val">
                            <span x-text="progress?.variants_ok ?? 0"></span>
                            <span style="opacity:.65;">/</span>
                            <span x-text="progress?.variants_total ?? 0"></span>
                        </div>
                        <div class="shp-kv-sub">
                            +<span x-text="progress?.prices_created ?? 0"></span>
                            ~<span x-text="progress?.prices_reused ?? 0"></span>
                            ↻<span x-text="progress?.prices_replaced ?? 0"></span>
                        </div>
                    </div>
                </div>

                {{-- Last error diagnostics --}}
                <template x-if="importResult && importResult.last_error">
                    <div class="shp-alert shp-alert-err">
                        <div class="shp-alert-title">Last error</div>
                        <div class="shp-alert-body">
                            <div style="font-weight:900; color: var(--shp-text);" x-text="importResult.last_error.message"></div>
                            <div class="shp-mini shp-mono" style="margin-top: 6px;">
                                <span x-text="importResult.last_error.at"></span>
                                <template x-if="importResult.last_error.exception && importResult.last_error.exception.class">
                                    <span> · <span x-text="importResult.last_error.exception.class"></span></span>
                                </template>
                                <template x-if="importResult.last_error.exception && importResult.last_error.exception.file">
                                    <span> · <span x-text="importResult.last_error.exception.file"></span>:<span x-text="importResult.last_error.exception.line"></span></span>
                                </template>
                            </div>

                            <template x-if="importResult.last_error.exception && importResult.last_error.exception.trace_head">
                                <div class="shp-trace" x-text="importResult.last_error.exception.trace_head"></div>
                            </template>
                        </div>
                    </div>
                </template>

                {{-- Console --}}
                <div class="shp-console-wrap">
                    <div class="shp-console-head">
                        <div class="shp-console-title">Import-konsoll</div>
                        <div class="shp-console-tools">
                            <button type="button" class="shp-linkbtn" @click="scrollConsoleBottom()">Bottom</button>
                            <button type="button" class="shp-linkbtn" wire:click="clearConsole" @click="clearConsoleLocal()">Tøm</button>
                        </div>
                    </div>

                    <div x-ref="console" class="shp-console">
                        <template x-if="(consoleLines?.length ?? 0) === 0">
                            <div class="shp-console-empty">
                                Venter på output… Kjør <strong>Analyser CSV</strong> eller <strong>Kjør import</strong>.
                            </div>
                        </template>

                        <template x-for="(line, idx) in (consoleLines || [])" :key="idx">
                            <div class="shp-console-line">
                                <span class="shp-console-time" x-text="line.time ?? ''"></span>
                                <span class="shp-console-dot" :style="{ background: consoleDot(line.level) }"></span>
                                <span class="shp-console-msg" x-text="line.message ?? line"></span>
                            </div>
                        </template>
                    </div>

                    <div class="shp-mini">
                        Protip: Hvis den stopper: sjekk Horizon/Forge worker + <span class="shp-code">storage/logs/laravel.log</span>.
                    </div>
                </div>

                {{-- Recent --}}
                <div class="shp-recent">
                    <div class="shp-recent-head">
                        <div class="shp-recent-title">Latest products</div>
                        <div class="shp-recent-note">
                            showing <span x-text="Math.min(10, (recentProducts?.length ?? 0))"></span> / <span x-text="(recentProducts?.length ?? 0)"></span>
                        </div>
                    </div>

                    <div class="shp-recent-list">
                        <template x-if="(recentProducts?.length ?? 0) === 0">
                            <div class="shp-recent-item">
                                <div class="shp-name">Ingen produkter behandlet ennå</div>
                                <div class="shp-meta">Når jobber kjører, dukker mini-previews opp her.</div>
                            </div>
                        </template>

                        {{-- Live stream of last 10, newest first --}}
                        <template x-for="(p, idx) in (recentProducts || []).slice(-10).reverse()" :key="idx">
                            <div class="shp-recent-item">
                                <div class="shp-row">
                                    {{-- LEFT: Image + meta --}}
                                    <div style="display:flex; gap:10px; min-width:0; flex: 1 1 auto;">
                                        <template x-if="p.image_url">
                                            <div class="shp-recent-img-wrap">
                                                <img :src="p.image_url" alt="" loading="lazy">
                                            </div>
                                        </template>

                                        <div style="min-width:0; flex:1 1 auto;">
                                            <div class="shp-name" x-text="p.title ?? '—'"></div>
                                            <div class="shp-handle" x-text="p.handle ?? ''"></div>

                                            <div class="shp-meta">
                                                <div>
                                                    <strong x-text="p.vendor || '—'"></strong>
                                                    <span style="opacity:.7;"> · </span>
                                                    <span x-text="p.type || '—'"></span>
                                                </div>
                                                <template x-if="p.tags">
                                                    <div class="shp-meta-tags" x-text="p.tags"></div>
                                                </template>
                                                <div class="shp-mini">
                                                    variants: <strong x-text="p.variant_count ?? 0"></strong>
                                                    · images: <strong x-text="p.image_count ?? 0"></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- RIGHT: status chip --}}
                                    <div style="flex: 0 0 auto;">
                                        <span class="shp-chip"
                                              :class="{
                                                'shp-chip-create': (p.status === 'created'),
                                                'shp-chip-update': (p.status === 'updated'),
                                                'shp-chip-skip': (p.status === 'skipped'),
                                                'shp-chip-error': (p.status === 'error'),
                                              }"
                                        >
                                            <span class="shp-dot" :style="{ background: statusChipDot(p.status) }"></span>
                                            <span x-text="String((p.status ?? 'unknown')).toUpperCase()"></span>
                                        </span>
                                    </div>
                                </div>

                                <div class="shp-meta">
                                    <template x-if="p.message">
                                        <span x-text="p.message"></span>
                                    </template>
                                    <template x-if="!p.message">
                                        <span x-text="p.at || ''"></span>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        {{-- PARSE OVERVIEW --}}
        @if ($parseResult)
            @php
                $stats          = $parseResult['stats'] ?? [];
                $products       = $parseResult['products'] ?? [];
                $sampleProducts = array_slice($products, 0, 10);
            @endphp

            <section class="shp-section" id="shp-section-parse">
                <div class="shp-section-head">
                    <div>
                        <div class="shp-section-title">Analyse – oversikt</div>
                        <div class="shp-section-sub">High-level view av Shopify CSV. Ingen endringer skjer i Stripe i dette steget.</div>
                    </div>
                    <div class="shp-actions">
                        <button type="button" class="shp-btn shp-btn-ghost shp-btn-mini" @click="scrollTo('top')">↑ Top</button>
                    </div>
                </div>

                @php
                    $tiles = [
                        ['label' => 'Produkter',               'value' => $stats['total_products'] ?? ($parseResult['total_products'] ?? 0)],
                        ['label' => 'Varianter',               'value' => $stats['total_variants'] ?? ($parseResult['total_variants'] ?? 0)],
                        ['label' => 'Bilder',                  'value' => $stats['total_images'] ?? 0],
                        ['label' => 'Duplikat handles',        'value' => $stats['duplicate_handles'] ?? 0],
                        ['label' => 'Manglende handle',        'value' => $stats['missing_handle'] ?? 0],
                        ['label' => 'Varianter uten pris',     'value' => $stats['variants_missing_price'] ?? 0],
                    ];
                @endphp

                <div class="shp-tiles">
                    @foreach ($tiles as $tile)
                        <div class="shp-tile">
                            <div class="shp-tile-l">{{ $tile['label'] }}</div>
                            <div class="shp-tile-v">{{ $tile['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if (! empty($sampleProducts))
                    <div style="margin-top: 12px;">
                        <div class="shp-section-head">
                            <div>
                                <div class="shp-section-title" style="font-size: 12px;">Eksempelprodukter (første {{ count($sampleProducts) }})</div>
                                <div class="shp-section-sub">Sjekk titler, handles, pris-intervall og bildekonto før import.</div>
                            </div>
                        </div>

                        <div class="shp-table-wrap">
                            <table class="shp-table">
                                <thead>
                                <tr>
                                    <th>Tittel</th>
                                    <th>Handle</th>
                                    <th style="text-align:right;">Var</th>
                                    <th style="text-align:right;">Min</th>
                                    <th style="text-align:right;">Maks</th>
                                    <th style="text-align:right;">Img</th>
                                    <th>Vendor / type</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($sampleProducts as $prod)
                                    @php
                                        $variantCount = $prod['variant_count'] ?? count($prod['variants'] ?? []);
                                        $min          = $prod['variant_min_price'] ?? null;
                                        $max          = $prod['variant_max_price'] ?? null;
                                        $imgs         = is_array($prod['images'] ?? null) ? count($prod['images']) : 0;
                                        $tags         = (string) ($prod['tags'] ?? '');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div style="font-weight: 900;">{{ $prod['title'] ?? 'N/A' }}</div>
                                            <div class="shp-mini">{{ \Illuminate\Support\Str::limit($tags, 80) }}</div>
                                        </td>
                                        <td class="shp-mono">{{ $prod['handle'] ?? '' }}</td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">{{ (int) $variantCount }}</td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            @if ($min !== null) {{ number_format((float) $min, 2, ',', ' ') }} @else &mdash; @endif
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            @if ($max !== null) {{ number_format((float) $max, 2, ',', ' ') }} @else &mdash; @endif
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">{{ (int) $imgs }}</td>
                                        <td class="shp-mini">
                                            <div>{{ $prod['vendor'] ?? '—' }}</div>
                                            <div class="shp-mono" style="opacity:.85;">{{ $prod['type'] ?? '—' }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <details style="margin-top: 12px;">
                    <summary class="shp-details-summary">Raw analyse-payload (debug)</summary>
                    <pre class="shp-pre">{{ json_encode($parseResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </section>
        @endif

        {{-- IMPORT PLAN --}}
        @if ($planResult)
            @php
                $planItems  = $planResult['items'] ?? [];
                $planSample = array_slice($planItems, 0, 18);
                $pstats = $parseResult['stats'] ?? [];
            @endphp

            <section class="shp-section" id="shp-section-plan">
                <div class="shp-section-head">
                    <div>
                        <div class="shp-section-title">Importplan</div>
                        <div class="shp-section-sub">Dette er hva som vil skje (create / update / skip) basert på handle + stripe_account_id.</div>
                    </div>
                </div>

                <div class="shp-tiles">
                    <div class="shp-tile"><div class="shp-tile-l">Total</div><div class="shp-tile-v">{{ (int)($planResult['total_products'] ?? 0) }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Nye</div><div class="shp-tile-v">{{ (int)($planResult['new'] ?? 0) }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Eksisterende</div><div class="shp-tile-v">{{ (int)($planResult['existing'] ?? 0) }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Ville oppdatert</div><div class="shp-tile-v">{{ (int)($planResult['would_update'] ?? 0) }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Ville skippet</div><div class="shp-tile-v">{{ (int)($planResult['will_skip'] ?? 0) }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Bilder (CSV)</div><div class="shp-tile-v">{{ (int)($pstats['total_images'] ?? 0) }}</div></div>
                </div>

                @if (!empty($planSample))
                    <div style="margin-top: 12px;">
                        <div class="shp-section-head">
                            <div>
                                <div class="shp-section-title" style="font-size: 12px;">Plan preview (første {{ count($planSample) }})</div>
                                <div class="shp-section-sub">“Diff” er best-effort (title/variants/images).</div>
                            </div>
                        </div>

                        <div class="shp-table-wrap">
                            <table class="shp-table">
                                <thead>
                                <tr>
                                    <th>Tittel</th>
                                    <th>Handle</th>
                                    <th>Plan</th>
                                    <th style="text-align:right;">Var</th>
                                    <th style="text-align:right;">Img</th>
                                    <th>Diff</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($planSample as $row)
                                    @php
                                        $action = (string)($row['action'] ?? 'skip');
                                        $chipClass =
                                            $action === 'create' ? 'shp-chip shp-chip-create' :
                                            ($action === 'update' ? 'shp-chip shp-chip-update' :
                                                'shp-chip shp-chip-skip');
                                        $diffs = (array)($row['diffs'] ?? []);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div style="font-weight: 900;">{{ $row['title'] ?? '—' }}</div>
                                            <div class="shp-mini">{{ $row['vendor'] ?? '—' }} · {{ $row['type'] ?? '—' }}</div>
                                        </td>
                                        <td class="shp-mono">{{ $row['handle'] ?? '' }}</td>
                                        <td>
                                            <span class="{{ $chipClass }}">
                                                <span class="shp-dot" style="background:
                                                    {{ $action === 'create' ? 'rgba(34,197,94,.95)' : ($action === 'update' ? 'rgba(14,165,233,.95)' : 'rgba(245,158,11,.95)') }}
                                                "></span>
                                                <span>{{ strtoupper($action) }}</span>
                                            </span>
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">{{ (int)($row['variant_count'] ?? 0) }}</td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">{{ (int)($row['image_count'] ?? 0) }}</td>
                                        <td class="shp-mini">{{ !empty($diffs) ? implode(', ', $diffs) : '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (! empty($planResult['existing_items']))
                    <details style="margin-top: 12px;">
                        <summary class="shp-details-summary">Eksisterende produkter (DB match på handle)</summary>
                        <div class="shp-table-wrap" style="margin-top: 10px;">
                            <table class="shp-table">
                                <thead>
                                <tr>
                                    <th>Handle</th>
                                    <th>Tittel</th>
                                    <th>Stripe product</th>
                                    <th>Oppdatert</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach (array_slice($planResult['existing_items'], 0, 60) as $ex)
                                    <tr>
                                        <td class="shp-mono">{{ $ex['handle'] ?? '' }}</td>
                                        <td style="font-weight: 900;">{{ $ex['title'] ?? '—' }}</td>
                                        <td class="shp-mono">{{ $ex['stripe_product_id'] ?? '—' }}</td>
                                        <td class="shp-mini">{{ $ex['updated_at'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>
        @endif

        {{-- IMPORT SUMMARY --}}
        @if ($importResult)
            @php
                $istats     = data_get($importResult, 'stats.import', []);
                $created    = (int) data_get($istats, 'created', 0);
                $updated    = (int) data_get($istats, 'updated', 0);
                $skipped    = (int) data_get($istats, 'skipped', 0);
                $errCount   = (int) data_get($istats, 'error_count', 0);
                $total      = (int) data_get($istats, 'total_products', 0);
                $perProduct = $importResult['per_product'] ?? [];
            @endphp

            <section class="shp-section" id="shp-section-result">
                <div class="shp-section-head">
                    <div>
                        <div class="shp-section-title">Import – oppsummering</div>
                        <div class="shp-section-sub">Resultat fra siste kjøring (fra cache/batch).</div>
                    </div>
                </div>

                <div class="shp-tiles">
                    <div class="shp-tile"><div class="shp-tile-l">Created</div><div class="shp-tile-v">{{ $created }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Updated</div><div class="shp-tile-v">{{ $updated }}</div></div>
                    <div class="shp-tile"><div class="shp-tile-l">Skipped</div><div class="shp-tile-v">{{ $skipped }}</div></div>
                    <div class="shp-tile" style="border-color: rgba(239,68,68,.35); background: rgba(239,68,68,.10);">
                        <div class="shp-tile-l" style="color: rgba(239,68,68,.95);">Errors</div>
                        <div class="shp-tile-v" style="color: rgba(239,68,68,.95);">{{ $errCount }}</div>
                    </div>
                    <div class="shp-tile"><div class="shp-tile-l">Totalt</div><div class="shp-tile-v">{{ $total }}</div></div>
                </div>

                @if (! empty($perProduct))
                    <div style="margin-top: 12px;">
                        <div class="shp-section-head">
                            <div>
                                <div class="shp-section-title" style="font-size: 12px;">Resultat per produkt (første {{ min(30, count($perProduct)) }})</div>
                                <div class="shp-section-sub">created/updated/skipped/error + message (best-effort).</div>
                            </div>
                        </div>

                        <div class="shp-table-wrap">
                            <table class="shp-table">
                                <thead>
                                <tr>
                                    <th>Tittel</th>
                                    <th>Handle</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Var</th>
                                    <th style="text-align:right;">Img</th>
                                    <th>Melding</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach (array_slice($perProduct, 0, 30) as $item)
                                    @php
                                        $status = (string) data_get($item, 'status', 'unknown');
                                        $chipClass =
                                            $status === 'created' ? 'shp-chip shp-chip-create' :
                                            ($status === 'updated' ? 'shp-chip shp-chip-update' :
                                            ($status === 'skipped' ? 'shp-chip shp-chip-skip' :
                                            ($status === 'error'   ? 'shp-chip shp-chip-error' :
                                                                    'shp-chip')));
                                    @endphp
                                    <tr>
                                        <td><div style="font-weight: 900;">{{ data_get($item, 'title', 'N/A') }}</div></td>
                                        <td class="shp-mono">{{ data_get($item, 'handle', '') }}</td>
                                        <td>
                                            <span class="{{ $chipClass }}">
                                                <span class="shp-dot" style="background:
                                                    {{ $status === 'created' ? 'rgba(34,197,94,.95)' : ($status === 'updated' ? 'rgba(14,165,233,.95)' : ($status === 'skipped' ? 'rgba(245,158,11,.95)' : ($status === 'error' ? 'rgba(239,68,68,.95)' : 'rgba(148,163,184,.95)'))) }}
                                                "></span>
                                                <span>{{ strtoupper($status) }}</span>
                                            </span>
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">{{ (int) data_get($item, 'variant_count', 0) }}</td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">{{ (int) data_get($item, 'image_count', 0) }}</td>
                                        <td class="shp-mini">{{ data_get($item, 'message', '—') }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (! empty($importResult['errors']))
                    <div style="margin-top: 12px;">
                        <div class="shp-section-title" style="font-size: 12px; color: rgba(239,68,68,.95);">Første feil (maks 20)</div>
                        <ul style="margin-top: 6px; padding-left: 18px; font-size: 12px; color: var(--shp-muted); line-height: 1.55;">
                            @foreach (array_slice($importResult['errors'], 0, 20) as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <details style="margin-top: 12px;">
                    <summary class="shp-details-summary">Raw import-payload (debug)</summary>
                    <pre class="shp-pre">{{ json_encode($importResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </details>
            </section>
        @endif

        {{-- MODAL: ANALYSE CSV --}}
        <template x-if="showParse">
            <div class="shp-backdrop" @keydown.escape.window="closeParseModal()">
                <div class="shp-modal">
                    <div class="shp-modal-title">Analyser Shopify CSV</div>
                    <div class="shp-modal-body">
                        CSV leses, grupperes til produkter/varianter og plan genereres (create/update/skip).
                        Ingen Stripe-kall gjøres i dette steget.
                    </div>

                    <div class="shp-modal-callout">
                        Tips: Se etter <strong>duplikate handles</strong> og <strong>varianter uten pris</strong> før du importerer.
                    </div>

                    <div class="shp-modal-footer">
                        <button type="button" class="shp-btn shp-btn-ghost" @click="closeParseModal()">Avbryt</button>
                        <button
                            type="button"
                            class="shp-btn shp-btn-warn"
                            wire:click="parseCsv"
                            @click="closeParseModal(); pushConsoleLocal('Analyse startet…', 'info')"
                        >
                            Kjør analyse
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- MODAL: KJØR IMPORT --}}
        <template x-if="showImport">
            <div class="shp-backdrop" @keydown.escape.window="closeImportModal()">
                <div class="shp-modal">
                    <div class="shp-modal-title">Kjør import til Stripe</div>
                    <div class="shp-modal-body">
                        Importen dispatches som batch med chunk-jobs på dedikert queue.
                        Stripe Prices er immutable: ved prisendring opprettes ny Price og gammel settes inaktiv (best effort).
                    </div>

                    <div class="shp-modal-kvs">
                        <div class="shp-modal-kv">
                            <div class="shp-modal-k">Images</div>
                            <div class="shp-modal-v" x-text="progress?.include_images ? 'ON (URLs only)' : 'OFF'"></div>
                        </div>
                        <div class="shp-modal-kv">
                            <div class="shp-modal-k">Strict image check</div>
                            <div class="shp-modal-v" x-text="progress?.strict_image_check ? 'ON' : 'OFF'"></div>
                        </div>
                        <div class="shp-modal-kv">
                            <div class="shp-modal-k">Update existing</div>
                            <div class="shp-modal-v" x-text="progress?.update_existing ? 'ON' : 'OFF'"></div>
                        </div>
                        <div class="shp-modal-kv">
                            <div class="shp-modal-k">Chunk / Currency</div>
                            <div class="shp-modal-v">
                                <span x-text="String(progress?.chunk_size ?? 10)"></span>
                                ·
                                <span x-text="(progress?.currency || 'nok').toUpperCase()"></span>
                            </div>
                        </div>
                    </div>

                    <div class="shp-modal-callout">
                        <template x-if="progress?.include_images">
                            <div>✅ Bilder: vi sender <strong>kun validerte HTTPS-URLer</strong> til Stripe product.images.</div>
                        </template>
                        <template x-if="!progress?.include_images">
                            <div>⚠️ Bilder er avskrudd. Slå på <strong>Include product images</strong> i skjemaet hvis du vil sende URLer.</div>
                        </template>
                    </div>

                    <div class="shp-modal-footer">
                        <button type="button" class="shp-btn shp-btn-ghost" @click="closeImportModal()">Avbryt</button>
                        <button
                            type="button"
                            class="shp-btn shp-btn-primary"
                            wire:click="runImport"
                            @click="closeImportModal(); pushConsoleLocal('Import startet…', 'info')"
                        >
                            Start import
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @once
        @push('scripts')
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('shopifyImportPage', (payload) => ({
                        showParse: false,
                        showImport: false,

                        // Entangled state
                        progress: payload.progress,
                        consoleLines: payload.consoleLines || [],
                        recentProducts: payload.recentProducts || [],
                        importResult: payload.importResult || null,
                        runId: payload.runId || null,
                        batchId: payload.batchId || null,

                        openParseModal()  { this.showParse = true },
                        closeParseModal() { this.showParse = false },
                        openImportModal() { this.showImport = true },
                        closeImportModal(){ this.showImport = false },

                        clampPercent(v) {
                            const n = Number(v ?? 0);
                            if (Number.isNaN(n)) return 0;
                            return Math.max(0, Math.min(100, Math.round(n)));
                        },

                        statusLabel() {
                            switch (this.progress?.status) {
                                case 'pending':  return 'Klar';
                                case 'running':  return 'Kjører…';
                                case 'finished': return 'Ferdig';
                                case 'failed':   return 'Feilet';
                                default:         return 'Inaktiv';
                            }
                        },

                        statusDotColor() {
                            switch (this.progress?.status) {
                                case 'running':  return 'var(--shp-warn)';
                                case 'finished': return 'var(--shp-success)';
                                case 'failed':   return 'var(--shp-danger)';
                                case 'pending':  return 'var(--shp-primary)';
                                default:         return 'rgba(148,163,184,.95)';
                            }
                        },

                        statusBorderColor() {
                            switch (this.progress?.status) {
                                case 'running':  return 'rgba(245,158,11,.35)';
                                case 'finished': return 'rgba(34,197,94,.35)';
                                case 'failed':   return 'rgba(239,68,68,.35)';
                                case 'pending':  return 'rgba(14,165,233,.35)';
                                default:         return 'rgba(148,163,184,.35)';
                            }
                        },

                        statusChipDot(status) {
                            switch (status) {
                                case 'created': return 'var(--shp-success)';
                                case 'updated': return 'var(--shp-primary)';
                                case 'skipped': return 'var(--shp-warn)';
                                case 'error':   return 'var(--shp-danger)';
                                default:        return 'rgba(148,163,184,.95)';
                            }
                        },

                        consoleDot(level) {
                            const l = String(level || 'info').toLowerCase();
                            if (l === 'ok' || l === 'success') return 'var(--shp-success)';
                            if (l === 'warn' || l === 'warning') return 'var(--shp-warn)';
                            if (l === 'err' || l === 'error') return 'var(--shp-danger)';
                            return 'rgba(148,163,184,.85)';
                        },

                        formatEta(sec) {
                            if (sec === null || typeof sec === 'undefined') return '—';
                            const s = Number(sec);
                            if (!Number.isFinite(s) || s <= 0) return '—';
                            const h = Math.floor(s / 3600);
                            const m = Math.floor((s % 3600) / 60);
                            const r = Math.floor(s % 60);
                            if (h > 0) return `${h}h ${m}m`;
                            if (m > 0) return `${m}m ${r}s`;
                            return `${r}s`;
                        },

                        workerCmd() {
                            const q = this.progress?.queue || 'shopify-import';
                            return `php artisan queue:work --queue=${q},default`;
                        },

                        async copy(text, toastMsg) {
                            try {
                                await navigator.clipboard.writeText(String(text ?? ''));
                                this.pushConsoleLocal(toastMsg || 'Copied', 'ok');
                            } catch (e) {
                                this.pushConsoleLocal('Copy failed (clipboard blocked)', 'warn');
                            }
                        },

                        scrollTo(id) {
                            if (id === 'top') {
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                                return;
                            }
                            const el = document.getElementById(id);
                            if (!el) return;
                            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        },

                        pushConsoleLocal(message, level = 'info') {
                            const line = {
                                time: new Date().toLocaleTimeString(),
                                level,
                                message: String(message ?? ''),
                            };
                            // Keep array object; push new entry
                            this.consoleLines.push(line);
                            this.$nextTick(() => this.scrollConsoleBottom());
                        },

                        clearConsoleLocal() {
                            // Do NOT replace entangled array reference; clear in-place
                            if (Array.isArray(this.consoleLines)) this.consoleLines.splice(0, this.consoleLines.length);
                            this.$nextTick(() => {
                                const el = this.$refs.console;
                                if (el) el.scrollTop = 0;
                            });
                        },

                        scrollConsoleBottom() {
                            const el = this.$refs.console;
                            if (!el) return;
                            el.scrollTop = el.scrollHeight;
                        },

                        init() {
                            // Ensure expected keys exist (matches backend naming)
                            const defaults = {
                                status: 'idle',
                                current: 0,
                                total: 0,
                                percent: 0,
                                imported: 0,
                                skipped: 0,
                                updated: 0,
                                created: 0,
                                errors: 0,

                                images_total: 0,
                                images_valid: 0,
                                images_invalid: 0,

                                variants_total: 0,
                                variants_ok: 0,
                                variants_bad: 0,
                                prices_created: 0,
                                prices_reused: 0,
                                prices_replaced: 0,

                                started_at: null,
                                last_tick_at: null,
                                rate_per_min: 0,
                                eta_seconds: null,

                                include_images: false,
                                strict_image_check: true,
                                update_existing: true,
                                currency: 'nok',
                                chunk_size: 10,

                                queue: 'shopify-import',
                                run_id: null,
                                batch_id: null,
                            };

                            if (this.progress && typeof this.progress === 'object') {
                                for (const [k, v] of Object.entries(defaults)) {
                                    if (typeof this.progress[k] === 'undefined') this.progress[k] = v;
                                }
                            }

                            // Auto-scroll console on new output
                            this.$watch('consoleLines.length', () => {
                                this.$nextTick(() => this.scrollConsoleBottom());
                            });
                        },
                    }));
                });
            </script>
        @endpush
    @endonce
</x-filament::page>

{{-- resources/views/filament/pages/shopify-import-test.blade.php --}}
<x-filament::page>
    @push('styles')
        <style>
            /* =========================================================================
             *  Shopify Import Page – Local CSS (no Tailwind required)
             * ========================================================================= */

            .shp-root {
                max-width: 1120px;
                margin: 0 auto;
                display: flex;
                flex-direction: column;
                gap: 24px;
                font-family: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text",
                "Segoe UI", sans-serif;
                color: #0f172a;
                font-size: 14px;
            }

            .shp-hero {
                border-radius: 16px;
                padding: 18px 20px;
                background: linear-gradient(120deg, #eff6ff, #ffffff, #eef2ff);
                box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
                border: 1px solid rgba(148, 163, 184, 0.4);
            }

            .shp-hero-inner {
                display: flex;
                flex-direction: column;
                gap: 16px;
            }

            @media (min-width: 768px) {
                .shp-hero-inner {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }
            }

            .shp-hero-main {
                display: flex;
                flex-direction: column;
                gap: 8px;
                max-width: 640px;
            }

            .shp-hero-title-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
            }

            .shp-hero-title {
                font-size: 18px;
                font-weight: 600;
                letter-spacing: -0.01em;
                color: #020617;
            }

            .shp-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border-radius: 999px;
                padding: 2px 8px;
                font-size: 11px;
                font-weight: 500;
                background: rgba(255, 255, 255, 0.9);
                border: 1px solid rgba(148, 163, 184, 0.4);
                color: #0f172a;
            }

            .shp-pill-dot {
                width: 6px;
                height: 6px;
                border-radius: 999px;
                background: #22c55e;
                animation: shp-pulse 1.8s ease-out infinite;
            }

            @keyframes shp-pulse {
                0% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.4); opacity: 0.5; }
                100% { transform: scale(1); opacity: 1; }
            }

            .shp-hero-text {
                font-size: 12px;
                line-height: 1.5;
                color: #334155;
            }

            .shp-hero-code {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
                "Courier New", monospace;
                background: rgba(15, 23, 42, 0.04);
                border-radius: 4px;
                padding: 1px 4px;
                border: 1px solid rgba(148, 163, 184, 0.4);
            }

            .shp-hero-steps {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-top: 4px;
                font-size: 11px;
                color: #475569;
                align-items: center;
            }

            .shp-steps-label {
                text-transform: uppercase;
                letter-spacing: 0.18em;
                font-size: 10px;
                color: #64748b;
            }

            .shp-step-inline {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                flex-wrap: wrap;
            }

            .shp-step-number {
                width: 20px;
                height: 20px;
                border-radius: 999px;
                background: #ffffff;
                border: 1px solid rgba(148, 163, 184, 0.7);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 11px;
                font-weight: 500;
                color: #020617;
            }

            .shp-step-separator {
                width: 20px;
                height: 1px;
                background: rgba(148, 163, 184, 0.7);
            }

            .shp-mode-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 999px;
                border: 1px solid rgba(148, 163, 184, 0.6);
                background: rgba(255, 255, 255, 0.9);
                font-size: 11px;
                color: #475569;
            }

            .shp-mode-dot {
                width: 6px;
                height: 6px;
                border-radius: 999px;
                background: #facc15;
            }

            .shp-hero-actions {
                display: flex;
                flex-direction: row;
                gap: 8px;
            }

            .shp-btn {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                border-radius: 999px;
                padding: 6px 12px;
                font-size: 12px;
                font-weight: 500;
                border: none;
                cursor: pointer;
                transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
                white-space: nowrap;
            }

            .shp-btn:focus-visible {
                outline: 2px solid #0ea5e9;
                outline-offset: 2px;
            }

            .shp-btn-secondary {
                background: #ffffff;
                border: 1px solid rgba(148, 163, 184, 0.7);
                color: #0f172a;
            }

            .shp-btn-secondary:hover {
                background: #f1f5f9;
            }

            .shp-btn-primary {
                background: #22c55e;
                color: #022c22;
                box-shadow: 0 10px 25px rgba(22, 163, 74, 0.35);
            }

            .shp-btn-primary:hover {
                background: #16a34a;
                transform: translateY(-1px);
                box-shadow: 0 12px 30px rgba(22, 163, 74, 0.4);
            }

            .shp-btn-emoji {
                font-size: 14px;
            }

            /* Layout: form + status */
            .shp-main-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
            }

            @media (min-width: 960px) {
                .shp-main-grid {
                    grid-template-columns: 2fr 1fr;
                }
            }

            .shp-card {
                border-radius: 16px;
                background: #ffffff;
                border: 1px solid rgba(148, 163, 184, 0.4);
                box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
                padding: 18px 18px;
            }

            .shp-card-muted {
                background: #f8fafc;
            }

            .shp-card-header {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 8px;
                margin-bottom: 10px;
            }

            .shp-card-title {
                font-size: 13px;
                font-weight: 600;
                color: #020617;
            }

            .shp-card-subtitle {
                font-size: 12px;
                color: #64748b;
                margin-top: 2px;
            }

            .shp-body-text-small {
                font-size: 11px;
                color: #6b7280;
                line-height: 1.45;
            }

            /* Progress card */
            .shp-status-pill-row {
                text-align: right;
                font-size: 11px;
                color: #64748b;
            }

            .shp-status-pill {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 999px;
                border: 1px solid #cbd5f0;
                background: #ffffff;
                font-size: 11px;
                color: #475569;
            }

            .shp-status-dot {
                width: 6px;
                height: 6px;
                border-radius: 999px;
            }

            /* Colors per status will be applied inline / via x-bind */

            .shp-progress-bar {
                width: 100%;
                height: 8px;
                border-radius: 999px;
                background: #e5e7eb;
                overflow: hidden;
                margin-top: 8px;
                margin-bottom: 4px;
            }

            .shp-progress-inner {
                height: 8px;
                border-radius: 999px;
                background: linear-gradient(90deg, #0ea5e9, #6366f1, #22c55e);
                width: 0;
                transition: width 0.4s ease-out;
            }

            .shp-progress-meta {
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-size: 11px;
                color: #6b7280;
            }

            .shp-progress-meta strong {
                color: #111827;
            }

            .shp-progress-meta .shp-error-count {
                font-weight: 600;
            }

            /* Console */
            .shp-console-wrapper {
                display: flex;
                flex-direction: column;
                gap: 4px;
                margin-top: 8px;
            }

            .shp-console-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                font-size: 11px;
            }

            .shp-console-title {
                font-weight: 600;
                color: #111827;
            }

            .shp-console-clear {
                border: none;
                background: none;
                padding: 0;
                font-size: 11px;
                color: #6b7280;
                cursor: pointer;
            }

            .shp-console-clear:hover {
                color: #111827;
                text-decoration: underline;
            }

            .shp-console-box {
                margin-top: 2px;
                max-height: 190px;
                overflow-y: auto;
                border-radius: 12px;
                background: #020617;
                padding: 8px 10px;
                font-size: 11px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
                "Courier New", monospace;
                color: #bbf7d0;
            }

            .shp-console-line {
                white-space: pre-wrap;
            }

            .shp-console-time {
                color: #6b7280;
                margin-right: 4px;
            }

            .shp-console-empty {
                color: #6b7280;
            }

            .shp-console-footer {
                font-size: 10px;
                color: #9ca3af;
            }

            /* Parse overview & import summary cards */
            .shp-section-card {
                border-radius: 16px;
                background: #ffffff;
                border: 1px solid rgba(148, 163, 184, 0.4);
                box-shadow: 0 10px 25px rgba(15, 23, 42, 0.04);
                padding: 18px 18px 20px;
            }

            .shp-section-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                margin-bottom: 10px;
            }

            .shp-section-title {
                font-size: 13px;
                font-weight: 600;
                color: #020617;
            }

            .shp-section-subtitle {
                font-size: 11px;
                color: #64748b;
                margin-top: 2px;
            }

            .shp-tiles {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 8px;
                margin-top: 6px;
            }

            @media (min-width: 768px) {
                .shp-tiles {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            @media (min-width: 1100px) {
                .shp-tiles {
                    grid-template-columns: repeat(6, minmax(0, 1fr));
                }
            }

            .shp-tile {
                border-radius: 12px;
                background: #f8fafc;
                border: 1px solid rgba(148, 163, 184, 0.4);
                padding: 8px 10px;
            }

            .shp-tile-label {
                font-size: 11px;
                color: #64748b;
            }

            .shp-tile-value {
                margin-top: 4px;
                font-size: 18px;
                font-weight: 600;
                color: #020617;
                font-variant-numeric: tabular-nums;
            }

            /* Table */
            .shp-table-wrapper {
                margin-top: 10px;
                border-radius: 12px;
                border: 1px solid rgba(148, 163, 184, 0.5);
                overflow: hidden;
            }

            .shp-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 12px;
            }

            .shp-table th,
            .shp-table td {
                padding: 6px 8px;
                border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            }

            .shp-table thead {
                background: #f1f5f9;
            }

            .shp-table th {
                text-align: left;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                font-size: 11px;
                color: #64748b;
            }

            .shp-table tbody tr:hover {
                background: #f9fafb;
            }

            .shp-table td {
                color: #111827;
                vertical-align: top;
            }

            .shp-table-meta {
                font-size: 11px;
                color: #6b7280;
                margin-top: 2px;
            }

            .shp-table-mono {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
                "Courier New", monospace;
                font-size: 11px;
                color: #4b5563;
            }

            .shp-tagline-small {
                font-size: 11px;
                color: #6b7280;
            }

            /* Status chips in import summary */
            .shp-chip {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                border-radius: 999px;
                border: 1px solid #cbd5f0;
                font-size: 11px;
            }

            .shp-chip-dot {
                width: 6px;
                height: 6px;
                border-radius: 999px;
            }

            .shp-chip-imported {
                background: #ecfdf3;
                border-color: #bbf7d0;
                color: #166534;
            }

            .shp-chip-imported .shp-chip-dot {
                background: #16a34a;
            }

            .shp-chip-skipped {
                background: #fffbeb;
                border-color: #fed7aa;
                color: #92400e;
            }

            .shp-chip-skipped .shp-chip-dot {
                background: #f97316;
            }

            .shp-chip-error {
                background: #fef2f2;
                border-color: #fecaca;
                color: #b91c1c;
            }

            .shp-chip-error .shp-chip-dot {
                background: #ef4444;
            }

            .shp-chip-unknown {
                background: #f3f4f6;
                border-color: #d1d5db;
                color: #4b5563;
            }

            .shp-chip-unknown .shp-chip-dot {
                background: #6b7280;
            }

            /* Details debug block */
            .shp-details-summary {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                cursor: pointer;
                font-size: 12px;
                color: #64748b;
            }

            .shp-details-bullet {
                width: 16px;
                height: 16px;
                border-radius: 999px;
                border: 1px solid #cbd5f0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 10px;
            }

            .shp-details-pre {
                margin-top: 8px;
                max-height: 320px;
                overflow: auto;
                border-radius: 12px;
                background: #020617;
                color: #e5e7eb;
                padding: 10px 12px;
                font-size: 11px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono",
                "Courier New", monospace;
                white-space: pre-wrap;
            }

            /* Modals */
            .shp-modal-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, 0.5);
                backdrop-filter: blur(4px);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 40;
            }

            .shp-modal {
                width: 100%;
                max-width: 420px;
                background: #ffffff;
                border-radius: 18px;
                border: 1px solid rgba(148, 163, 184, 0.7);
                box-shadow: 0 20px 50px rgba(15, 23, 42, 0.35);
                padding: 16px 18px 14px;
            }

            .shp-modal-title {
                font-size: 14px;
                font-weight: 600;
                color: #020617;
                margin-bottom: 4px;
            }

            .shp-modal-body {
                font-size: 12px;
                color: #4b5563;
                line-height: 1.5;
            }

            .shp-modal-body-small {
                font-size: 11px;
                color: #6b7280;
                margin-top: 4px;
            }

            .shp-modal-hint {
                margin-top: 8px;
                border-radius: 12px;
                padding: 8px 10px;
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                font-size: 11px;
                color: #1d4ed8;
            }

            .shp-modal-footer {
                display: flex;
                justify-content: flex-end;
                gap: 8px;
                margin-top: 12px;
            }

            .shp-btn-outline {
                border-radius: 999px;
                padding: 6px 12px;
                font-size: 12px;
                border: 1px solid #cbd5f0;
                background: #ffffff;
                color: #111827;
                cursor: pointer;
            }

            .shp-btn-outline:hover {
                background: #f3f4f6;
            }

            .shp-btn-solid {
                border-radius: 999px;
                padding: 6px 12px;
                font-size: 12px;
                border: none;
                cursor: pointer;
            }

            .shp-btn-solid-primary {
                background: #0ea5e9;
                color: #f9fafb;
            }

            .shp-btn-solid-primary:hover {
                background: #0284c7;
            }

            .shp-btn-solid-success {
                background: #22c55e;
                color: #022c22;
            }

            .shp-btn-solid-success:hover {
                background: #16a34a;
            }

            /* Utility */
            .shp-text-right { text-align: right; }
            .shp-mt-8 { margin-top: 8px; }
            .shp-mt-10 { margin-top: 10px; }
            .shp-mb-2 { margin-bottom: 2px; }

        </style>
    @endpush

    <div
        x-data="shopifyImportPage({
            progress: @entangle('importProgress'),
            consoleLines: @entangle('importConsole'),
        })"
        class="shp-root"
    >
        {{-- HERO --}}
        <section class="shp-hero">
            <div class="shp-hero-inner">
                <div class="shp-hero-main">
                    <div class="shp-hero-title-row">
                        <div class="shp-hero-title">
                            Shopify CSV til Stripe-import
                        </div>
                        <div class="shp-pill">
                            <span class="shp-pill-dot"></span>
                            Butikk · POS · Stripe
                        </div>
                    </div>

                    <p class="shp-hero-text">
                        Upload a Shopify products CSV, connect a Stripe account and import produkter,
                        varianter, priser og valgfritt bilder. Kjør først
                        <span class="shp-hero-code">Analyser CSV</span>
                        for å kontrollere data, deretter
                        <span class="shp-hero-code">Kjør import</span>
                        for å skrive til Stripe.
                    </p>

                    <div class="shp-hero-steps">
                        <div class="shp-steps-label">Steg</div>
                        <div class="shp-step-inline">
                            <div class="shp-step-number">1</div>
                            <span>Last opp CSV</span>
                            <div class="shp-step-separator"></div>
                            <div class="shp-step-number">2</div>
                            <span>Analyser og kontroller</span>
                            <div class="shp-step-separator"></div>
                            <div class="shp-step-number">3</div>
                            <span>Importer til Stripe</span>
                        </div>
                        <div class="shp-mode-pill">
                            <span class="shp-mode-dot"></span>
                            <span x-show="!progress.download_images">Rask modus – bilder hoppes over</span>
                            <span x-show="progress.download_images" x-cloak>Full modus – bilder importeres</span>
                        </div>
                    </div>
                </div>

                <div class="shp-hero-actions">
                    <button
                        type="button"
                        @click="openParseModal()"
                        class="shp-btn shp-btn-secondary"
                    >
                        <span class="shp-btn-emoji">🔍</span>
                        Analyser CSV
                    </button>
                    <button
                        type="button"
                        @click="openImportModal()"
                        class="shp-btn shp-btn-primary"
                    >
                        <span class="shp-btn-emoji">⚡</span>
                        Kjør import
                    </button>
                </div>
            </div>
        </section>

        {{-- FORM + STATUS --}}
        <section class="shp-main-grid">
            {{-- Left: form card --}}
            <div class="shp-card">
                <div class="shp-card-header">
                    <div>
                        <div class="shp-card-title">
                            Shopify CSV og Stripe-konto
                        </div>
                        <div class="shp-card-subtitle">
                            Eksporter fra <span class="shp-hero-code">Shopify → Products → Export CSV</span>.
                            Last opp filen, sett Stripe-konto og velg om bilder skal importeres.
                        </div>
                    </div>
                </div>

                <div>
                    {{-- Filament form --}}
                    {{ $this->form }}

                    <p class="shp-body-text-small shp-mt-8">
                        For større kataloger er det anbefalt å kjøre selve importen som en kø-jobb
                        (<span class="shp-hero-code">php artisan</span>) og bruke denne siden som
                        operatør-dashboard. Konsollen under viser løpende hendelser.
                    </p>
                </div>
            </div>

            {{-- Right: status + console --}}
            <div class="shp-card shp-card-muted">
                <div class="shp-card-header">
                    <div>
                        <div class="shp-card-title">Importstatus</div>
                        <div class="shp-card-subtitle">
                            Siste kjøring og fremdrift.
                        </div>
                    </div>
                    <div class="shp-status-pill-row">
                        <div
                            class="shp-status-pill"
                            :style="{
                                borderColor:
                                    progress.status === 'running'  ? '#fbbf24' :
                                    progress.status === 'finished' ? '#4ade80' :
                                    progress.status === 'failed'   ? '#fca5a5' :
                                    progress.status === 'pending'  ? '#38bdf8' : '#cbd5f5',
                                backgroundColor:
                                    progress.status === 'running'  ? '#fffbeb' :
                                    progress.status === 'finished' ? '#ecfdf3' :
                                    progress.status === 'failed'   ? '#fef2f2' :
                                    progress.status === 'pending'  ? '#e0f2fe' : '#f9fafb',
                            }"
                        >
                            <span
                                class="shp-status-dot"
                                :style="{
                                    backgroundColor:
                                        progress.status === 'running'  ? '#fbbf24' :
                                        progress.status === 'finished' ? '#22c55e' :
                                        progress.status === 'failed'   ? '#ef4444' :
                                        progress.status === 'pending'  ? '#0ea5e9' : '#9ca3af',
                                }"
                            ></span>
                            <span x-text="statusLabel()"></span>
                        </div>
                        <div class="shp-mt-8">
                            <span class="shp-table-mono">
                                <span x-text="progress.current"></span> /
                                <span x-text="progress.total"></span> produkter
                            </span>
                        </div>
                    </div>
                </div>

                <div class="shp-progress-bar">
                    <div
                        class="shp-progress-inner"
                        :style="`width: ${Math.max(0, Math.min(100, progress.percent ?? 0))}%;`"
                    ></div>
                </div>

                <div class="shp-progress-meta">
                    <span class="shp-table-mono" x-text="(progress.percent ?? 0) + '%'"></span>
                    <span>
                        Importert
                        <strong x-text="progress.imported ?? 0"></strong>
                        · Hoppet over
                        <strong x-text="progress.skipped ?? 0"></strong>
                        · Feil
                        <span
                            class="shp-error-count"
                            :style="{ color: (progress.errors ?? 0) ? '#b91c1c' : '#111827' }"
                            x-text="progress.errors ?? 0"
                        ></span>
                    </span>
                </div>

                {{-- Console --}}
                <div class="shp-console-wrapper">
                    <div class="shp-console-header">
                        <div class="shp-console-title">Import-konsoll</div>
                        <button
                            type="button"
                            class="shp-console-clear"
                            @click="clearConsole()"
                        >
                            Tøm
                        </button>
                    </div>

                    <div x-ref="console" class="shp-console-box">
                        <template x-if="consoleLines.length === 0">
                            <div class="shp-console-empty">
                                Venter på output… Kjør <strong>Analyser CSV</strong> eller
                                <strong>Kjør import</strong>.
                            </div>
                        </template>

                        <template x-for="(line, idx) in consoleLines" :key="idx">
                            <div class="shp-console-line">
                                <span class="shp-console-time" x-text="line.time ?? ''"></span>
                                <span x-text="line.message ?? line"></span>
                            </div>
                        </template>
                    </div>

                    <div class="shp-console-footer">
                        Hver produkt/variant kan skrive til konsollen. I produksjon kan logg speiles til
                        eget kanal for revisjon.
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

            <section class="shp-section-card">
                <div class="shp-section-header">
                    <div>
                        <div class="shp-section-title">Analyse – oversikt</div>
                        <div class="shp-section-subtitle">
                            High-level view av Shopify CSV. Ingen endringer skjer i Stripe her.
                        </div>
                    </div>
                </div>

                @php
                    $tiles = [
                        ['label' => 'Produkter',          'value' => $stats['total_products'] ?? ($parseResult['total_products'] ?? 0)],
                        ['label' => 'Varianter',          'value' => $stats['total_variants'] ?? ($parseResult['total_variants'] ?? 0)],
                        ['label' => 'Variable produkter', 'value' => $stats['variable_products'] ?? 0],
                        ['label' => 'Enkle produkter',    'value' => $stats['single_like_products'] ?? 0],
                        ['label' => 'Leverandører',       'value' => $stats['unique_vendors'] ?? 0],
                        ['label' => 'Typer',              'value' => $stats['unique_types'] ?? 0],
                        ['label' => 'Kategorier',         'value' => $stats['unique_categories'] ?? 0],
                        ['label' => 'Tagger',             'value' => $stats['unique_tags'] ?? 0],
                        ['label' => 'Bilder',             'value' => $stats['total_images'] ?? 0],
                    ];
                @endphp

                <div class="shp-tiles">
                    @foreach ($tiles as $tile)
                        <div class="shp-tile">
                            <div class="shp-tile-label">{{ $tile['label'] }}</div>
                            <div class="shp-tile-value">{{ $tile['value'] }}</div>
                        </div>
                    @endforeach
                </div>

                @if (! empty($sampleProducts))
                    <div class="shp-mt-10">
                        <div class="shp-section-header">
                            <div>
                                <div class="shp-section-title" style="font-size: 12px;">
                                    Eksempelprodukter (første {{ count($sampleProducts) }})
                                </div>
                                <div class="shp-section-subtitle">
                                    Sjekk titler, handles, antall varianter, pris-intervall og bildekonto
                                    før import.
                                </div>
                            </div>
                        </div>

                        <div class="shp-table-wrapper">
                            <table class="shp-table">
                                <thead>
                                <tr>
                                    <th>Tittel</th>
                                    <th>Handle</th>
                                    <th style="text-align:right;">Varianter</th>
                                    <th style="text-align:right;">Min. pris</th>
                                    <th style="text-align:right;">Maks. pris</th>
                                    <th style="text-align:right;">Bilder</th>
                                    <th>Leverandør / type</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($sampleProducts as $prod)
                                    @php
                                        $variantCount = $prod['variant_count'] ?? count($prod['variants'] ?? []);
                                        $min          = $prod['variant_min_price'] ?? null;
                                        $max          = $prod['variant_max_price'] ?? null;
                                        $imgs         = count($prod['images'] ?? []);
                                        $tags         = (string) ($prod['tags'] ?? '');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;">
                                                {{ $prod['title'] ?? 'N/A' }}
                                            </div>
                                            <div class="shp-table-meta">
                                                {{ \Illuminate\Support\Str::limit($tags, 80) }}
                                            </div>
                                        </td>
                                        <td class="shp-table-mono">
                                            {{ $prod['handle'] ?? '' }}
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            {{ $variantCount }}
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            @if ($min !== null)
                                                {{ number_format($min, 2, ',', ' ') }}
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            @if ($max !== null)
                                                {{ number_format($max, 2, ',', ' ') }}
                                            @else
                                                &mdash;
                                            @endif
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            {{ $imgs }}
                                        </td>
                                        <td>
                                            <div class="shp-table-meta">
                                                {{ $prod['vendor'] ?? '—' }}
                                            </div>
                                            <div class="shp-table-meta">
                                                {{ $prod['type'] ?? '—' }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <details class="shp-mt-10">
                    <summary class="shp-details-summary">
                        <span class="shp-details-bullet">❯</span>
                        Raw analyse-payload (debug)
                    </summary>
                    <pre class="shp-details-pre">
{{ json_encode($parseResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                    </pre>
                </details>
            </section>
        @endif

        {{-- IMPORT SUMMARY --}}
        @if ($importResult)
            @php
                $istats     = $importResult['stats']['import'] ?? [];
                $errCount   = (int) ($importResult['error_count'] ?? ($istats['error_count'] ?? 0));
                $perProduct = $importResult['per_product'] ?? [];
            @endphp

            <section class="shp-section-card">
                <div class="shp-section-header">
                    <div>
                        <div class="shp-section-title">
                            Import – oppsummering
                        </div>
                        <div class="shp-section-subtitle">
                            Resultat fra siste Stripe-import. Kombiner dette med applikasjonslogger for
                            full revisjon.
                        </div>
                    </div>
                </div>

                <div class="shp-tiles" style="margin-top: 4px;">
                    <div class="shp-tile">
                        <div class="shp-tile-label">Importert</div>
                        <div class="shp-tile-value">
                            {{ $istats['imported'] ?? ($importResult['imported'] ?? 0) }}
                        </div>
                    </div>
                    <div class="shp-tile">
                        <div class="shp-tile-label">Hoppet over</div>
                        <div class="shp-tile-value">
                            {{ $istats['skipped'] ?? ($importResult['skipped'] ?? 0) }}
                        </div>
                    </div>
                    <div class="shp-tile" style="background:#fef2f2;border-color:#fecaca;">
                        <div class="shp-tile-label" style="color:#b91c1c;">Feil</div>
                        <div class="shp-tile-value" style="color:#b91c1c;">
                            {{ $errCount }}
                        </div>
                    </div>
                    <div class="shp-tile">
                        <div class="shp-tile-label">Totalt antall produkter</div>
                        <div class="shp-tile-value">
                            {{ $istats['total_products'] ?? 0 }}
                        </div>
                    </div>
                </div>

                @if (! empty($perProduct))
                    <div class="shp-mt-10">
                        <div class="shp-section-header">
                            <div>
                                <div class="shp-section-title" style="font-size: 12px;">
                                    Resultat per produkt (første {{ min(20, count($perProduct)) }})
                                </div>
                                <div class="shp-section-subtitle">
                                    Bruk dette sammen med Stripe- og applikasjonslogg ved feilsøking.
                                </div>
                            </div>
                        </div>

                        <div class="shp-table-wrapper">
                            <table class="shp-table">
                                <thead>
                                <tr>
                                    <th>Tittel</th>
                                    <th>Handle</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Varianter</th>
                                    <th style="text-align:right;">Bilder</th>
                                    <th>Melding</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach (array_slice($perProduct, 0, 20) as $item)
                                    @php
                                        $status = data_get($item, 'status', 'ukjent');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;">
                                                {{ data_get($item, 'title', 'N/A') }}
                                            </div>
                                        </td>
                                        <td class="shp-table-mono">
                                            {{ data_get($item, 'handle', '') }}
                                        </td>
                                        <td>
                                            @php
                                                $chipClass =
                                                    $status === 'imported' ? 'shp-chip shp-chip-imported' :
                                                    ($status === 'skipped' ? 'shp-chip shp-chip-skipped' :
                                                    ($status === 'error'   ? 'shp-chip shp-chip-error'   :
                                                                             'shp-chip shp-chip-unknown'));
                                            @endphp
                                            <span class="{{ $chipClass }}">
                                                    <span class="shp-chip-dot"></span>
                                                    <span>{{ ucfirst($status) }}</span>
                                                </span>
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            {{ data_get($item, 'variant_count', 0) }}
                                        </td>
                                        <td style="text-align:right; font-variant-numeric: tabular-nums;">
                                            {{ data_get($item, 'image_count', 0) }}
                                        </td>
                                        <td>
                                            <div class="shp-table-meta">
                                                {{ data_get($item, 'message', '—') }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                @if (! empty($importResult['errors']))
                    <div class="shp-mt-10">
                        <div class="shp-section-title" style="font-size: 12px; color:#b91c1c;">
                            Første feil (maks 20)
                        </div>
                        <ul style="margin-top:6px; padding-left:18px; font-size:12px; color:#374151;">
                            @foreach (array_slice($importResult['errors'], 0, 20) as $e)
                                <li>{{ $e }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <details class="shp-mt-10">
                    <summary class="shp-details-summary">
                        <span class="shp-details-bullet">❯</span>
                        Raw import-payload (debug)
                    </summary>
                    <pre class="shp-details-pre">
{{ json_encode($importResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                    </pre>
                </details>
            </section>
        @endif

        {{-- MODAL: ANALYSE CSV --}}
        <template x-if="showParse">
            <div
                class="shp-modal-backdrop"
                @keydown.escape.window="closeParseModal()"
            >
                <div class="shp-modal">
                    <div class="shp-modal-title">
                        Analyser Shopify CSV
                    </div>
                    <div class="shp-modal-body">
                        CSV-filen leses, grupperes til produkter og varianter og statistikk kalkuleres.
                        Ingen Stripe-kall gjøres i dette steget.
                    </div>
                    <div class="shp-modal-body-small">
                        Bruk analysen til å finne rare priser, manglende tagger eller duplikater
                        <strong>før</strong> du kjører import mot Stripe.
                    </div>
                    <div class="shp-modal-hint">
                        Tips: Ta vare på raw-payload for feilsøking etter at importen har kjørt i produksjon.
                    </div>
                    <div class="shp-modal-footer">
                        <button
                            type="button"
                            class="shp-btn-outline"
                            @click="closeParseModal()"
                        >
                            Avbryt
                        </button>
                        <button
                            type="button"
                            class="shp-btn-solid shp-btn-solid-primary"
                            wire:click="parseCsv"
                            @click="closeParseModal(); pushConsole('Analyse startet…');"
                        >
                            Kjør analyse
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- MODAL: KJØR IMPORT --}}
        <template x-if="showImport">
            <div
                class="shp-modal-backdrop"
                @keydown.escape.window="closeImportModal()"
            >
                <div class="shp-modal">
                    <div class="shp-modal-title">
                        Kjør import til Stripe
                    </div>
                    <div class="shp-modal-body">
                        Alle analyserte produkter og varianter importeres til valgt Stripe-konto.
                        Hvis bildeimport er aktivert, lastes bilder ned via Spatie og lastes opp til Stripe.
                    </div>
                    <ul class="shp-modal-body-small" style="margin-left: 16px; list-style: disc;">
                        <li>Produkter dedupliseres på Shopify-handle + Stripe-konto.</li>
                        <li>Variable produkter gir varianter med egne Stripe-produkter/priser.</li>
                        <li>Enkle produkter får én hovedpris (ingen DB-varianter).</li>
                    </ul>
                    <div class="shp-modal-hint">
                        <span
                            style="display:inline-block;width:8px;height:8px;border-radius:999px;margin-right:6px;"
                            :style="{ backgroundColor: progress.download_images ? '#22c55e' : '#f59e0b' }"
                        ></span>
                        <span x-show="progress.download_images" x-cloak>
                            Bilder vil hentes fra Shopify og lastes opp til Stripe i denne kjøringen.
                        </span>
                        <span x-show="!progress.download_images">
                            Bilder blir <strong>hoppet over</strong>. Slå på bilde-bryteren i skjemaet for å inkludere dem.
                        </span>
                    </div>
                    <div class="shp-modal-footer">
                        <button
                            type="button"
                            class="shp-btn-outline"
                            @click="closeImportModal()"
                        >
                            Avbryt
                        </button>
                        <button
                            type="button"
                            class="shp-btn-solid shp-btn-solid-success"
                            wire:click="runImport"
                            @click="closeImportModal(); pushConsole('Import startet…');"
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

                        progress: Object.assign({
                            status: 'idle',
                            current: 0,
                            total: 0,
                            percent: 0,
                            imported: 0,
                            skipped: 0,
                            errors: 0,
                            download_images: false,
                        }, payload.progress || {}),

                        consoleLines: payload.consoleLines || [],

                        openParseModal()  { this.showParse  = true },
                        closeParseModal() { this.showParse  = false },
                        openImportModal() { this.showImport = true },
                        closeImportModal(){ this.showImport = false },

                        statusLabel() {
                            switch (this.progress.status) {
                                case 'pending':  return 'Klar til import';
                                case 'running':  return 'Kjører…';
                                case 'finished': return 'Ferdig';
                                case 'failed':   return 'Feilet';
                                default:         return 'Inaktiv';
                            }
                        },

                        pushConsole(message) {
                            const line = typeof message === 'string'
                                ? { time: new Date().toLocaleTimeString(), message }
                                : message;

                            this.consoleLines.push(line);

                            this.$nextTick(() => {
                                const el = this.$refs.console;
                                if (el) el.scrollTop = el.scrollHeight;
                            });
                        },

                        clearConsole() {
                            this.consoleLines = [];
                        },

                        init() {
                            this.$watch('consoleLines', () => {
                                this.$nextTick(() => {
                                    const el = this.$refs.console;
                                    if (el) el.scrollTop = el.scrollHeight;
                                });
                            });
                        },
                    }))
                })
            </script>
        @endpush
    @endonce
</x-filament::page>

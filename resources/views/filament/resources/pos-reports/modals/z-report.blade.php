{{-- ponytail: preview and PDF share reports.partials.z-report-body so they cannot drift --}}
<style>
    @include('reports.partials.z-report-styles')
</style>

<div style="margin-bottom: 1rem; text-align: right;">
    <a href="{{ route('reports.z-report.pdf', ['tenant' => $session->store->slug, 'sessionId' => $session->id]) }}"
       target="_blank"
       style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background-color: rgb(239 68 68); color: white; text-decoration: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500;">
        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        Last ned PDF
    </a>
</div>

@include('reports.partials.z-report-body', ['report' => $report, 'session' => $session])

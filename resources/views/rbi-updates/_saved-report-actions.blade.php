<div class="saved-report-toolbar"><span>Report actions</span><div>
    <a class="button secondary-button rbi-download-action" href="{{ route('registry.new-inhabitant-monthly-reports.pdf', $reportMonth) }}">Download PDF</a>
    @if($monthlyRecords->every(fn($member) => filled($member->submitted_rbi_update_id)))
        <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Submitted to Municipal DILG</span>
    @else
        <form method="POST" action="{{ route('registry.new-inhabitant-monthly-reports.submit', $reportMonth) }}" onsubmit="return confirm('Submit this saved report to Municipal DILG?')">@csrf<button type="submit" class="rbi-submit-action">Submit to Municipal DILG</button></form>
    @endif
</div></div>

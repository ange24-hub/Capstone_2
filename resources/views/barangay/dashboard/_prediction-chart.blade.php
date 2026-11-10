<figure class="statistical-chart" data-forecast-chart data-chart-predicted-label="{{ $chartPredictedLabel ?? 'Predicted' }}" data-chart-actual-label="{{ $chartActualLabel ?? 'Actual' }}" aria-labelledby="{{ $chartId }}-title">
    <figcaption class="statistical-chart-caption">
        <span class="statistical-chart-title statistical-visually-hidden" id="{{ $chartId }}-title">{{ $chartTitle }}</span>
        <span class="statistical-chart-legend" aria-label="Chart legend">
            <span><i class="is-actual" aria-hidden="true"></i>{{ $chartActualLabel ?? 'Actual' }}</span>
            <span><i class="is-predicted" aria-hidden="true"></i>{{ $chartPredictedLabel ?? 'Predicted' }}</span>
        </span>
    </figcaption>
    <script type="application/json" data-chart-rows>@json($chartRows)</script>
    <div class="statistical-chart-plot" data-chart-plot>
        <noscript>Enable JavaScript to view the graph. Values are available in the source reports and forecast details.</noscript>
    </div>
    <p class="statistical-chart-value" data-chart-value aria-live="polite">{{ $chartNote }}</p>
</figure>

<figure class="statistical-chart" data-forecast-chart aria-labelledby="{{ $chartId }}-title">
    <figcaption class="statistical-chart-caption">
        <span class="statistical-chart-title statistical-visually-hidden" id="{{ $chartId }}-title">{{ $chartTitle }}</span>
        <span class="statistical-chart-legend" aria-label="Chart legend">
            <span><i class="is-actual" aria-hidden="true"></i>Actual</span>
            <span><i class="is-predicted" aria-hidden="true"></i>Predicted</span>
        </span>
    </figcaption>
    <script type="application/json" data-chart-rows>@json($chartRows)</script>
    <div class="statistical-chart-plot" data-chart-plot>
        <noscript>Enable JavaScript to view the graph. Values are available in the source reports and forecast details.</noscript>
    </div>
    <p class="statistical-chart-value" data-chart-value aria-live="polite">{{ $chartNote }}</p>
</figure>

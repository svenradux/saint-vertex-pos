@inject('widgetService', 'App\Services\WidgetService')
<div id="dashboard-content" class="px-4">
    <header class="vtx-dashboard-intro">
        <div>
            <p class="vtx-eyebrow">VERTEX POS</p>
            <h1>{{ __m('Ringkasan toko', 'SaintVertex') }}</h1>
            <p>{{ __m('Pantau penjualan, pelanggan, dan aktivitas toko dalam satu tampilan.', 'SaintVertex') }}</p>
        </div>
        <span class="vtx-local-badge">{{ __m('Saint Vertex · Lokal', 'SaintVertex') }}</span>
    </header>
    <ns-dashboard>
        <ns-dragzone
            :raw-columns="{{ $widgetService->getWidgetsArea('ns-dashboard-widgets')->values()->toJson() }}"
            :raw-widgets="{{ $widgetService->getWidgets()->values()->toJson() }}">
        </ns-dragzone>
    </ns-dashboard>
</div>

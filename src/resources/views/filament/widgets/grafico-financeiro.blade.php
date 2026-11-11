<div data-grafico-financeiro>
    {{-- Preserva o canvas e seus listeners ao alternar entre períodos com e sem dados. --}}
    <div @style(['display: none' => ! $this->temDados()])>
        @include('filament-widgets::chart-widget')
    </div>
    @if (! $this->temDados())
        <x-filament-widgets::widget class="fi-wi-chart">
            <x-filament::section :heading="$this->getHeading()" :description="$this->getDescription()">
                <div class="flex flex-col items-center justify-center gap-y-3 text-center text-sm text-gray-500 dark:text-gray-400" style="min-height: 180px;">
                    <x-filament::icon icon="heroicon-o-chart-bar" class="h-10 w-10" />
                    <p>Os valores aparecerão aqui quando houver registros neste intervalo.</p>
                </div>
            </x-filament::section>
        </x-filament-widgets::widget>
    @endif
</div>

{{-- Single persistent root element (Livewire requires one even when the banner is hidden). --}}
<div>
    @php $stalled = $this->getStalled(); @endphp

    @if ($stalled > 0)
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-500/40 dark:bg-amber-500/10">
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="space-y-1">
                    <p class="font-semibold text-amber-800 dark:text-amber-200">
                        {{ $stalled }} import{{ $stalled === 1 ? '' : 's' }} have been waiting a while with nothing processing
                    </p>
                    <p class="text-amber-700 dark:text-amber-300">
                        The background queue worker doesn’t appear to be running. In production, check that the
                        queue-worker cron is active. Locally, start one in the project folder:
                    </p>
                    <code class="mt-1 inline-block rounded bg-amber-100 px-2 py-1 font-mono text-xs text-amber-900 dark:bg-amber-500/20 dark:text-amber-100">
                        php artisan queue:work --stop-when-empty
                    </code>
                    <p class="text-xs text-amber-600 dark:text-amber-400/80">
                        This table refreshes automatically — statuses move to “completed” once the worker processes them.
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>

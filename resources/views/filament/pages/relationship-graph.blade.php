<x-filament-panels::page>
    <x-filament::section>
        <div class="space-y-4">
            {{-- Row 0: saved views --}}
            @if (count($this->getSavedViewOptions()))
                <label class="flex flex-col gap-1 text-sm md:max-w-md">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Saved views</span>
                    <select wire:model.live="currentViewId"
                        class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-600">
                        <option value="">— Load a saved view —</option>
                        @foreach ($this->getSavedViewOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <span class="text-xs text-gray-400">Pick one to jump straight to that focus + filters. Save the current view with the button above.</span>
                </label>
            @endif

            {{-- Row 1: start-from + depth + reset --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-12">
                <div class="flex flex-col gap-1 text-sm md:col-span-6">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Start from</span>
                    <div wire:ignore
                        x-data="entityPicker(@js($this->getEntityOptions()), @js($focusEntityId))"
                        x-on:filters-reset.window="clear(true)"
                        x-on:focus-loaded.window="selectedId = ($event.detail[0]?.id ?? $event.detail.id ?? null)"
                        class="relative">
                        <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.q.focus())"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-300 px-3 py-2 text-left text-sm dark:border-gray-600 dark:bg-gray-800">
                            <span :class="selectedId ? '' : 'text-gray-400'" x-text="selectedLabel()"></span>
                            <span class="text-gray-400">▾</span>
                        </button>
                        <div x-show="open" x-transition @click.outside="open = false"
                            class="absolute z-30 mt-1 w-full rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <input type="text" x-ref="q" x-model="q" placeholder="Search people & organizations…"
                                class="mb-2 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-600">
                            <div class="max-h-64 space-y-0.5 overflow-y-auto">
                                <button type="button" @click="clear()"
                                    class="flex w-full items-center rounded px-2 py-1 text-left text-sm text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700"
                                    x-show="q === ''">— Whole network —</button>
                                <template x-for="[id, label] in filtered()" :key="id">
                                    <button type="button" @click="select(id)"
                                        class="flex w-full items-center rounded px-2 py-1 text-left text-sm text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                        :class="id === selectedId ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' : ''">
                                        <span x-text="label"></span>
                                    </button>
                                </template>
                                <p class="px-2 py-2 text-xs text-gray-400" x-show="filtered().length === 0">
                                    No matching entities.
                                </p>
                            </div>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400">Type to jump to a person or organization.</span>
                </div>

                <div class="flex flex-col gap-1 text-sm md:col-span-4">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Degrees of separation</span>
                    <div class="inline-flex overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600">
                        @foreach ([1 => '1st', 2 => '2nd', 3 => '3rd'] as $d => $label)
                            <button type="button" wire:click="$set('depth', {{ $d }})"
                                @class([
                                    'flex-1 px-3 py-2 text-sm transition',
                                    'bg-primary-600 text-white font-medium' => $depth === $d,
                                    'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300' => $depth !== $d,
                                ])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-end md:col-span-2">
                    <button type="button" wire:click="resetFilters"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300">
                        Reset
                    </button>
                </div>
            </div>

            {{-- Row 2: verification chips --}}
            <div class="flex flex-col gap-1.5 text-sm">
                <span class="font-medium text-gray-700 dark:text-gray-300">Show verification levels</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->getVerificationOptions() as $val => $label)
                        <label @class([
                            'cursor-pointer rounded-full border px-3 py-1 text-xs transition select-none',
                            'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => in_array($val, $verificationStates, true),
                            'border-gray-300 text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-400' => ! in_array($val, $verificationStates, true),
                        ])>
                            <input type="checkbox" wire:model.live="verificationStates" value="{{ $val }}" class="hidden">
                            {{ $label }}
                        </label>
                    @endforeach
                    <span class="self-center text-xs text-gray-400">None selected = show all.</span>
                </div>
            </div>

            {{-- Row 3: collapsible type + issue --}}
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Connection types</span>
                    <div wire:ignore
                        x-data="typePicker(@js($this->getTypeOptions()), @js($types))"
                        x-on:filters-reset.window="selected = []; q = ''; open = false"
                        x-on:types-loaded.window="selected = ($event.detail[0]?.types ?? $event.detail.types ?? []).map(Number)"
                        class="relative">
                        <button type="button" @click="open = !open"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-300 px-3 py-2 text-left text-sm dark:border-gray-600 dark:bg-gray-800">
                            <span x-text="selected.length ? selected.length + ' selected' : 'All types'"></span>
                            <span class="text-gray-400">▾</span>
                        </button>
                        <div x-show="open" x-transition @click.outside="open = false"
                            class="absolute z-20 mt-1 w-full rounded-lg border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                            <input type="text" x-model="q" placeholder="Search types…"
                                class="mb-2 w-full rounded-lg border-gray-300 text-sm dark:bg-gray-900 dark:border-gray-600">
                            <div class="max-h-56 space-y-1 overflow-y-auto">
                                <template x-for="[id, label] in entries" :key="id">
                                    <label class="flex items-center gap-2 rounded px-1 py-0.5 text-sm text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700"
                                        x-show="q === '' || label.toLowerCase().includes(q.toLowerCase())">
                                        <input type="checkbox" class="rounded border-gray-300 text-primary-600"
                                            :checked="selected.includes(id)" @change="toggle(id)">
                                        <span x-text="label"></span>
                                    </label>
                                </template>
                                <p class="px-1 py-2 text-xs text-gray-400"
                                    x-show="!entries.some(([id, label]) => q === '' || label.toLowerCase().includes(q.toLowerCase()))">
                                    No matching types.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <label class="flex flex-col gap-1 text-sm">
                    <span class="font-medium text-gray-700 dark:text-gray-300">Limit to issue</span>
                    <select wire:model.live="issueTagId"
                        class="fi-input rounded-lg border-gray-300 text-sm dark:bg-gray-800 dark:border-gray-600">
                        <option value="">Any issue</option>
                        @foreach ($this->getIssueOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
        </div>
    </x-filament::section>

    <div wire:ignore
        x-data="graphView(@js($graph))"
        x-on:graph-updated.window="update($event.detail[0]?.graph ?? $event.detail.graph)"
        class="mt-4 flex flex-col gap-4 lg:flex-row">

        {{-- Canvas --}}
        <div class="min-w-0 flex-1">
            {{-- Breadcrumb trail: the path of entities you've walked into --}}
            <div x-show="trail.length" class="mb-2 flex flex-wrap items-center gap-1 text-xs">
                <button type="button" @click="goBack()"
                    class="mr-1 inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2 py-1 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300">
                    ← Back
                </button>
                <button type="button" @click="clearFocus()"
                    class="rounded px-1.5 py-1 text-gray-500 hover:bg-gray-50 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                    Whole network
                </button>
                <template x-for="(crumb, i) in trail" :key="crumb.id">
                    <span class="flex items-center gap-1">
                        <span class="text-gray-300 dark:text-gray-600">›</span>
                        <button type="button" @click="focusOn(crumb.id)"
                            class="rounded px-1.5 py-1 hover:bg-gray-50 dark:hover:bg-gray-800"
                            :class="i === trail.length - 1
                                ? 'font-semibold text-gray-800 dark:text-gray-100'
                                : 'text-primary-600 hover:underline dark:text-primary-400'"
                            x-text="crumb.label"></button>
                    </span>
                </template>
            </div>

            <div class="mb-2 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span x-text="statusText()"></span>
                <button type="button" @click="fit()"
                    class="rounded-lg border border-gray-300 px-2.5 py-1 hover:bg-gray-50 dark:border-gray-600">
                    Fit to screen
                </button>
            </div>
            <div x-ref="cy"
                class="w-full rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"
                style="height: 600px;"></div>
            <p x-show="empty" class="mt-3 text-sm text-gray-500">
                No connections to display. Pick a focus entity that has connections, or build one from a committee in the Contributions tab.
            </p>
        </div>

        {{-- Detail / legend panel --}}
        <aside class="shrink-0 rounded-xl border border-gray-200 p-4 text-sm dark:border-gray-700 lg:w-80">
            {{-- Nothing selected: legend --}}
            <div x-show="!sel">
                <p class="mb-3 font-medium text-gray-700 dark:text-gray-300">Click any node or connection for details.</p>
                <div class="space-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded-full" style="background:#2563eb"></span> Person</div>
                    <div class="flex items-center gap-2"><span class="inline-block h-3 w-3 rounded" style="background:#16a34a"></span> Organization</div>
                    <hr class="my-2 border-gray-200 dark:border-gray-700">
                    <div>▬ <span class="text-green-600">Verified</span> &nbsp; ▬ <span class="text-sky-600">Corroborated</span></div>
                    <div>– – <span class="text-amber-600">Reported</span> &nbsp; · · · Lead</div>
                    <div class="text-red-600">▬ Disputed</div>
                </div>
            </div>

            {{-- Node selected --}}
            <template x-if="sel && sel.kind === 'node'">
                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-wide text-gray-400" x-text="sel.data.typeLabel"></div>
                    <div class="text-base font-semibold text-gray-800 dark:text-gray-100" x-text="sel.data.label"></div>
                    <div class="text-sm text-gray-500" x-show="sel.data.sub" x-text="sel.data.sub"></div>
                    <div class="mt-2 flex flex-col gap-2">
                        <button type="button" @click="focusOn(sel.data.id)"
                            x-show="!sel.data.focus"
                            class="inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                            <span>Explore</span>
                            <span class="font-semibold" x-text="sel.data.label"></span>
                            <span>’s connections →</span>
                        </button>
                        <p x-show="sel.data.focus" class="text-xs text-gray-400">
                            This is the current focus of the graph.
                        </p>
                        <a :href="sel.data.url" target="_blank"
                            class="inline-flex rounded-lg border border-gray-300 px-3 py-1.5 text-center text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300">
                            Open dossier →
                        </a>
                    </div>
                </div>
            </template>

            {{-- Edge selected --}}
            <template x-if="sel && sel.kind === 'edge'">
                <div class="space-y-2">
                    <div class="text-xs uppercase tracking-wide text-gray-400">Connection</div>
                    <div class="text-sm text-gray-800 dark:text-gray-100">
                        <span class="font-semibold" x-text="sel.data.fromLabel"></span>
                        <span class="text-gray-500" x-text="' — ' + sel.data.label + ' → '"></span>
                        <span class="font-semibold" x-text="sel.data.toLabel"></span>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="rounded-full px-2 py-0.5 text-xs"
                            :class="{
                                'bg-green-100 text-green-700': sel.data.verification === 'verified',
                                'bg-sky-100 text-sky-700': sel.data.verification === 'corroborated',
                                'bg-amber-100 text-amber-700': sel.data.verification === 'reported',
                                'bg-gray-100 text-gray-600': sel.data.verification === 'lead',
                                'bg-red-100 text-red-700': ['disputed','disproven'].includes(sel.data.verification),
                            }" x-text="sel.data.verification"></span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600" x-show="sel.data.confidence" x-text="'confidence ' + sel.data.confidence + '/5'"></span>
                    </div>
                    <div class="text-xs text-gray-500" x-show="sel.data.start || sel.data.end"
                        x-text="'Dates: ' + (sel.data.start || '?') + ' – ' + (sel.data.end || 'present')"></div>
                    <p class="max-h-40 overflow-y-auto whitespace-pre-line rounded-lg bg-gray-50 p-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        x-show="sel.data.notes" x-text="sel.data.notes"></p>
                    <a :href="sel.data.url" target="_blank"
                        class="mt-1 inline-flex rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-700">
                        Open connection &amp; evidence →
                    </a>
                </div>
            </template>
        </aside>
    </div>

    <script>
        function __loadCytoscape(cb) {
            if (window.cytoscape) { cb(); return; }
            if (window.__cyLoading) { window.__cyLoading.push(cb); return; }
            window.__cyLoading = [cb];
            const s = document.createElement('script');
            s.src = @js(asset('js/cytoscape.min.js'));
            s.onload = () => { const q = window.__cyLoading; window.__cyLoading = null; q.forEach(fn => fn()); };
            document.head.appendChild(s);
        }

        window.entityPicker = function (options, initial) {
            return {
                open: false,
                q: '',
                entries: Object.entries(options).map(([id, label]) => [parseInt(id, 10), label]),
                selectedId: initial ? Number(initial) : null,
                selectedLabel() {
                    if (!this.selectedId) return '— Whole network —';
                    const hit = this.entries.find(([id]) => id === this.selectedId);
                    return hit ? hit[1] : '— Whole network —';
                },
                filtered() {
                    const q = this.q.trim().toLowerCase();
                    if (q === '') return this.entries.slice(0, 50);
                    return this.entries.filter(([, label]) => label.toLowerCase().includes(q)).slice(0, 50);
                },
                select(id) {
                    this.selectedId = Number(id);
                    this.open = false;
                    this.q = '';
                    this.$wire.set('focusEntityId', this.selectedId);
                },
                clear(silent = false) {
                    this.selectedId = null;
                    this.open = false;
                    this.q = '';
                    if (!silent) this.$wire.set('focusEntityId', null);
                },
            };
        };

        window.typePicker = function (options, initial) {
            return {
                open: false,
                q: '',
                entries: Object.entries(options).map(([id, label]) => [parseInt(id, 10), label]),
                selected: (initial || []).map(Number),
                toggle(id) {
                    id = Number(id);
                    const i = this.selected.indexOf(id);
                    if (i >= 0) { this.selected.splice(i, 1); } else { this.selected.push(id); }
                    this.$wire.set('types', this.selected);
                },
            };
        };

        window.graphView = function (initial) {
            return {
                cy: null,
                empty: true,
                sel: null,
                counts: { nodes: 0, edges: 0 },
                truncated: false,
                trail: [], // breadcrumb of focus entities: [{id, label}, ...]
                init() {
                    __loadCytoscape(() => this.render(initial));
                },
                update(graph) {
                    if (!graph) return;
                    this.sel = null;
                    __loadCytoscape(() => this.render(graph));
                },
                // Keep the breadcrumb in step with whatever the graph is actually focused on.
                reconcileTrail(graph) {
                    const focusId = graph.meta ? (graph.meta.focus ?? null) : null;
                    if (!focusId) { this.trail = []; return; }
                    const node = (graph.nodes || []).find(n => n.data && Number(n.data.id) === Number(focusId));
                    const label = node ? node.data.label : ('#' + focusId);
                    const idx = this.trail.findIndex(t => Number(t.id) === Number(focusId));
                    if (idx >= 0) {
                        this.trail = this.trail.slice(0, idx + 1); // jumped back — drop forward history
                    } else {
                        this.trail.push({ id: Number(focusId), label });
                    }
                },
                goBack() {
                    if (this.trail.length >= 2) {
                        this.focusOn(this.trail[this.trail.length - 2].id);
                    } else {
                        this.clearFocus();
                    }
                },
                clearFocus() {
                    this.sel = null;
                    this.$wire.set('focusEntityId', null);
                    this.$dispatch('focus-loaded', { id: null });
                },
                statusText() {
                    return this.counts.nodes + ' entities · ' + this.counts.edges + ' connections'
                        + (this.truncated ? '  (capped — narrow the filters)' : '');
                },
                fit() { if (this.cy) this.cy.fit(undefined, 40); },
                focusOn(id) {
                    if (!id) return;
                    id = Number(id);
                    this.sel = null;
                    // Re-center the graph on this entity; server rebuilds and pushes graph-updated back.
                    this.$wire.set('focusEntityId', id);
                    // Keep the searchable "Start from" picker in sync (it's wire:ignore).
                    this.$dispatch('focus-loaded', { id });
                },
                render(graph) {
                    const nodes = graph.nodes || [], edges = graph.edges || [];
                    this.counts = { nodes: nodes.length, edges: edges.length };
                    this.truncated = !!(graph.meta && graph.meta.truncated);
                    this.empty = nodes.length === 0;
                    this.reconcileTrail(graph);
                    const elements = [...nodes, ...edges];

                    if (!this.cy) {
                        this.cy = cytoscape({
                            container: this.$refs.cy,
                            elements,
                            style: this.styles(),
                            layout: { name: 'cose', animate: false, padding: 30 },
                            wheelSensitivity: 0.2,
                        });
                        this.cy.on('tap', 'node', (e) => { this.sel = { kind: 'node', data: e.target.data() }; });
                        this.cy.on('tap', 'edge', (e) => { this.sel = { kind: 'edge', data: e.target.data() }; });
                        this.cy.on('tap', (e) => { if (e.target === this.cy) this.sel = null; });
                    } else {
                        this.cy.elements().remove();
                        this.cy.add(elements);
                        this.cy.layout({ name: 'cose', animate: false, padding: 30 }).run();
                    }
                },
                styles() {
                    return [
                        { selector: 'node', style: {
                            'label': 'data(label)', 'font-size': 10, 'color': '#111827',
                            'text-valign': 'center', 'text-halign': 'center', 'text-wrap': 'wrap', 'text-max-width': 90,
                            'background-color': '#64748b', 'width': 34, 'height': 34,
                            'text-outline-color': '#ffffff', 'text-outline-width': 2,
                        }},
                        { selector: 'node[kind = "person"]', style: { 'background-color': '#2563eb' }},
                        { selector: 'node[kind = "org"]', style: { 'background-color': '#16a34a', 'shape': 'round-rectangle' }},
                        { selector: 'node[?focus]', style: { 'border-width': 4, 'border-color': '#f59e0b' }},
                        { selector: 'node[sensitivity = "sealed"]', style: { 'border-width': 2, 'border-color': '#dc2626' }},
                        { selector: ':selected', style: { 'border-width': 4, 'border-color': '#0ea5e9' }},

                        { selector: 'edge', style: {
                            'label': 'data(label)', 'font-size': 8, 'color': '#6b7280', 'curve-style': 'bezier',
                            'width': 2, 'line-color': '#9ca3af', 'text-rotation': 'autorotate',
                            'text-background-color': '#ffffff', 'text-background-opacity': 0.8, 'text-background-padding': 2,
                        }},
                        { selector: 'edge[directed]', style: { 'target-arrow-shape': 'triangle', 'target-arrow-color': '#9ca3af' }},
                        { selector: 'edge[verification = "verified"]', style: { 'line-color': '#16a34a', 'target-arrow-color': '#16a34a', 'line-style': 'solid', 'width': 3 }},
                        { selector: 'edge[verification = "corroborated"]', style: { 'line-color': '#0ea5e9', 'target-arrow-color': '#0ea5e9', 'line-style': 'solid' }},
                        { selector: 'edge[verification = "reported"]', style: { 'line-color': '#d97706', 'target-arrow-color': '#d97706', 'line-style': 'dashed' }},
                        { selector: 'edge[verification = "lead"]', style: { 'line-color': '#9ca3af', 'line-style': 'dotted' }},
                        { selector: 'edge[verification = "disputed"]', style: { 'line-color': '#dc2626', 'target-arrow-color': '#dc2626', 'line-style': 'solid' }},
                        { selector: 'edge[verification = "disproven"]', style: { 'line-color': '#9ca3af', 'line-style': 'dotted' }},
                    ];
                },
            };
        };
    </script>
</x-filament-panels::page>

{{--
    Export dialog for committee delegates: pick the format, the committees (unless $committee is given) and the columns.
    Include with ['committees' => ...] or ['committee' => ...]; open it with <flux:modal.trigger name="committee-export">.
--}}
@php
    $committee ??= null;
    $columns = \App\Support\CommitteeExport::columns();
    $defaults = \App\Support\CommitteeExport::DEFAULT_COLUMNS;

    // On a single committee's page the Committee column is noise, so leave it off by default.
    if ($committee) {
        $defaults = array_values(array_diff($defaults, ['committee']));
    }
@endphp

<flux:modal name="committee-export" class="md:w-xl">
    <form
        method="GET"
        action="{{ route('staff.committees.export') }}"
        class="space-y-6"
        x-data="{
            format: 'csv',
            committees: @js($committee ? [(string) $committee->id] : $committees->map(fn ($c) => (string) $c->id)->values()),
            columns: @js($defaults),
            allColumns: @js(array_keys($columns)),
        }"
        x-bind:target="format === 'pdf' ? '_blank' : '_self'"
        x-on:submit="if (! columns.length || ! committees.length) { $event.preventDefault(); return } $nextTick(() => $flux.modal('committee-export').close())"
    >
        <div>
            <flux:heading size="lg">{{ __('Export delegates') }}</flux:heading>
            <flux:text class="mt-1">
                {{ $committee
                    ? __('The delegates allocated to :committee.', ['committee' => $committee->label()])
                    : __('The delegates allocated to each committee you pick.') }}
            </flux:text>
        </div>

        <flux:radio.group x-model="format" :label="__('Format')" variant="segmented">
            <flux:radio value="csv" icon="table-cells" :label="__('CSV (Excel)')" />
            <flux:radio value="pdf" icon="document-text" :label="__('PDF')" />
        </flux:radio.group>
        <flux:text class="-mt-4 text-xs" x-show="format === 'pdf'" x-cloak>
            {{ __('Opens a print-ready page in a new tab; choose “Save as PDF” in the print window.') }}
        </flux:text>

        @unless ($committee)
            <flux:checkbox.group x-model="committees" :label="__('Committees')">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($committees as $option)
                        <flux:checkbox value="{{ $option->id }}" :label="$option->label()" />
                    @endforeach
                </div>
            </flux:checkbox.group>
        @endunless

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <flux:label>{{ __('Columns') }}</flux:label>
                <div class="flex gap-3 text-sm">
                    <flux:link as="button" type="button" variant="subtle" x-on:click="columns = [...allColumns]">{{ __('All') }}</flux:link>
                    <flux:link as="button" type="button" variant="subtle" x-on:click="columns = []">{{ __('None') }}</flux:link>
                </div>
            </div>
            <flux:checkbox.group x-model="columns">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($columns as $key => $label)
                        <flux:checkbox value="{{ $key }}" :label="$label" />
                    @endforeach
                </div>
            </flux:checkbox.group>
            <flux:text class="text-xs text-red-600 dark:text-red-400" x-show="! columns.length" x-cloak>{{ __('Pick at least one column.') }}</flux:text>
            <flux:text class="text-xs text-red-600 dark:text-red-400" x-show="! committees.length" x-cloak>{{ __('Pick at least one committee.') }}</flux:text>
        </div>

        {{-- What actually gets sent; the Flux controls above only drive this state. --}}
        <input type="hidden" name="format" x-bind:value="format" />
        <template x-for="id in committees" x-bind:key="'c' + id">
            <input type="hidden" name="committees[]" x-bind:value="id" />
        </template>
        <template x-for="key in allColumns.filter(key => columns.includes(key))" x-bind:key="'k' + key">
            <input type="hidden" name="columns[]" x-bind:value="key" />
        </template>

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" icon="arrow-down-tray" x-bind:disabled="! columns.length || ! committees.length">
                <span x-text="format === 'pdf' ? @js(__('Open PDF')) : @js(__('Download CSV'))">{{ __('Download CSV') }}</span>
            </flux:button>
        </div>
    </form>
</flux:modal>

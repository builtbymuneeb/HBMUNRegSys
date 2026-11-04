{{-- Create or edit an ambassador task. Bound to the page's AmbassadorTaskForm $form. --}}
<flux:modal name="task-form" class="w-full md:max-w-2xl">
    <form wire:submit="saveTask" class="space-y-5">
        <flux:heading size="lg">{{ $form->task ? __('Edit task') : __('New task') }}</flux:heading>

        <flux:input wire:model="form.title" :label="__('Title')" :placeholder="__('e.g. Post the HBMUN reel on your Instagram story')" />
        <flux:textarea wire:model="form.description" :label="__('Instructions')" rows="4" :placeholder="__('What to do, and what proof to upload.')" />

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="form.points" type="number" min="1" :label="__('Points')" :description="__('Given each time it is approved.')" />
            <flux:select wire:model.live="form.audience" :label="__('For')">
                @foreach (\App\Enums\TaskAudience::cases() as $audience)
                    <flux:select.option :value="$audience->value">{{ $audience->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($form->audience === \App\Enums\TaskAudience::Selected->value)
            <flux:field>
                <flux:label>{{ __('Ambassadors') }} <span class="ms-1 text-zinc-400">({{ count($form->assignees) }})</span></flux:label>
                <div x-data="{ q: '' }" class="flex flex-col gap-2">
                    <flux:input x-model="q" icon="magnifying-glass" size="sm" :placeholder="__('Search by name or code')" />
                    <div class="max-h-56 overflow-y-auto rounded-lg border border-zinc-200 p-2 dark:border-white/10">
                        @foreach ($this->allAmbassadors as $option)
                            <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm hover:bg-zinc-50 dark:hover:bg-white/5"
                                x-show="q === '' || @js(\Illuminate\Support\Str::lower($option->name.' '.$option->code)).includes(q.toLowerCase())">
                                <input type="checkbox" wire:model="form.assignees" value="{{ $option->id }}" class="rounded">
                                <span class="flex-1">{{ $option->name }}</span>
                                <span class="text-xs text-zinc-500">{{ $option->code }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <flux:error name="form.assignees" />
            </flux:field>
        @endif

        <flux:radio.group wire:model.live="form.frequency" :label="__('How often it counts')" variant="segmented">
            <flux:radio value="once" :label="__('One time')" />
            <flux:radio value="repeat" :label="__('Repeatable')" />
        </flux:radio.group>
        @if ($form->frequency === 'repeat')
            <flux:input wire:model="form.maxCompletions" type="number" min="1" :label="__('Most times per ambassador')" :description="__('Leave blank for no limit.')" />
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="form.dueDate" type="date" :label="__('Due date (optional)')" :description="__('No more submissions after this.')" />
            <flux:input wire:model="form.dueTime" type="time" :label="__('Due time')" />
        </div>

        @unless ($form->task)
            <flux:checkbox wire:model="form.notify" :label="__('Email the ambassadors who have portal access')" />
        @endunless

        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary" data-test="save-task">{{ $form->task ? __('Save') : __('Post task') }}</flux:button>
        </div>
    </form>
</flux:modal>

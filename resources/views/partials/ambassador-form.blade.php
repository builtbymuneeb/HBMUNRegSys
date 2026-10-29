<flux:modal name="ambassador-form" class="md:w-xl">
    <form wire:submit="save" class="space-y-5">
        <div>
            <flux:heading size="lg">{{ $form->ambassador ? __('Edit campus ambassador') : __('Add campus ambassador') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Registrations that enter this code are counted for them, including any that came in before they were added.') }}</flux:text>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="form.code" :label="__('Referral code')" placeholder="HB-001" class="uppercase" />
            <flux:input wire:model="form.name" :label="__('Full name')" />
            <flux:input wire:model="form.email" type="email" :label="__('Email')" />
            <flux:input wire:model="form.phone" type="tel" :label="__('Phone (WhatsApp)')" placeholder="03XX XXXXXXX" />
            <flux:input wire:model="form.institution" :label="__('Institution')" />
            <flux:input wire:model="form.city" :label="__('City')" />
            <flux:textarea wire:model="form.notes" :label="__('Notes')" rows="2" class="sm:col-span-2" />
            <flux:textarea wire:model="form.flag_reason" :label="__('Needs checking')" :description="__('What to verify, e.g. “Not sure this number is on WhatsApp”. Leave empty when everything is fine.')" rows="2" class="sm:col-span-2" />
        </div>
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ $form->ambassador ? __('Save changes') : __('Add ambassador') }}</flux:button>
        </div>
    </form>
</flux:modal>

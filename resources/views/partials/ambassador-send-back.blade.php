{{-- The "send back" reason box, for pages using ReviewsAmbassadorSubmissions. --}}
<flux:modal name="send-back" class="w-full md:max-w-lg">
    <form wire:submit="sendBack" class="space-y-5">
        <div>
            <flux:heading size="lg">{{ __('Send back') }}</flux:heading>
            <flux:text class="mt-1">{{ __('They get no points for it and are emailed your reason. If the task is still open they can try again.') }}</flux:text>
        </div>
        <flux:textarea wire:model="sendBackReason" :label="__('Reason')" rows="3" :placeholder="__('e.g. The screenshot doesn’t show your story was posted.')" />
        <div class="flex gap-2">
            <flux:spacer />
            <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
            <flux:button type="submit" variant="danger">{{ __('Send back') }}</flux:button>
        </div>
    </form>
</flux:modal>

<?php

use App\Actions\Ambassadors\EmailAmbassadors;
use App\Actions\Ambassadors\ImportAmbassadors;
use App\Http\Controllers\AmbassadorListController;
use App\Enums\RegistrationStatus;
use App\Livewire\Forms\AmbassadorForm;
use App\Models\CampusAmbassador;
use App\Models\Registration;
use App\Notifications\AmbassadorMessage;
use App\Support\Spreadsheet;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Campus ambassadors')] class extends Component {
    use WithFileUploads;

    public AmbassadorForm $form;

    #[Url]
    public string $search = '';

    #[Url(as: 'check')]
    public bool $onlyNeedsCheck = false;

    public $sheet;

    public string $exportFrom = '';

    /** 'welcome' or 'custom'. */
    public string $messageKind = 'welcome';

    /** 'all', 'not_welcomed' or 'range'. */
    public string $messageAudience = 'all';

    public string $messageFrom = '';

    public string $messageTo = '';

    public string $messageSubject = '';

    public string $messageBody = '';

    public bool $showWhatsAppChecklist = false;

    public string $exportTo = '';

    /** @var list<list<string>> Rows read from the uploaded sheet, kept until the import is confirmed. */
    public array $sheetRows = [];

    /**
     * @return Collection<int, CampusAmbassador>
     */
    #[Computed]
    public function ambassadors(): Collection
    {
        $search = trim($this->search);

        return CampusAmbassador::query()
            ->withReferralCounts()
            ->with('whatsAppLogs')
            ->when($this->onlyNeedsCheck, fn ($query) => $query->flagged())
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('institution', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderByDesc('verified_delegates_count')
            ->orderByDesc('delegates_count')
            ->orderBy('code')
            ->get();
    }

    /**
     * Codes registrants typed that don't belong to any ambassador, with how often each was used.
     *
     * @return SupportCollection<string, int>
     */
    #[Computed]
    public function unmatchedCodes(): SupportCollection
    {
        return Registration::query()
            ->whereNull('campus_ambassador_id')
            ->whereNotNull('referral_code')
            ->selectRaw('referral_code, count(*) as total')
            ->groupBy('referral_code')
            ->orderByDesc('total')
            ->pluck('total', 'referral_code');
    }

    #[Computed]
    public function referredRegistrations(): int
    {
        return Registration::whereNotNull('campus_ambassador_id')->count();
    }

    #[Computed]
    public function referredVerifiedDelegates(): int
    {
        return Registration::query()
            ->whereNotNull('campus_ambassador_id')
            ->where('status', RegistrationStatus::Verified)
            ->withCount('delegates')
            ->get()
            ->sum('delegates_count');
    }

    /**
     * What importing the uploaded sheet would do.
     *
     * @return list<array{name: string, email: string|null, phone: string|null, institution: string|null, city: string|null, code: string, flag_reason: string|null, generated: bool, skip: string|null}>
     */
    #[Computed]
    public function importPreview(): array
    {
        return $this->sheetRows === [] ? [] : app(ImportAmbassadors::class)->preview($this->sheetRows);
    }

    /**
     * Every code in list order, for the export range pickers.
     *
     * @return list<string>
     */
    #[Computed]
    public function exportCodes(): array
    {
        return AmbassadorListController::inCodeOrder(CampusAmbassador::all(['id', 'code']))->pluck('code')->all();
    }

    #[Computed]
    public function exportCount(): int
    {
        $codes = $this->exportCodes;
        $from = array_search($this->exportFrom, $codes, true);
        $to = array_search($this->exportTo, $codes, true);

        return $from === false || $to === false ? 0 : abs($to - $from) + 1;
    }

    /**
     * Who the message dialog will reach, in code order.
     *
     * @return SupportCollection<int, CampusAmbassador>
     */
    #[Computed]
    public function messageRecipients(): SupportCollection
    {
        $all = AmbassadorListController::inCodeOrder(CampusAmbassador::with('whatsAppLogs.sender')->get());

        return match ($this->messageAudience) {
            'not_welcomed' => $all->filter(fn (CampusAmbassador $ambassador) => ! $ambassador->welcome_emailed_at && ! $ambassador->welcomeWhatsApp())->values(),
            'range' => AmbassadorListController::between($all, $this->messageFrom, $this->messageTo),
            default => $all,
        };
    }

    public function openMessage(): void
    {
        $this->reset('messageKind', 'messageAudience', 'messageSubject', 'messageBody', 'showWhatsAppChecklist');
        $this->resetValidation();
        $this->messageFrom = $this->exportCodes[0] ?? '';
        $this->messageTo = Arr::last($this->exportCodes) ?? '';
        Flux::modal('ambassador-message')->show();
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'message') && $property !== 'messageSubject') {
            $this->showWhatsAppChecklist = false;
        }
    }

    public function showChecklist(): void
    {
        if ($this->messageKind === 'custom') {
            $this->validate(['messageBody' => ['required', 'string', 'max:2000']], attributes: ['messageBody' => __('message')]);
        }

        $this->showWhatsAppChecklist = true;
    }

    public function sendEmails(EmailAmbassadors $emailAmbassadors): void
    {
        Gate::authorize('manage-ambassadors');

        if ($this->messageKind === 'custom') {
            $validated = $this->validate([
                'messageSubject' => ['required', 'string', 'max:150'],
                'messageBody' => ['required', 'string', 'max:2000'],
            ], attributes: ['messageSubject' => __('subject'), 'messageBody' => __('message')]);

            $count = $emailAmbassadors->message($this->messageRecipients, $validated['messageSubject'], $validated['messageBody'], Auth::user());
        } else {
            $count = $emailAmbassadors->welcome($this->messageRecipients, Auth::user());
        }

        Flux::modal('ambassador-message')->close();
        Flux::toast(variant: 'success', text: trans_choice(':count email is on its way.|:count emails are on their way.', $count));
    }

    /**
     * The staff route that opens WhatsApp with this dialog's message for one ambassador.
     */
    public function whatsAppLink(CampusAmbassador $ambassador): string
    {
        return $this->messageKind === 'custom'
            ? route('staff.whatsapp.ambassador.message', ['ambassador' => $ambassador, 'text' => $this->messageBody])
            : route('staff.whatsapp.ambassador.welcome', $ambassador);
    }

    public function openExport(): void
    {
        $this->exportFrom = $this->exportCodes[0] ?? '';
        $this->exportTo = Arr::last($this->exportCodes) ?? '';
        Flux::modal('ambassador-export')->show();
    }

    public function add(): void
    {
        $this->form->create();
        Flux::modal('ambassador-form')->show();
    }

    public function save(): void
    {
        Gate::authorize('manage-ambassadors');

        $ambassador = $this->form->save();

        Flux::modal('ambassador-form')->close();
        Flux::toast(variant: 'success', text: __(':name added with code :code.', ['name' => $ambassador->name, 'code' => $ambassador->code]));
    }

    public function openImport(): void
    {
        $this->reset('sheet', 'sheetRows');
        $this->resetValidation();
        Flux::modal('ambassador-import')->show();
    }

    public function updatedSheet(): void
    {
        $this->sheetRows = [];
        $this->validate(['sheet' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx']], attributes: ['sheet' => 'sheet']);

        try {
            $rows = Spreadsheet::rows($this->sheet->getRealPath(), $this->sheet->getClientOriginalExtension());
            app(ImportAmbassadors::class)->preview($rows);
            $this->sheetRows = $rows;
        } catch (RuntimeException $exception) {
            $this->addError('sheet', $exception->getMessage());
        } catch (ValidationException $exception) {
            $this->addError('sheet', $exception->validator->errors()->first('sheet'));
        }
    }

    public function import(ImportAmbassadors $importAmbassadors): void
    {
        Gate::authorize('manage-ambassadors');

        $count = $importAmbassadors->import($this->sheetRows, Auth::user());

        $this->reset('sheet', 'sheetRows');
        Flux::modal('ambassador-import')->close();
        Flux::toast(variant: 'success', text: trans_choice(':count ambassador imported.|:count ambassadors imported.', $count));
    }
}; ?>

<div class="flex w-full flex-col gap-6 p-6 lg:p-8">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Campus ambassadors') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Each ambassador has a referral code. Registrations that enter it are counted here.') }}</flux:text>
        </div>
        <div class="flex flex-wrap gap-2">
            <flux:button icon="paper-airplane" wire:click="openMessage">{{ __('Message ambassadors') }}</flux:button>
            <flux:button icon="document-arrow-down" wire:click="openExport">{{ __('Export list') }}</flux:button>
            <flux:button icon="arrow-up-tray" wire:click="openImport">{{ __('Import from sheet') }}</flux:button>
            <flux:button icon="plus" variant="primary" wire:click="add">{{ __('Add ambassador') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Ambassadors') }}</flux:text>
            <flux:heading size="xl">{{ CampusAmbassador::count() }}</flux:heading>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Referred registrations') }}</flux:text>
            <flux:heading size="xl">{{ $this->referredRegistrations }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('delegations, any status') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Referred verified delegates') }}</flux:text>
            <flux:heading size="xl">{{ $this->referredVerifiedDelegates }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('people in verified delegations') }}</flux:text>
        </flux:card>
        <flux:card class="flex flex-col gap-1">
            <flux:text>{{ __('Unmatched codes') }}</flux:text>
            <flux:heading size="xl">{{ $this->unmatchedCodes->sum() }}</flux:heading>
            <flux:text size="sm" class="text-zinc-400">{{ __('registrations with a code no ambassador has') }}</flux:text>
        </flux:card>
        @php($needsCheckCount = CampusAmbassador::flagged()->count())
        <button type="button" wire:click="$toggle('onlyNeedsCheck')" @class(['text-left rounded-xl', 'ring-2 ring-amber-400' => $onlyNeedsCheck])>
            <flux:card class="flex h-full flex-col gap-1">
                <flux:text>{{ __('Needs checking') }}</flux:text>
                <flux:heading size="xl" @class(['text-amber-400' => $needsCheckCount])>{{ $needsCheckCount }}</flux:heading>
                <flux:text size="sm" class="text-zinc-400">{{ $onlyNeedsCheck ? __('showing only these · click to show all') : __('details to verify · click to filter') }}</flux:text>
            </flux:card>
        </button>
    </div>

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search code, name, institution, phone, email…')" class="max-w-xl" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Code') }}</flux:table.column>
            <flux:table.column>{{ __('Ambassador') }}</flux:table.column>
            <flux:table.column>{{ __('Phone') }}</flux:table.column>
            <flux:table.column>{{ __('Welcome') }}</flux:table.column>
            <flux:table.column>{{ __('Registrations') }}</flux:table.column>
            <flux:table.column>{{ __('Delegates') }}</flux:table.column>
            <flux:table.column>{{ __('Verified delegates') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->ambassadors as $ambassador)
                <flux:table.row :key="$ambassador->id">
                    <flux:table.cell variant="strong">{{ $ambassador->code }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2 font-medium text-zinc-800 dark:text-white">
                            {{ $ambassador->name }}
                            @if ($ambassador->needsCheck())
                                <flux:badge size="sm" color="amber" icon="exclamation-triangle" inset="top bottom">{{ __('Check') }}</flux:badge>
                            @endif
                        </div>
                        <div class="text-xs">{{ collect([$ambassador->institution, $ambassador->city])->filter()->implode(', ') }}</div>
                        @if ($ambassador->needsCheck())
                            <div class="mt-0.5 max-w-md text-xs whitespace-normal text-amber-500 dark:text-amber-400">{{ $ambassador->flag_reason }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $ambassador->phone }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex gap-1">
                            <flux:badge size="sm" icon="envelope" inset="top bottom" :color="$ambassador->welcome_emailed_at ? 'green' : 'zinc'"
                                :title="$ambassador->welcome_emailed_at ? __('Welcome email sent :time', ['time' => $ambassador->welcome_emailed_at->timezone('Asia/Karachi')->format('j M, g:i a')]) : __('Welcome email not sent')">{{ $ambassador->welcome_emailed_at ? '✓' : '–' }}</flux:badge>
                            <flux:badge size="sm" icon="chat-bubble-oval-left-ellipsis" inset="top bottom" :color="$ambassador->welcomeWhatsApp() ? 'green' : 'zinc'"
                                :title="$ambassador->welcomeWhatsApp() ? __('WhatsApp welcome opened :time', ['time' => $ambassador->welcomeWhatsApp()->created_at->timezone('Asia/Karachi')->format('j M, g:i a')]) : __('WhatsApp welcome not sent')">{{ $ambassador->welcomeWhatsApp() ? '✓' : '–' }}</flux:badge>
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $ambassador->registrations_count }}</flux:table.cell>
                    <flux:table.cell>{{ $ambassador->delegates_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$ambassador->verified_delegates_count ? 'green' : 'zinc'" inset="top bottom">{{ $ambassador->verified_delegates_count }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" :href="route('staff.ambassadors.show', $ambassador)" wire:navigate>{{ __('View') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8" class="py-10 text-center">
                        {{ trim($search) !== '' || $onlyNeedsCheck ? __('No ambassadors match this search.') : __('No campus ambassadors yet. Add one, or import your sign-up sheet.') }}
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($this->unmatchedCodes->isNotEmpty())
        <flux:card class="flex flex-col gap-3">
            <div>
                <flux:heading>{{ __('Codes that don’t match an ambassador') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Typos, or ambassadors not added yet. Adding an ambassador with one of these codes links these registrations automatically.') }}</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($this->unmatchedCodes as $code => $total)
                    @can('viewAny', \App\Models\Registration::class)
                        <flux:badge :href="route('staff.registrations.index', ['status' => '', 'search' => $code])" wire:navigate>{{ $code }} · {{ $total }}</flux:badge>
                    @else
                        <flux:badge>{{ $code }} · {{ $total }}</flux:badge>
                    @endcan
                @endforeach
            </div>
        </flux:card>
    @endif

    @include('partials.ambassador-form')

    <flux:modal name="ambassador-message" class="w-full md:max-w-2xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Message campus ambassadors') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Email goes out to everyone at once. WhatsApp can’t send in bulk, so you get a checklist: open each chat, press send, and tick through the list.') }}</flux:text>
            </div>

            <flux:radio.group wire:model.live="messageKind" :label="__('Message')" variant="segmented">
                <flux:radio value="welcome" :label="__('Welcome (code + link)')" />
                <flux:radio value="custom" :label="__('Write your own')" />
            </flux:radio.group>

            @if ($messageKind === 'custom')
                <flux:input wire:model="messageSubject" :label="__('Subject (email only)')" />
                <flux:textarea wire:model.live.debounce.400ms="messageBody" :label="__('Message')" rows="6"
                    :description="__('Each one starts with “Hello <first name>,”. You can use :placeholders.', ['placeholders' => collect(AmbassadorMessage::PLACEHOLDERS)->map(fn ($meaning, $tag) => $tag.' ('.$meaning.')')->implode(', ')])" />
            @else
                <flux:text class="text-sm">{{ __('Welcomes them to the team with their referral code and their own registration link that fills the code in.') }}</flux:text>
            @endif

            <flux:radio.group wire:model.live="messageAudience" :label="__('Send to')">
                <flux:radio value="all" :label="__('Everyone')" />
                <flux:radio value="not_welcomed" :label="__('Only those who haven’t had the welcome yet (by email or WhatsApp)')" />
                <flux:radio value="range" :label="__('A range of codes')" />
            </flux:radio.group>

            @if ($messageAudience === 'range')
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="messageFrom" :label="__('From')">
                        @foreach ($this->exportCodes as $code)
                            <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="messageTo" :label="__('To')">
                        @foreach ($this->exportCodes as $code)
                            <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            @php($withEmail = $this->messageRecipients->filter(fn ($ambassador) => filled($ambassador->email))->count())
            <flux:text>
                {{ trans_choice(':count ambassador selected.|:count ambassadors selected.', $this->messageRecipients->count()) }}
                @if ($withEmail < $this->messageRecipients->count())
                    {{ trans_choice(':count has no email address.|:count have no email address.', $this->messageRecipients->count() - $withEmail) }}
                @endif
            </flux:text>

            @if ($showWhatsAppChecklist)
                <div class="max-h-80 overflow-auto rounded-lg border border-white/10" data-test="whatsapp-checklist">
                    @forelse ($this->messageRecipients as $ambassador)
                        @php($sent = $messageKind === 'welcome' ? $ambassador->welcomeWhatsApp() : null)
                        <div wire:key="wa-{{ $ambassador->id }}" class="flex items-center justify-between gap-3 border-b border-white/5 px-3 py-2 last:border-0" x-data="{ opened: false }">
                            <div class="min-w-0 text-sm">
                                <span class="font-medium text-zinc-800 dark:text-white">{{ $ambassador->code }} · {{ $ambassador->name }}</span>
                                <span class="text-xs text-zinc-400">{{ $ambassador->phone }}</span>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span x-show="opened" x-cloak class="text-xs text-green-500">✓ {{ __('Opened') }}</span>
                                @if ($sent)
                                    <span x-show="! opened" class="text-xs text-green-600 dark:text-green-400" title="{{ __('Opened by :name', ['name' => $sent->sender?->name ?? __('a former staff member')]) }}">✓ {{ $sent->created_at->timezone('Asia/Karachi')->format('j M') }}</span>
                                @endif
                                @if (\App\Support\WhatsApp::number($ambassador->phone))
                                    <flux:button size="xs" icon="chat-bubble-oval-left-ellipsis" :href="$this->whatsAppLink($ambassador)" target="_blank" rel="noopener" x-on:click="opened = true">{{ __('Open') }}</flux:button>
                                @else
                                    <span class="text-xs text-zinc-500">{{ __('No WhatsApp number') }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-3 py-6 text-center text-sm text-zinc-400">{{ __('Nobody to message.') }}</div>
                    @endforelse
                </div>
            @endif

            <div class="flex flex-wrap gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Close') }}</flux:button>
                </flux:modal.close>
                @unless ($showWhatsAppChecklist)
                    <flux:button icon="chat-bubble-oval-left-ellipsis" wire:click="showChecklist" :disabled="$this->messageRecipients->isEmpty()">{{ __('WhatsApp checklist') }}</flux:button>
                @endunless
                <flux:button variant="primary" icon="envelope" wire:click="sendEmails" :disabled="$withEmail === 0"
                    wire:confirm="{{ trans_choice('Send this email to :count ambassador?|Send this email to :count ambassadors?', $withEmail) }}">{{ trans_choice('Email :count ambassador|Email :count ambassadors', $withEmail) }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="ambassador-export" class="w-full md:max-w-lg">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Export campus ambassador list') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Opens the branded A4 list (code, name, campus) ready to save as a PDF. Pick a range to export only part of it.') }}</flux:text>
            </div>

            @if ($this->exportCodes === [])
                <flux:text>{{ __('Add some ambassadors first.') }}</flux:text>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="exportFrom" :label="__('From')">
                        @foreach ($this->exportCodes as $code)
                            <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="exportTo" :label="__('To')">
                        @foreach ($this->exportCodes as $code)
                            <flux:select.option :value="$code">{{ $code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:text>
                    {{ trans_choice(':count ambassador will be included.|:count ambassadors will be included.', $this->exportCount) }}
                    <flux:button size="xs" variant="ghost" wire:click="openExport" class="ms-1">{{ __('Select all') }}</flux:button>
                </flux:text>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                @if ($this->exportCodes !== [])
                    <flux:button variant="primary" icon="document-arrow-down" target="_blank"
                        :href="route('staff.ambassadors.list', ['from' => $exportFrom, 'to' => $exportTo, 'print' => 1])">{{ __('Open & save as PDF') }}</flux:button>
                @endif
            </div>
        </div>
    </flux:modal>

    <flux:modal name="ambassador-import" class="w-full md:max-w-3xl">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Import campus ambassadors') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Upload the sign-up sheet as .xlsx or .csv. In Google Sheets: File → Download → Microsoft Excel or CSV. The first row must hold the headings; name, email, phone, institution, city and code columns are picked up by their headings. Anyone without a code gets the next HB## code. Rows with a phone or email that looks wrong, or with something in a “note” or “status” column, are flagged for checking.') }}
                </flux:text>
            </div>

            <flux:input type="file" wire:model="sheet" :label="__('Sheet')" accept=".csv,.xlsx" />

            <div wire:loading wire:target="sheet" class="text-sm text-zinc-400">{{ __('Reading the sheet…') }}</div>

            @if ($this->importPreview !== [])
                @php($ready = collect($this->importPreview)->whereNull('skip'))
                <flux:text>
                    {{ trans_choice(':count new ambassador will be added.|:count new ambassadors will be added.', $ready->count()) }}
                    @if ($flagged = $ready->whereNotNull('flag_reason')->count())
                        {{ trans_choice(':count will be flagged for checking.|:count will be flagged for checking.', $flagged) }}
                    @endif
                    @if ($skipped = count($this->importPreview) - $ready->count())
                        {{ trans_choice(':count row will be skipped.|:count rows will be skipped.', $skipped) }}
                    @endif
                </flux:text>

                <div class="max-h-80 overflow-auto rounded-lg border border-white/10">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-zinc-900 text-xs text-zinc-400">
                            <tr>
                                <th class="px-3 py-2 font-medium">{{ __('Code') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Name') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Institution') }}</th>
                                <th class="px-3 py-2 font-medium">{{ __('Phone') }}</th>
                                <th class="px-3 py-2 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach ($this->importPreview as $index => $entry)
                                <tr wire:key="preview-{{ $index }}" @class(['opacity-50' => $entry['skip']])>
                                    <td class="px-3 py-2 font-medium whitespace-nowrap">{{ $entry['code'] ?: '—' }}</td>
                                    <td class="px-3 py-2">{{ $entry['name'] ?: '—' }}<div class="text-xs text-zinc-400">{{ $entry['email'] }}</div></td>
                                    <td class="px-3 py-2">{{ $entry['institution'] }}</td>
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $entry['phone'] }}</td>
                                    <td class="px-3 py-2 text-xs">
                                        @if ($entry['skip'])
                                            <span class="whitespace-nowrap text-amber-400">{{ __('Skipped: :reason', ['reason' => $entry['skip']]) }}</span>
                                        @else
                                            @if ($entry['generated'])
                                                <div class="whitespace-nowrap text-zinc-400">{{ __('New code') }}</div>
                                            @endif
                                            @if ($entry['flag_reason'])
                                                <div class="text-amber-400">{{ __('Check: :reason', ['reason' => $entry['flag_reason']]) }}</div>
                                            @endif
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="import" :disabled="collect($this->importPreview)->whereNull('skip')->isEmpty()">{{ __('Import') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>

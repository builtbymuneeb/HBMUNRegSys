<?php

use App\Mail\InboxEmail;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Models\InboxMessage;
use App\Models\Registration;
use App\Models\User;
use App\Support\SafeHtml;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The Gmail-style "New email" window on the staff Inbox: To, Cc and Bcc with address suggestions,
 * a subject, formatted text, attachments and a saved draft.
 */
new class extends Component {
    use WithFileUploads;

    /** Gmail's limit for everything attached to one email, in kilobytes. */
    public const MAX_ATTACHMENTS_KB = 25 * 1024;

    public const MAX_RECIPIENTS = 50;

    /** @var array<int, string> */
    public array $to = [];

    /** @var array<int, string> */
    public array $cc = [];

    /** @var array<int, string> */
    public array $bcc = [];

    public string $subject = '';

    public string $body = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    /** @var array<int, TemporaryUploadedFile> Files just picked, moved into $attachments once checked. */
    public array $uploads = [];

    public function updatedUploads(): void
    {
        $this->validate(['uploads.*' => ['file', 'max:'.self::MAX_ATTACHMENTS_KB]], attributes: ['uploads.*' => __('attachment')]);

        $this->attachments = [...$this->attachments, ...$this->uploads];
        $this->uploads = [];
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function discard(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    /**
     * People to suggest as the sender types in To, Cc or Bcc: anyone already in the inbox,
     * campus ambassadors, and (for conference managers) registrations and portal users.
     *
     * @return array<int, array{email: string, name: ?string}>
     */
    #[Renderless]
    public function suggestions(string $query): array
    {
        Gate::authorize('use-inbox');

        $query = trim($query);

        if (mb_strlen($query) < 2) {
            return [];
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';
        $match = fn ($builder, string $nameColumn, string $emailColumn) => $builder
            ->where(fn ($builder) => $builder->where($nameColumn, 'like', $like)->orWhere($emailColumn, 'like', $like))
            ->limit(8)
            ->get([$nameColumn.' as name', $emailColumn.' as email']);

        $people = collect()
            ->when(Auth::user()->can('manage-ambassadors'), fn ($people) => $people->concat($match(CampusAmbassador::query(), 'name', 'email')))
            ->concat($match(InboxMessage::visibleTo(Auth::user())->whereNotNull('name'), 'name', 'email'));

        if (Auth::user()->canManageConference()) {
            $people = $people
                ->concat($match(Registration::query()->latest('id'), 'contact_name', 'contact_email'))
                ->concat($match(User::query(), 'name', 'email'))
                ->concat($match(InboxMessage::query()->whereNull('name'), 'email', 'email'));
        }

        return $people
            ->map(fn ($person) => ['email' => Str::lower(trim((string) $person->email)), 'name' => $person->name && $person->name !== $person->email ? $person->name : null])
            ->filter(fn (array $person) => filter_var($person['email'], FILTER_VALIDATE_EMAIL))
            ->unique('email')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * Send the email and add it to each recipient's conversation. Returns whether it went.
     */
    public function send(): bool
    {
        Gate::authorize('use-inbox');

        $this->to = $this->normalise($this->to);
        $this->cc = array_values(array_diff($this->normalise($this->cc), $this->to));
        $this->bcc = array_values(array_diff($this->normalise($this->bcc), $this->to, $this->cc));
        $recipients = [...$this->to, ...$this->cc, ...$this->bcc];
        $html = SafeHtml::clean($this->body);

        $this->validate([
            'to' => ['array'],
            'to.*' => ['email:rfc'],
            'cc.*' => ['email:rfc'],
            'bcc.*' => ['email:rfc'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:200000'],
        ], [
            'to.*.email' => __(':input isn’t a valid email address.'),
            'cc.*.email' => __(':input isn’t a valid email address.'),
            'bcc.*.email' => __(':input isn’t a valid email address.'),
        ]);

        $fail = fn (string $message) => throw ValidationException::withMessages(['to' => $message]);

        if ($recipients === []) {
            $fail(__('Add at least one recipient.'));
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            $fail(__('An email can go to at most :max people.', ['max' => self::MAX_RECIPIENTS]));
        }

        if ($html === '' && $this->attachments === []) {
            throw ValidationException::withMessages(['body' => __('Write a message or attach a file.')]);
        }

        if (array_sum(array_map(fn (TemporaryUploadedFile $file) => $file->getSize(), $this->attachments)) > self::MAX_ATTACHMENTS_KB * 1024) {
            throw ValidationException::withMessages(['attachments' => __('Attachments can be 25 MB at most in total.')]);
        }

        $ambassadors = CampusAmbassador::whereIn(DB::raw('lower(email)'), $recipients)->get(['id', 'name', 'email'])->keyBy(fn ($ambassador) => Str::lower($ambassador->email));

        if (! Auth::user()->canSeeWholeInbox() && ($others = array_diff($recipients, $ambassadors->keys()->all()))) {
            $fail(__('You can only email campus ambassadors. :email isn’t one.', ['email' => reset($others)]));
        }

        $subject = trim($this->subject) ?: __('(no subject)');
        $messageId = '<'.Str::uuid().'@'.(Str::after((string) config('mail.from.address'), '@') ?: 'hbmun.info').'>';

        try {
            Mail::to($this->to)->cc($this->cc)->bcc($this->bcc)->send(new InboxEmail($subject, $html, $messageId, $this->attachments));
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['to' => __('The mail server didn’t accept the email, so it wasn’t sent. Try again in a minute.')]);
        }

        $text = SafeHtml::toText($html);

        if ($this->attachments !== []) {
            $text = trim($text."\n\n".__('Attached: :files', ['files' => collect($this->attachments)->map->getClientOriginalName()->join(', ')]));
        }

        $sent = collect($recipients)->map(fn (string $email, int $index) => InboxMessage::create([
            'direction' => InboxMessage::OUT,
            'message_id' => $index === 0 ? $messageId : null,
            'email' => $email,
            'name' => $ambassadors[$email]->name
                ?? Registration::whereRaw('lower(contact_email) = ?', [$email])->latest('id')->value('contact_name')
                ?? InboxMessage::where('email', $email)->whereNotNull('name')->latest('id')->value('name'),
            'subject' => Str::limit($subject, 255, ''),
            'body' => $text,
            'html' => $html ?: null,
            'received_at' => now(),
            'read_at' => now(),
            'campus_ambassador_id' => $ambassadors[$email]->id ?? null,
            'registration_id' => Registration::whereRaw('lower(contact_email) = ?', [$email])->latest('id')->value('id'),
            'sent_by' => Auth::id(),
        ]));

        AuditLog::record('inbox_sent', $sent->first(), Auth::user(), array_filter([
            'to' => $this->to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'subject' => $subject,
            'attachments' => count($this->attachments) ?: null,
        ]));

        $this->discard();
        $this->dispatch('inbox-sent');
        Flux::toast(variant: 'success', text: __('Message sent.'));

        return true;
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, string>
     */
    private function normalise(array $emails): array
    {
        return collect($emails)->map(fn ($email) => Str::lower(trim((string) $email)))->filter()->unique()->values()->all();
    }
}; ?>

<div
    x-data="{
        open: false,
        minimized: false,
        fullscreen: false,
        showCc: false,
        showBcc: false,
        formatting: true,
        sending: false,
        uploading: false,
        progress: 0,
        linkOpen: false,
        linkUrl: '',
        savedRange: null,
        draftSaved: false,
        names: {},
        key: 'hbmun-inbox-draft-{{ auth()->id() }}',

        init() {
            try {
                const draft = JSON.parse(localStorage.getItem(this.key) || 'null');
                if (draft) {
                    $wire.to = draft.to || [];
                    $wire.cc = draft.cc || [];
                    $wire.bcc = draft.bcc || [];
                    $wire.subject = draft.subject || '';
                    $wire.body = draft.body || '';
                    this.names = draft.names || {};
                    this.showCc = $wire.cc.length > 0;
                    this.showBcc = $wire.bcc.length > 0;
                    this.$refs.editor.innerHTML = $wire.body;
                }
            } catch (e) {}
        },

        compose(detail = {}) {
            if (detail.to && ! this.hasContent()) {
                $wire.to = [].concat(detail.to);
                if (detail.name) { this.names[[].concat(detail.to)[0]] = detail.name }
            }
            this.open = true;
            this.minimized = false;
            this.$nextTick(() => ($wire.to.length ? this.$refs.subject : this.$el.querySelector('[data-recipient-input=to]'))?.focus());
        },

        hasContent() {
            return $wire.to.length || $wire.cc.length || $wire.bcc.length || $wire.subject.trim() || this.$refs.editor.innerText.trim();
        },

        title() {
            return $wire.subject.trim() || @js(__('New message'));
        },

        save() {
            $wire.body = this.$refs.editor.innerHTML;
            if (! this.hasContent()) { localStorage.removeItem(this.key); this.draftSaved = false; return }
            localStorage.setItem(this.key, JSON.stringify({ to: $wire.to, cc: $wire.cc, bcc: $wire.bcc, subject: $wire.subject, body: $wire.body, names: this.names }));
            this.draftSaved = true;
        },

        close() {
            this.save();
            this.open = false;
            this.fullscreen = false;
        },

        async discard() {
            localStorage.removeItem(this.key);
            this.$refs.editor.innerHTML = '';
            this.names = {};
            this.showCc = this.showBcc = this.fullscreen = this.open = this.draftSaved = false;
            await $wire.discard();
        },

        async send() {
            if (this.sending || this.uploading) return;
            this.$el.querySelectorAll('[data-recipient-input]').forEach(input => input.dispatchEvent(new CustomEvent('commit')));
            $wire.body = this.$refs.editor.innerHTML;
            if (! $wire.subject.trim() && this.$refs.editor.innerText.trim() && ! confirm(@js(__('Send this message without a subject?')))) return;
            this.sending = true;
            try {
                if (await $wire.send()) {
                    localStorage.removeItem(this.key);
                    this.$refs.editor.innerHTML = '';
                    this.names = {};
                    this.showCc = this.showBcc = this.fullscreen = this.open = this.draftSaved = false;
                }
            } finally {
                this.sending = false;
            }
        },

        format(command, value = null) {
            this.$refs.editor.focus();
            document.execCommand(command, false, value);
            this.save();
        },

        openLink() {
            const selection = window.getSelection();
            this.savedRange = selection.rangeCount && this.$refs.editor.contains(selection.anchorNode) ? selection.getRangeAt(0).cloneRange() : null;
            this.linkUrl = '';
            this.linkOpen = true;
            this.$nextTick(() => this.$refs.linkInput.focus());
        },

        insertLink() {
            let url = this.linkUrl.trim();
            this.linkOpen = false;
            if (! url) return;
            if (! /^(https?:\/\/|mailto:)/i.test(url)) url = url.includes('@') && ! url.includes('/') ? 'mailto:' + url : 'https://' + url;
            this.$refs.editor.focus();
            const selection = window.getSelection();
            if (this.savedRange) { selection.removeAllRanges(); selection.addRange(this.savedRange) }
            if (selection.isCollapsed || ! this.savedRange) {
                const link = document.createElement('a');
                link.href = url;
                link.textContent = url.replace(/^mailto:/i, '');
                document.execCommand('insertHTML', false, link.outerHTML + '&nbsp;');
            } else {
                document.execCommand('createLink', false, url);
            }
            this.save();
        },

        size(bytes) {
            return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' KB';
        },
    }"
    x-on:compose-email.window="compose($event.detail || {})"
    x-on:keydown.ctrl.enter="open && send()"
    x-on:keydown.meta.enter="open && send()"
>
    {{-- Full-screen backdrop --}}
    <div x-show="open && fullscreen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/40" x-on:click="fullscreen = false"></div>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="translate-y-4 opacity-0"
        x-transition:enter-end="translate-y-0 opacity-100"
        role="dialog"
        aria-label="{{ __('New message') }}"
        x-bind:class="fullscreen
            ? 'fixed inset-0 z-50 m-auto h-[88vh] w-[min(1000px,94vw)] rounded-xl'
            : (minimized
                ? 'fixed bottom-0 right-4 z-50 h-auto w-72 rounded-t-xl sm:right-6'
                : 'fixed inset-0 z-50 sm:inset-auto sm:bottom-0 sm:right-6 sm:h-[min(600px,calc(100vh-5rem))] sm:w-[560px] sm:rounded-t-xl')"
        class="flex flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-black/10 dark:bg-zinc-900 dark:ring-white/10"
    >
        {{-- Title bar --}}
        <div class="flex shrink-0 cursor-pointer items-center gap-1 bg-zinc-100 py-2 pr-2 pl-4 dark:bg-zinc-800" x-on:click="if (! fullscreen) minimized = ! minimized">
            <span class="flex-1 truncate text-sm font-medium text-zinc-800 dark:text-zinc-100" x-text="title()"></span>
            <button type="button" class="rounded p-1 text-zinc-600 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click.stop="fullscreen = false; minimized = ! minimized" x-bind:title="minimized ? @js(__('Open')) : @js(__('Minimise'))">
                <flux:icon.minus variant="micro" x-show="! minimized" />
                <flux:icon.chevron-up variant="micro" x-show="minimized" x-cloak />
            </button>
            <button type="button" class="hidden rounded p-1 text-zinc-600 hover:bg-zinc-200 sm:block dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click.stop="minimized = false; fullscreen = ! fullscreen" x-bind:title="fullscreen ? @js(__('Exit full screen')) : @js(__('Full screen'))">
                <flux:icon.arrows-pointing-out variant="micro" x-show="! fullscreen" />
                <flux:icon.arrows-pointing-in variant="micro" x-show="fullscreen" x-cloak />
            </button>
            <button type="button" class="rounded p-1 text-zinc-600 hover:bg-zinc-200 dark:text-zinc-300 dark:hover:bg-zinc-700" x-on:click.stop="close()" title="{{ __('Save draft and close') }}">
                <flux:icon.x-mark variant="micro" />
            </button>
        </div>

        <div x-show="! minimized" class="flex min-h-0 flex-1 flex-col">
            {{-- From --}}
            <div class="mx-4 flex items-center gap-3 border-b border-zinc-200 py-2 text-sm dark:border-zinc-700">
                <span class="text-zinc-500">{{ __('From') }}</span>
                <span class="truncate text-zinc-700 dark:text-zinc-200">{{ config('mail.from.name') }} &lt;{{ config('mail.from.address') }}&gt;</span>
            </div>

            {{-- To, Cc, Bcc --}}
            @foreach (['to' => __('To'), 'cc' => __('Cc'), 'bcc' => __('Bcc')] as $field => $label)
                <div
                    wire:key="recipient-{{ $field }}"
                    @if ($field !== 'to') x-show="{{ $field === 'cc' ? 'showCc' : 'showBcc' }}" x-cloak @endif
                    x-data="{
                        field: @js($field),
                        query: '',
                        results: [],
                        active: 0,
                        timer: null,
                        focused: false,
                        valid(email) { return /^[^\s@<>,;]+@[^\s@<>,;]+\.[^\s@<>,;]+$/.test(email) },
                        add(raw) {
                            const found = raw.match(/[^\s<>,;:&quot;']+@[^\s<>,;:&quot;']+/g) || [];
                            const typed = (found.length ? found : [raw.trim()]).map(email => email.toLowerCase()).filter(Boolean);
                            if (typed.length) $wire[this.field] = [...new Set([...$wire[this.field], ...typed])];
                            this.query = '';
                            this.results = [];
                            save();
                        },
                        pick(person) {
                            if (person.name) names[person.email] = person.name;
                            $wire[this.field] = [...new Set([...$wire[this.field], person.email])];
                            this.query = '';
                            this.results = [];
                            save();
                            this.$refs.input.focus();
                        },
                        remove(email) {
                            $wire[this.field] = $wire[this.field].filter(item => item !== email);
                            save();
                        },
                        search() {
                            clearTimeout(this.timer);
                            if (/[,;\n]/.test(this.query)) { this.add(this.query); return }
                            if (this.query.trim().length < 2) { this.results = []; return }
                            const asked = this.query;
                            this.timer = setTimeout(async () => {
                                const results = await $wire.suggestions(asked);
                                if (asked === this.query) { this.results = results.filter(person => ! $wire[this.field].includes(person.email)); this.active = 0 }
                            }, 200);
                        },
                        keydown(event) {
                            if (event.key === 'ArrowDown' && this.results.length) { event.preventDefault(); this.active = (this.active + 1) % this.results.length }
                            else if (event.key === 'ArrowUp' && this.results.length) { event.preventDefault(); this.active = (this.active + this.results.length - 1) % this.results.length }
                            else if (event.key === 'Escape' && this.results.length) { event.stopPropagation(); this.results = [] }
                            else if (['Enter', 'Tab', ',', ';'].includes(event.key) && ! (event.ctrlKey || event.metaKey)) {
                                if (this.results.length && event.key !== ',' && event.key !== ';') { event.preventDefault(); this.pick(this.results[this.active]) }
                                else if (this.query.trim()) { event.preventDefault(); this.add(this.query) }
                            }
                            else if (event.key === 'Backspace' && this.query === '' && $wire[this.field].length) {
                                this.remove($wire[this.field][$wire[this.field].length - 1]);
                            }
                        },
                    }"
                    class="relative mx-4 flex items-start gap-2 border-b border-zinc-200 py-1.5 text-sm dark:border-zinc-700"
                    x-on:click="$refs.input.focus()"
                >
                    <span class="py-1 text-zinc-500">{{ $label }}</span>
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                        <template x-for="email in $wire[field]" x-bind:key="email">
                            <span
                                x-bind:title="email"
                                x-bind:class="valid(email) ? 'bg-zinc-100 text-zinc-800 ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-600' : 'bg-red-50 text-red-700 ring-red-300 dark:bg-red-950 dark:text-red-200 dark:ring-red-800'"
                                class="inline-flex max-w-full items-center gap-1.5 rounded-full py-0.5 pr-1 pl-0.5 ring-1"
                            >
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-hb-purple text-[10px] font-semibold text-white uppercase" x-text="(names[email] || email).charAt(0)"></span>
                                <span class="truncate" x-text="names[email] || email"></span>
                                <button type="button" class="rounded-full p-0.5 hover:bg-black/10 dark:hover:bg-white/10" x-on:click.stop="remove(email)" aria-label="{{ __('Remove') }}">
                                    <flux:icon.x-mark variant="micro" class="size-3" />
                                </button>
                            </span>
                        </template>
                        <input
                            x-ref="input"
                            data-recipient-input="{{ $field }}"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            aria-label="{{ $label }}"
                            class="min-w-32 flex-1 border-0 bg-transparent py-1 text-sm text-zinc-800 outline-none focus:ring-0 dark:text-zinc-100"
                            x-model="query"
                            x-on:input="search()"
                            x-on:keydown="keydown($event)"
                            x-on:focus="focused = true"
                            x-on:blur="focused = false; setTimeout(() => { if (! focused) { query.trim() && add(query); results = [] } }, 150)"
                            x-on:commit="query.trim() && add(query)"
                            x-on:paste="setTimeout(() => /[,;\s]/.test(query.trim()) && add(query))"
                        />
                    </div>
                    @if ($field === 'to')
                        <div class="flex gap-2 py-1 text-zinc-600 dark:text-zinc-300">
                            <button type="button" class="hover:underline" x-show="! showCc" x-on:click.stop="showCc = true; $nextTick(() => $el.closest('[role=dialog]').querySelector('[data-recipient-input=cc]').focus())">{{ __('Cc') }}</button>
                            <button type="button" class="hover:underline" x-show="! showBcc" x-on:click.stop="showBcc = true; $nextTick(() => $el.closest('[role=dialog]').querySelector('[data-recipient-input=bcc]').focus())">{{ __('Bcc') }}</button>
                        </div>
                    @endif

                    {{-- Suggestions --}}
                    <div x-show="results.length" x-cloak class="absolute top-full right-0 left-10 z-10 mt-1 max-h-72 overflow-y-auto rounded-lg bg-white py-1 shadow-lg ring-1 ring-black/10 dark:bg-zinc-800 dark:ring-white/10">
                        <template x-for="(person, index) in results" x-bind:key="person.email">
                            <button
                                type="button"
                                class="flex w-full items-center gap-3 px-3 py-2 text-left"
                                x-bind:class="index === active ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                                x-on:mouseenter="active = index"
                                x-on:mousedown.prevent="pick(person)"
                            >
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-hb-purple text-xs font-semibold text-white uppercase" x-text="(person.name || person.email).charAt(0)"></span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm text-zinc-800 dark:text-zinc-100" x-text="person.name || person.email"></span>
                                    <span class="block truncate text-xs text-zinc-500" x-show="person.name" x-text="person.email"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>
            @endforeach

            {{-- Subject --}}
            <div class="mx-4 border-b border-zinc-200 dark:border-zinc-700">
                <input
                    x-ref="subject"
                    type="text"
                    wire:model="subject"
                    x-on:input.debounce.500ms="save()"
                    maxlength="255"
                    placeholder="{{ __('Subject') }}"
                    aria-label="{{ __('Subject') }}"
                    class="w-full border-0 bg-transparent px-0 py-2 text-sm text-zinc-800 outline-none placeholder:text-zinc-500 focus:ring-0 dark:text-zinc-100"
                />
            </div>

            @if ($errors->any())
                <div class="mx-4 mt-2 flex items-start gap-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-200" role="alert">
                    <flux:icon.exclamation-circle variant="mini" class="mt-0.5 shrink-0" />
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            {{-- Message --}}
            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3" x-on:click.self="$refs.editor.focus()">
                <div
                    wire:ignore
                    x-ref="editor"
                    contenteditable="true"
                    role="textbox"
                    aria-multiline="true"
                    aria-label="{{ __('Message') }}"
                    data-placeholder="{{ __('Write your message…') }}"
                    x-on:input.debounce.400ms="save()"
                    x-on:keydown.ctrl.k.prevent="openLink()"
                    x-on:keydown.meta.k.prevent="openLink()"
                    class="min-h-32 text-sm leading-relaxed text-zinc-800 outline-none empty:before:text-zinc-400 empty:before:content-[attr(data-placeholder)] dark:text-zinc-100 [&_a]:text-blue-600 [&_a]:underline dark:[&_a]:text-blue-400 [&_blockquote]:border-l-2 [&_blockquote]:border-zinc-300 [&_blockquote]:pl-3 [&_blockquote]:text-zinc-600 [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6"
                ></div>
                <div class="mt-4 text-sm text-zinc-500" title="{{ __('Added to the end of every email') }}">
                    <div>--</div>
                    <div>{{ InboxEmail::signature() }}</div>
                </div>
            </div>

            {{-- Attachments --}}
            <div class="flex shrink-0 flex-col gap-1 px-4">
                @foreach ($attachments as $index => $file)
                    <div wire:key="attachment-{{ $index }}-{{ $file->getFilename() }}" class="flex items-center gap-2 rounded-md bg-zinc-100 px-3 py-1.5 text-sm dark:bg-zinc-800">
                        <flux:icon.paper-clip variant="micro" class="shrink-0 text-zinc-500" />
                        <span class="min-w-0 flex-1 truncate font-medium text-blue-700 dark:text-blue-300">{{ $file->getClientOriginalName() }}</span>
                        <span class="shrink-0 text-xs text-zinc-500" x-text="size({{ (int) $file->getSize() }})"></span>
                        <button type="button" class="rounded p-0.5 text-zinc-500 hover:bg-zinc-200 dark:hover:bg-zinc-700" wire:click="removeAttachment({{ $index }})" aria-label="{{ __('Remove attachment') }}">
                            <flux:icon.x-mark variant="micro" />
                        </button>
                    </div>
                @endforeach
                <div x-show="uploading" x-cloak class="flex items-center gap-2 rounded-md bg-zinc-100 px-3 py-1.5 text-sm text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                    <flux:icon.arrow-up-tray variant="micro" />
                    <span>{{ __('Uploading…') }}</span>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"><div class="h-full bg-blue-600 transition-all" x-bind:style="`width: ${progress}%`"></div></div>
                </div>
            </div>

            {{-- Formatting --}}
            <div x-show="formatting" class="relative mx-3 mt-2 flex shrink-0 flex-wrap items-center gap-0.5 rounded-md border border-zinc-200 px-1 py-0.5 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                @php
                    $tools = [
                        ['undo', 'arrow-uturn-left', __('Undo (Ctrl+Z)')],
                        ['redo', 'arrow-uturn-right', __('Redo (Ctrl+Y)')],
                        null,
                        ['bold', 'bold', __('Bold (Ctrl+B)')],
                        ['italic', 'italic', __('Italic (Ctrl+I)')],
                        ['underline', 'underline', __('Underline (Ctrl+U)')],
                        ['strikeThrough', 'strikethrough', __('Strike-through')],
                        null,
                        ['insertOrderedList', 'numbered-list', __('Numbered list')],
                        ['insertUnorderedList', 'list-bullet', __('Bulleted list')],
                        ['outdent', 'bars-arrow-up', __('Indent less')],
                        ['indent', 'bars-arrow-down', __('Indent more')],
                        ['formatBlock:blockquote', 'chat-bubble-bottom-center-text', __('Quote')],
                        null,
                        ['removeFormat', 'no-symbol', __('Remove formatting')],
                    ];
                @endphp
                @foreach ($tools as $tool)
                    @if ($tool === null)
                        <span class="mx-1 h-5 w-px bg-zinc-200 dark:bg-zinc-700"></span>
                    @else
                        @php([$command, $value] = array_pad(explode(':', $tool[0], 2), 2, null))
                        <button type="button" class="rounded p-1.5 hover:bg-zinc-100 dark:hover:bg-zinc-800" title="{{ $tool[2] }}" aria-label="{{ $tool[2] }}" x-on:mousedown.prevent="format(@js($command), @js($value))">
                            <flux:icon :icon="$tool[1]" variant="micro" />
                        </button>
                    @endif
                @endforeach

                {{-- Link box --}}
                <div x-show="linkOpen" x-cloak x-on:click.outside="linkOpen = false" class="absolute bottom-full left-0 z-10 mb-2 flex w-80 items-center gap-2 rounded-lg bg-white p-2 shadow-lg ring-1 ring-black/10 dark:bg-zinc-800 dark:ring-white/10">
                    <input x-ref="linkInput" x-model="linkUrl" type="text" placeholder="{{ __('Web address or email') }}" class="min-w-0 flex-1 rounded-md border border-zinc-300 bg-transparent px-2 py-1 text-sm outline-none focus:border-blue-500 dark:border-zinc-600 dark:text-zinc-100" x-on:keydown.enter.prevent="insertLink()" x-on:keydown.escape.stop="linkOpen = false" />
                    <button type="button" class="rounded-md px-2 py-1 text-sm font-medium text-blue-700 hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-blue-950" x-on:click="insertLink()">{{ __('OK') }}</button>
                </div>
            </div>

            {{-- Bottom bar --}}
            <div class="flex shrink-0 items-center gap-1 px-3 py-3">
                <div class="flex overflow-hidden rounded-full bg-blue-600 text-white shadow-sm">
                    <button type="button" class="flex items-center gap-2 px-5 py-2 text-sm font-medium hover:bg-blue-700 disabled:opacity-60" x-on:click="send()" x-bind:disabled="sending || uploading" title="{{ __('Send (Ctrl+Enter)') }}">
                        <span x-show="! sending">{{ __('Send') }}</span>
                        <span x-show="sending" x-cloak>{{ __('Sending…') }}</span>
                    </button>
                </div>

                <button type="button" class="ml-1 rounded-full p-2 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" x-bind:class="formatting && 'bg-zinc-100 dark:bg-zinc-800'" x-on:click="formatting = ! formatting" title="{{ __('Formatting options') }}" aria-label="{{ __('Formatting options') }}">
                    <flux:icon.language variant="mini" />
                </button>
                <label class="cursor-pointer rounded-full p-2 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" title="{{ __('Attach files') }}">
                    <flux:icon.paper-clip variant="mini" />
                    <input
                        type="file"
                        multiple
                        class="sr-only"
                        wire:model="uploads"
                        x-on:livewire-upload-start="uploading = true; progress = 0"
                        x-on:livewire-upload-progress="progress = $event.detail.progress"
                        x-on:livewire-upload-finish="uploading = false; $el.value = ''"
                        x-on:livewire-upload-error="uploading = false; $el.value = ''"
                        x-on:livewire-upload-cancel="uploading = false; $el.value = ''"
                    />
                </label>
                <button type="button" class="rounded-full p-2 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" x-on:mousedown.prevent="openLink()" title="{{ __('Insert link (Ctrl+K)') }}" aria-label="{{ __('Insert link') }}">
                    <flux:icon.link variant="mini" />
                </button>

                <span class="ml-2 hidden text-xs text-zinc-500 sm:inline" x-show="draftSaved" x-cloak>{{ __('Draft saved') }}</span>
                <flux:spacer />
                <button type="button" class="rounded-full p-2 text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800" x-on:click="discard()" title="{{ __('Discard draft') }}" aria-label="{{ __('Discard draft') }}">
                    <flux:icon.trash variant="mini" />
                </button>
            </div>
        </div>
    </div>
</div>

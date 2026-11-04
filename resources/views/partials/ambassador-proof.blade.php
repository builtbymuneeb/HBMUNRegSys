{{-- The proof on one ambassador submission: files, link and note. --}}
@if ($submission->files->isNotEmpty())
    <div class="flex flex-wrap gap-2">
        @foreach ($submission->files as $file)
            <a href="{{ $file->url() }}" target="_blank" rel="noopener" class="block" title="{{ $file->original_name }}">
                @if ($file->isImage())
                    <img src="{{ $file->url() }}" alt="{{ $file->original_name }}" loading="lazy" class="size-24 rounded-lg border border-zinc-200 object-cover hover:opacity-90 dark:border-white/10">
                @else
                    <span class="flex size-24 flex-col items-center justify-center gap-1 rounded-lg border border-zinc-200 bg-zinc-50 p-2 text-center text-[11px] text-zinc-500 hover:bg-zinc-100 dark:border-white/10 dark:bg-white/5">
                        <flux:icon.document class="size-6" />
                        <span class="line-clamp-2 break-all">{{ $file->original_name }}</span>
                    </span>
                @endif
            </a>
        @endforeach
    </div>
@endif
@if ($submission->link)
    <a href="{{ $submission->link }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-1.5 text-sm break-all text-purple-600 underline underline-offset-2 dark:text-purple-300">
        <flux:icon.link variant="micro" class="shrink-0" /> {{ $submission->link }}
    </a>
@endif
@if ($submission->note)
    <p class="text-sm break-words whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $submission->note }}</p>
@endif

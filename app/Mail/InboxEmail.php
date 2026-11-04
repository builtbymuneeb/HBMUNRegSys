<?php

namespace App\Mail;

use App\Support\SafeHtml;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\Mime\Email;

/**
 * A new email staff write in the inbox compose window, sent from the contact@ address in the Homebridge design.
 * It is sent straight away rather than queued, so the attached files are still on disk when it goes.
 */
class InboxEmail extends Mailable
{
    /**
     * @param  string  $bodyHtml  Already cleaned by SafeHtml.
     * @param  array<int, TemporaryUploadedFile>  $files
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyHtml,
        public string $messageId,
        public array $files = [],
    ) {}

    public function envelope(): Envelope
    {
        $messageId = trim($this->messageId, '<>');

        return new Envelope(
            subject: $this->subjectLine,
            using: [fn (Email $email) => $email->getHeaders()->addIdHeader('Message-ID', $messageId)],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.inbox-email',
            text: 'mail.inbox-email-text',
            with: [
                'bodyHtml' => $this->bodyHtml,
                'text' => SafeHtml::toText($this->bodyHtml),
                'signature' => self::signature(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (TemporaryUploadedFile $file) => Attachment::fromPath($file->getRealPath())
                ->as($file->getClientOriginalName())
                ->withMime($file->getMimeType() ?: 'application/octet-stream'),
            $this->files,
        );
    }

    /**
     * The sign-off added below every email, the same one replies end with.
     */
    public static function signature(): string
    {
        return 'The '.config('conference.name').' team';
    }
}

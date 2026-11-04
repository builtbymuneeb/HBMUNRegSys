<?php

namespace App\Livewire\Concerns;

use App\Models\PaymentScreenshot;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Lets a registrant pick several payment receipts. Files are uploaded one at a time into
 * $newScreenshot (keeping each request under the server's upload limit) and then collected
 * in $paymentScreenshots.
 */
trait CollectsPaymentScreenshots
{
    /**
     * The receipts picked so far, one per payment (a delegation may pay in several transfers).
     *
     * @var list<TemporaryUploadedFile>
     */
    public array $paymentScreenshots = [];

    /**
     * Each picked file lands here first, then joins $paymentScreenshots.
     */
    public $newScreenshot;

    /**
     * What each payment screenshot must be.
     *
     * @return list<string>
     */
    protected function screenshotRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png', 'max:5120'];
    }

    /**
     * Rules for the collected receipts, to merge into the component's own.
     *
     * @return array<string, mixed>
     */
    protected function paymentScreenshotsRules(): array
    {
        return [
            'paymentScreenshots' => ['required', 'array', 'max:'.PaymentScreenshot::MAX_UPLOADS],
            'paymentScreenshots.*' => $this->screenshotRules(),
        ];
    }

    /**
     * Check the file just uploaded and add it to the receipts, up to the limit.
     */
    public function updatedNewScreenshot(): void
    {
        $this->resetValidation('newScreenshot');

        if (count($this->paymentScreenshots) >= PaymentScreenshot::MAX_UPLOADS) {
            $this->addError('newScreenshot', __('You can upload up to :max screenshots.', ['max' => PaymentScreenshot::MAX_UPLOADS]));
        } else {
            $validator = Validator::make(
                ['newScreenshot' => $this->newScreenshot],
                ['newScreenshot' => $this->screenshotRules()],
                attributes: ['newScreenshot' => 'payment screenshot'],
            );

            if ($validator->fails()) {
                $this->addError('newScreenshot', $validator->errors()->first('newScreenshot'));
            } else {
                $this->paymentScreenshots[] = $this->newScreenshot;
                $this->resetValidation('paymentScreenshots');
            }
        }

        $this->reset('newScreenshot');
    }

    public function removeScreenshot(int $index): void
    {
        unset($this->paymentScreenshots[$index]);
        $this->paymentScreenshots = array_values($this->paymentScreenshots);
        $this->resetValidation('newScreenshot');
    }
}

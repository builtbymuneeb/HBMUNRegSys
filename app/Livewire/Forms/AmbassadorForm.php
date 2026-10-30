<?php

namespace App\Livewire\Forms;

use App\Enums\AmbassadorType;
use App\Models\AuditLog;
use App\Models\CampusAmbassador;
use App\Support\ReferralCode;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AmbassadorForm extends Form
{
    public ?CampusAmbassador $ambassador = null;

    public string $code = '';

    public string $type = 'hb';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $institution = '';

    public string $city = '';

    public string $notes = '';

    public string $flag_reason = '';

    /**
     * Start a new ambassador with the next free code filled in.
     */
    public function create(): void
    {
        $this->reset();
        $this->resetValidation();
        $this->code = CampusAmbassador::nextCode();
    }

    public function edit(CampusAmbassador $ambassador): void
    {
        $this->resetValidation();
        $this->ambassador = $ambassador;
        $this->fill([
            ...$ambassador->only(['code', 'name']),
            'type' => $ambassador->type->value,
            ...array_map(fn ($value) => (string) $value, $ambassador->only(['email', 'phone', 'institution', 'city', 'notes', 'flag_reason'])),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\- ]+$/', function (string $attribute, string $value, Closure $fail) {
                $taken = CampusAmbassador::query()
                    ->where('code_key', ReferralCode::key($value))
                    ->when($this->ambassador, fn ($query) => $query->whereKeyNot($this->ambassador->id))
                    ->value('name');

                if ($taken) {
                    $fail(__('This code already belongs to :name.', ['name' => $taken]));
                }
            }],
            'type' => ['required', Rule::enum(AmbassadorType::class)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'institution' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'flag_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'code.regex' => __('Use letters, numbers and dashes only, e.g. HB01.'),
        ];
    }

    public function save(): CampusAmbassador
    {
        $validated = array_map(fn ($value) => is_string($value) ? (trim($value) ?: null) : $value, $this->validate());

        $ambassador = $this->ambassador ?? new CampusAmbassador;
        $ambassador->fill($validated)->save();

        AuditLog::record($ambassador->wasRecentlyCreated ? 'ambassador_added' : 'ambassador_updated', $ambassador, Auth::user(), ['code' => $ambassador->code]);

        return $ambassador;
    }
}

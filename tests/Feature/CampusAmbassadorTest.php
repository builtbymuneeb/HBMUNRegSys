<?php

use App\Actions\Ambassadors\ImportAmbassadors;
use App\Enums\RegistrationStatus;
use App\Enums\UserRole;
use App\Models\CampusAmbassador;
use App\Models\Delegate;
use App\Models\Registration;
use App\Models\User;
use App\Support\ReferralCode;
use App\Support\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

/**
 * Write a minimal one-sheet .xlsx file with inline strings and return its path.
 *
 * @param  list<list<string>>  $rows
 */
function xlsxFile(array $rows): string
{
    $sheetRows = '';
    foreach ($rows as $r => $row) {
        $cells = '';
        foreach ($row as $c => $value) {
            $cells .= sprintf('<c r="%s%d" t="inlineStr"><is><t>%s</t></is></c>', chr(65 + $c), $r + 1, htmlspecialchars($value));
        }
        $sheetRows .= sprintf('<row r="%d">%s</row>', $r + 1, $cells);
    }

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Form Responses 1" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    $zip->close();

    return $path;
}

test('the registrations team, ambassador managers and admins can open the ambassador pages', function (UserRole $role, int $status) {
    $ambassador = CampusAmbassador::factory()->create();

    $this->actingAs(User::factory()->role($role)->create())->get(route('staff.ambassadors'))->assertStatus($status);
    $this->actingAs(User::factory()->role($role)->create())->get(route('staff.ambassadors.show', $ambassador))->assertStatus($status);
})->with([
    [UserRole::Registrations, 200],
    [UserRole::Ambassadors, 200],
    [UserRole::Admin, 200],
    [UserRole::Delegate, 403],
    [UserRole::Applicant, 403],
]);

test('ambassador managers cannot reach registrations or the rest of the conference tools', function () {
    $manager = User::factory()->role(UserRole::Ambassadors)->create();
    $registration = Registration::factory()->create();

    $this->actingAs($manager);

    $this->get(route('staff.registrations.index'))->assertForbidden();
    $this->get(route('staff.registrations.show', $registration))->assertForbidden();
    $this->get(route('staff.allocations'))->assertForbidden();
    $this->get(route('staff.team'))->assertForbidden();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(route('staff.ambassadors'))
        ->assertDontSee(route('staff.registrations.index'))
        ->assertDontSee(route('staff.allocations'));
});

test('ambassador managers see who used a code without a link into the registration', function () {
    $ambassador = CampusAmbassador::factory()->create();
    $registration = Registration::factory()->create(['referral_code' => $ambassador->code, 'campus_ambassador_id' => $ambassador->id]);

    Livewire::actingAs(User::factory()->role(UserRole::Ambassadors)->create())
        ->test('pages::staff.ambassadors.show', ['ambassador' => $ambassador])
        ->assertSeeText($registration->reference)
        ->assertDontSee(route('staff.registrations.show', $registration));
});

test('codes typed in different ways match the same ambassador', function (string $typed) {
    expect(ReferralCode::key($typed))->toBe(ReferralCode::key('HB-001'));
})->with(['HB-001', 'hb-001', 'HB001', 'hb 1', 'HB-1', ' Hb - 001 ']);

test('registrations that used a code before the ambassador was added are linked when they are', function () {
    $registration = Registration::factory()->create(['referral_code' => 'HB-012']);
    $other = Registration::factory()->create(['referral_code' => 'HB-013']);

    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB-12']);

    expect($registration->refresh()->campus_ambassador_id)->toBe($ambassador->id)
        ->and($other->refresh()->campus_ambassador_id)->toBeNull();

    $ambassador->update(['code' => 'HB-013']);

    expect($registration->refresh()->campus_ambassador_id)->toBeNull()
        ->and($other->refresh()->campus_ambassador_id)->toBe($ambassador->id);
});

test('staff add an ambassador with the next free code', function () {
    CampusAmbassador::factory()->create(['code' => 'HB-004']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('add')
        ->assertSet('form.code', 'HB-005')
        ->set('form.name', 'Ayesha Khan')
        ->set('form.institution', 'Beaconhouse')
        ->call('save')
        ->assertHasNoErrors();

    expect(CampusAmbassador::where('code', 'HB-005')->value('name'))->toBe('Ayesha Khan');
});

test('two ambassadors cannot share a code, however it is written', function () {
    CampusAmbassador::factory()->create(['code' => 'HB-001']);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('add')
        ->set('form.code', 'hb1')
        ->set('form.name', 'Someone Else')
        ->call('save')
        ->assertHasErrors('form.code');
});

test('the list counts registrations and verified delegates per ambassador', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB-001', 'name' => 'Ali Raza']);
    Registration::factory()->verified()->has(Delegate::factory()->count(4))->create(['referral_code' => 'HB-001', 'campus_ambassador_id' => $ambassador->id]);
    Registration::factory()->has(Delegate::factory()->count(3))->create(['referral_code' => 'HB-001', 'campus_ambassador_id' => $ambassador->id]);
    Registration::factory()->create(['referral_code' => 'HB-999']);

    $counted = CampusAmbassador::withReferralCounts()->find($ambassador->id);

    expect($counted->registrations_count)->toBe(2)
        ->and($counted->verified_registrations_count)->toBe(1)
        ->and($counted->delegates_count)->toBe(7)
        ->and($counted->verified_delegates_count)->toBe(4);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->assertSeeText(['Ali Raza', 'HB-999'])
        ->assertSet('search', '');
});

test('the profile lists the registrations that used the code', function () {
    $ambassador = CampusAmbassador::factory()->create();
    $registration = Registration::factory()->create(['referral_code' => $ambassador->code, 'campus_ambassador_id' => $ambassador->id, 'status' => RegistrationStatus::Verified]);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.show', ['ambassador' => $ambassador])
        ->assertSeeText([$ambassador->name, $registration->reference]);
});

test('removing an ambassador keeps the code on their registrations', function () {
    $ambassador = CampusAmbassador::factory()->create(['code' => 'HB-003']);
    $registration = Registration::factory()->create(['referral_code' => 'HB-003', 'campus_ambassador_id' => $ambassador->id]);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.show', ['ambassador' => $ambassador])
        ->call('delete')
        ->assertRedirect(route('staff.ambassadors'));

    expect(CampusAmbassador::count())->toBe(0)
        ->and($registration->refresh()->referral_code)->toBe('HB-003')
        ->and($registration->campus_ambassador_id)->toBeNull();
});

test('a Google Forms sheet is imported by its headings, giving codes to anyone without one', function () {
    CampusAmbassador::factory()->create(['code' => 'HB-002', 'email' => 'taken@example.com']);
    $registration = Registration::factory()->create(['referral_code' => 'HB-003']);

    $rows = [
        ['Timestamp', 'Full Name', 'Email Address', 'WhatsApp Number', 'Institution Name', 'City', 'Referral Code'],
        ['10/20/2026 10:00', 'Hamza Ali', 'hamza@example.com', '3001234567', 'LGS', 'Lahore', ''],
        ['10/20/2026 11:00', 'Sara Ahmed', 'SARA@example.com', '03331234567', 'KGS', 'Karachi', 'hb-010'],
        ['10/20/2026 12:00', 'Already Here', 'taken@example.com', '', '', '', ''],
        ['', '', '', '', '', '', ''],
        ['10/20/2026 13:00', '', 'noname@example.com', '', '', '', ''],
    ];

    $preview = app(ImportAmbassadors::class)->preview($rows);

    expect(collect($preview)->whereNull('skip')->pluck('code')->all())->toBe(['HB-011', 'HB-010'])
        ->and(collect($preview)->whereNotNull('skip')->count())->toBe(2);

    $path = xlsxFile($rows);

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->call('openImport')
        ->set('sheet', UploadedFile::fake()->createWithContent('responses.xlsx', file_get_contents($path)))
        ->assertHasNoErrors()
        ->assertSeeText(['Hamza Ali', 'Email already added'])
        ->call('import');

    $hamza = CampusAmbassador::where('name', 'Hamza Ali')->sole();

    expect($hamza->code)->toBe('HB-011')
        ->and($hamza->phone)->toBe('03001234567')
        ->and($hamza->institution)->toBe('LGS')
        ->and(CampusAmbassador::where('code', 'HB-010')->value('email'))->toBe('sara@example.com')
        ->and(CampusAmbassador::count())->toBe(3)
        ->and($registration->refresh()->campus_ambassador_id)->toBeNull();
});

test('CSV exports are read too, and a sheet without a name column is refused', function () {
    $csv = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($csv, "\xEF\xBB\xBFName,Phone\nBilal,03001112223\n");

    expect(Spreadsheet::rows($csv, 'csv'))->toBe([['Name', 'Phone'], ['Bilal', '03001112223']]);

    file_put_contents($csv, "Phone,City\n03001112223,Lahore\n");

    Livewire::actingAs(staffMember())
        ->test('pages::staff.ambassadors.index')
        ->set('sheet', UploadedFile::fake()->createWithContent('responses.csv', file_get_contents($csv)))
        ->assertSeeText('Couldn’t find a name column');
});

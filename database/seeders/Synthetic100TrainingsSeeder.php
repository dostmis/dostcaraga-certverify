<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 100 synthetic trainings with participants distributed across every Caraga
 * municipality/city and all DOST programs. Used to populate the analytics
 * dashboard and public Atlas with realistic volume.
 *
 * All generated data is clearly tagged and removable:
 *   - certificates.certificate_code LIKE 'SYN-%'
 *   - users/recipients.email LIKE '%@synthetic.test'
 *   - certificate_endorsements.payload->>'synthetic' = 'true'
 * Re-running the seeder first clears the previous synthetic batch, so it is
 * idempotent. REFUSES to run in production. Run `analytics:refresh` afterwards.
 */
class Synthetic100TrainingsSeeder extends Seeder
{
    private const PROGRAMS = [
        'National/Regular Program',
        'LGIA (Local Grants-in-Aid Program)',
        'CEST (Community Empowerment through Science and Technology Program)',
        'SSCP (Smart and Sustainable Communities Program)',
        'SETUP (Small Enterprise Technology Upgrading Program)',
    ];

    private const PROVINCE_OFFICE = [
        'Agusan del Norte' => 'DOST Caraga - PSTO-Agusan Del Norte',
        'Agusan del Sur' => 'DOST Caraga - PSTO-Agusan Del Sur',
        'Surigao del Norte' => 'DOST Caraga - PSTO-Surigao Del Norte',
        'Surigao del Sur' => 'DOST Caraga - PSTO-Surigao Del Sur',
        'Dinagat Islands' => 'DOST Caraga - PSTO-Province of Dinagat Island',
    ];

    private const TITLES = [
        'Food Safety and GMP Training', 'Packaging and Labeling Design Workshop',
        'Technology Needs Assessment Seminar', 'Digital Marketing for MSMEs',
        'Coffee Processing and Quality Grading', 'Cacao Production Technology',
        'Bookkeeping and Financial Management', 'Halal Awareness and Certification',
        'Disaster Risk Reduction Technology Forum', 'Community-Based Water Monitoring',
        'Innovation and Technopreneurship Bootcamp', 'Productivity Improvement (5S / Lean)',
        'E-Commerce Onboarding for Rural Enterprises', 'Metrology and Calibration Awareness',
        'Renewable Energy for Communities', 'Science and Technology Scholarship Orientation',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Synthetic100TrainingsSeeder refuses to run in production.');

            return;
        }

        $faker = \Faker\Factory::create('en_PH');
        $faker->seed(1000100);

        $this->clearPreviousBatch();

        $geo = $this->loadGeo();                 // [province => [municipalities]]
        $provinces = array_keys($geo);
        $allMunis = [];                           // flat [ [province, muni], ... ] for coverage pass
        foreach ($geo as $prov => $munis) {
            foreach ($munis as $m) {
                $allMunis[] = [$prov, $m];
            }
        }

        // Synthetic staff (submitters + approver), tagged for easy removal.
        $organizers = [
            $this->synthUser($faker, 'Syntetic Organizer A', 'syn.org.a@synthetic.test', User::ROLE_ORGANIZER),
            $this->synthUser($faker, 'Syntetic Organizer B', 'syn.org.b@synthetic.test', User::ROLE_ORGANIZER),
            $this->synthUser($faker, 'Syntetic Supervisor', 'syn.sup@synthetic.test', User::ROLE_UNIT_SUPERVISOR),
        ];
        $rd = $this->synthUser($faker, 'Syntetic RD', 'syn.rd@synthetic.test', User::ROLE_REGIONAL_DIRECTOR, true);

        $genders = ['Male', 'Female'];
        $activityByCertType = [
            'Training' => 'Certificate of Participation',
            'Workshop' => 'Certificate of Participation',
            'Seminar' => 'Certificate of Participation',
            'Conference' => 'Certificate of Appreciation',
            'Webinar' => 'Certificate of Participation',
        ];
        $activityTypes = array_keys($activityByCertType);

        $certBuffer = [];
        $recipientBuffer = [];
        $totalParticipants = 0;
        $coverageCursor = 0; // walks $allMunis so every municipality gets a participant

        for ($t = 1; $t <= 100; $t++) {
            $program = self::PROGRAMS[($t - 1) % count(self::PROGRAMS)]; // all programs covered
            $province = $provinces[($t - 1) % count($provinces)];
            $office = self::PROVINCE_OFFICE[$province] ?? 'DOST Caraga - Fields Operation Division';
            $activity = $faker->randomElement($activityTypes);
            $certType = $activityByCertType[$activity];
            $title = $faker->randomElement(self::TITLES);
            $start = Carbon::parse($faker->dateTimeBetween('2026-01-05', '2026-10-10')->format('Y-m-d'));
            $end = (clone $start)->addDays($faker->numberBetween(0, 2));
            $hours = $faker->randomElement([8, 16, 24, 40]);
            $count = $faker->numberBetween(15, 38);

            $endorsementId = DB::table('certificate_endorsements')->insertGetId([
                'status' => 'rd_approved',
                'submitted_by' => $faker->randomElement($organizers)->id,
                'rd_approved_by' => $rd->id,
                'rd_approved_at' => (clone $start)->addDays(3),
                'participants_count' => $count,
                'generated_count' => $count,
                'participants_file_path' => 'synthetic/participants.csv',
                'template_pdf_path' => 'synthetic/template.pdf',
                'payload' => json_encode([
                    'synthetic' => 'true',
                    'training_title' => $title,
                    'dost_program' => $program,
                ]),
                'created_at' => $start,
                'updated_at' => (clone $start)->addDays(3),
            ]);

            for ($p = 0; $p < $count; $p++) {
                // Coverage pass: first participants walk the full municipality
                // list so every city/municipality is represented; the rest are
                // sampled within this training's province.
                if ($coverageCursor < count($allMunis)) {
                    [$cProv, $cMuni] = $allMunis[$coverageCursor++];
                    $certProvince = $cProv;
                    $certMuni = $cMuni;
                    $certOffice = self::PROVINCE_OFFICE[$cProv] ?? $office;
                } else {
                    $certProvince = $province;
                    $certMuni = $faker->randomElement($geo[$province]);
                    $certOffice = $office;
                }

                $totalParticipants++;
                $seq = str_pad((string) $totalParticipants, 5, '0', STR_PAD_LEFT);
                $gender = $faker->randomElement($genders);
                $name = $faker->name($gender === 'Male' ? 'male' : 'female');

                // ~40% of participants get a recipient account (60% of those claimed).
                $recipientId = null;
                $email = null;
                if ($faker->boolean(40)) {
                    $email = "syn.r{$seq}@synthetic.test";
                    $claimed = $faker->boolean(60);
                    $recipientBuffer[] = [
                        'name' => $name,
                        'email' => $email,
                        'contact_number' => '09'.$faker->numerify('#########'),
                        'gender' => $gender,
                        'birthdate' => $faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
                        'password' => $claimed ? Hash::make('password123') : null,
                        'claim_token' => $claimed ? null : (string) Str::uuid(),
                        'created_at' => $start,
                        'updated_at' => $start,
                        '_seq' => $seq, // resolved to recipient_id after insert
                    ];
                }

                $certBuffer[] = [
                    'certificate_code' => "SYN-2026-{$t}-{$seq}",
                    'participant_name' => $name,
                    'training_title' => $title,
                    'training_date' => $start->toDateString(),
                    'training_date_to' => $end->toDateString(),
                    'issuing_office' => $certOffice,
                    'status' => 'valid',
                    'public_token' => (string) Str::uuid(),
                    'gender' => $gender,
                    'age' => $faker->numberBetween(18, 65),
                    'region' => 'Region XIII (Caraga)',
                    'province' => $certProvince,
                    'city_municipality' => $certMuni,
                    'barangay' => 'Barangay '.$faker->numberBetween(1, 30),
                    'dost_program' => $program,
                    'activity_type' => $activity,
                    'certificate_type' => $certType,
                    'recipient_type' => 'Participant',
                    'email' => $email,
                    'venue' => "{$certMuni}, {$certProvince}",
                    'number_of_training_hours' => $hours,
                    'email_delivery_status' => $faker->randomElement(['sent', 'sent', 'sent', 'failed', null]),
                    'caption_alignment' => 'center',
                    'endorsement_id' => $endorsementId,
                    'endorsement_participant_index' => $p,
                    'created_at' => $start,
                    'updated_at' => $start,
                    '_email' => $email, // resolved to recipient_id after recipient insert
                ];
            }
        }

        // Insert recipients, map email -> id.
        $emailToRecipientId = [];
        foreach (array_chunk($recipientBuffer, 500) as $chunk) {
            $rows = array_map(fn ($r) => collect($r)->except('_seq')->all(), $chunk);
            DB::table('recipients')->insert($rows);
        }
        foreach (DB::table('recipients')->where('email', 'like', '%@synthetic.test')->get(['id', 'email']) as $r) {
            $emailToRecipientId[$r->email] = $r->id;
        }

        // Insert certificates, linking recipient_id by email.
        foreach (array_chunk($certBuffer, 500) as $chunk) {
            $rows = array_map(function ($c) use ($emailToRecipientId) {
                $email = $c['_email'];
                unset($c['_email']);
                $c['recipient_id'] = $email ? ($emailToRecipientId[$email] ?? null) : null;

                return $c;
            }, $chunk);
            DB::table('certificates')->insert($rows);
        }

        $this->command?->info("Seeded 100 synthetic trainings, {$totalParticipants} certificates across ".count($allMunis).' municipalities and '.count(self::PROGRAMS).' programs.');
        $this->command?->warn('Run `php artisan analytics:refresh` to rebuild the analytics views.');
    }

    private function clearPreviousBatch(): void
    {
        DB::table('certificates')->where('certificate_code', 'like', 'SYN-%')->delete();
        DB::table('certificate_endorsements')->whereRaw("payload->>'synthetic' = 'true'")->delete();
        DB::table('recipients')->where('email', 'like', '%@synthetic.test')->delete();
        User::where('email', 'like', '%@synthetic.test')->delete();
    }

    /** @return array<string, array<int, string>> province => municipality names */
    private function loadGeo(): array
    {
        $provinces = [];
        $provFile = json_decode((string) file_get_contents(public_path('geo/caraga-provinces.0.001.json')), true);
        foreach ($provFile['features'] as $f) {
            $provinces[(int) $f['properties']['adm2_psgc']] = $f['properties']['adm2_en'];
        }

        $geo = [];
        $muniFile = json_decode((string) file_get_contents(public_path('geo/caraga-municipalities.0.001.json')), true);
        foreach ($muniFile['features'] as $f) {
            $prov = $provinces[(int) $f['properties']['adm2_psgc']] ?? null;
            if ($prov === null) {
                continue;
            }
            $geo[$prov][] = $f['properties']['adm3_en'];
        }

        return $geo;
    }

    private function synthUser($faker, string $name, string $email, string $role, bool $admin = false): User
    {
        return User::create([
            'name' => $name,
            'username' => Str::slug($name, '_').'_'.$faker->numberBetween(100, 999),
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_admin' => $admin,
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ]);
    }
}

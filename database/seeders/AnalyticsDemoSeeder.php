<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Realistic synthetic Caraga data for local analytics development. Populates
 * recipients (some claimed), certificates, endorsements, intake events and
 * intakes so every dashboard panel has something to show.
 *
 * REFUSES to run in production. After seeding, run `php artisan analytics:refresh`.
 */
class AnalyticsDemoSeeder extends Seeder
{
    /** Province => municipalities (names normalise to the PSGC crosswalk). */
    private const GEO = [
        'Agusan del Norte' => ['Butuan City', 'Cabadbaran City', 'Buenavista', 'Nasipit', 'Las Nieves'],
        'Agusan del Sur' => ['Bayugan City', 'San Francisco', 'Prosperidad', 'Bunawan'],
        'Surigao del Norte' => ['Surigao City', 'Dapa', 'Del Carmen', 'Placer'],
        'Surigao del Sur' => ['Bislig City', 'Tandag City', 'Barobo', 'Cantilan'],
        'Dinagat Islands' => ['San Jose', 'Basilisa', 'Cagdianao', 'Loreto'],
    ];

    private const PROGRAMS = [
        'SETUP (Small Enterprise Technology Upgrading Program)',
        'CEST (Community Empowerment through Science and Technology Program)',
        'LGIA (Local Grants-in-Aid Program)',
        'National/Regular Program',
    ];

    private const OFFICES = [
        'DOST Caraga - Fields Operation Division',
        'DOST Caraga - Technology Transfer Division',
    ];

    private const GENDERS = ['Male', 'Female'];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('AnalyticsDemoSeeder refuses to run in production.');

            return;
        }

        $faker = \Faker\Factory::create('en_PH');
        $faker->seed(161013);

        $organizer = $this->demoUser('Demo Organizer', 'demo.organizer@dostcaraga.test', User::ROLE_ORGANIZER);
        $supervisor = $this->demoUser('Demo Supervisor', 'demo.supervisor@dostcaraga.test', User::ROLE_UNIT_SUPERVISOR);
        $rd = $this->demoUser('Demo RD', 'demo.rd@dostcaraga.test', User::ROLE_REGIONAL_DIRECTOR, true);

        $now = Carbon::now();

        // --- Recipients (≈45; ~60% claimed) ---
        $recipientIds = [];
        $recipientEmails = [];
        for ($i = 1; $i <= 45; $i++) {
            $claimed = $faker->boolean(60);
            $email = "demo.recipient{$i}@example.test";
            $id = DB::table('recipients')->insertGetId([
                'name' => $faker->name(),
                'email' => $email,
                'contact_number' => '09'.$faker->numerify('#########'),
                'gender' => $faker->randomElement(self::GENDERS),
                'birthdate' => $faker->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
                'password' => $claimed ? Hash::make('password123') : null,
                'claim_token' => $claimed ? null : (string) Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $recipientIds[] = $id;
            $recipientEmails[$id] = $email;
        }

        // --- Endorsements (12) with mixed outcomes + turnaround ---
        $endorsementIds = [];
        $outcomes = ['rd_approved', 'rd_approved', 'rd_approved', 'rejected', 'endorsed', 'generating'];
        for ($i = 1; $i <= 12; $i++) {
            $status = $faker->randomElement($outcomes);
            $submittedAt = (clone $now)->subDays($faker->numberBetween(1, 200));
            $decided = in_array($status, ['rd_approved', 'rejected'], true);
            $decidedAt = $decided ? (clone $submittedAt)->addHours($faker->numberBetween(1, 72)) : null;
            $endorsementIds[] = DB::table('certificate_endorsements')->insertGetId([
                'status' => $status,
                'submitted_by' => $faker->randomElement([$organizer->id, $supervisor->id]),
                'rd_approved_by' => $status === 'rd_approved' ? $rd->id : null,
                'rd_approved_at' => $status === 'rd_approved' ? $decidedAt : null,
                'rd_rejected_by' => $status === 'rejected' ? $rd->id : null,
                'rd_rejected_at' => $status === 'rejected' ? $decidedAt : null,
                'participants_count' => $faker->numberBetween(10, 60),
                'generation_error' => $faker->boolean(10) ? 'Demo generation error' : null,
                'participants_file_path' => 'demo/participants.csv',
                'template_pdf_path' => 'demo/template.pdf',
                'payload' => json_encode(['dost_program' => $faker->randomElement(self::PROGRAMS)]),
                'created_at' => $submittedAt,
                'updated_at' => $decidedAt ?? $submittedAt,
            ]);
        }

        // --- Certificates (320) spread across geography/time/programs ---
        $certRows = [];
        for ($i = 1; $i <= 320; $i++) {
            $province = $faker->randomElement(array_keys(self::GEO));
            $municipality = $faker->randomElement(self::GEO[$province]);
            $trainingDate = $faker->dateTimeBetween('2026-01-01', '2026-10-15');
            $recipientId = $faker->boolean(75) ? $faker->randomElement($recipientIds) : null;
            $certRows[] = [
                'certificate_code' => 'DEMO-2026-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'participant_name' => $faker->name(),
                'training_title' => $faker->randomElement(['Food Safety Training', 'GMP Workshop', 'Digital Literacy', 'Packaging Design', 'Coffee Processing', 'Business Mentoring']),
                'training_date' => $trainingDate->format('Y-m-d'),
                'issuing_office' => $faker->randomElement(self::OFFICES),
                'status' => 'valid',
                'public_token' => (string) Str::uuid(),
                'gender' => $faker->randomElement(self::GENDERS),
                'age' => $faker->numberBetween(18, 65),
                'province' => $province,
                'city_municipality' => $municipality,
                'barangay' => 'Barangay '.$faker->numberBetween(1, 20),
                'dost_program' => $faker->randomElement(self::PROGRAMS),
                'activity_type' => $faker->randomElement(['Training', 'Workshop', 'Seminar']),
                'email_delivery_status' => $faker->randomElement(['sent', 'sent', 'sent', 'failed', null]),
                'recipient_id' => $recipientId,
                'email' => $recipientId ? $recipientEmails[$recipientId] : null,
                'endorsement_id' => $faker->boolean(70) ? $faker->randomElement($endorsementIds) : null,
                'caption_alignment' => 'center',
                'created_at' => $trainingDate,
                'updated_at' => $trainingDate,
            ];
        }
        foreach (array_chunk($certRows, 100) as $chunk) {
            DB::table('certificates')->insert($chunk);
        }

        // --- Intake events + intakes (some convert to certificates) ---
        for ($e = 1; $e <= 3; $e++) {
            $eventId = DB::table('participant_intake_events')->insertGetId([
                'user_id' => $faker->randomElement([$organizer->id, $supervisor->id]),
                'event_name' => "Demo Intake Event {$e}",
                'public_token' => (string) Str::uuid(),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            for ($p = 1; $p <= 25; $p++) {
                $province = $faker->randomElement(array_keys(self::GEO));
                // Some intakes reuse a recipient email so the MV marks them converted.
                $email = $faker->boolean(40)
                    ? $recipientEmails[$faker->randomElement($recipientIds)]
                    : "demo.intake{$e}_{$p}@example.test";
                DB::table('participant_intakes')->insert([
                    'participant_name' => $faker->name(),
                    'first_name' => $faker->firstName(),
                    'last_name' => $faker->lastName(),
                    'email' => $email,
                    'contact_number' => '09'.$faker->numerify('#########'),
                    'gender' => $faker->randomElement(self::GENDERS),
                    'age' => $faker->numberBetween(18, 65),
                    'province' => $province,
                    'city_municipality' => $faker->randomElement(self::GEO[$province]),
                    'barangay' => 'Barangay '.$faker->numberBetween(1, 20),
                    'status' => $faker->randomElement(['pending', 'done', 'rd_approved']),
                    'has_attended_dost_training' => $faker->randomElement(['Yes', 'No']),
                    'dost_program_beneficiary' => json_encode($faker->boolean(50) ? ['SETUP'] : []),
                    'interested_dost_services' => json_encode($faker->boolean(60) ? ['Testing', 'Training'] : []),
                    'participant_intake_event_id' => $eventId,
                    'owner_user_id' => $faker->randomElement([$organizer->id, $supervisor->id]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $this->command?->info('AnalyticsDemoSeeder: seeded demo recipients, certificates, endorsements, and intakes.');
        $this->command?->warn('Run `php artisan analytics:refresh` to rebuild the analytics views.');
    }

    private function demoUser(string $name, string $email, string $role, bool $admin = false): User
    {
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => Str::slug($name, '_'),
                'password' => Hash::make('password123'),
                'role' => $role,
                'is_admin' => $admin,
                'approval_status' => 'approved',
                'email_verified_at' => now(),
            ],
        );
    }
}

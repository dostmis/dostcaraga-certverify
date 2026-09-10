<?php

namespace Database\Seeders;

use App\Models\CertificateEndorsement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds endorsement packages awaiting Regional Director approval.
 *
 * Builds each package the way the endorsement flow does — a participants CSV
 * and a template PDF on the local disk, plus a payload from the same keys
 * buildTrainingPayload() produces — so the approvals queue, the preview
 * endpoints, and queued generation all behave as they would in production.
 *
 * Run with:
 *   php artisan db:seed --class=CertificateEndorsementSeeder
 */
class CertificateEndorsementSeeder extends Seeder
{
    private const PARTICIPANTS_DIR = 'certificate-endorsements/participants';
    private const TEMPLATES_DIR = 'certificate-endorsements/templates';

    public function run(): void
    {
        $submitters = $this->submitters();

        foreach ($this->packages() as $index => $package) {
            $submitter = $submitters[$index % count($submitters)];
            $participants = $this->participantsFor($package['participant_count'], $index);

            $endorsement = CertificateEndorsement::create([
                'status' => CertificateEndorsement::STATUS_ENDORSED,
                'submitted_by' => $submitter->id,
                'participants_count' => count($participants),
                'participants_file_path' => $this->storeParticipants($participants),
                'template_pdf_path' => $this->storeTemplate($package['certificate_type']),
                'payload' => $this->payload($package, count($participants)),
            ]);

            $this->command?->info(sprintf(
                'Endorsement #%d — %s (%d participants) — %s',
                $endorsement->id,
                $package['certificate_type'],
                count($participants),
                $package['training_title']
            ));
        }
    }

    /**
     * Endorsers are organizers and unit supervisors, never the RD — the RD is
     * the approver, so a package they submitted to themselves is not a state
     * the real workflow produces.
     *
     * @return array<int, User>
     */
    private function submitters(): array
    {
        $definitions = [
            ['Seed Organizer (FOD)', 'seed.organizer.fod', User::ROLE_ORGANIZER],
            ['Seed Organizer (TSS)', 'seed.organizer.tss', User::ROLE_ORGANIZER],
            ['Seed Unit Supervisor', 'seed.supervisor', User::ROLE_UNIT_SUPERVISOR],
        ];

        $submitters = [];
        foreach ($definitions as [$name, $username, $role]) {
            $submitters[] = User::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username . '@seed.invalid',
                    'password' => bcrypt(Str::random(32)),
                    'role' => $role,
                    'approval_status' => 'approved',
                    'approved_at' => now(),
                    'email_verified_at' => now(),
                    'is_admin' => false,
                ]
            );
        }

        return $submitters;
    }

    /**
     * 10 Certificate of Participation, 5 Certificate of Appreciation.
     *
     * Participant counts vary deliberately: small packages finish before the
     * progress poll fires, larger ones show the bar advancing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function packages(): array
    {
        $participation = [
            ['Food Safety and Good Manufacturing Practices Training', 'Food', 'Training', 42, 'DOST Caraga - Fields Operation Division'],
            ['Basic Welding and Metal Fabrication Workshop', 'Metals and Engineering', 'Workshop', 28, 'DOST Caraga - PSTO-Agusan Del Norte'],
            ['Technopreneurship Bootcamp for Startups', 'Startups and Technopreneurship', 'Training', 55, 'DOST Caraga - Innovation Unit'],
            ['Handloom Weaving and Natural Dyeing Seminar', 'Textile', 'Seminar', 18, 'DOST Caraga - PSTO-Surigao Del Sur'],
            ['Circular Economy Practices for MSMEs', 'Circular Economy and Sustainable Innovations', 'Workshop', 34, 'DOST Caraga - Technical Support Services'],
            ['Introduction to Internet of Things for Agriculture', 'Emerging Technologies', 'Training', 25, 'DOST Caraga - PSTO-Agusan Del Sur'],
            ['Cacao Post-Harvest Processing Training', 'Food', 'Training', 47, 'DOST Caraga - PSTO-Surigao Del Norte'],
            ['Packaging and Labeling Design Workshop', 'Others', 'Workshop', 21, 'DOST Caraga - PSTO-Province of Dinagat Island'],
            ['Data Analytics Fundamentals for LGU Planners', 'Emerging Technologies', 'Webinar', 63, 'DOST Caraga - Office of the Regional Director'],
            ['Coconut By-Product Utilization Training', 'Food', 'Training', 9, 'DOST Caraga - Fields Operation Division'],
        ];

        $appreciation = [
            ['Regional Science and Technology Week Symposium', 'Emerging Technologies', 'Conference', 6, 'DOST Caraga - Office of the Regional Director'],
            ['Caraga Innovation Summit Panel Session', 'Startups and Technopreneurship', 'Conference', 5, 'DOST Caraga - Innovation Unit'],
            ['Food Innovation Center Technical Forum', 'Food', 'Seminar', 4, 'DOST Caraga - Technical Support Services'],
            ['Community Empowerment Through Science Orientation', 'Others', 'Seminar', 8, 'DOST Caraga - PSTO-Agusan Del Norte'],
            ['Sustainable Manufacturing Roundtable', 'Circular Economy and Sustainable Innovations', 'Workshop', 3, 'DOST Caraga - Fields Operation Division'],
        ];

        $packages = [];

        foreach ($participation as $i => [$title, $topic, $activity, $count, $office]) {
            $packages[] = $this->package($title, $topic, $activity, $count, $office, 'Certificate of Participation', 'Participant', $i);
        }

        foreach ($appreciation as $i => [$title, $topic, $activity, $count, $office]) {
            $packages[] = $this->package($title, $topic, $activity, $count, $office, 'Certificate of Appreciation', 'Resource Person/Speaker', $i + 10);
        }

        return $packages;
    }

    /**
     * @return array<string, mixed>
     */
    private function package(
        string $title,
        string $topic,
        string $activity,
        int $count,
        string $office,
        string $certificateType,
        string $recipientType,
        int $index
    ): array {
        // Spread the schedules across recent weeks so the queue is not one
        // wall of identical dates.
        $from = now('Asia/Manila')->subDays(60 - ($index * 3))->startOfDay();
        $to = $from->copy()->addDays($index % 3);

        $programs = [
            ['National/Regular Program', 'Regular Funds', 'Not Applicable'],
            ['LGIA (Local Grants-in-Aid Program)', 'Project Funds', 'Not Applicable'],
            ['CEST (Community Empowerment through Science and Technology Program)', 'Project Funds', 'Not Applicable'],
            ['SETUP (Small Enterprise Technology Upgrading Program)', 'Regular Funds', 'Regional Office (Main)'],
        ];
        [$program, $sourceOfFunds, $setupProvince] = $programs[$index % count($programs)];

        $pillars = [
            'Human Well-Being Promoted',
            'Wealth Creation Fostered',
            'Wealth Protection Reinforced',
            'Sustainability Institutionalized',
        ];

        return [
            'training_title' => $title,
            'topic' => $topic,
            'activity_type' => $activity,
            'certificate_type' => $certificateType,
            'recipient_type' => $recipientType,
            'participant_count' => $count,
            'issuing_office' => $office,
            'venue' => $this->venues()[$index % count($this->venues())],
            'training_date_from' => $from->toDateString(),
            'training_date_to' => $to->toDateString(),
            'number_of_training_hours' => [4, 8, 16, 24][$index % 4],
            'dost_program' => $program,
            'source_of_funds' => $sourceOfFunds,
            'setup_office_province' => $setupProvince,
            'pillar' => $pillars[$index % count($pillars)],
            'training_budget' => 25000 + ($index * 7500),
            'expected_number_of_participants' => $count + 5,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function venues(): array
    {
        return [
            'DOST Caraga Regional Office, Butuan City',
            'Father Saturnino Urios University, Butuan City',
            'Caraga State University, Ampayon, Butuan City',
            'Provincial Capitol, Cabadbaran City',
            'Surigao del Norte Provincial Training Center, Surigao City',
            'San Francisco Municipal Hall, Agusan del Sur',
        ];
    }

    /**
     * @param  array<string, mixed>  $package
     * @return array<string, mixed>
     */
    private function payload(array $package, int $participantCount): array
    {
        return [
            'training_title' => $package['training_title'],
            'caption_text' => null,
            'caption_alignment' => 'center',
            'activity_type' => $package['activity_type'],
            'certificate_type' => $package['certificate_type'],
            'recipient_type' => $package['recipient_type'],
            'venue' => $package['venue'],
            'topic' => $package['topic'],
            'training_date_from' => $package['training_date_from'],
            'training_date_to' => $package['training_date_to'],
            'number_of_training_hours' => $package['number_of_training_hours'],
            'dost_program' => $package['dost_program'],
            'setup_office_province' => $package['setup_office_province'],
            'pillar' => $package['pillar'],
            'dost_project' => 'Not Applicable',
            'project_code' => 'Not Applicable',
            'source_of_funds' => $package['source_of_funds'],
            'training_budget' => (float) $package['training_budget'],
            'expected_number_of_participants' => $package['expected_number_of_participants'],
            'issuing_office' => $package['issuing_office'],
            // One entry per participant, all unmatched — the same shape
            // finalizeEndorsement() writes when no recipient is matched.
            'recipient_matches' => array_fill(0, $participantCount, null),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function participantsFor(int $count, int $packageIndex): array
    {
        $first = ['Maria', 'Juan', 'Ana', 'Jose', 'Cherie', 'Ramon', 'Liza', 'Nestor', 'Grace', 'Arnel',
            'Divina', 'Rogelio', 'Melinda', 'Eduardo', 'Corazon', 'Ferdinand', 'Imelda', 'Benigno',
            'Rosario', 'Teodoro', 'Luzviminda', 'Ricardo', 'Erlinda', 'Alfredo', 'Nenita'];
        $last = ['Dela Cruz', 'Santos', 'Reyes', 'Bautista', 'Ocampo', 'Villanueva', 'Mercado',
            'Aquino', 'Tolentino', 'Salvador', 'Gabriel', 'Domingo', 'Castillo', 'Padilla',
            'Flores', 'Rosales', 'Navarro', 'Alvarez', 'Cabrera', 'Espinosa', 'Magsaysay',
            'Buenaflor', 'Panganiban', 'Sarmiento', 'Zamora'];

        $participants = [];
        for ($i = 0; $i < $count; $i++) {
            $seed = ($packageIndex * 31) + $i;
            $firstName = $first[$seed % count($first)];
            $middle = strtoupper(substr($first[($seed * 7) % count($first)], 0, 1));
            $lastName = $last[($seed * 13) % count($last)];

            $participants[] = [
                'name' => "{$firstName} {$middle}. {$lastName}",
                // Deliberately .invalid so a stray queued email cannot reach a
                // real inbox from a dev machine.
                'email' => sprintf('%s.%s%d@seed.invalid', strtolower($firstName), strtolower(str_replace([' ', "'"], '', $lastName)), $seed),
                'gender' => $seed % 2 === 0 ? 'Female' : 'Male',
                'age' => 22 + ($seed % 38),
                'region' => 'Region XIII (Caraga)',
                'province' => ['Agusan del Norte', 'Agusan del Sur', 'Surigao del Norte', 'Surigao del Sur', 'Dinagat Islands'][$seed % 5],
                'city_municipality' => ['Butuan City', 'Cabadbaran City', 'Surigao City', 'Bislig City', 'San Jose'][$seed % 5],
                'barangay' => 'Barangay ' . (1 + ($seed % 30)),
                'industry' => ['Food Processing', 'Metalworking', 'Handicraft', 'Agriculture', 'Services'][$seed % 5],
            ];
        }

        return $participants;
    }

    /**
     * @param  array<int, array<string, string>>  $participants
     */
    private function storeParticipants(array $participants): string
    {
        $columns = ['name', 'email', 'gender', 'age', 'region', 'province', 'city_municipality', 'barangay', 'industry'];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $columns);
        foreach ($participants as $participant) {
            fputcsv($handle, array_map(fn (string $column) => (string) ($participant[$column] ?? ''), $columns));
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $path = self::PARTICIPANTS_DIR . '/participants_' . Str::uuid() . '.csv';
        Storage::disk('local')->put($path, $csv);

        return $path;
    }

    /**
     * Copy the embedded default template for the certificate type, exactly as
     * storeTemplatePdfForRequest() does for a non-custom upload.
     */
    private function storeTemplate(string $certificateType): string
    {
        $files = [
            'Certificate of Appreciation' => 'Appreciation.pdf',
            'Certificate of Participation' => 'Participation.pdf',
        ];

        $fileName = $files[$certificateType] ?? null;
        if ($fileName === null) {
            throw new \RuntimeException("No seed template configured for {$certificateType}.");
        }

        $sourceAbs = public_path('templates/' . $fileName);
        if (! is_file($sourceAbs)) {
            throw new \RuntimeException("Default template file not found: {$fileName}.");
        }

        $path = self::TEMPLATES_DIR . '/template_' . Str::uuid() . '.pdf';
        Storage::disk('local')->put($path, file_get_contents($sourceAbs));

        return $path;
    }
}

<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CertificateReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Certificate $certificate)
    {
    }

    public function build(): self
    {
        $recipient = $this->certificate->recipient;
        $claimUrl = null;
        $isDormant = false;

        if ($recipient) {
            $isDormant = ! $recipient->isClaimed();
            if ($isDormant && $recipient->claim_token) {
                $claimUrl = $this->baseUrl() . route('recipient.claim.form', [
                    'token' => $recipient->claim_token,
                ], false);
            }
        }

        // The certificate PDF is deliberately NOT attached. Bulk identical
        // attachments are a strong spam signal and were getting messages
        // blocked; the recipient downloads it from the tokenised link instead.
        return $this->subject($this->subjectLine())
            ->view('emails.certificates.ready', [
                'certificate' => $this->certificate,
                'downloadUrl' => $this->downloadUrl(),
                'verifyUrl' => $this->verifyUrl(),
                'dateRange' => $this->dateRange(),
                'claimUrl' => $claimUrl,
                'isDormant' => $isDormant,
            ]);
    }

    private function subjectLine(): string
    {
        $parts = ['DOST Caraga'];

        $certificateType = trim((string) ($this->certificate->certificate_type ?? 'Certificate'));
        if ($certificateType !== '') {
            $parts[] = $certificateType;
        }

        $trainingTitle = trim((string) ($this->certificate->training_title ?? ''));
        if ($trainingTitle !== '') {
            $parts[] = $trainingTitle;
        }

        return implode(' - ', $parts);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    private function verifyUrl(): string
    {
        return $this->baseUrl() . route('cert.verify', ['t' => $this->certificate->public_token], false);
    }

    private function downloadUrl(): string
    {
        return $this->baseUrl() . route('cert.download', ['t' => $this->certificate->public_token], false);
    }

    private function dateRange(): string
    {
        $from = optional($this->certificate->training_date)->format('F j, Y');
        $to = optional($this->certificate->training_date_to)->format('F j, Y');

        if (! $from) {
            return 'Schedule not specified';
        }

        if (! $to || $to === $from) {
            return $from;
        }

        return "{$from} to {$to}";
    }
}

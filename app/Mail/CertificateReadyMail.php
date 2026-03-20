<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CertificateReadyMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Certificate $certificate)
    {
    }

    public function build(): self
    {
        $mail = $this->subject($this->subjectLine())
            ->view('emails.certificates.ready', [
                'certificate' => $this->certificate,
                'downloadUrl' => $this->downloadUrl(),
                'verifyUrl' => $this->verifyUrl(),
                'dateRange' => $this->dateRange(),
            ]);

        $attachment = $this->certificateAttachmentPath();
        if ($attachment) {
            $mail->attach($attachment, [
                'as' => $this->certificate->certificate_code . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
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

    private function certificateAttachmentPath(): ?string
    {
        $path = (string) ($this->certificate->stamped_pdf_path ?? '');
        if ($path === '') {
            return null;
        }

        $localDisk = Storage::disk('local');
        if ($localDisk->exists($path)) {
            return $localDisk->path($path);
        }

        $publicDisk = Storage::disk('public');
        if ($publicDisk->exists($path)) {
            return $publicDisk->path($path);
        }

        return null;
    }
}

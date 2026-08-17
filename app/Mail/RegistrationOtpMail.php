<?php

namespace App\Mail;

use App\Models\CompanyProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The 6-digit verification code.
     */
    public $otp;

    /**
     * Company profile metadata (title/logo/mobile/email/address) injected
     * into the email header + footer. Null-safe — falls back to app defaults
     * inside the blade when no CompanyProfile row exists yet.
     */
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct($otp)
    {
        $this->otp = $otp;
        $this->company = CompanyProfile::first();
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $title = $this->company?->title ?: config('app.name', 'Like Online');

        return new Envelope(
            subject: "Your {$title} Registration Verification Code",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-otp',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

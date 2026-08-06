<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $amount;
    public $transactionId;
    public $customerName;

    public function __construct($amount, $transactionId, $customerName)
    {
        $this->amount = $amount;
        $this->transactionId = $transactionId;
        $this->customerName = $customerName;
    }

    public function build()
    {
        return $this->view('email.payment_success')
            ->subject('Payment Successful')
            ->with([
                'amount' => $this->amount,
                'transactionId' => $this->transactionId,
                'customerName' => $this->customerName,
            ]);
    }
}

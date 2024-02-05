<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Contracts\Queue\ShouldQueue;

class AdminNotification extends Mailable
{
    use Queueable, SerializesModels;
 
    /**
     * Create a new message instance.
     */
    protected $userId;
    protected $transactionId;
    protected $user;
    
    public function __construct($user, $transactionId)
    {
        $this->user = $user;
        $this->userId = $user->id;
        $this->transactionId = $transactionId;
    }
    

    public function build()
    {
        $url = URL::temporarySignedRoute(
            'admin.approve', now()->addMinutes(30), ['userId' => $this->userId, 'transactionId' => $this->transactionId]
        );
    
        return $this->subject('Approved Check Product From The Crew')
                    ->view('emails.approved_crew')
                    ->with([
                        'user' => $this->user,
                        'approvalUrl' => $url
                    ]);
    }
    

}

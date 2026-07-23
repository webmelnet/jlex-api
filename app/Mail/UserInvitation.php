<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class UserInvitation extends Mailable
{
    
    use Queueable, SerializesModels;

    public $user;
    public $invitationUrl;

    public function __construct(User $user, $invitationUrl)
    {
        $this->invitationUrl = $invitationUrl;
        $this->user = $user->load('roles');
    }

    public function build()
    {
        return $this->view('emails.user-invitation')
                    ->subject('Welcome to WinV Estimator App')
                    ->bcc([
                        'webmelnet@gmail.com',
                        'mel@moveyourbiz.com',
                        'trina@moveyourbiz.com'
                    ]);                    
    }    

}
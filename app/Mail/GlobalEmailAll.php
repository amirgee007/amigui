<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class GlobalEmailAll extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public  $content,$counter,$user;
    public function __construct($subject, $content , $counter = [] , $user)
    {
        $this->content = $content;
        $this->subject = $subject;
        $this->counter = $counter;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->from('admin@amiguiec.xyz', 'Laravel Server');
        return $this->markdown('emails.system.index')->with(['content' => $this->content]);
    }
}

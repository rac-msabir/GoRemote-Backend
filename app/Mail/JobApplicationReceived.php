<?php

namespace App\Mail;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class JobApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public Job $job;
    public JobApplication $application;

    /**
     * Create a new message instance.
     */
    public function __construct(Job $job, JobApplication $application)
    {
        $this->job = $job;
        $this->application = $application;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $mail = $this->subject('New Application for: ' . $this->job->title)
            ->view('emails.job_application_received', [
                'job'         => $this->job,
                'app'         => $this->application,
                'experiences' => $this->application->experiences()->get(),
                'educations'  => $this->application->educations()->get(),
            ]);

        // Attach resume if present (stored on "public" disk)
        if ($this->application->resume_path) {
            $absolute = Storage::disk('public')->path($this->application->resume_path);
            if (is_file($absolute)) {
                $mail->attach($absolute);
            }
        }

        return $mail;
    }
}

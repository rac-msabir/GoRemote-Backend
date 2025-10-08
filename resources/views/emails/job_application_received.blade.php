<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New Job Application</title>
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- keep styles minimal; many clients strip advanced CSS -->
  <style>
    /* dark mode tweaks for clients that support it */
    @media (prefers-color-scheme: dark) {
      .bg-page { background: #0b0e14 !important; }
      .card { background: #111827 !important; border-color: #1f2937 !important; }
      .text { color: #e5e7eb !important; }
      .muted { color: #9ca3af !important; }
      .heading { color: #c7d2fe !important; }
      .link { color: #93c5fd !important; }
      .bar { background: linear-gradient(90deg, #3730a3, #2563eb) !important; }
    }
  </style>
</head>
<body class="bg-page" style="margin:0; padding:0; background:#f6f7fb;">
  <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f6f7fb;">
    <tr>
      <td align="center" style="padding:32px 12px;">
        <!-- card -->
        <table role="presentation" cellpadding="0" cellspacing="0" width="600" class="card" style="width:600px; max-width:100%; background:#ffffff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden;">
          <!-- top bar / title -->
          <tr>
            <td class="bar" style="padding:18px 24px; background:linear-gradient(90deg,#4f46e5,#3b82f6);">
              <h1 class="heading" style="margin:0; font-family:Arial,Helvetica,sans-serif; font-size:20px; line-height:1.4; color:#ffffff;">
                New Application for: {{ $job->title }}
              </h1>
            </td>
          </tr>

          <!-- content -->
          <tr>
            <td style="padding:24px;">
              <!-- applicant block -->
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="text" style="font-family:Arial,Helvetica,sans-serif; color:#111827; font-size:14px; line-height:1.6;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#4f46e5; font-family:Arial,Helvetica,sans-serif;">Applicant</h2>

                    <p style="margin:0 0 6px;"><strong>Name:</strong> {{ $app->name }}</p>
                    <p style="margin:0 0 6px;"><strong>Email:</strong> {{ $app->email }}</p>
                    <p style="margin:0 0 6px;"><strong>Phone:</strong> {{ $app->phone }}</p>

                    <p style="margin:12px 0 6px;">
                      <strong>Location:</strong>
                      {{ $app->address }},
                      {{ $app->city }},
                      {{ $app->province }},
                      {{ $app->zip }},
                      {{ $app->country }}
                    </p>

                    @if($app->linkedin_url)
                      <p style="margin:0 0 6px;">
                        <strong>LinkedIn:</strong>
                        <a href="{{ $app->linkedin_url }}" class="link" style="color:#2563eb; text-decoration:none;">
                          {{ $app->linkedin_url }}
                        </a>
                      </p>
                    @endif
                  </td>
                </tr>
              </table>

              <!-- divider -->
              <hr style="border:none; border-top:1px solid #e5e7eb; margin:20px 0;">

              <!-- cover letter -->
              @if($app->cover_letter)
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="text" style="font-family:Arial,Helvetica,sans-serif; color:#111827; font-size:14px; line-height:1.6;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#4f46e5;">Cover Letter</h2>
                    <p style="margin:0; white-space:pre-wrap;">{{ $app->cover_letter }}</p>
                  </td>
                </tr>
              </table>
              <hr style="border:none; border-top:1px solid #e5e7eb; margin:20px 0;">
              @endif

              <!-- experiences -->
              @if($experiences->count())
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="text" style="font-family:Arial,Helvetica,sans-serif; color:#111827; font-size:14px; line-height:1.6;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#4f46e5;">Experiences</h2>
                    <ul style="margin:0; padding-left:18px;">
                      @foreach($experiences as $exp)
                        <li style="margin:0 0 12px;">
                          <strong>{{ $exp->company_name }}</strong><br>
                          <span class="muted" style="color:#6b7280;">
                            {{ $exp->start_date }} — {{ $exp->is_current ? 'Present' : ($exp->end_date ?? 'N/A') }}
                          </span><br>
                          @if($exp->description)
                            <em style="color:#374151;">{{ $exp->description }}</em>
                          @endif
                        </li>
                      @endforeach
                    </ul>
                  </td>
                </tr>
              </table>
              <hr style="border:none; border-top:1px solid #e5e7eb; margin:20px 0;">
              @endif

              <!-- education -->
              @if($educations->count())
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="text" style="font-family:Arial,Helvetica,sans-serif; color:#111827; font-size:14px; line-height:1.6;">
                    <h2 style="margin:0 0 12px; font-size:16px; color:#4f46e5;">Education</h2>
                    <ul style="margin:0; padding-left:18px;">
                      @foreach($educations as $edu)
                        <li style="margin:0 0 12px;">
                          <strong>{{ $edu->degree_title }}</strong> — {{ $edu->institution }}<br>
                          <span class="muted" style="color:#6b7280;">
                            {{ $edu->start_date }} — {{ $edu->is_current ? 'Present' : ($edu->end_date ?? 'N/A') }}
                          </span>
                        </li>
                      @endforeach
                    </ul>
                  </td>
                </tr>
              </table>
              <hr style="border:none; border-top:1px solid #e5e7eb; margin:20px 0;">
              @endif

              <!-- resume badge -->
              @if($app->resume_path)
              <table role="presentation" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="font-family:Arial,Helvetica,sans-serif; font-size:13px; color:#065f46; background:#ecfdf5; border:1px solid #a7f3d0; border-radius:999px; padding:6px 12px;">
                    ✅ Resume attached
                  </td>
                </tr>
              </table>
              <div style="height:16px; line-height:16px;">&nbsp;</div>
              @endif

              <!-- footer meta -->
              <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                  <td class="muted" style="font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#6b7280;">
                    <hr style="border:none; border-top:1px dashed #e5e7eb; margin:8px 0 12px;">
                    Job ID: {{ $job->uuid ?? $job->id }} • Application ID: {{ $app->uuid ?? $app->id }}
                  </td>
                </tr>
              </table>

            </td>
          </tr>
        </table>

        <!-- tiny footer -->
        <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:600px; max-width:100%; margin-top:12px;">
          <tr>
            <td class="muted" style="text-align:center; font-family:Arial,Helvetica,sans-serif; font-size:12px; color:#9ca3af;">
              © {{ now()->year }} {{ config('app.name') }} • This is an automated notification
            </td>
          </tr>
        </table>

      </td>
    </tr>
  </table>
</body>
</html>

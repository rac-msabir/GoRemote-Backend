<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>New Job Application</title>
  <meta name="x-apple-disable-message-reformatting">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Simple, light-only styling -->
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f3f4f6;
    }

    .wrapper {
      width: 100%;
      padding: 32px 16px;
      background: #f3f4f6;
    }

    .container {
      width: 640px;
      max-width: 100%;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 4px;
      border: 1px solid #e5e7eb;
      font-family: Arial, Helvetica, sans-serif;
    }

    .header {
      padding: 24px 32px 8px;
      border-bottom: 1px solid #e5e7eb;
      text-align: left;
    }

    .brand {
      font-size: 14px;
      font-weight: 700;
      color: #2563eb;
      letter-spacing: 0.02em;
    }

    .status-line {
      margin: 24px 0 4px;
      font-size: 13px;
      color: #4b5563;
      font-weight: 600;
    }

    .status-line span {
      color: #2563eb;
      font-size: 14px;
      margin-right: 4px;
    }

    .job-title {
      margin: 0;
      font-size: 22px;
      line-height: 1.3;
      color: #111827;
      font-weight: 700;
      text-decoration: underline;
      text-decoration-thickness: 1px;
    }

    .company-line {
      margin: 8px 0 0;
      font-size: 13px;
      color: #4b5563;
    }

    .company-name {
      font-weight: 700;
      color: #111827;
    }

    .divider {
      margin: 24px 32px 0;
      border-top: 1px solid #e5e7eb;
    }

    .section {
      padding: 16px 32px 8px;
      font-size: 13px;
      color: #111827;
      line-height: 1.6;
    }

    .section p {
      margin: 0 0 8px;
    }

    .section-title {
      font-weight: 700;
      margin-bottom: 8px;
    }

    .list {
      margin: 0 0 16px 18px;
      padding: 0;
    }

    .list li {
      margin-bottom: 4px;
    }

    .info-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }

    .info-label {
      width: 160px;
      padding: 4px 0;
      font-size: 13px;
      color: #6b7280;
      font-weight: 600;
      vertical-align: top;
    }

    .info-value {
      padding: 4px 0;
      font-size: 13px;
      color: #111827;
      vertical-align: top;
    }

    a {
      color: #2563eb;
      text-decoration: underline;
    }

    .meta {
      padding: 12px 32px 24px;
      font-size: 11px;
      color: #6b7280;
      border-top: 1px solid #e5e7eb;
    }

    .footer {
      width: 640px;
      max-width: 100%;
      margin: 8px auto 0;
      text-align: center;
      font-family: Arial, Helvetica, sans-serif;
      font-size: 11px;
      color: #9ca3af;
    }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="container">
      <!-- Header like Indeed -->
      <div class="header">
        <div class="brand">
          {{ config('app.name') }}
        </div>

        <h1 class="job-title">
          {{ $job->title }}
        </h1>

        <p class="company-line">
          @if(!empty($job->location))
            – {{ $job->location }}
          @endif
        </p>
      </div>

      <div class="divider"></div>

      <!-- Candidate details block -->
      <div class="section">
        <p class="section-title">Candidate details</p>

        <table class="info-table" role="presentation" cellpadding="0" cellspacing="0">
          <tr>
            <td class="info-label">Full Name</td>
            <td class="info-value">{{ $app->name }}</td>
          </tr>
          <tr>
            <td class="info-label">Email Address</td>
            <td class="info-value">{{ $app->email }}</td>
          </tr>
          <tr>
            <td class="info-label">Phone Number</td>
            <td class="info-value">{{ $app->phone }}</td>
          </tr>
          <tr>
            <td class="info-label">Location</td>
            <td class="info-value">
              {{ $app->address }},
              {{ $app->city }},
              {{ $app->province }},
              {{ $app->zip }},
              {{ $app->country }}
            </td>
          </tr>
          @if($app->linkedin_url)
          <tr>
            <td class="info-label">LinkedIn</td>
            <td class="info-value">
              <a href="{{ $app->linkedin_url }}">{{ $app->linkedin_url }}</a>
            </td>
          </tr>
          @endif
        </table>
      </div>

      <!-- Meta info -->
      <div class="meta">
        Job ID: {{ $job->uuid ?? $job->id }}<br>
        Application ID: {{ $app->uuid ?? $app->id }}
      </div>
    </div>

    <!-- Footer -->
    <div class="footer">
      © {{ now()->year }} {{ config('app.name') }} • This is an automated notification
    </div>
  </div>
</body>
</html>

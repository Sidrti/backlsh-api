<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>Daily Team Report</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body {
    background-color: #f0f2f5;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    color: #1a1a2e;
    line-height: 1.6;
  }
  .wrapper { width: 100%; background-color: #f0f2f5; padding: 32px 16px; }
  .email-container {
    max-width: 680px;
    margin: 0 auto;
    background-color: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
  }

  /* Header */
  .header {
    background-color: #0f172a;
    padding: 28px 36px 24px;
    border-bottom: 3px solid #3b5bdb;
  }
  .header-eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #3b5bdb;
    margin-bottom: 6px;
  }
  .header-title {
    font-size: 22px;
    font-weight: 700;
    color: #f8fafc;
    letter-spacing: -0.02em;
  }
  .header-meta {
    margin-top: 6px;
    font-size: 13px;
    color: #94a3b8;
  }

  /* Greeting */
  .greeting {
    padding: 20px 36px 0;
    font-size: 14px;
    color: #475569;
  }

  /* Summary bar */
  .summary-bar {
    margin: 20px 36px;
    background-color: #eef2ff;
    border-left: 4px solid #3b5bdb;
    border-radius: 0 6px 6px 0;
    padding: 14px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .summary-hours {
    font-size: 28px;
    font-weight: 800;
    color: #1e1b4b;
    letter-spacing: -0.04em;
    line-height: 1;
  }
  .summary-label {
    font-size: 12px;
    color: #6366f1;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
  }
  .summary-sub {
    font-size: 12px;
    color: #818cf8;
    margin-top: 2px;
  }

  /* Section */
  .section { padding: 24px 36px; border-top: 1px solid #e2e8f0; }
  .section-title {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 16px;
  }

  /* Table */
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  thead tr { background-color: #f8fafc; }
  th {
    padding: 10px 12px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: #64748b;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
  }
  td {
    padding: 11px 12px;
    color: #334155;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
  }
  tbody tr:last-child td { border-bottom: none; }
  tbody tr:hover td { background-color: #f8fafc; }

  /* Employee name cell */
  .emp-name { font-weight: 600; color: #0f172a; }
  .emp-hours {
    font-weight: 700;
    color: #1e1b4b;
    background-color: #eef2ff;
    border-radius: 4px;
    padding: 2px 7px;
    display: inline-block;
    font-size: 12px;
  }
  .time-val { font-weight: 500; color: #475569; font-size: 13px; }
  .meta-list { color: #64748b; font-size: 12px; line-height: 1.7; }

  /* Project table */
  .badge {
    display: inline-block;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.6;
  }
  .badge-green { background-color: #dcfce7; color: #166534; }
  .badge-gray  { background-color: #f1f5f9; color: #475569; }
  .badge-zero  { color: #cbd5e1; font-size: 12px; }

  /* Footer */
  .footer {
    padding: 20px 36px;
    border-top: 1px solid #e2e8f0;
    background-color: #f8fafc;
  }
  .footer p { font-size: 11px; color: #94a3b8; line-height: 1.7; }
  .footer strong { color: #64748b; }

  /* Empty */
  .empty { font-size: 13px; color: #94a3b8; font-style: italic; padding: 4px 0; }

  @media only screen and (max-width: 520px) {
    .header, .greeting, .section, .footer { padding-left: 20px; padding-right: 20px; }
    .summary-bar { margin-left: 20px; margin-right: 20px; }
    .summary-hours { font-size: 22px; }
    th, td { padding: 8px; }
  }
</style>
</head>
<body>
<div class="wrapper">
<div class="email-container">

  <!-- Header -->
  <div class="header">
    <div class="header-eyebrow">Backlsh &mdash; Automated Report</div>
    <div class="header-title">Daily Team Report</div>
    <div class="header-meta">{{ $reportData['date'] }}</div>
  </div>

  <!-- Greeting -->
  <div class="greeting">
    <p>Hi {{ $reportData['ownerName'] }}, here is your team's activity summary for yesterday.</p>
  </div>

  <!-- Total Hours -->
  <div class="summary-bar">
    <div>
      <div class="summary-hours">{{ $reportData['totalTeamHours'] }}</div>
    </div>
    <div>
      <div class="summary-label">Total Hours Tracked</div>
      <div class="summary-sub">Across {{ count($reportData['employeesData']) }} team member(s)</div>
    </div>
  </div>

  <!-- Team Productivity -->
  <div class="section">
    <div class="section-title">Team Productivity &mdash; Ranked by Hours Worked</div>
    @if(count($reportData['employeesData']) > 0)
    <table>
      <thead>
        <tr>
          <th>Employee</th>
          <th>Hours</th>
          <th>Login</th>
          <th>Logout</th>
          <th>Top Websites</th>
          <th>Top Apps</th>
        </tr>
      </thead>
      <tbody>
        @foreach($reportData['employeesData'] as $employee)
        <tr>
          <td><span class="emp-name">{{ $employee['name'] }}</span></td>
          <td><span class="emp-hours">{{ $employee['total_hours'] }}</span></td>
          <td><span class="time-val">{{ $employee['logged_in'] }}</span></td>
          <td><span class="time-val">{{ $employee['logged_out'] }}</span></td>
          <td><div class="meta-list">{{ $employee['top_websites'] }}</div></td>
          <td><div class="meta-list">{{ $employee['top_apps'] }}</div></td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <p class="empty">No team activity recorded for this period.</p>
    @endif
  </div>

  <!-- Active Projects -->
  <div class="section">
    <div class="section-title">Active Projects</div>
    @if(count($reportData['projectsData']) > 0)
    <table>
      <thead>
        <tr>
          <th>Project</th>
          <th>Tasks Done</th>
          <th>Tasks Left</th>
          <th>Issues Resolved</th>
          <th>Issues Open</th>
        </tr>
      </thead>
      <tbody>
        @foreach($reportData['projectsData'] as $project)
        <tr>
          <td><span class="emp-name">{{ $project['name'] }}</span></td>
          <td>
            @if($project['tasks_completed_today'] > 0)
              <span class="badge badge-green">&#10003; {{ $project['tasks_completed_today'] }}</span>
            @else
              <span class="badge-zero">&mdash;</span>
            @endif
          </td>
          <td>
            @if($project['tasks_left'] > 0)
              <span class="badge badge-gray">{{ $project['tasks_left'] }}</span>
            @else
              <span class="badge-zero">&mdash;</span>
            @endif
          </td>
          <td>
            @if($project['issues_completed_today'] > 0)
              <span class="badge badge-green">&#10003; {{ $project['issues_completed_today'] }}</span>
            @else
              <span class="badge-zero">&mdash;</span>
            @endif
          </td>
          <td>
            @if($project['issues_left'] > 0)
              <span class="badge badge-gray">{{ $project['issues_left'] }}</span>
            @else
              <span class="badge-zero">&mdash;</span>
            @endif
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @else
    <p class="empty">No project activity recorded for this period.</p>
    @endif
  </div>

  <!-- Footer -->
  <div class="footer">
    <p>
      This report was generated automatically by <strong>Backlsh</strong> for {{ $reportData['date'] }}.<br>
      You are receiving this as an account owner. To manage notification settings, log in to your Backlsh dashboard.
    </p>
  </div>

</div>
</div>
</body>
</html>

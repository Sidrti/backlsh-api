<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .wrapper {
            width: 100%;
            background-color: #f3f4f6;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .header {
            background-color: #696cff;
            padding: 30px 20px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .content {
            padding: 40px 30px;
            color: #374151;
            line-height: 1.6;
        }
        .content h2 {
            color: #111827;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .button-container {
            text-align: center;
            margin: 35px 0 10px;
        }
        .button {
            display: inline-block;
            padding: 12px 28px;
            background-color: #696cff;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 15px;
            transition: background-color 0.2s;
        }
        .details-card {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 25px 0;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table th {
            text-align: left;
            color: #6b7280;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 0;
            width: 40%;
            border-bottom: 1px solid #e5e7eb;
        }
        .details-table td {
            color: #111827;
            font-weight: 500;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
            text-align: right;
        }
        .details-table tr:last-child th,
        .details-table tr:last-child td {
            border-bottom: none;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        .footer p {
            color: #6b7280;
            font-size: 13px;
            margin: 0;
        }
        .footer a {
            color: #696cff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <h1>Backlsh</h1>
            </div>
            <div class="content">
                <h2>{{ $title }}</h2>
                <p>Hi {{ $recipientName }},</p>
                <div style="margin-top: 15px;">
                    {!! $body !!}
                </div>

                @if(!empty($details))
                    <div class="details-card">
                        <table class="details-table">
                            @foreach($details as $label => $value)
                                <tr>
                                    <th>{{ $label }}</th>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                @endif

                @if(!empty($actionUrl) && !empty($actionText))
                    <div class="button-container">
                        <a href="{{ $actionUrl }}" class="button">{{ $actionText }}</a>
                    </div>
                @endif
            </div>
            <div class="footer">
                <p>You received this email because of your account settings in Backlsh.</p>
                <p style="margin-top: 10px; font-size: 12px;">&copy; {{ date('Y') }} Backlsh. All rights reserved.</p>
            </div>
        </div>
    </div>
</body>
</html>

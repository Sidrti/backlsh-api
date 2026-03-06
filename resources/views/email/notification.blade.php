<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 5px;
            border: 1px solid #e0e0e0;
        }
        .content {
            padding: 20px;
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 10px 24px;
            background-color: #696cff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            margin: 15px 0;
        }
        .details-table {
            width: 100%;
            margin: 15px 0;
            border-collapse: collapse;
        }
        .details-table th, .details-table td {
            padding: 8px;
            border: 1px solid #ddd;
        }
        .details-table th {
            background-color: #f9f9f9;
            text-align: left;
            font-weight: bold;
            width: 30%;
        }
        .description {
            font-size: 12px;
            color: #aaaaaa;
            text-align: center;
            margin-top: 20px;
            padding: 0 40px;
        }
    </style>
</head>
<body>
    <table role="presentation" class="container">
        <tr>
            <td>
                <div class="content">
                    <h2 style="text-align: center;">{{ $title }}</h2>
                    <p>Hi {{ $recipientName }},</p>
                    <p>{!! $body !!}</p>

                    @if(!empty($details))
                        <table class="details-table">
                            @foreach($details as $label => $value)
                                <tr>
                                    <th>{{ $label }}</th>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif

                    @if(!empty($actionUrl) && !empty($actionText))
                        <div style="text-align: center;">
                            <a href="{{ $actionUrl }}" class="button">{{ $actionText }}</a>
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

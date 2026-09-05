<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You got a shoutout 🎉</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #2D3748; background-color: #F7FAFC; margin: 0; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #E2E8F0;">
        <div style="font-size: 28px; margin-bottom: 16px;">👏</div>
        <p style="font-size: 16px; margin: 0 0 16px 0;">Hi {{ $recipientName }},</p>
        <p style="font-size: 16px; margin: 0 0 20px 0;"><strong>{{ $senderName }}</strong> gave you a shoutout this week:</p>
        
        <div style="background-color: #F0F7FF; border-left: 4px solid #3B82F6; border-radius: 6px; padding: 16px 20px; margin: 0 0 24px 0;">
            <p style="font-size: 18px; font-weight: 600; color: #1E40AF; margin: 0;">
                "{{ $message }}"
            </p>
        </div>

        <p style="font-size: 16px; margin: 0 0 24px 0;">Keep it up.</p>
        
        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 24px 0;" />
        <p style="font-size: 13px; color: #718096; margin: 0;">— Sent via Backlsh</p>
    </div>
</body>
</html>

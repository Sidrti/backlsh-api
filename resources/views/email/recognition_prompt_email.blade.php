<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Recognition Opportunity 🎉' }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #2D3748; background-color: #F7FAFC; margin: 0; padding: 24px;">
    <div style="max-width: 540px; margin: 0 auto; background: #FFFFFF; border-radius: 12px; padding: 32px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05); border: 1px solid #E2E8F0;">
        <div style="font-size: 28px; margin-bottom: 12px;">💡</div>
        <p style="font-size: 16px; margin: 0 0 16px 0;">Hi {{ $managerName }},</p>
        <p style="font-size: 15px; margin: 0 0 16px 0; color: #4A5568;">
            Here is a great moment to acknowledge your team:
        </p>

        <!-- Highlight Callout Box -->
        <div style="background-color: #F0FDF4; border-left: 4px solid #22C55E; border-radius: 8px; padding: 16px 20px; margin: 0 0 24px 0;">
            <p style="font-size: 14px; font-weight: 700; color: #15803D; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 6px 0;">
                Milestone Noticed
            </p>
            <p style="font-size: 17px; font-weight: 600; color: #166534; margin: 0 0 8px 0;">
                {{ $candidateName }}
            </p>
            <p style="font-size: 15px; color: #1E293B; margin: 0;">
                {{ $promptMessage }}
            </p>
        </div>

        <p style="font-size: 14px; color: #64748B; margin: 0 0 24px 0;">
            Taking 30 seconds to send a shoutout reinforces positive momentum and lets your team know their effort is seen.
        </p>

        <!-- CTA Button -->
        <div style="text-align: center; margin: 28px 0;">
            <a href="{{ $actionUrl ?? config('app.url') . '/dashboard' }}" style="display: inline-block; background-color: #696CFF; color: #FFFFFF; font-weight: 600; font-size: 15px; padding: 12px 28px; border-radius: 8px; text-decoration: none; box-shadow: 0 2px 8px rgba(105, 108, 255, 0.35);">
                Send Shoutout on Backlsh 👏
            </a>
        </div>

        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 24px 0;" />
        <p style="font-size: 12px; color: #94A3B8; margin: 0; text-align: center;">
            Sent automatically by Backlsh Recognition Engine based on tracked team milestones.
        </p>
    </div>
</body>
</html>

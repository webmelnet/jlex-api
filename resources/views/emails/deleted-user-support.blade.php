<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Request - Deleted User Account</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div
        style="width: 100%; max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; box-sizing: border-box;">
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="{{ config('app.frontend_url') }}/images/logo/logo.png" alt="{{ config('app.name') }}"
                style="max-width: 200px; height: auto;">
        </div>

        <h1 style="text-align: center; color: #333; font-size: 20px; margin-bottom: 20px;">Support Request: Deleted User Account</h1>

        <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="color: #333; font-size: 16px; margin-bottom: 15px; margin-top: 0;">User Information</h2>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #555;">Name:</strong>
                <span style="color: #333; margin-left: 10px;">{{ $supportData['name'] }}</span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #555;">Email:</strong>
                <span style="color: #333; margin-left: 10px;">{{ $supportData['email'] }}</span>
            </div>
            
            <div style="margin-bottom: 10px;">
                <strong style="color: #555;">Subject:</strong>
                <span style="color: #333; margin-left: 10px;">{{ $supportData['subject'] }}</span>
            </div>
        </div>

        <div style="background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="color: #856404; font-size: 16px; margin-bottom: 15px; margin-top: 0;">Message</h2>
            <p style="color: #856404; line-height: 1.6; margin: 0; white-space: pre-wrap;">{{ $supportData['message'] }}</p>
        </div>

        <div style="background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
            <p style="color: #0c5460; margin: 0; font-size: 14px; font-weight: bold;">
                ⚠️ This support request is related to a deleted user account issue.
            </p>
        </div>

        <p style="color: #666; line-height: 1.6; margin-bottom: 20px; font-size: 14px;">
            Please review this support request and respond to the user directly at: 
            <a href="mailto:{{ $supportData['email'] }}" style="color: #01AEF0; text-decoration: none;">{{ $supportData['email'] }}</a>
        </p>

        <p style="color: #666; line-height: 1.6; margin-bottom: 10px; font-size: 12px;">
            <strong>Timestamp:</strong> {{ now()->format('F j, Y \a\t g:i A T') }}
        </p>
    </div>
    
    <div style="display: table; width: 100%; max-width: 600px; margin: 10px auto; padding: 10px;">
        <div style="display: table-cell; text-align: left; vertical-align: middle; font-size: 12px; color: #c6c6c6;">
            &#169; 2026 MPOS | www.mpos.com
        </div>
        <div style="display: table-cell; text-align: right; vertical-align: middle;">
            <a href="{{ config('app.frontend_url') }}" style="color: #c6c6c6; text-decoration: none; font-size: 12px;">
                MPOS Support
            </a>
        </div>
    </div>
</body>

</html>
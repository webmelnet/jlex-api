<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name') }}</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f5f5f5;">
    <div
        style="width: 100%; max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; box-sizing: border-box;">
        <!-- Logo -->
        <div style="text-align: center; margin-bottom: 30px;">
            <img src="{{ config('app.frontend_url') }}/images/logo/logo.png" alt="{{ config('app.name') }}"
                style="max-width: 200px; height: auto;">
        </div>

        <h1 style="text-align: center; color: #333; font-size: 14px; margin-bottom: 20px;">Hi {{ $user->name }}</h1>

        <p style="text-align: center; font-size: 18px; color: #666; line-height: 1.6; margin-bottom: 20px;">
            @if($user->roles && $user->roles->count() > 0)
                You have been added as 
                @if($user->roles->count() == 1)
                    {{ $user->roles->first()->name }}
                @elseif($user->roles->count() == 2)
                    {{ $user->roles->first()->name }} and {{ $user->roles->last()->name }}
                @else
                    @php
                        $lastRole = $user->roles->last()->name;
                        $otherRoles = $user->roles->slice(0, -1)->pluck('name')->implode(', ');
                    @endphp
                    {{ $otherRoles }} and {{ $lastRole }}
                @endif
                in the MPOS App.
            @else
                You have been added to the MPOS App.
            @endif
            <br />
            Click the button below to set your password.
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $invitationUrl }}"
                style="background-color: #01AEF0; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Go to MPOS
            </a>
        </div>

        <p style="text-align: center; color: #666; line-height: 1.6; margin-bottom: 10px;">
            This link will expire in 48 hours for security reasons.
        </p>
    </div>
    <div style="display: table; width: 100%; max-width: 600px; margin: 10px auto; padding: 10px;">
        <div style="display: table-cell; text-align: left; vertical-align: middle; font-size: 12px; color: #c6c6c6;">
            &#169; 2026 MPOS | www.mpos.com
        </div>
        <div style="display: table-cell; text-align: right; vertical-align: middle;">
            <a href="{{ config('app.frontend_url') }}" style="color: #c6c6c6; text-decoration: none; font-size: 12px;">
                Log in to MPOS
            </a>
        </div>
    </div>
</body>

</html>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; background: #f4f4f5; margin: 0; padding: 40px 0; }
        .card { background: #fff; max-width: 520px; margin: 0 auto; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,.08); }
        .header { background: linear-gradient(135deg, #7c3aed, #4f46e5); padding: 36px 32px; color: #fff; }
        .header h1 { margin: 0; font-size: 22px; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 14px; }
        .body { padding: 32px; }
        .detail { display: flex; gap: 10px; margin-bottom: 12px; font-size: 15px; color: #374151; }
        .label { color: #6b7280; font-size: 13px; min-width: 70px; }
        .footer { padding: 20px 32px; background: #f9fafb; color: #9ca3af; font-size: 12px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>You're on the list! 🎉</h1>
        <p>Hi {{ $attendeeName }}, your spot is confirmed.</p>
    </div>
    <div class="body">
        <p style="margin-top:0;color:#374151">Here are the details for your upcoming event:</p>
        <div class="detail"><span class="label">Event</span> <strong>{{ $event['name'] }}</strong></div>
        <div class="detail"><span class="label">Date</span> {{ date('D, d M Y · g:i A', $event['starts_at']) }} UTC</div>
        @if($event['venue'])<div class="detail"><span class="label">Venue</span> {{ $event['venue'] }}</div>@endif
        <div class="detail"><span class="label">Location</span> {{ $event['location'] }}</div>
        <p style="color:#6b7280;font-size:13px;margin-top:20px">We'll send you a reminder 3 days before and again 24 hours before the event. See you there!</p>
    </div>
    <div class="footer">You're receiving this because you registered for this event.</div>
</div>
</body>
</html>

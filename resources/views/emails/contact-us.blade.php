<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; line-height: 1.6; margin: 0; padding: 20px; background: #f8fafc; }
        .container { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin-top: 0; }
        .meta { color: #64748b; font-size: .9rem; margin-bottom: 16px; }
        .body { padding: 16px 0; border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; margin: 16px 0; }
        .footer { font-size: .8rem; color: #94a3b8; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>New Contact Message</h1>
        <div class="meta"><strong>From:</strong> {{ $senderName }} ({{ $senderEmail }})</div>
        <div class="body">{!! nl2br(e($body)) !!}</div>
        <div class="footer">This message was sent via the contact form on {{ config('app.name') }}.</div>
    </div>
</body>
</html>

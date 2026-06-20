<x-mail::message>
<div style="text-align: center; margin-bottom: 32px;">
    <h1 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0;">New Ventures Hosting</h1>
    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Reliable hosting, built for growth.</p>
</div>

# Hi {{ $name }},

Your service **{{ $service->name }}** has been suspended and is no longer accessible.

If you believe this is a mistake or would like more information, please open a support ticket and our team will assist you.

<x-mail::button :url="config('app.frontend_url') . '/support'" color="primary">
Open a Support Ticket
</x-mail::button>

---

<small style="color: #9ca3af;">
© {{ date('Y') }} New Ventures Hosting. All rights reserved.
</small>
</x-mail::message>

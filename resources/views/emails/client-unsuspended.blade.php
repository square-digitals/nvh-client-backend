<x-mail::message>
<div style="text-align: center; margin-bottom: 32px;">
    <h1 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0;">New Ventures Hosting</h1>
    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Reliable hosting, built for growth.</p>
</div>

# Good news, {{ $name }}!

Your account has been reactivated and you now have full access to the client portal.

<x-mail::button :url="config('app.frontend_url') . '/login'" color="primary">
Log In to Your Portal
</x-mail::button>

If you have any questions, our support team is here to help.

---

<small style="color: #9ca3af;">
© {{ date('Y') }} New Ventures Hosting. All rights reserved.
</small>
</x-mail::message>

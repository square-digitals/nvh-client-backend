<x-mail::message>
<div style="text-align: center; margin-bottom: 32px;">
    <h1 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0;">New Ventures Hosting</h1>
    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Reliable hosting, built for growth.</p>
</div>

# Welcome, {{ $name }}!

Thanks for creating your account. You're one step away from accessing your client portal — where you can manage your hosting services, view invoices, and get support.

Please verify your email address to activate your account.

<x-mail::button :url="$url" color="primary">
Verify Email Address
</x-mail::button>

This link expires in **60 minutes**. If you didn't create an account, you can safely ignore this email.

---

**What's next after verifying?**

- Request a hosting service
- Track your service status in real time
- View and pay invoices
- Open support tickets

---

<small style="color: #9ca3af;">
If the button doesn't work, copy and paste this link into your browser:<br>
{{ $url }}
</small>

<small style="color: #9ca3af;">
© {{ date('Y') }} New Ventures Hosting. All rights reserved.
</small>
</x-mail::message>

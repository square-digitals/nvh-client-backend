<x-mail::message>
<div style="text-align: center; margin-bottom: 32px;">
    <h1 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0;">New Ventures Hosting</h1>
    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Reliable hosting, built for growth.</p>
</div>

# Good news, {{ $name }}!

Your service **{{ $service->name }}** has been reactivated and is now live again.

@if($service->url)
<x-mail::button :url="$service->url" color="primary">
Visit Your Site
</x-mail::button>
@endif

If you have any questions, our support team is here to help.

---

<small style="color: #9ca3af;">
© {{ date('Y') }} New Ventures Hosting. All rights reserved.
</small>
</x-mail::message>

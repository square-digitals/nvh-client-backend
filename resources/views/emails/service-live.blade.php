<x-mail::message>
<div style="text-align: center; margin-bottom: 32px;">
    <h1 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0;">New Ventures Hosting</h1>
    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Reliable hosting, built for growth.</p>
</div>

# Great news, {{ $name }}!

Your service **{{ $service->name }}** is now live and ready to use.

@if($service->url)
<x-mail::button :url="$service->url" color="primary">
Visit Your Site
</x-mail::button>
@endif

**Service details**

| | |
|---|---|
| **Name** | {{ $service->name }} |
| **Type** | {{ ucfirst($service->type) }} |
@if($service->domain)
| **Domain** | {{ $service->domain }} |
@endif
@if($service->url)
| **URL** | {{ $service->url }} |
@endif
| **Provisioned** | {{ $service->provisioned_at?->format('d M Y, H:i') ?? 'N/A' }} |

---

Log in to your client portal to manage your service, view invoices, and get support.

<small style="color: #9ca3af;">
© {{ date('Y') }} New Ventures Hosting. All rights reserved.
</small>
</x-mail::message>

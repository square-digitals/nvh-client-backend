<x-mail::message>
<div style="text-align: center; margin-bottom: 32px;">
    <h1 style="font-size: 24px; font-weight: 700; color: #1a1a1a; margin: 0;">New Ventures Hosting</h1>
    <p style="font-size: 13px; color: #6b7280; margin: 4px 0 0;">Reliable hosting, built for growth.</p>
</div>

# Hi {{ $name }},

We're writing to let you know that your service request for **{{ $service->name }}** could not be {{ $service->status === 'rejected' ? 'approved' : 'provisioned' }}.

@if($service->failed_reason)
**Reason:** {{ $service->failed_reason }}
@endif

If you have any questions or would like to submit a new request, please open a support ticket from your client portal and our team will be happy to help.

<x-mail::button :url="config('app.frontend_url') . '/support'" color="primary">
Open a Support Ticket
</x-mail::button>

---

<small style="color: #9ca3af;">
© {{ date('Y') }} New Ventures Hosting. All rights reserved.
</small>
</x-mail::message>

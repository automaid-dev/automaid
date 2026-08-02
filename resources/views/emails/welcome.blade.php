@component('mail::message')

Welcome to Automaid!

Thank you for your registration at Automaid platform. We are very happy to have you as our client.

Name: {{ $mailData['name'] }}<br/>
Email: {{ $mailData['email'] }}

Thanks,<br/>
{{ config('app.name') }}
@endcomponent
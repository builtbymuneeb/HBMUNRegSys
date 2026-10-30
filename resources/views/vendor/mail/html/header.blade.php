@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="{{ asset('images/email-logo.png') }}" class="logo" width="96" height="96" alt="{{ config('conference.name') }}">
</a>
<p class="header-title">{{ config('conference.name') }}</p>
<p class="header-subtitle">{{ config('conference.dates_label') }} &middot; {{ __('Virtual') }}</p>
</td>
</tr>

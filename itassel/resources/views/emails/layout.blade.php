@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    \Carbon\Carbon::setLocale($locale);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ITASSEL</title>
</head>
<body style="margin:0;padding:0;background:#F5F7FA;font-family:{{ $rtl ? 'Tahoma, Arial, sans-serif' : 'Roboto, Arial, sans-serif' }};color:#1F2A24;text-align:{{ $rtl ? 'right' : 'left' }};">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FA;padding:24px 0;" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
    <tr>
      <td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#FFFFFF;border-radius:8px;overflow:hidden;border:1px solid #E5E7EB;">
          <tr>
            <td style="background:#006B3F;padding:18px 24px;color:#FFFFFF;text-align:{{ $rtl ? 'right' : 'left' }};">
              <div style="font-size:18px;font-weight:bold;">ITASSEL</div>
              <div style="font-size:12px;opacity:0.9;">Espace d'écoute et de contact — Ministère des Sports</div>
            </td>
          </tr>
          <tr>
            <td style="padding:24px;font-size:14px;line-height:1.6;text-align:{{ $rtl ? 'right' : 'left' }};">
              @yield('content')
            </td>
          </tr>
          <tr>
            <td style="padding:16px 24px;background:#F5F7FA;font-size:12px;color:#6B7280;text-align:{{ $rtl ? 'right' : 'left' }};">
              {{ __('emails.pied') }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>

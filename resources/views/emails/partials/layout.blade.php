{{-- Layout base de los mails de cuenta: una sola pieza para que los tres se vean iguales. --}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>@yield('title', 'DMIT')</title>
</head>

<body
    style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="background:#f4f5f7;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
                    <tr>
                        <td
                            style="background:#0f172a;padding:18px 28px;color:#ffffff;font-size:16px;font-weight:600;letter-spacing:.3px;">
                            DMIT · Facturador Online
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;font-size:15px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td
                            style="padding:16px 28px;background:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;line-height:1.5;">
                            @yield('footer', 'Este correo lo envió el sistema de facturación de DMIT. Si no esperabas este mensaje, podés ignorarlo.')
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>

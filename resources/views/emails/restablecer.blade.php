<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recupera tu acceso a VENTRACK</title>
</head>
<body style="margin:0;padding:0;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#1f2a3d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f7;padding:32px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(10,31,68,.08);">
                <tr>
                    <td style="height:6px;background:linear-gradient(90deg,#0461d8,#1597a5,#2dcb6a);background-color:#1597a5;"></td>
                </tr>
                <tr>
                    <td align="center" style="padding:28px 32px 8px;">
                        <img src="{{ url('/images/ventrack-logo.png') }}" alt="VENTRACK" width="200" style="display:block;max-width:200px;height:auto;">
                    </td>
                </tr>
                <tr>
                    <td style="padding:12px 32px 4px;">
                        <h1 style="margin:0 0 12px;font-size:22px;color:#0a1f44;">Hola, {{ $nombre }}</h1>
                        <p style="margin:0 0 14px;font-size:15px;line-height:1.6;">
                            Recibimos una solicitud para restablecer la contraseña de tu cuenta de <b>VENTRACK</b>.
                            Da clic en el botón para crear una nueva:
                        </p>
                    </td>
                </tr>
                <tr>
                    <td align="center" style="padding:10px 32px 18px;">
                        <a href="{{ $url }}" style="display:inline-block;background:#0461d8;color:#ffffff;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 30px;border-radius:10px;">
                            Restablecer contraseña
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 32px 8px;">
                        <p style="margin:0 0 12px;font-size:13.5px;line-height:1.6;color:#44566c;">
                            El enlace vence en <b>{{ $minutos }} minutos</b>. Si tú no lo solicitaste, ignora este correo: tu contraseña no cambiará.
                        </p>
                        <p style="margin:0 0 18px;font-size:12.5px;line-height:1.6;color:#6b7a90;">
                            Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                            <a href="{{ $url }}" style="color:#0461d8;word-break:break-all;">{{ $url }}</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px;background:#f6f9fc;border-top:1px solid #e3e9f1;font-size:12px;color:#6b7a90;text-align:center;">
                        VENTRACK · Promoción de ventas en tiempo real<br>
                        Este es un correo automático, por favor no respondas.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

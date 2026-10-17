<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bienvenido a Friend Soccer</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#0f172a; padding:24px 32px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">Friend Soccer</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="font-size:16px; color:#0f172a; margin:0 0 16px;">
                                ¡Hola {{ $user->first_name }}!
                            </p>
                            <p style="font-size:14px; color:#475569; line-height:1.6; margin:0 0 16px;">
                                Tu cuenta en Friend Soccer ya está lista. Ahora podés unirte a torneos,
                                armar tus propios grupos de pronósticos y competir contra tus amigos
                                (y contra Franco, nuestra IA).
                            </p>
                            <p style="font-size:14px; color:#475569; line-height:1.6; margin:0;">
                                ¡Que gane el mejor pronosticador!
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background-color:#f8fafc; font-size:12px; color:#94a3b8;">
                            Recibiste este correo porque te registraste en Friend Soccer con {{ $user->email }}.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>

<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Zahlungserinnerung</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

<h2>Zahlungserinnerung</h2>

<p>Sehr geehrte Damen und Herren,</p>

<p>
    F\u00fcr die Rechnung <strong>{{ $invoice->invoice_number }}</strong>
    steht noch ein offener Betrag aus.
</p>

<table style="width:100%; border-collapse: collapse; margin: 20px 0;">
    <tr>
        <td style="padding: 8px 4px; font-weight: bold;">Offener Betrag</td>
        <td style="padding: 8px 4px; font-weight: bold; text-align: right;">
            {{ number_format($invoice->balanceMilli() / 1_000_000, 2, ',', '.') }} \u20ac
        </td>
    </tr>
</table>

<p>Bitte begleichen Sie den ausstehenden Betrag zeitnah.</p>

<p>Mit freundlichen Gr\u00fc\u00dfen</p>

</body>
</html>

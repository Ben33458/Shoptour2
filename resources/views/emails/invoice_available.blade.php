<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><title>Rechnung verf\u00fcgbar</title></head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

<h2>Ihre Rechnung ist verf\u00fcgbar</h2>

<p>Sehr geehrte Damen und Herren,</p>

<p>
    Ihre Rechnung <strong>{{ $invoice->invoice_number }}</strong>
    vom {{ $invoice->finalized_at?->format('d.m.Y') }}
    wurde erstellt.
</p>

<table style="width:100%; border-collapse: collapse; margin: 20px 0;">
    <tr>
        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">Nettobetrag</td>
        <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">
            {{ number_format($invoice->total_net_milli / 1_000_000, 2, ',', '.') }} \u20ac
        </td>
    </tr>
    <tr>
        <td style="padding: 8px 0; border-bottom: 1px solid #eee;">MwSt.</td>
        <td style="padding: 8px 0; border-bottom: 1px solid #eee; text-align: right;">
            {{ number_format($invoice->total_tax_milli / 1_000_000, 2, ',', '.') }} \u20ac
        </td>
    </tr>
    <tr>
        <td style="padding: 8px 4px; font-weight: bold;">Gesamtbetrag</td>
        <td style="padding: 8px 4px; font-weight: bold; text-align: right;">
            {{ number_format($invoice->total_gross_milli / 1_000_000, 2, ',', '.') }} \u20ac
        </td>
    </tr>
</table>

<p>Die Rechnung steht Ihnen im Kundenportal zum Download bereit.</p>

<p>Mit freundlichen Gr\u00fc\u00dfen</p>

</body>
</html>

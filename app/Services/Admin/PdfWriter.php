<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin\Invoice;

/**
 * Generates minimal but valid PDF files for invoices.
 *
 * Uses raw PDF 1.4 format. No external dependencies.
 * Supports WinAnsiEncoding for Latin characters (incl. German umlauts).
 *
 * Output is a real, openable PDF — viewable in any PDF reader.
 */
class PdfWriter
{
    /** @var string[] */
    private array $objects = [];

    private function addObject(string $definition): int
    {
        $this->objects[] = $definition;

        return count($this->objects); // 1-based object number
    }

    /**
     * Generate PDF bytes for the given invoice and return the raw string.
     */
    public function generateInvoicePdf(Invoice $invoice): string
    {
        $this->objects = [];

        $order    = $invoice->order()->with('customer')->first();
        $customer = $order?->customer;

        $customerName = $customer
            ? trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''))
            : 'Unbekannt';
        $customerNum  = $customer?->customer_number ?? '-';

        $invoiceNum  = $invoice->invoice_number ?? ('ENTWURF-' . $invoice->id);
        $invoiceDate = $invoice->finalized_at?->format('d.m.Y') ?? now()->format('d.m.Y');
        $orderDate   = $order?->created_at?->format('d.m.Y') ?? '-';

        // Build content lines (PDF stream text)
        $lines   = [];
        $lines[] = $this->line('RECHNUNG', 16, 50, 790);
        $lines[] = $this->line('Rechnung-Nr.: ' . $invoiceNum, 11, 50, 760);
        $lines[] = $this->line('Datum: ' . $invoiceDate, 11, 50, 746);
        $lines[] = $this->line('Auftrag vom: ' . $orderDate, 11, 50, 732);
        $lines[] = $this->line('Kunde: ' . $this->sanitize($customerName) . '  [' . $customerNum . ']', 11, 50, 718);

        // Separator
        $lines[] = $this->line(str_repeat('-', 80), 9, 50, 700);

        // Column headers
        $lines[] = $this->line('Pos.', 9, 50, 685);
        $lines[] = $this->line('Beschreibung', 9, 120, 685);
        $lines[] = $this->line('Menge', 9, 370, 685);
        $lines[] = $this->line('Einzelpreis', 9, 430, 685);
        $lines[] = $this->line('Gesamt', 9, 510, 685);
        $lines[] = $this->line(str_repeat('-', 80), 9, 50, 680);

        // Line items
        $yPos  = 665;
        $posNo = 1;
        foreach ($invoice->items as $item) {
            if ($yPos < 120) {
                break; // Basic overflow guard (single-page MVP)
            }
            $unitEur  = $this->milliToEur($item->unit_price_gross_milli);
            $totalEur = $this->milliToEur($item->line_total_gross_milli);
            $desc     = $this->sanitize(mb_substr($item->description, 0, 55));
            $qtyStr   = number_format((float) $item->qty, 0, ',', '.');

            $lines[] = $this->line((string) $posNo, 9, 50, $yPos);
            $lines[] = $this->line($desc, 9, 120, $yPos);
            $lines[] = $this->line($qtyStr, 9, 370, $yPos);
            $lines[] = $this->line($unitEur . ' EUR', 9, 430, $yPos);
            $lines[] = $this->line($totalEur . ' EUR', 9, 510, $yPos);

            $yPos -= 14;
            $posNo++;
        }

        // Totals
        $yPos -= 8;
        $lines[] = $this->line(str_repeat('-', 80), 9, 50, $yPos);
        $yPos   -= 14;
        $lines[] = $this->line('Nettobetrag:', 10, 380, $yPos);
        $lines[] = $this->line($this->milliToEur($invoice->total_net_milli) . ' EUR', 10, 510, $yPos);
        $yPos   -= 14;
        $lines[] = $this->line('MwSt.:', 10, 380, $yPos);
        $lines[] = $this->line($this->milliToEur($invoice->total_tax_milli) . ' EUR', 10, 510, $yPos);

        if ($invoice->total_adjustments_milli !== 0) {
            $yPos   -= 14;
            $lines[] = $this->line('Anpassungen:', 10, 380, $yPos);
            $lines[] = $this->line($this->milliToEur($invoice->total_adjustments_milli) . ' EUR', 10, 510, $yPos);
        }

        if ($invoice->total_deposit_milli !== 0) {
            $yPos   -= 14;
            $lines[] = $this->line('Pfand (brutto):', 10, 380, $yPos);
            $lines[] = $this->line($this->milliToEur($invoice->total_deposit_milli) . ' EUR', 10, 510, $yPos);
        }

        $yPos   -= 16;
        $lines[] = $this->line('GESAMTBETRAG (brutto):', 12, 330, $yPos);
        $lines[] = $this->line($this->milliToEur($invoice->total_gross_milli) . ' EUR', 12, 510, $yPos);

        $contentStream = implode("\n", $lines);
        $streamLen     = strlen($contentStream);

        // Build PDF object tree
        // 1: Catalog
        // 2: Pages
        // 3: Page
        // 4: Content stream
        // 5: Font (Helvetica)

        $this->objects[] = '<< /Type /Catalog /Pages 2 0 R >>'; // obj 1
        $this->objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>'; // obj 2
        $this->objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>'; // obj 3
        $this->objects[] = "<< /Length {$streamLen} >>\nstream\n{$contentStream}\nendstream"; // obj 4
        $this->objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>'; // obj 5

        return $this->buildPdf();
    }

    /** Build a complete PDF binary string from collected objects. */
    private function buildPdf(): string
    {
        $body    = "%PDF-1.4\n%\xc2\xa5\xc2\xb1\xc3\xab\n";
        $offsets = [];

        foreach ($this->objects as $index => $objDef) {
            $objNum        = $index + 1;
            $offsets[$objNum] = strlen($body);
            $body         .= "{$objNum} 0 obj\n{$objDef}\nendobj\n";
        }

        $xrefOffset = strlen($body);
        $objCount   = count($this->objects) + 1; // +1 for free entry 0

        $xref = "xref\n0 {$objCount}\n";
        $xref .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $xref .= sprintf("%010d 00000 n \n", $offset);
        }

        $body .= $xref;
        $body .= "trailer\n<< /Size {$objCount} /Root 1 0 R >>\n";
        $body .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $body;
    }

    /** Build a PDF text-positioning command (absolute coordinates). */
    private function line(string $text, int $fontSize, int $x, int $y): string
    {
        return "BT /F1 {$fontSize} Tf {$x} {$y} Td ({$text}) Tj ET";
    }

    /** Convert milli-cents to display EUR string. */
    private function milliToEur(int $milli): string
    {
        return number_format($milli / 1_000_000, 2, ',', '.');
    }

    /**
     * Sanitize text for PDF stream — remove/replace characters that would break
     * the PDF string syntax. Keeps printable ASCII + replaces common German chars.
     */
    private function sanitize(string $text): string
    {
        // Replace German umlauts with their transliterations for safe PDF output
        $text = str_replace(
            ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', '€'],
            ['Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', 'EUR'],
            $text
        );
        // Escape parentheses and backslashes (PDF string special chars)
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        // Strip any remaining non-printable-ASCII
        return preg_replace('/[^\x20-\x7E]/', '?', $text) ?? $text;
    }
}

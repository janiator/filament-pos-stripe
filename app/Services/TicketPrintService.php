<?php

namespace App\Services;

use App\Models\ReceiptPrinter;
use App\Models\ReceiptTemplate;
use Illuminate\Support\Facades\File;
use RuntimeException;

class TicketPrintService
{
    public function renderFreeTicket(int $storeId, ReceiptPrinter $printer, array $params): string
    {
        $template = $this->resolveTemplateContent($storeId, 'freeticket', 'freeticket_template.xml');
        [$header, $loopBlock, $footer] = $this->splitTemplate($template, '<!-- FREETICKET-START -->', '<!-- FREETICKET-END -->');

        $date = $this->stringOrDefault($params['date'] ?? null, 'Alle');
        $place = $this->stringOrDefault($params['place'] ?? null, 'Alle');
        $code = $this->stringOrDefault($params['code'] ?? null, '');
        $amount = (int) ($params['amount'] ?? 1);
        $discount = $this->stringOrDefault($params['discount'] ?? null, '');
        $appliesTo = $this->stringOrDefault($params['applies_to'] ?? null, '');
        $maxTickets = (int) ($params['max_tickets'] ?? 0);
        $printerIdentifier = $this->escape($printer->device_id ?: (string) $printer->id);

        if ($code === '' || $amount <= 0) {
            throw new RuntimeException('Missing one or more required parameters: printer_id, code, or valid amount.');
        }

        $outputLoop = '';

        for ($i = 0; $i < $amount; $i++) {
            $ticketBlock = str_replace(
                ['<code>', '<place>', '<date>', '<printerid>', '<maxTickets>', '<appliesTo>'],
                [
                    $this->escape($code),
                    $this->escape($place),
                    $this->escape($date),
                    $printerIdentifier,
                    $this->escape((string) $maxTickets),
                    $this->escape($appliesTo),
                ],
                $loopBlock
            );

            $ticketBlock = $this->replaceConditionalBlock(
                $ticketBlock,
                'DISCOUNTLINE',
                $discount !== '',
                fn (string $block): string => str_replace('<discount>', $this->escape($discount), $block)
            );

            $ticketBlock = $this->replaceConditionalBlock(
                $ticketBlock,
                'EXPIRESAT',
                $date !== '' && $date !== 'Alle',
                fn (string $block): string => str_replace('<date>', $this->escape($date), $block)
            );

            $ticketBlock = $this->replaceConditionalBlock(
                $ticketBlock,
                'MAXTICKETS',
                $maxTickets > 0,
                fn (string $block): string => str_replace('<maxTickets>', $this->escape((string) $maxTickets), $block)
            );

            $ticketBlock = $this->replaceConditionalBlock(
                $ticketBlock,
                'APPLIESTO',
                $appliesTo !== '',
                fn (string $block): string => str_replace('<appliesTo>', $this->escape($appliesTo), $block)
            );

            $outputLoop .= $ticketBlock;
        }

        $xmlOutput = $header.$outputLoop.$footer;

        return $this->finalizeXml(str_replace('<printerid>', $printerIdentifier, $xmlOutput));
    }

    public function renderBookingTicket(int $storeId, ReceiptPrinter $printer, array $params): string
    {
        $template = $this->resolveTemplateContent($storeId, 'ticket', 'ticket_template.xml');
        [$header, $loopBlock, $footer] = $this->splitTemplate($template, '<!-- START LOOP -->', '<!-- END LOOP -->');

        $printerIdentifier = $this->escape($printer->device_id ?: (string) $printer->id);
        $heading = $this->stringOrDefault($params['heading'] ?? null, '');
        $orderNumber = $this->stringOrDefault($params['order_number'] ?? null, '');
        $date = $this->stringOrDefault($params['date'] ?? null, '');
        $place = $this->stringOrDefault($params['place'] ?? null, '');
        $amountPaid = $params['amount_paid'] ?? 0;
        $tickets = is_array($params['tickets'] ?? null) ? $params['tickets'] : [];

        if ($orderNumber === '' || $date === '' || $place === '' || $tickets === []) {
            throw new RuntimeException('Missing one or more required parameters: printer_id, order_number, date, place, or tickets.');
        }

        $ticketCount = count($tickets);
        $fallbackTicketPrice = $ticketCount > 0
            ? $this->formatTicketPriceFromMinorUnits($amountPaid / $ticketCount)
            : $this->formatTicketPriceFromMinorUnits(0);

        $outputLoop = '';

        foreach ($tickets as $ticket) {
            $category = $this->stringOrDefault($ticket['category'] ?? null, '');

            $ticketBlock = str_replace(
                [
                    '<heading>',
                    '<category>',
                    '<section>',
                    '<row>',
                    '<seat>',
                    '<orderNumber>',
                    '<dateTime>',
                    '<place>',
                    '<entrance>',
                    '<printerid>',
                    '<ticketPrice>',
                ],
                [
                    $this->escape($heading),
                    $this->escape($category),
                    $this->escape($this->stringOrDefault($ticket['section'] ?? null, '')),
                    $this->escape($this->stringOrDefault($ticket['row'] ?? null, '')),
                    $this->escape($this->stringOrDefault($ticket['seat'] ?? null, '')),
                    $this->escape($orderNumber),
                    $this->escape($date),
                    $this->escape($place),
                    $this->escape($this->stringOrDefault($ticket['entrance'] ?? null, '')),
                    $printerIdentifier,
                    $this->escape($this->formatTicketPrice($ticket['ticket_price'] ?? null) ?? $fallbackTicketPrice),
                ],
                $loopBlock
            );

            if (in_array($category, ['Losje', 'VIP Losje'], true)) {
                $ticketBlock = preg_replace('/<!--\s*TRIBUNE-START\s*-->.*?<!--\s*TRIBUNE-END\s*-->/s', '', $ticketBlock) ?? $ticketBlock;
                $ticketBlock = str_replace(['<!-- LOSJE-START -->', '<!-- LOSJE-END -->'], '', $ticketBlock);
            } elseif (in_array($category, ['Tribune', 'Sidetribune'], true)) {
                $ticketBlock = preg_replace('/<!--\s*LOSJE-START\s*-->.*?<!--\s*LOSJE-END\s*-->/s', '', $ticketBlock) ?? $ticketBlock;
                $ticketBlock = str_replace(['<!-- TRIBUNE-START -->', '<!-- TRIBUNE-END -->'], '', $ticketBlock);
            } else {
                $ticketBlock = preg_replace('/<!--\s*TRIBUNE-START\s*-->.*?<!--\s*TRIBUNE-END\s*-->/s', '', $ticketBlock) ?? $ticketBlock;
                $ticketBlock = preg_replace('/<!--\s*LOSJE-START\s*-->.*?<!--\s*LOSJE-END\s*-->/s', '', $ticketBlock) ?? $ticketBlock;
            }

            $outputLoop .= $ticketBlock;
        }

        $xmlOutput = $header.$outputLoop.$footer;

        return $this->finalizeXml(str_replace('<printerid>', $printerIdentifier, $xmlOutput));
    }

    private function resolveTemplateContent(int $storeId, string $templateType, string $fallbackFilename): string
    {
        $template = ReceiptTemplate::getTemplate($storeId, $templateType);

        if ($template) {
            return $template->content;
        }

        $path = base_path('resources/receipt-templates/epson/'.$fallbackFilename);

        if (! File::exists($path)) {
            throw new RuntimeException("Template file not found: {$fallbackFilename}");
        }

        return File::get($path);
    }

    private function splitTemplate(string $template, string $startMarker, string $endMarker): array
    {
        $startLoopPos = strpos($template, $startMarker);
        $endLoopPos = strpos($template, $endMarker);

        if ($startLoopPos === false || $endLoopPos === false || $endLoopPos < $startLoopPos) {
            throw new RuntimeException("Loop block not found in template for markers {$startMarker} / {$endMarker}.");
        }

        $loopStartOffset = $startLoopPos + strlen($startMarker);
        $loopBlock = substr($template, $loopStartOffset, $endLoopPos - $loopStartOffset);
        $header = substr($template, 0, $startLoopPos);
        $footer = substr($template, $endLoopPos + strlen($endMarker));

        return [$header, $loopBlock, $footer];
    }

    private function replaceConditionalBlock(string $content, string $markerName, bool $shouldKeep, callable $renderer): string
    {
        $pattern = sprintf(
            '/<!--\s*%s-START\s*-->(.*?)<!--\s*%s-END\s*-->/s',
            preg_quote($markerName, '/'),
            preg_quote($markerName, '/')
        );

        if (! $shouldKeep) {
            return preg_replace($pattern, '', $content) ?? $content;
        }

        return preg_replace_callback(
            $pattern,
            fn (array $matches): string => $renderer($matches[1]),
            $content
        ) ?? $content;
    }

    private function formatTicketPrice(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return $this->formatTicketPriceFromMinorUnits((int) $value);
        }

        if (! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, ',', ' ');
    }

    private function formatTicketPriceFromMinorUnits(int|float $value): string
    {
        return number_format(((float) $value) / 100, 2, ',', ' ');
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' || strtolower($trimmed) === 'null'
            ? $default
            : $trimmed;
    }

    private function finalizeXml(string $xml): string
    {
        return preg_replace('/^\s*[\r\n]+/m', '', $xml) ?? $xml;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }
}

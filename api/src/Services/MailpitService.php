<?php

namespace App\Services;

class MailpitService
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    public function getMessages(int $limit = 50): array
    {
        $json = @file_get_contents("{$this->baseUrl}/api/v1/messages?limit={$limit}");
        if ($json === false) {
            return ['total' => 0, 'messages' => []];
        }

        $data = json_decode($json, true);

        return [
            'total' => $data['total'] ?? 0,
            'messages' => array_map(fn($m) => [
                'id' => $m['ID'],
                'subject' => $m['Subject'],
                'from' => $m['From']['Address'] ?? '',
                'to' => implode(', ', array_column($m['To'] ?? [], 'Address')),
                'snippet' => $m['Snippet'] ?? '',
                'created' => $m['Created'],
                'read' => $m['Read'] ?? false,
            ], $data['messages'] ?? []),
        ];
    }

    public function getMessage(string $id): ?array
    {
        $json = @file_get_contents("{$this->baseUrl}/api/v1/message/{$id}");
        if ($json === false) {
            return null;
        }

        $m = json_decode($json, true);

        return [
            'id' => $m['ID'],
            'subject' => $m['Subject'],
            'from' => $m['From']['Address'] ?? '',
            'to' => implode(', ', array_column($m['To'] ?? [], 'Address')),
            'text' => trim($m['Text'] ?? ''),
            'created' => $m['Date'] ?? $m['Created'] ?? null,
        ];
    }
}

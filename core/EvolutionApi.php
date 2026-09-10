<?php
/**
 * EvolutionApi — Helper centralizado para a Evolution API (WhatsApp)
 * ============================================================
 * Compatível com Evolution API v1 e v2.
 */
class EvolutionApi
{
    // ── Endpoints ──────────────────────────────────────────────
    private const EP_SEND_TEXT        = '/message/sendText/{instance}';
    private const EP_FETCH_GROUPS     = '/group/fetchAllGroups/{instance}?getParticipants=false';
    private const EP_INSTANCE_CREATE  = '/instance/create';
    private const EP_INSTANCE_CONNECT = '/instance/connect/{instance}';
    private const EP_INSTANCE_STATE   = '/instance/connectionState/{instance}';
    private const EP_INSTANCE_DELETE  = '/instance/delete/{instance}';

    // ── Timeout padrão e timeout para listagem de grupos ───────
    private const TIMEOUT_DEFAULT     = 20;
    private const TIMEOUT_FETCH_GROUPS = 120; // suporta 500+ grupos sem timeout

    // ── Envia mensagem de texto (individual) ───────────────────
    public static function sendText(string $instance, string $number, string $text): array
    {
        return self::request('POST', self::ep(self::EP_SEND_TEXT, $instance), [
            'number' => $number,
            'text'   => $text,
        ]);
    }

    // ── Envia mensagem para grupo ──────────────────────────────
    public static function sendTextGroup(string $instance, string $groupId, string $text): array
    {
        return self::request('POST', self::ep(self::EP_SEND_TEXT, $instance), [
            'number' => $groupId,
            'text'   => $text,
        ]);
    }

    // ── Lista grupos da instância ──────────────────────────────
    // Timeout de 120s para suportar contas com 500+ grupos
    public static function fetchGroups(string $instance): array
    {
        $res = self::request('GET', self::ep(self::EP_FETCH_GROUPS, $instance), [], self::TIMEOUT_FETCH_GROUPS);
        return $res['data'] ?? (is_array($res) && !isset($res['error']) ? $res : []);
    }

    // ── Cria instância — compatível com v1 e v2 ────────────────
    public static function createInstance(string $name, string $webhookUrl = ''): array
    {
        $webhook = $webhookUrl ?: EVOLUTION_WEBHOOK_URL;

        // Body compatível com v2 (estrutura de webhook aninhada)
        // A v1 também aceita esse formato sem problemas
        $body = [
            'instanceName' => $name,
            'qrcode'       => true,
            'integration'  => 'WHATSAPP-BAILEYS',
            'webhook'      => [
                'url'      => $webhook,
                'byEvents' => false,
                'base64'   => false,
                'events'   => [
                    'QRCODE_UPDATED',
                    'CONNECTION_UPDATE',
                    'MESSAGES_UPSERT',
                ],
            ],
        ];

        $result = self::request('POST', self::EP_INSTANCE_CREATE, $body);

        // Se der Bad Request com webhook aninhado, tenta formato v1 (webhook flat)
        if (isset($result['ok']) && $result['ok'] === false && ($result['code'] ?? 0) === 400) {
            $bodyV1 = [
                'instanceName'      => $name,
                'qrcode'            => true,
                'integration'       => 'WHATSAPP-BAILEYS',
                'webhookUrl'        => $webhook,
                'webhookByEvents'   => false,
                'events'            => ['QRCODE_UPDATED', 'CONNECTION_UPDATE', 'MESSAGES_UPSERT'],
            ];
            $result = self::request('POST', self::EP_INSTANCE_CREATE, $bodyV1);
        }

        // Se ainda der erro, tenta sem webhook (mínimo absoluto)
        if (isset($result['ok']) && $result['ok'] === false && ($result['code'] ?? 0) === 400) {
            $bodyMin = [
                'instanceName' => $name,
                'qrcode'       => true,
                'integration'  => 'WHATSAPP-BAILEYS',
            ];
            $result = self::request('POST', self::EP_INSTANCE_CREATE, $bodyMin);
        }

        return $result;
    }

    public static function connectInstance(string $instance): array
    {
        return self::request('GET', self::ep(self::EP_INSTANCE_CONNECT, $instance));
    }

    public static function connectionState(string $instance): array
    {
        return self::request('GET', self::ep(self::EP_INSTANCE_STATE, $instance));
    }

    public static function deleteInstance(string $instance): array
    {
        // Evolution API v2: logout antes de deletar (ignora erro se já desconectada)
        self::request('DELETE', '/instance/logout/' . urlencode($instance));

        // Deleta a instância
        $result = self::request('DELETE', self::ep(self::EP_INSTANCE_DELETE, $instance));

        // Fallback para outros builds da Evolution API
        if (isset($result['ok']) && $result['ok'] === false) {
            self::request('DELETE', '/instance/' . urlencode($instance));
        }

        return $result;
    }

    // ── Core HTTP ──────────────────────────────────────────────
    public static function request(string $method, string $endpoint, array $body = [], int $timeout = self::TIMEOUT_DEFAULT): array
    {
        $url      = rtrim(EVOLUTION_API_URL, '/') . $endpoint;
        $maxTries = 2; // 1 tentativa + 1 retry automático
        $lastErr  = '';
        $code     = 0;
        $data     = [];

        for ($attempt = 1; $attempt <= $maxTries; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'apikey: ' . EVOLUTION_API_KEY,
                ],
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            } elseif ($method === 'DELETE') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            }

            $raw  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($err) {
                $lastErr = 'cURL: ' . $err;
                if ($attempt < $maxTries) sleep(2); // aguarda 2s antes do retry
                continue;
            }

            $data = $raw ? (json_decode($raw, true) ?? []) : [];

            // Retry apenas em erros de servidor (5xx) ou timeout (0)
            if ($code === 0 || $code >= 500) {
                $lastErr = "HTTP $code";
                if ($attempt < $maxTries) sleep(2);
                continue;
            }

            break; // sucesso ou erro do cliente (4xx) — não faz retry
        }

        if ($lastErr && $code === 0) return ['ok' => false, 'error' => $lastErr, 'code' => 0];

        $data = $data ?: [];

        if ($code >= 200 && $code < 300) {
            $data['ok'] = true;
            return $data;
        }

        $msg = $data['message'] ?? $data['error'] ?? "HTTP $code";
        if (is_array($msg)) $msg = implode(', ', $msg);
        return ['ok' => false, 'error' => $msg, 'code' => $code];
    }

    // ── Substitui {instance} no endpoint ──────────────────────
    private static function ep(string $template, string $instance): string
    {
        return str_replace('{instance}', urlencode($instance), $template);
    }
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

/** Petición HTTP mínima con curl. */
function http_req(string $method, string $url, array $headers, ?string $body): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $r = curl_exec($ch);
    if ($r === false) { $err = curl_error($ch); curl_close($ch); throw new RuntimeException('HTTP: ' . $err); }
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($code >= 400) throw new RuntimeException("HTTP $code: " . substr($r, 0, 300));
    return $r;
}

/** Credenciales temporales del rol de instancia vía IMDSv2. */
function imds_creds(): array {
    $token = http_req('PUT', 'http://169.254.169.254/latest/api/token',
        ['X-aws-ec2-metadata-token-ttl-seconds: 60'], '');
    $h = ['X-aws-ec2-metadata-token: ' . $token];
    $role = trim(http_req('GET',
        'http://169.254.169.254/latest/meta-data/iam/security-credentials/', $h, null));
    $json = http_req('GET',
        'http://169.254.169.254/latest/meta-data/iam/security-credentials/' . $role, $h, null);
    $c = json_decode($json, true);
    return ['key' => $c['AccessKeyId'], 'secret' => $c['SecretAccessKey'], 'token' => $c['Token']];
}

/** Envía un email transaccional por SES v2 (SigV4, sin SDK). Lanza excepción si falla. */
function ses_send(string $toEmail, string $subject, string $html, string $text): string {
    $region = cfg('AWS_REGION', 'eu-west-3');
    $host    = "email.$region.amazonaws.com";
    $uri     = '/v2/email/outbound-emails';
    $from     = cfg('MAIL_FROM');
    $fromName = cfg('MAIL_FROM_NAME', '24h');

    $payload = json_encode([
        'FromEmailAddress' => sprintf('%s <%s>', $fromName, $from),
        'Destination'      => ['ToAddresses' => [$toEmail]],
        'Content' => ['Simple' => [
            'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
            'Body'    => [
                'Html' => ['Data' => $html, 'Charset' => 'UTF-8'],
                'Text' => ['Data' => $text, 'Charset' => 'UTF-8'],
            ],
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $creds = imds_creds();
    $amzdate   = gmdate('Ymd\THis\Z');
    $datestamp = gmdate('Ymd');
    $service   = 'ses';
    $payloadHash = hash('sha256', $payload);

    $canonHeaders = "content-type:application/json\n"
        . "host:$host\n"
        . "x-amz-content-sha256:$payloadHash\n"
        . "x-amz-date:$amzdate\n"
        . "x-amz-security-token:{$creds['token']}\n";
    $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date;x-amz-security-token';
    $canonReq = "POST\n$uri\n\n$canonHeaders\n$signedHeaders\n$payloadHash";

    $scope  = "$datestamp/$region/$service/aws4_request";
    $toSign = "AWS4-HMAC-SHA256\n$amzdate\n$scope\n" . hash('sha256', $canonReq);

    $kDate    = hash_hmac('sha256', $datestamp, 'AWS4' . $creds['secret'], true);
    $kRegion  = hash_hmac('sha256', $region, $kDate, true);
    $kService = hash_hmac('sha256', $service, $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $toSign, $kSigning);

    $auth = "AWS4-HMAC-SHA256 Credential={$creds['key']}/$scope, "
        . "SignedHeaders=$signedHeaders, Signature=$signature";

    $resp = http_req('POST', "https://$host$uri", [
        'Content-Type: application/json',
        'X-Amz-Date: ' . $amzdate,
        'X-Amz-Content-Sha256: ' . $payloadHash,
        'X-Amz-Security-Token: ' . $creds['token'],
        'Authorization: ' . $auth,
    ], $payload);

    $d = json_decode($resp, true);
    if (!isset($d['MessageId'])) throw new RuntimeException('SES: ' . substr($resp, 0, 300));
    return $d['MessageId'];
}

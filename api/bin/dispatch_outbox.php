<?php
declare(strict_types=1);

require __DIR__ . '/../src/Core/Env.php';
\App\Core\Env::load(__DIR__ . '/../.env');

$config = require __DIR__ . '/../config/config.php';

require __DIR__ . '/../src/Core/Database.php';
require __DIR__ . '/../src/Core/HttpClient.php';
require __DIR__ . '/../src/Core/Hmac.php';
require __DIR__ . '/../src/Repositories/OutboxRepository.php';
require __DIR__ . '/../src/Repositories/AutomationRunsRepository.php';
require __DIR__ . '/../src/Services/OutboxService.php';

use App\Core\Database;
use App\Core\HttpClient;
use App\Core\Hmac;
use App\Repositories\OutboxRepository;
use App\Repositories\AutomationRunsRepository;
use App\Services\OutboxService;

$db = new Database($config['db']);
$outbox = new OutboxRepository($db);
$runs = new AutomationRunsRepository($db);

$n8nUrl = $config['n8n']['events_webhook_url'] ?? (getenv('N8N_EVENTS_WEBHOOK_URL') ?: '');
$secret = $config['n8n']['hmac_secret'] ?? (getenv('N8N_HMAC_SECRET') ?: '');
$timeout = (int)($config['n8n']['http_timeout'] ?? (getenv('OUTBOX_HTTP_TIMEOUT') ?: 10));
$maxAttempts = (int)($config['n8n']['max_attempts'] ?? (getenv('OUTBOX_MAX_ATTEMPTS') ?: 20));
$batchSize = (int)($config['n8n']['batch_size'] ?? (getenv('OUTBOX_BATCH_SIZE') ?: 20));

if (!$n8nUrl) {
  fwrite(STDERR, "Missing N8N_EVENTS_WEBHOOK_URL\n");
  exit(1);
}

$batch = $outbox->claimBatch($batchSize);
if (!$batch) {
  echo "No events to dispatch.\n";
  exit(0);
}

foreach ($batch as $evt) {
  $id = (int)$evt['id'];
  $attempts = (int)$evt['attempts'] + 1; // en DB ya se incrementó; esto es orientativo
  $correlationId = (string)$evt['correlation_id'];
  $eventType = (string)$evt['event_type'];

  $payload = [
    'correlationId' => $correlationId,
    'eventType' => $eventType,
    'aggregateType' => (string)$evt['aggregate_type'],
    'aggregateId' => (string)$evt['aggregate_id'],
    'payload' => $evt['payload'] ?? [],
    'ts' => date('c'),
  ];

  $raw = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $sig = $secret ? Hmac::sign($raw ?: '', $secret) : '';

  $headers = [
    'X-Correlation-Id: ' . $correlationId,
  ];
  if ($sig) $headers[] = 'X-Signature: ' . $sig;

  $resp = HttpClient::postJson($n8nUrl, $payload, $headers, $timeout);

  if ($resp['status'] >= 200 && $resp['status'] < 300) {
    $outbox->markDelivered($id);

    // Creamos/actualizamos run "received" (n8n podrá marcar succeeded/failed por callback)
    $runs->upsertReceived($correlationId, $eventType, null, null);

    echo "DELIVERED id={$id} correlationId={$correlationId}\n";
    continue;
  }

  $err = "dispatch_failed status={$resp['status']} body=" . mb_substr($resp['body'], 0, 2000);

  if ($attempts >= $maxAttempts) {
    $outbox->markDead($id, $err);
    $runs->upsertReceived($correlationId, $eventType, null, null);
    $runs->markFailed($correlationId, "dead_letter: " . $err);
    echo "DEAD id={$id} correlationId={$correlationId}\n";
    continue;
  }

  $next = OutboxService::nextAttemptAt($attempts);
  $outbox->markFailed($id, $err, $next);

  echo "FAILED id={$id} correlationId={$correlationId} next={$next->format('c')}\n";
}

exit(0);

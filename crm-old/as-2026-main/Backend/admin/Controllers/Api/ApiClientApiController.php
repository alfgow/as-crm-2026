<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Helpers/ApiAuthConfig.php';
require_once __DIR__ . '/../../Helpers/ApiScopeHelper.php';
require_once __DIR__ . '/../../Models/ApiClientModel.php';

use App\Helpers\ApiAuthConfig;
use App\Helpers\ApiScopeHelper;
use App\Models\ApiClientModel;
use Throwable;

class ApiClientApiController
{
    public function __construct(private readonly ApiClientModel $apiClientModel = new ApiClientModel())
    {
    }

    public function index(): void
    {
        $payload = [
            'clients'           => $this->apiClientModel->all(),
            'supported_scopes'  => ApiScopeHelper::descriptions(),
            'expected_audience' => ApiAuthConfig::expectedAudience(),
        ];

        $this->jsonResponse($payload);
    }

    public function store(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['error' => 'invalid_request'], 400);
                return;
            }

            $name      = trim((string)($data['name'] ?? ''));
            $scopes    = isset($data['scopes']) ? (array)$data['scopes'] : [];
            $rateLimit = (int)($data['rate_limit'] ?? 60);

            if ($name === '') {
                $this->jsonResponse(['error' => 'missing_name'], 400);
                return;
            }

            $scopes = ApiScopeHelper::filter($scopes);
            if ($scopes === []) {
                $this->jsonResponse(['error' => 'missing_scopes'], 400);
                return;
            }

            $result = $this->apiClientModel->createClient($name, $scopes, max(1, $rateLimit));

            $this->jsonResponse([
                'client_id'     => $result['client_id'] ?? null,
                'client_secret' => $result['client_secret'] ?? null,
                'scopes'        => $scopes,
            ]);
        } catch (Throwable $exception) {
            $this->jsonResponse(['error' => 'server_error'], 500);
        }
    }

    public function rotateSecret(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['error' => 'invalid_request'], 400);
                return;
            }

            $clientId = (int)($data['client_id'] ?? 0);
            if ($clientId <= 0) {
                $this->jsonResponse(['error' => 'invalid_client'], 400);
                return;
            }

            $result = $this->apiClientModel->rotateSecret($clientId);

            $this->jsonResponse([
                'client_id'     => $result['client_id'] ?? null,
                'client_secret' => $result['client_secret'] ?? null,
                'is_rotation'   => true,
            ]);
        } catch (Throwable $exception) {
            $this->jsonResponse(['error' => 'server_error'], 500);
        }
    }

    private function readJsonInput(): ?array
    {
        $input = file_get_contents('php://input');
        if ($input === false || trim($input) === '') {
            return null;
        }

        $decoded = json_decode($input, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

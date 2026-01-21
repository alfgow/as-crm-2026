<?php

declare(strict_types=1);

namespace App\Controllers\Api;

require_once __DIR__ . '/../../Models/BlogModel.php';

use App\Models\BlogModel;
use PDOException;
use Throwable;

class BlogApiController
{
    public function __construct(private readonly BlogModel $blogModel = new BlogModel())
    {
    }

    public function index(): void
    {
        $this->jsonResponse([
            'posts' => $this->blogModel->all(),
        ]);
    }

    public function store(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['error' => 'invalid_request'], 400);
                return;
            }

            $titulo    = trim((string) ($data['title'] ?? $data['titulo'] ?? ''));
            $contenido = trim((string) ($data['contenido'] ?? ''));
            $categoria = trim((string) ($data['category'] ?? $data['categoria'] ?? ''));
            $etiquetas = trim((string) ($data['tags'] ?? $data['etiquetas'] ?? ''));
            $imagenKey = trim((string) ($data['image_key'] ?? $data['imagen_key'] ?? ''));
            $slug      = trim((string) ($data['slug'] ?? ''));

            if ($titulo === '' || $contenido === '' || $categoria === '') {
                $this->jsonResponse(['error' => 'missing_fields'], 400);
                return;
            }

            $id = $this->blogModel->create([
                'titulo'     => $titulo,
                'contenido'  => $contenido,
                'categoria'  => $categoria,
                'etiquetas'  => $etiquetas,
                'imagen_key' => $imagenKey,
                'slug'       => $slug,
            ]);

            $this->jsonResponse([
                'id' => $id,
            ], 201);
        } catch (PDOException $exception) {
            $this->jsonResponse(['error' => 'invalid_request'], 400);
        } catch (Throwable $exception) {
            $this->jsonResponse(['error' => 'server_error'], 500);
        }
    }

    public function update(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['error' => 'invalid_request'], 400);
                return;
            }

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                $this->jsonResponse(['error' => 'invalid_id'], 400);
                return;
            }

            $payload = array_filter([
                'titulo'     => isset($data['title']) || isset($data['titulo'])
                    ? trim((string) ($data['title'] ?? $data['titulo']))
                    : null,
                'contenido'  => isset($data['contenido']) ? trim((string) $data['contenido']) : null,
                'categoria'  => isset($data['category']) || isset($data['categoria'])
                    ? trim((string) ($data['category'] ?? $data['categoria']))
                    : null,
                'etiquetas'  => isset($data['tags']) || isset($data['etiquetas'])
                    ? trim((string) ($data['tags'] ?? $data['etiquetas']))
                    : null,
                'imagen_key' => isset($data['image_key']) || isset($data['imagen_key'])
                    ? trim((string) ($data['image_key'] ?? $data['imagen_key']))
                    : null,
                'slug'       => isset($data['slug']) ? trim((string) $data['slug']) : null,
            ], static fn($value) => $value !== null);

            $updated = $this->blogModel->update($id, $payload);

            $this->jsonResponse([
                'updated' => $updated,
            ]);
        } catch (PDOException $exception) {
            $this->jsonResponse(['error' => 'invalid_request'], 400);
        } catch (Throwable $exception) {
            $this->jsonResponse(['error' => 'server_error'], 500);
        }
    }

    public function delete(): void
    {
        try {
            $data = $this->readJsonInput();
            if ($data === null) {
                $this->jsonResponse(['error' => 'invalid_request'], 400);
                return;
            }

            $id = (int) ($data['id'] ?? 0);
            if ($id <= 0) {
                $this->jsonResponse(['error' => 'invalid_id'], 400);
                return;
            }

            $deleted = $this->blogModel->delete($id);

            $this->jsonResponse([
                'deleted' => $deleted,
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

<?php
namespace App\Repositories;

use App\Core\Database;

final class BlogRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(): array {
    $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
    $st = $this->pdo->query($sql);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT * FROM blog_posts WHERE id = :id LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function findBySlug(string $slug): ?array {
    $sql = "SELECT * FROM blog_posts WHERE slug = :slug LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':slug' => $slug]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): array {
    $titulo = trim((string)($data['titulo'] ?? ''));
    $contenido = (string)($data['contenido'] ?? '');
    $categoria = trim((string)($data['categoria'] ?? ''));
    $etiquetas = trim((string)($data['etiquetas'] ?? ''));
    $imagenKey = trim((string)($data['imagen_key'] ?? ''));
    $slugInput = isset($data['slug']) ? trim((string)$data['slug']) : '';

    if ($titulo === '' || $contenido === '' || $categoria === '') {
      throw new \RuntimeException('Faltan campos obligatorios: titulo, contenido, categoria.');
    }

    $slugBase = $slugInput !== '' ? $slugInput : $this->slugify($titulo);
    $slug = $this->ensureUniqueSlug($slugBase);

    $sql = "INSERT INTO blog_posts
            (titulo, contenido, categoria, etiquetas, imagen_key, created_at, updated_at, slug)
            VALUES
            (:titulo, :contenido, :categoria, :etiquetas, :imagen_key, NOW(), NOW(), :slug)";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':titulo' => $titulo,
      ':contenido' => $contenido,
      ':categoria' => $categoria,
      ':etiquetas' => $etiquetas,
      ':imagen_key' => $imagenKey,
      ':slug' => $slug,
    ]);

    $id = (int)$this->pdo->lastInsertId();
    return $this->findById($id) ?? [];
  }

  public function update(int $id, array $data): ?array {
    $post = $this->findById($id);
    if (!$post) {
      return null;
    }

    $titulo = trim((string)($data['titulo'] ?? $post['titulo']));
    $contenido = (string)($data['contenido'] ?? $post['contenido']);
    $categoria = trim((string)($data['categoria'] ?? $post['categoria']));
    $etiquetas = trim((string)($data['etiquetas'] ?? (string)($post['etiquetas'] ?? '')));
    $imagenKey = trim((string)($data['imagen_key'] ?? (string)($post['imagen_key'] ?? '')));
    $slugInput = isset($data['slug']) ? trim((string)$data['slug']) : '';

    if ($slugInput !== '') {
      $slug = $this->ensureUniqueSlug($slugInput, $id);
    } elseif ($titulo !== (string)$post['titulo']) {
      $slug = $this->ensureUniqueSlug($this->slugify($titulo), $id);
    } else {
      $slug = (string)($post['slug'] ?? '');
    }

    $sql = "UPDATE blog_posts
            SET titulo = :titulo,
                contenido = :contenido,
                categoria = :categoria,
                etiquetas = :etiquetas,
                imagen_key = :imagen_key,
                slug = :slug,
                updated_at = NOW()
            WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':titulo' => $titulo,
      ':contenido' => $contenido,
      ':categoria' => $categoria,
      ':etiquetas' => $etiquetas,
      ':imagen_key' => $imagenKey,
      ':slug' => $slug,
      ':id' => $id,
    ]);

    return $this->findById($id);
  }

  public function delete(int $id): bool {
    $sql = "DELETE FROM blog_posts WHERE id = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    return $st->rowCount() > 0;
  }

  private function slugify(string $texto): string {
    $texto = trim($texto);
    $reemplazos = [
      'ñ' => 'n', 'Ñ' => 'n',
      'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
      'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
      'ü' => 'u', 'Ü' => 'u',
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/u', '-', $texto) ?? '';
    return trim($texto, '-');
  }

  private function ensureUniqueSlug(string $baseSlug, ?int $excludeId = null): string {
    $slug = $baseSlug !== '' ? $baseSlug : 'post';
    $suffix = 1;

    while ($this->slugExists($slug, $excludeId)) {
      $suffix++;
      $slug = $baseSlug . '-' . $suffix;
    }

    return $slug;
  }

  private function slugExists(string $slug, ?int $excludeId = null): bool {
    $sql = "SELECT COUNT(*) FROM blog_posts WHERE slug = :slug";
    $params = [':slug' => $slug];

    if ($excludeId !== null) {
      $sql .= " AND id <> :id";
      $params[':id'] = $excludeId;
    }

    $st = $this->pdo->prepare($sql);
    $st->execute($params);
    return ((int)$st->fetchColumn()) > 0;
  }
}

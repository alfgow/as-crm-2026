<?php
namespace App\Repositories;

use App\Core\Database;

final class AsesorProspectadoComentarioRepository {
  private \PDO $pdo;

  public function __construct(Database $db) {
    $this->pdo = $db->pdo();
  }

  public function findAll(array $filters = []): array {
    $sql = "SELECT `id`, `id_prospecto`, `comentario`, `fecha`
            FROM `asesores-prospectados-comentarios`";
    $where = [];
    $params = [];

    if (isset($filters['id_prospecto']) && (int)$filters['id_prospecto'] > 0) {
      $where[] = "`id_prospecto` = :id_prospecto";
      $params[':id_prospecto'] = (int)$filters['id_prospecto'];
    }

    if ($where !== []) {
      $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY `id` DESC";

    $st = $this->pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public function findById(int $id): ?array {
    $sql = "SELECT `id`, `id_prospecto`, `comentario`, `fecha`
            FROM `asesores-prospectados-comentarios`
            WHERE `id` = :id
            LIMIT 1";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public function create(array $data): array {
    $sql = "INSERT INTO `asesores-prospectados-comentarios` (`id_prospecto`, `comentario`, `fecha`)
            VALUES (:id_prospecto, :comentario, :fecha)";
    $st = $this->pdo->prepare($sql);
    $st->execute([
      ':id_prospecto' => (int)$data['id_prospecto'],
      ':comentario' => trim((string)$data['comentario']),
      ':fecha' => trim((string)$data['fecha']),
    ]);

    return $this->findById((int)$this->pdo->lastInsertId()) ?? [];
  }

  public function update(int $id, array $data): ?array {
    $fields = [];
    $params = [':id' => $id];

    if (array_key_exists('id_prospecto', $data)) {
      $fields[] = "`id_prospecto` = :id_prospecto";
      $params[':id_prospecto'] = (int)$data['id_prospecto'];
    }
    if (array_key_exists('comentario', $data)) {
      $fields[] = "`comentario` = :comentario";
      $params[':comentario'] = trim((string)$data['comentario']);
    }
    if (array_key_exists('fecha', $data)) {
      $fields[] = "`fecha` = :fecha";
      $params[':fecha'] = trim((string)$data['fecha']);
    }

    if ($fields === []) {
      return $this->findById($id);
    }

    $sql = "UPDATE `asesores-prospectados-comentarios`
            SET " . implode(', ', $fields) . "
            WHERE `id` = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute($params);

    if ($st->rowCount() === 0 && $this->findById($id) === null) {
      return null;
    }

    return $this->findById($id);
  }

  public function delete(int $id): bool {
    $sql = "DELETE FROM `asesores-prospectados-comentarios` WHERE `id` = :id";
    $st = $this->pdo->prepare($sql);
    $st->execute([':id' => $id]);
    return $st->rowCount() > 0;
  }
}

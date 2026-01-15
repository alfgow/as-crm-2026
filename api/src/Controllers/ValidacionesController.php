<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\ValidacionRepository;
use App\Repositories\InquilinoRepository;

final class ValidacionesController {
  private array $config;
  private ValidacionRepository $validaciones;
  private InquilinoRepository $inquilinos;

  public function __construct(array $config, ValidacionRepository $validaciones, InquilinoRepository $inquilinos) {
    $this->config = $config;
    $this->validaciones = $validaciones;
    $this->inquilinos = $inquilinos;
  }

  // GET /api/v1/inquilinos/{id}/validaciones
  public function show(Request $req, Response $res, array $params): void {
      $idInquilino = (int)($params['id'] ?? 0);
      
      // Verify inquilino exists
      $inquilino = $this->inquilinos->findById($idInquilino);
      if (!$inquilino) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]], 404);
          return;
      }

      $data = $this->validaciones->findByInquilinoId($idInquilino);
      
      // If no validation record exists yet, return empty object or null? 
      // Usually better to return null or default structure. Let's return null if not found but with 200 OK.
      
      $res->json(['data' => $data, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
  }

  // PUT /api/v1/inquilinos/{id}/validaciones
  public function update(Request $req, Response $res, array $params): void {
      $idInquilino = (int)($params['id'] ?? 0);
      $body = $req->getJson();

      // Verify inquilino exists
      $inquilino = $this->inquilinos->findById($idInquilino);
      if (!$inquilino) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'not_found', 'message' => 'Inquilino not found']]], 404);
          return;
      }

      if (empty($body)) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'bad_request', 'message' => 'No data to update']]], 400);
          return;
      }

      try {
          $updated = $this->validaciones->upsert($idInquilino, $body);
          $res->json(['data' => $updated, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => []]);
      } catch (\Throwable $e) {
          $res->json(['data' => null, 'meta' => ['requestId' => $req->getRequestId()], 'errors' => [['code' => 'db_error', 'message' => $e->getMessage()]]], 500);
      }
  }
}

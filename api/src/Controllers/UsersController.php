<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\UserRepository;

final class UsersController {
  private array $config;
  private UserRepository $users;

  public function __construct(array $config, UserRepository $users) {
    $this->config = $config;
    $this->users = $users;
  }

  public function me(Request $req, Response $res, array $ctx): void {
    $user = $this->users->findById($ctx['userId']);
    if (!$user) {
      $res->json([
        'data' => null,
        'meta' => ['requestId' => $req->getRequestId()],
        'errors' => [[ 'code' => 'not_found', 'message' => 'User not found' ]],
      ], 404);
    }

    unset($user['password']); // nunca regreses hashes
    $res->json([
      'data' => $user,
      'meta' => ['requestId' => $req->getRequestId()],
      'errors' => [],
    ]);
  }
}

<?php
namespace App\Services;

final class ApiClientScopeCatalog {
  private const DEFAULT_SCOPES = [
    [
      'value' => 'leads:read',
      'label' => 'Leads - Lectura',
      'resource' => 'leads',
      'access' => 'read',
    ],
    [
      'value' => 'leads:write',
      'label' => 'Leads - Escritura',
      'resource' => 'leads',
      'access' => 'write',
    ],
    [
      'value' => 'polizas:read',
      'label' => 'Polizas - Lectura',
      'resource' => 'polizas',
      'access' => 'read',
    ],
    [
      'value' => 'polizas:write',
      'label' => 'Polizas - Escritura',
      'resource' => 'polizas',
      'access' => 'write',
    ],
    [
      'value' => 'finanzas:read',
      'label' => 'Finanzas - Lectura',
      'resource' => 'finanzas',
      'access' => 'read',
    ],
    [
      'value' => 'finanzas:write',
      'label' => 'Finanzas - Escritura',
      'resource' => 'finanzas',
      'access' => 'write',
    ],
    [
      'value' => 'inmuebles:read',
      'label' => 'Inmuebles - Lectura',
      'resource' => 'inmuebles',
      'access' => 'read',
    ],
    [
      'value' => 'inmuebles:write',
      'label' => 'Inmuebles - Escritura',
      'resource' => 'inmuebles',
      'access' => 'write',
    ],
    [
      'value' => 'asesores-prospectados:read',
      'label' => 'Asesores prospectados - Lectura',
      'resource' => 'asesores-prospectados',
      'access' => 'read',
    ],
    [
      'value' => 'asesores-prospectados:write',
      'label' => 'Asesores prospectados - Escritura',
      'resource' => 'asesores-prospectados',
      'access' => 'write',
    ],
    [
      'value' => 'asesores-prospectados-comentarios:read',
      'label' => 'Comentarios de asesores prospectados - Lectura',
      'resource' => 'asesores-prospectados-comentarios',
      'access' => 'read',
    ],
    [
      'value' => 'asesores-prospectados-comentarios:write',
      'label' => 'Comentarios de asesores prospectados - Escritura',
      'resource' => 'asesores-prospectados-comentarios',
      'access' => 'write',
    ],
  ];

  public function all(): array {
    return self::DEFAULT_SCOPES;
  }

  public function values(): array {
    return array_values(array_map(
      static fn(array $scope): string => $scope['value'],
      self::DEFAULT_SCOPES
    ));
  }

  public function filterRequested(array $scopes): array {
    $allowed = array_fill_keys($this->values(), true);
    $normalized = [];

    foreach ($scopes as $scope) {
      if (!is_string($scope)) {
        continue;
      }

      $scope = trim($scope);
      if ($scope === '' || !isset($allowed[$scope])) {
        continue;
      }

      $normalized[$scope] = true;
    }

    return array_keys($normalized);
  }
}

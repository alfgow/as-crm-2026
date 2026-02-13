<?php
namespace App\Services;

final class DocxTemplateService {
  /**
   * @return array<int, string>
   */
  public function getVariables(string $templatePath): array {
    $parts = $this->readXmlParts($templatePath);
    $found = [];
    foreach ($parts as $xml) {
      if (preg_match_all('/\$\{([^}]+)\}/u', $xml, $matches)) {
        foreach ($matches[1] as $variable) {
          $found[] = trim((string)$variable);
        }
      }
    }
    $found = array_values(array_unique(array_filter($found, fn($v) => $v !== '')));
    sort($found);
    return $found;
  }

  /**
   * @param array<string, scalar|null> $values
   */
  public function renderToFile(string $templatePath, array $values, string $destinationPath): void {
    if (!copy($templatePath, $destinationPath)) {
      throw new \RuntimeException('No fue posible crear archivo temporal DOCX');
    }

    $outZip = new \ZipArchive();
    if ($outZip->open($destinationPath) !== true) {
      throw new \RuntimeException('No fue posible abrir el DOCX de salida');
    }

    $patterns = [];
    foreach ($values as $key => $value) {
      $patterns['${' . $key . '}'] = $this->xmlSafe((string)($value ?? ''));
    }

    for ($i = 0; $i < $outZip->numFiles; $i++) {
      $name = $outZip->getNameIndex($i);
      if (!is_string($name) || !preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name)) {
        continue;
      }
      $xml = $outZip->getFromIndex($i);
      if (!is_string($xml) || $xml === '') {
        continue;
      }
      $updated = strtr($xml, $patterns);
      if ($updated !== $xml) {
        $outZip->addFromString($name, $updated);
      }
    }

    if (!$outZip->close()) {
      throw new \RuntimeException('No fue posible finalizar el DOCX de salida');
    }
  }

  /**
   * @return array<int, string>
   */
  private function readXmlParts(string $templatePath): array {
    $zip = new \ZipArchive();
    if ($zip->open($templatePath) !== true) {
      throw new \RuntimeException('No fue posible abrir la plantilla DOCX');
    }

    $parts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
      $name = $zip->getNameIndex($i);
      if (!is_string($name) || !preg_match('#^word/(document|header\d+|footer\d+)\.xml$#', $name)) {
        continue;
      }
      $xml = $zip->getFromIndex($i);
      if (is_string($xml) && $xml !== '') {
        $parts[] = $xml;
      }
    }
    $zip->close();

    return $parts;
  }

  private function xmlSafe(string $text): string {
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  }
}

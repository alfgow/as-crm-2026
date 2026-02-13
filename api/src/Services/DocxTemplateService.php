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
      $textStream = $this->xmlTextStream($xml);
      if (preg_match_all('/\$\{([^}]+)\}/u', $textStream, $matches)) {
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

      $updated = $this->replaceInXmlTextNodes($xml, $patterns);
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

  private function replaceInXmlTextNodes(string $xml, array $patterns): string {
    if ($patterns === []) {
      return $xml;
    }

    $dom = new \DOMDocument();
    if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      return strtr($xml, $patterns);
    }

    $xpath = new \DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $nodes = $xpath->query('//w:t');
    if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
      return strtr($xml, $patterns);
    }

    $texts = [];
    $ranges = [];
    $cursor = 0;
    for ($i = 0; $i < $nodes->length; $i++) {
      $node = $nodes->item($i);
      if (!$node instanceof \DOMElement) {
        continue;
      }
      $text = $node->textContent;
      $len = strlen($text);
      $texts[$i] = $text;
      $ranges[$i] = [$cursor, $cursor + $len, $node];
      $cursor += $len;
    }

    $stream = implode('', $texts);
    if ($stream === '') {
      return $xml;
    }

    $tokens = array_keys($patterns);
    usort($tokens, fn(string $a, string $b): int => strlen($b) <=> strlen($a));

    foreach ($tokens as $token) {
      $offset = 0;
      $tokenLen = strlen($token);
      if ($tokenLen === 0) {
        continue;
      }

      while (($pos = strpos($stream, $token, $offset)) !== false) {
        $end = $pos + $tokenLen;
        $startNodeIndex = $this->findNodeIndexForPosition($ranges, $pos);
        $endNodeIndex = $this->findNodeIndexForPosition($ranges, $end - 1);
        if ($startNodeIndex === null || $endNodeIndex === null) {
          $offset = $end;
          continue;
        }

        [$startNodeStart, , $startNode] = $ranges[$startNodeIndex];
        [, $endNodeEnd, $endNode] = $ranges[$endNodeIndex];

        $startOffset = $pos - $startNodeStart;
        $endOffset = $end - ($endNodeEnd - strlen($texts[$endNodeIndex]));

        $prefix = substr($texts[$startNodeIndex], 0, $startOffset);
        $suffix = substr($texts[$endNodeIndex], $endOffset);

        $replacement = (string)$patterns[$token];
        $texts[$startNodeIndex] = $prefix . $replacement . $suffix;
        $startNode->nodeValue = $texts[$startNodeIndex];

        for ($j = $startNodeIndex + 1; $j <= $endNodeIndex; $j++) {
          $texts[$j] = '';
          $ranges[$j][2]->nodeValue = '';
        }

        $stream = substr($stream, 0, $pos) . $replacement . substr($stream, $end);
        $delta = strlen($replacement) - $tokenLen;
        for ($j = $startNodeIndex; $j < count($ranges); $j++) {
          if ($j === $startNodeIndex) {
            $ranges[$j][1] += $delta;
            continue;
          }
          if ($j <= $endNodeIndex) {
            $ranges[$j][0] = $ranges[$startNodeIndex][1];
            $ranges[$j][1] = $ranges[$startNodeIndex][1];
          } else {
            $ranges[$j][0] += $delta;
            $ranges[$j][1] += $delta;
          }
        }

        $offset = $pos + strlen($replacement);
      }
    }

    return $dom->saveXML() ?: $xml;
  }

  private function findNodeIndexForPosition(array $ranges, int $pos): ?int {
    foreach ($ranges as $index => [$start, $end]) {
      if ($pos >= $start && $pos < $end) {
        return $index;
      }
    }
    return null;
  }

  private function xmlTextStream(string $xml): string {
    $dom = new \DOMDocument();
    if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      return $xml;
    }

    $xpath = new \DOMXPath($dom);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $nodes = $xpath->query('//w:t');
    if (!$nodes instanceof \DOMNodeList || $nodes->length === 0) {
      return $xml;
    }

    $text = '';
    for ($i = 0; $i < $nodes->length; $i++) {
      $node = $nodes->item($i);
      if ($node instanceof \DOMElement) {
        $text .= $node->textContent;
      }
    }

    return $text;
  }

  private function xmlSafe(string $text): string {
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
  }
}

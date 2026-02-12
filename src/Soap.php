<?php

namespace Hadder\NfseNacional;

use DOMDocument;
use DOMNode;
use DOMXPath;
use Exception;

class Soap implements SoapInterface
{
    protected DOMDocument $dom;
    protected DOMXPath $xpath;

    /**
     * Carrega XML modelo
     */
    public function loadXml(string $xmlString): void
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = false;

        if (!$this->dom->loadXML($xmlString)) {
            throw new Exception("XML inválido.");
        }

        $this->xpath = new DOMXPath($this->dom);

        $this->removeSignature();
        $this->ensureRootId();
    }

    /**
     * Remove qualquer Signature existente
     */
    protected function removeSignature(): void
    {
        $nodes = $this->xpath->query('//*[local-name()="Signature"]');

        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Garante que o elemento raiz tenha Id válido
     */
    protected function ensureRootId(): void
    {
        $root = $this->dom->documentElement;

        if (!$root->hasAttribute('Id') || empty($root->getAttribute('Id'))) {
            $root->setAttribute('Id', $this->generateId($root->localName));
        }
    }

    /**
     * Gerador padrão de Id seguro
     */
    protected function generateId(string $prefix): string
    {
        return $prefix . date('YmdHis') . rand(1000, 9999);
    }

    /**
     * Retorna todas as tags editáveis (antes da Signature)
     */
    public function getEditableTags(): array
    {
        $editable = [];
        $this->extractNodes($this->dom->documentElement, '', $editable);
        return $editable;
    }

    protected function extractNodes(DOMNode $node, string $currentPath, array &$editable): void
    {
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        if ($node->localName === 'Signature') {
            return;
        }

        $newPath = $currentPath
            ? $currentPath . '/' . $node->localName
            : $node->localName;

        if (
            $node->childNodes->length === 1 &&
            $node->firstChild->nodeType === XML_TEXT_NODE
        ) {
            $editable[] = $newPath;
        }

        foreach ($node->childNodes as $child) {
            $this->extractNodes($child, $newPath, $editable);
        }
    }

    /**
     * Preenche XML dinamicamente
     */
    public function fill(array $data): string
    {
        foreach ($data as $path => $value) {
            $nodes = $this->getNodesBySimplePath($path);

            foreach ($nodes as $node) {
                $node->nodeValue = $value;
            }
        }

        return $this->dom->saveXML($this->dom->documentElement);
    }

    protected function getNodesBySimplePath(string $path): array
    {
        $segments = explode('/', $path);

        $query = '/*[local-name()="' . array_shift($segments) . '"]';

        foreach ($segments as $segment) {
            $query .= '/*[local-name()="' . $segment . '"]';
        }

        $nodeList = $this->xpath->query($query);

        $nodes = [];

        foreach ($nodeList as $node) {
            if ($node->localName !== 'Signature') {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    public function render(): string
    {
        return $this->dom->saveXML($this->dom->documentElement);
    }
}

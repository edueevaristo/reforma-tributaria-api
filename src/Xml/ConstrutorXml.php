<?php

declare(strict_types=1);

namespace DuNf\ReformaTributaria\Xml;

use DOMDocument;
use DOMElement;

/**
 * Cria elementos de um namespace em um DOMDocument.
 *
 * @internal
 */
final class ConstrutorXml
{
    private DOMDocument $documento;

    private string $namespace;

    public function __construct(DOMDocument $documento, string $namespace)
    {
        $this->documento = $documento;
        $this->namespace = $namespace;
    }

    /**
     * Elemento com os filhos informados. Filhos null sao ignorados (grupos opcionais).
     *
     * @param array<int, DOMElement|null> $filhos
     */
    public function grupo(string $nome, array $filhos): DOMElement
    {
        $grupo = $this->documento->createElementNS($this->namespace, $nome);

        foreach (array_filter($filhos) as $filho) {
            $grupo->appendChild($filho);
        }

        return $grupo;
    }

    /**
     * Elemento com conteudo de texto.
     */
    public function campo(string $nome, string $valor): DOMElement
    {
        $campo = $this->documento->createElementNS($this->namespace, $nome);
        $campo->appendChild($this->documento->createTextNode($valor));

        return $campo;
    }
}

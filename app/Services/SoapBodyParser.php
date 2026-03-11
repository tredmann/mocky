<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

class SoapBodyParser
{
    /**
     * Extract a value from a SOAP XML body using simplified dot notation.
     *
     * The path is evaluated starting from the first child element of the
     * SOAP <Body> element (i.e. the operation element). Namespace prefixes
     * are ignored — matching is done by local name only.
     *
     * Returns null on parse failure, missing path, or empty body.
     */
    public function extract(string $rawXml, string $dotPath): ?string
    {
        if ($rawXml === '') {
            return null;
        }

        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($rawXml);
        libxml_clear_errors();

        if (! $loaded) {
            return null;
        }

        $body = $this->findBodyElement($doc->documentElement);

        if ($body === null) {
            return null;
        }

        // Start traversal from the first element child of Body
        $current = $this->firstElementChild($body);

        if ($current === null) {
            return null;
        }

        $segments = explode('.', $dotPath);

        // The first segment should match the operation element (Body's first child)
        foreach ($segments as $index => $segment) {
            if ($index === 0) {
                // Must match the operation element's local name
                if ($current->localName !== $segment) {
                    return null;
                }

                continue;
            }

            $found = $this->findChildByLocalName($current, $segment);

            if ($found === null) {
                return null;
            }

            $current = $found;
        }

        return $current->textContent !== '' ? $current->textContent : null;
    }

    private function findBodyElement(DOMElement $root): ?DOMElement
    {
        // Check if root itself is Body
        if ($root->localName === 'Body') {
            return $root;
        }

        foreach ($root->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->localName === 'Body') {
                return $child;
            }

            // Recurse one level (Envelope > Body)
            $found = $this->findBodyElement($child);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    private function firstElementChild(DOMNode $node): ?DOMElement
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return $child;
            }
        }

        return null;
    }

    private function findChildByLocalName(DOMElement $parent, string $localName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $localName) {
                return $child;
            }
        }

        return null;
    }
}

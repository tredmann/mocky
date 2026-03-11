<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;

class SoapActionExtractor
{
    /**
     * Extract the SOAP action from a request.
     *
     * SOAP 1.1: reads the SOAPAction header and strips surrounding quotes.
     * SOAP 1.2: reads the action= parameter from the Content-Type header.
     *
     * Returns null if no action is present in either location.
     */
    public function extract(Request $request): ?string
    {
        // SOAP 1.1: SOAPAction header
        $soapActionHeader = $request->header('SOAPAction');

        if ($soapActionHeader !== null && $soapActionHeader !== '') {
            return trim($soapActionHeader, '"');
        }

        // SOAP 1.2: action= parameter in Content-Type
        $contentType = $request->header('Content-Type', '');

        if (preg_match('/\baction\s*=\s*"([^"]*)"/', $contentType, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

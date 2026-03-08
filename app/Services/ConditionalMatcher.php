<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ConditionalResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ConditionalMatcher
{
    /**
     * @param  Collection<int, ConditionalResponse>  $conditionals
     */
    public function match(Collection $conditionals, Request $request, array $pathSegments): ?ConditionalResponse
    {
        foreach ($conditionals as $conditional) {
            if ($conditional->matches($request, $pathSegments)) {
                return $conditional;
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ConditionOperator;
use App\Enums\ConditionSource;
use App\Models\ConditionalResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ConditionalMatcher
{
    public function __construct(
        private SoapBodyParser $soapBodyParser,
        private SoapActionExtractor $soapActionExtractor,
    ) {}

    /**
     * @param  Collection<int, ConditionalResponse>  $conditionals
     */
    public function match(Collection $conditionals, Request $request, array $pathSegments): ?ConditionalResponse
    {
        foreach ($conditionals as $conditional) {
            if ($this->evaluate($conditional, $request, $pathSegments)) {
                return $conditional;
            }
        }

        return null;
    }

    private function evaluate(ConditionalResponse $conditional, Request $request, array $pathSegments): bool
    {
        $actual = match ($conditional->condition_source) {
            ConditionSource::Body => data_get($request->json()->all(), $conditional->condition_field),
            ConditionSource::Query => $request->query($conditional->condition_field),
            ConditionSource::Header => $request->header($conditional->condition_field),
            ConditionSource::Path => $pathSegments[(int) $conditional->condition_field] ?? null,
            ConditionSource::SoapBody => $this->soapBodyParser->extract($request->getContent(), $conditional->condition_field),
            ConditionSource::SoapAction => $this->soapActionExtractor->extract($request),
        };

        if ($actual === null) {
            return false;
        }

        $actual = (string) $actual;
        $expected = $conditional->condition_value;

        return match ($conditional->condition_operator) {
            ConditionOperator::Equals => $actual === $expected,
            ConditionOperator::NotEquals => $actual !== $expected,
            ConditionOperator::Contains => str_contains($actual, $expected),
        };
    }
}

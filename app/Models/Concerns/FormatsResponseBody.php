<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\ResponseBodyFormatter;

trait FormatsResponseBody
{
    public static function bootFormatsResponseBody(): void
    {
        static::saving(function (self $model) {
            /** @var ResponseBodyFormatter $formatter */
            $formatter = app(ResponseBodyFormatter::class);
            $model->response_body = $formatter->format($model->content_type, $model->response_body);
        });
    }
}

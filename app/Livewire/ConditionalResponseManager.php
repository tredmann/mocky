<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\AddConditionalResponse;
use App\Models\ConditionalResponse;
use App\Models\Endpoint;
use App\Rules\ValidResponseSyntax;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Livewire\Component;

class ConditionalResponseManager extends Component
{
    #[Locked]
    public Endpoint $endpoint;

    #[Reactive]
    public string $method = 'GET';

    // Form visibility
    public bool $showForm = false;

    // Condition fields
    public string $condition_source = 'body';

    public string $condition_field = '';

    public string $condition_operator = 'equals';

    public string $condition_value = '';

    // Response fields
    public int $status_code = 200;

    public string $content_type = 'application/json';

    public string $response_body = '';

    public function mount(): void
    {
        $this->condition_source = $this->method === 'GET' ? 'query' : 'body';
    }

    public function updatedMethod(string $value): void
    {
        if (! $this->showForm) {
            $this->condition_source = $value === 'GET' ? 'query' : 'body';
        }
    }

    #[Computed]
    public function conditionalResponses()
    {
        return $this->endpoint->conditionalResponses()->get();
    }

    public function add(AddConditionalResponse $action): void
    {
        $this->validate([
            'condition_source' => ['required', 'in:body,query,header,path'],
            'condition_field' => ['required', 'string', 'max:255'],
            'condition_operator' => ['required', 'in:equals,not_equals,contains'],
            'condition_value' => ['required', 'string', 'max:255'],
            'status_code' => ['required', 'integer', 'min:100', 'max:599'],
            'content_type' => ['required', 'string', 'max:255'],
            'response_body' => ['nullable', 'string', new ValidResponseSyntax],
        ]);

        $action->handle(
            endpoint: $this->endpoint,
            conditionSource: $this->condition_source,
            conditionField: $this->condition_field,
            conditionOperator: $this->condition_operator,
            conditionValue: $this->condition_value,
            statusCode: $this->status_code,
            contentType: $this->content_type,
            responseBody: $this->response_body,
        );

        $this->resetForm();
        unset($this->conditionalResponses);
    }

    public function delete(int $id): void
    {
        ConditionalResponse::where('id', $id)
            ->where('endpoint_id', $this->endpoint->id)
            ->delete();

        unset($this->conditionalResponses);
    }

    public function resetForm(): void
    {
        $this->showForm = false;
        $this->condition_source = $this->method === 'GET' ? 'query' : 'body';
        $this->condition_field = '';
        $this->condition_operator = 'equals';
        $this->condition_value = '';
        $this->status_code = 200;
        $this->content_type = 'application/json';
        $this->response_body = '';
    }

    public function render()
    {
        return view('livewire.conditional-response-manager');
    }
}

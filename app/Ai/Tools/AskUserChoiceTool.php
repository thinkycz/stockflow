<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Thinkycz\LaravelCore\Support\Typer;

final class AskUserChoiceTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Return the stable provider-facing tool name.
     */
    public function name(): string
    {
        return 'ask_user_choice';
    }

    /**
     * Describe the tool's clarification-only boundary.
     */
    public function description(): string
    {
        return 'Ask the administrator to choose one option from a meaningful closed set. Use only for clarification, never for approval or free-form information. Provide 2 to 4 options and omit selected_option_id.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'question' => $schema->string()->max(500)->required(),
            'options' => $schema->array()->items($schema->object([
                'id' => $schema->string()->max(100)->required(),
                'label' => $schema->string()->max(160)->required(),
                'description' => $schema->string()->max(300),
            ])->withoutAdditionalProperties())->min(2)->max(4)->required(),
        ];
    }

    /**
     * Return the selected locked option as a bounded tool result.
     */
    public function handle(Request $request): string
    {
        $arguments = Typer::assertStringKeyArray($request->all());
        $selected = Typer::assertString($arguments['selected_option_id'] ?? null);
        $option = $this->option($arguments, $selected);

        return \json_encode([
            'selected_option_id' => $selected,
            'selected_label' => $option['label'],
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /**
     * Pause the conversation until one declared option is selected.
     */
    protected function needsApproval(Request $request): Approval
    {
        $arguments = Typer::assertStringKeyArray($request->all());
        $options = $this->options($arguments);

        return Approval::required(\json_encode([
            'version' => 1,
            'kind' => 'choice',
            'question' => Typer::assertString($arguments['question'] ?? null),
            'options' => $options,
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return list<array{id: string, label: string, description?: string}>
     */
    private function options(array $arguments): array
    {
        $options = [];

        foreach (Typer::assertArray($arguments['options'] ?? null) as $rawOption) {
            $option = Typer::assertStringKeyArray(Typer::assertArray($rawOption));
            $normalized = [
                'id' => Typer::assertString($option['id'] ?? null),
                'label' => Typer::assertString($option['label'] ?? null),
            ];

            if (\is_string($option['description'] ?? null) && $option['description'] !== '') {
                $normalized['description'] = $option['description'];
            }

            $options[] = $normalized;
        }

        if (\count($options) < 2 || \count($options) > 4 || \count(\array_unique(\array_column($options, 'id'))) !== \count($options)) {
            throw new InvalidArgumentException('Clarification choices require 2 to 4 options with unique IDs.');
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array{id: string, label: string, description?: string}
     */
    private function option(array $arguments, string $selected): array
    {
        foreach ($this->options($arguments) as $option) {
            if ($option['id'] === $selected) {
                return $option;
            }
        }

        throw new InvalidArgumentException('The selected clarification option is not available.');
    }
}

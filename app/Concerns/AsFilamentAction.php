<?php

namespace App\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait AsFilamentAction
{
    public static function filamentAction(): Action
    {
        return static::buildBaseAction();
    }

    // Protected helper for extension
    protected static function buildBaseAction(): Action
    {

        $action = Action::make(static::getActionName())
            ->label(static::getLabel())
            ->color(static::getColor())
            ->visible(fn (...$args) => static::isActionVisible(...$args));

        if ($icon = static::getIcon()) {
            $action->icon($icon);
        }

        if (static::requiresConfirmation()) {
            $action->requiresConfirmation();
        }

        $action->action(function ($record) {
            static::run($record);

            Notification::make()
                ->title(static::getSuccessMessage())
                ->success()
                ->send();
        });

        return $action;
    }

    // Provide sensible defaults
    protected static function getLabel(): string
    {
        return static::$actionLabel ?? str(class_basename(static::class))
            ->before('Action')
            ->headline()
            ->toString();
    }

    protected static function getIcon(): ?string
    {
        return static::$actionIcon ?? null;
    }

    protected static function getColor(): string
    {
        return static::$actionColor ?? 'primary';
    }

    protected static function requiresConfirmation(): bool
    {
        return static::$actionConfirm ?? false;
    }

    protected static function getModalHeading(): ?string
    {
        return static::$actionModalHeading ?? null;
    }

    protected static function getSuccessMessage(): string
    {
        return static::$actionSuccessMessage ?? 'Action completed successfully';
    }

    protected static function getActionName(): string
    {
        return static::$actionName ?? str(class_basename(static::class))
            ->before('Action')
            ->snake()
            ->toString();
    }

    protected static function isActionVisible(...$args): bool
    {
        return static::$actionVisible ?? true;
    }
}

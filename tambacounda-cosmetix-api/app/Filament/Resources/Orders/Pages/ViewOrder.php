<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Same status-transition / mark-as-paid actions as the list — reused
     * from OrdersTable rather than redefined here, so there is exactly one
     * place that decides which actions exist and when they're valid.
     *
     * Unlike the list's table (which re-renders its rows automatically
     * after any row action), this page's Infolist reads from `$this->record`,
     * a property Filament does NOT re-fetch on its own after a generic
     * custom Action — confirmed by a real click in Chrome: the mutation
     * landed correctly in the database, but the status badge and header
     * actions stayed stale until a manual reload. Redirecting back to this
     * same page after every action forces that refresh.
     */
    protected function getHeaderActions(): array
    {
        $refresh = fn () => redirect(OrderResource::getUrl('view', ['record' => $this->getRecord()]));

        return collect([...OrdersTable::transitionActions(), OrdersTable::markAsPaidAction()])
            ->map(fn (Action $action) => $action->after($refresh))
            ->all();
    }
}

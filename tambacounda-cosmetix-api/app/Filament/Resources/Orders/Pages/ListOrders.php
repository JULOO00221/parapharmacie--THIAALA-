<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * No CreateAction: orders are never hand-created in Filament — see
     * OrderResource's class doc comment.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}

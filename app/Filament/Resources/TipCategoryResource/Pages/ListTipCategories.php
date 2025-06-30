<?php

namespace App\Filament\Resources\TipCategoryResource\Pages;

use App\Filament\Resources\TipCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTipCategories extends ListRecords
{
    protected static string $resource = TipCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

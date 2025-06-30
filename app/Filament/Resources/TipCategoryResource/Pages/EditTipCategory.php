<?php

namespace App\Filament\Resources\TipCategoryResource\Pages;

use App\Filament\Resources\TipCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTipCategory extends EditRecord
{
    protected static string $resource = TipCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

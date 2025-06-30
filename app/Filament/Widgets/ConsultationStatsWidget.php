<?php

namespace App\Filament\Widgets;

use App\Models\Consultation;
use Filament\Widgets\Widget;

class ConsultationStatsWidget extends Widget
{
    protected static string $view = 'filament.widgets.consultation-stats-widget';

    public function getViewData(): array
    {
        return [
            'totalConsultations' => Consultation::count(),
        ];
    }
}

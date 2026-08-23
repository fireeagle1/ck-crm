<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class FulfilmentTimeline extends Component
{
    public array $stages;
    public string $currentStage;
    public array $labels;
    public string $layout;

    public const ADMIN_STAGE_LABELS = [
        'ordered' => 'Ordered',
        'packing' => 'Packing',
        'ready' => 'Ready',
        'checked_out' => 'Checked Out',
        'returned' => 'Returned',
        'inspected' => 'Inspected',
    ];

    public const CUSTOMER_STAGE_LABELS = [
        'ordered' => 'Order Placed',
        'packing' => 'Being Prepared',
        'ready' => 'Ready for Collection',
        'checked_out' => 'With You',
        'returned' => 'Returned',
        'inspected' => 'Complete',
    ];

    public function __construct(string $currentStage, array $labels, string $layout = 'responsive')
    {
        $this->currentStage = $currentStage;
        $this->labels = $labels;
        $this->layout = $layout;
        $this->stages = ['ordered', 'packing', 'ready', 'checked_out', 'returned', 'inspected'];
    }

    public function stageStatus(string $stage): string
    {
        $currentIndex = array_search($this->currentStage, $this->stages);
        $stageIndex = array_search($stage, $this->stages);

        if ($stageIndex < $currentIndex) return 'completed';
        if ($stageIndex === $currentIndex) return 'active';
        return 'future';
    }

    public function render(): View
    {
        return view('components.fulfilment-timeline');
    }
}

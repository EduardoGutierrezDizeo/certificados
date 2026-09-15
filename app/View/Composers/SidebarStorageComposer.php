<?php

namespace App\View\Composers;

use App\Services\LawyerStorageService;
use Illuminate\View\View;

class SidebarStorageComposer
{
    public function __construct(
        private readonly LawyerStorageService $storageService,
    ) {}

    /**
     * Comparte el uso de almacenamiento con el sidebar solo para abogados
     * autenticados. Admin y visitantes anónimos no disparan esta consulta.
     */
    public function compose(View $view): void
    {
        $user = auth()->user();

        if ($user === null || ! $user->hasRole('abogado')) {
            return;
        }

        $view->with('storageUsed', $this->storageService->usedCount($user));
        $view->with('storageLimit', $this->storageService->limit());
    }
}

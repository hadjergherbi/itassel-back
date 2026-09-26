<?php

use App\Models\Service;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Service::query()->exists()) {
            return;
        }

        Service::firstOrCreate(['nom_service' => Service::TOUS_LES_DOMAINES]);
    }

    public function down(): void
    {
        $service = Service::where('nom_service', Service::TOUS_LES_DOMAINES)->first();

        if ($service && ! $service->doleances()->exists() && ! $service->utilisateurs()->exists()) {
            $service->delete();
        }
    }
};

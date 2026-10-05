<?php

namespace App\Mail\Concerns;

trait MailCitoyenLocalise
{
    protected function appliquerLangue(?string $langue): void
    {
        $langue = in_array($langue, ['fr', 'en', 'ar'], true) ? $langue : 'fr';
        $this->locale($langue);
    }

    /**
     * @param  array<string, string>  $remplacements
     */
    protected function sujetTraduit(string $cle, array $remplacements = []): string
    {
        return $this->withLocale($this->locale ?: 'fr', fn () => __($cle, $remplacements));
    }
}

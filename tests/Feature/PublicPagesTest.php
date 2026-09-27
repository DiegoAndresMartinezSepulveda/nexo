<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_public_site_describes_the_pilot_and_keeps_workspace_login_separate(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('De la idea al seguimiento')
            ->assertSee('instalación independiente por equipo');

        $this->get('/app/')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_privacy_and_terms_are_honest_about_details_that_still_need_completion(): void
    {
        $this->get('/privacidad')->assertOk()->assertSee('Documento de lanzamiento pendiente de validación');
        $this->get('/terminos')->assertOk()->assertSee('Borrador que requiere completar antes de contratar');
        $this->get('/soporte')->assertOk()->assertSee('El correo de soporte aún no está configurado');
    }

    public function test_password_recovery_page_is_not_cached_or_shared_with_referrers(): void
    {
        $this->get('/password/forgot')
            ->assertOk()
            ->assertSee('Recupera tu acceso')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}

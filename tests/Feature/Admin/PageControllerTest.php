<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for CMS pages — public PageController + AdminPageController (PROJ-30).
 *
 * Public (shop):
 *  - GET /seite/{slug} renders an active page
 *  - GET /seite/{slug} returns 404 for unknown slug
 *
 * Admin:
 *  - GET  /admin/pages          lists pages
 *  - GET  /admin/pages/create   shows create form
 *  - POST /admin/pages          creates a page and redirects
 *  - POST /admin/pages          auto-generates slug from title when not supplied
 *  - POST /admin/pages          rejects missing title / content
 *  - GET  /admin/pages/{page}/edit  shows edit form
 *  - PUT  /admin/pages/{page}       updates page content
 *  - DELETE /admin/pages/{page}     deletes page
 *  - Unauthenticated access to admin routes is redirected
 */
class PageControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'slug'       => 'test-' . uniqid(),
            'title'      => 'Test Seite',
            'content'    => '<p>Inhalt</p>',
            'menu'       => 'footer',
            'sort_order' => 0,
            'active'     => true,
        ], $overrides));
    }

    // -------------------------------------------------------------------------
    // Public shop: GET /seite/{slug}
    // -------------------------------------------------------------------------

    /** @test */
    public function public_page_is_rendered_by_slug(): void
    {
        $page = $this->makePage(['slug' => 'impressum', 'title' => 'Impressum']);

        $response = $this->get(route('page.show', 'impressum'));

        $response->assertStatus(200);
        $response->assertViewIs('shop.page');
        $response->assertViewHas('page', fn ($p) => $p->id === $page->id);
    }

    /** @test */
    public function unknown_slug_returns_404(): void
    {
        $response = $this->get(route('page.show', 'does-not-exist'));

        $response->assertStatus(404);
    }

    // -------------------------------------------------------------------------
    // Admin: index
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_list_pages(): void
    {
        $admin = $this->makeAdmin();
        $this->makePage(['title' => 'AGB']);

        $response = $this->actingAs($admin)->get(route('admin.pages.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.pages.index');
    }

    /** @test */
    public function unauthenticated_user_is_redirected_from_pages_index(): void
    {
        $response = $this->get(route('admin.pages.index'));
        $response->assertRedirect();
    }

    // -------------------------------------------------------------------------
    // Admin: create + store
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_access_create_form(): void
    {
        $admin    = $this->makeAdmin();
        $response = $this->actingAs($admin)->get(route('admin.pages.create'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.pages.create');
    }

    /** @test */
    public function admin_can_create_a_page(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'title'      => 'Datenschutz',
            'slug'       => 'datenschutz',
            'menu'       => 'footer',
            'sort_order' => 10,
            'content'    => 'Datenschutzerklärung...',
            'active'     => true,
        ]);

        $response->assertRedirect(route('admin.pages.index'));
        $this->assertDatabaseHas('pages', ['slug' => 'datenschutz', 'title' => 'Datenschutz']);
    }

    /** @test */
    public function slug_is_auto_generated_from_title_when_not_provided(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.pages.store'), [
            'title'   => 'Über Uns',
            'menu'    => 'main',
            'content' => 'Wir sind Kolabri.',
        ]);

        $this->assertDatabaseHas('pages', ['slug' => 'uber-uns']);
    }

    /** @test */
    public function store_rejects_missing_title(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'menu'    => 'footer',
            'content' => 'Inhalt',
        ]);

        $response->assertSessionHasErrors('title');
    }

    /** @test */
    public function store_rejects_missing_content(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'title' => 'Impressum',
            'menu'  => 'footer',
        ]);

        $response->assertSessionHasErrors('content');
    }

    /** @test */
    public function store_rejects_duplicate_slug(): void
    {
        $this->makePage(['slug' => 'agb']);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.pages.store'), [
            'title'   => 'AGB Neu',
            'slug'    => 'agb',
            'menu'    => 'footer',
            'content' => 'Inhalt',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    // -------------------------------------------------------------------------
    // Admin: edit + update
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_access_edit_form(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makePage();

        $response = $this->actingAs($admin)->get(route('admin.pages.edit', $page));

        $response->assertStatus(200);
        $response->assertViewIs('admin.pages.edit');
        $response->assertViewHas('page', fn ($p) => $p->id === $page->id);
    }

    /** @test */
    public function admin_can_update_page_content(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makePage(['title' => 'Alt', 'content' => 'Alter Inhalt']);

        $response = $this->actingAs($admin)->put(route('admin.pages.update', $page), [
            'title'   => 'Neu',
            'menu'    => 'footer',
            'content' => 'Neuer Inhalt',
        ]);

        $response->assertRedirect(route('admin.pages.index'));
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'title' => 'Neu', 'content' => 'Neuer Inhalt']);
    }

    // -------------------------------------------------------------------------
    // Admin: delete
    // -------------------------------------------------------------------------

    /** @test */
    public function admin_can_delete_a_page(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makePage();

        $response = $this->actingAs($admin)->delete(route('admin.pages.destroy', $page));

        $response->assertRedirect(route('admin.pages.index'));
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }
}

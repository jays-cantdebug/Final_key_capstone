<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithDomainData;
use Tests\TestCase;

/**
 * Logout asks first: the sidebar's Logout buttons (desktop and mobile)
 * only open a confirmation dialog, and the one logout POST form, with its
 * CSRF token, lives inside that dialog. The route itself is unchanged.
 */
class LogoutConfirmationTest extends TestCase
{
    use InteractsWithDomainData;
    use RefreshDatabase;

    public function test_both_roles_get_a_logout_trigger_that_only_opens_the_dialog(): void
    {
        foreach (['psychometrician' => $this->psychometrician(), 'guidance counselor' => $this->guidanceCounselor()] as $role => $user) {
            foreach (['dashboard' => route('dashboard'), 'profile' => route('profile.edit')] as $page => $url) {
                $label = "{$role}, {$page}";
                $html = (string) $this->actingAs($user)->followingRedirects()->get($url)->assertOk()->getContent();
                $xpath = $this->xpath($html);

                // Desktop sidebar and mobile drawer: a plain button, outside any form.
                $triggers = $xpath->query('//button[@data-logout-trigger]');
                $this->assertSame(2, $triggers->length, "{$label}: two Logout triggers");
                foreach ($triggers as $trigger) {
                    $this->assertInstanceOf(DOMElement::class, $trigger);
                    $this->assertSame('button', $trigger->getAttribute('type'), "{$label}: the trigger never submits");
                    $this->assertSame('dialog', $trigger->getAttribute('aria-haspopup'));
                    $this->assertSame(0, $xpath->query('ancestor::form', $trigger)->length, "{$label}: the trigger is not in a form");
                    $this->assertSame('Logout', trim($trigger->textContent));
                }

                // (Alpine attributes are checked on the raw HTML: libxml drops `@click`.)
                $this->assertSame(2, substr_count($html, 'data-logout-trigger aria-haspopup="dialog" @click="open = false; $dispatch(\'open-modal\', \'confirm-logout\')"'), "{$label}: the trigger opens the dialog");

                // Exactly one logout form on the page, inside the dialog.
                $forms = $xpath->query('//form[@action="'.route('logout').'"]');
                $this->assertSame(1, $forms->length, "{$label}: one logout form");
                $form = $forms->item(0);
                $this->assertInstanceOf(DOMElement::class, $form);
                $this->assertSame('POST', $form->getAttribute('method'));
                $this->assertSame(1, $xpath->query('.//input[@type="hidden"][@name="_token"][@value!=""]', $form)->length, "{$label}: CSRF token");

                $dialogs = $xpath->query('ancestor::*[@role="dialog"]', $form);
                $this->assertSame(1, $dialogs->length, "{$label}: the form is inside the dialog");
                $dialog = $dialogs->item(0);
                $this->assertInstanceOf(DOMElement::class, $dialog);
                $this->assertSame('true', $dialog->getAttribute('aria-modal'));
                $title = $xpath->query('//*[@id="'.$dialog->getAttribute('aria-labelledby').'"]')->item(0);
                $this->assertSame('Log out?', trim((string) $title?->textContent), "{$label}: labelled by its title");
                $this->assertStringContainsString('You will need to sign in again to continue.', $dialog->textContent);

                // Cancel comes first (focused on open); the only submit is "Log out".
                $buttons = $xpath->query('.//button', $dialog);
                $this->assertSame(2, $buttons->length);
                $this->assertSame(['button', 'Cancel'], [$buttons->item(0)->getAttribute('type'), trim($buttons->item(0)->textContent)]);
                $this->assertSame(['submit', 'Log out'], [$buttons->item(1)->getAttribute('type'), trim($buttons->item(1)->textContent)]);

                // x-modal 'confirm-logout': hidden at first paint, focusable and restore-focus on.
                $open = strpos($html, 'x-on:open-modal.window="$event.detail == \'confirm-logout\'');
                $this->assertNotFalse($open, "{$label}: x-modal 'confirm-logout'");
                $start = strrpos(substr($html, 0, $open), '<div');
                $modalRoot = substr($html, $start, strpos($html, 'style="display: none;"', $open) - $start);
                $this->assertStringNotContainsString('<div', substr($modalRoot, 4), "{$label}: one element, hidden at first paint");
                $this->assertStringContainsString('firstFocusable().focus()', $modalRoot);
                $this->assertStringContainsString('returnFocusTo.focus()', $modalRoot);
            }
        }
    }

    public function test_the_logout_route_is_unchanged(): void
    {
        $route = Route::getRoutes()->getByName('logout');
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains(ValidateCsrfToken::class, $this->app->make(Kernel::class)->getMiddlewareGroups()['web']);

        // A GET never logs out.
        $user = User::factory()->create();
        $this->actingAs($user)->get('/logout')->assertStatus(405);
        $this->assertAuthenticatedAs($user);

        // A guest is sent to the login page.
        auth()->logout();
        $this->post(route('logout'))->assertRedirect(route('login'));

        // The POST still logs out.
        $this->actingAs($user)->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}

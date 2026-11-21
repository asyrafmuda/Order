<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Installer\Installation;
use App\Support\Updates\ChangedFilesException;
use App\Support\Updates\Updater;
use App\Support\Updates\UpdateServer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Super admin: one-click updates from the seller's license server.
 */
class SystemUpdateController extends Controller
{
    public function __construct(
        private readonly UpdateServer $server,
        private readonly Updater $updater,
        private readonly Installation $installation,
    ) {}

    public function show(): Response
    {
        $last = $this->server->last();
        $plan = $this->updater->savedPlan();
        $update = $last['update'];

        return Inertia::render('Admin/SystemUpdate/Show', [
            'current' => $this->installation->version(),
            'supported' => $this->server->supported(),
            'blockers' => $this->server->supported() ? $this->updater->blockers() : [],
            'checkedAt' => $last['checked_at'],
            'update' => $update ? [
                'version' => $update->version,
                'notes' => $update->notes,
                'size' => $update->size,
                'published_at' => $update->publishedAt,
            ] : null,
            'locked' => $last['locked'],
            // Waiting for the owner to agree to replace files they edited.
            'plan' => $plan && $update && $plan->to === $update->version ? [
                'to' => $plan->to,
                'changed' => array_slice($plan->changed, 0, 200),
                'changed_count' => count($plan->changed),
                'files' => count($plan->writes) + count($plan->deletes),
                'baseline' => $plan->baseline,
            ] : null,
            'backups' => array_slice($this->updater->backups(), 0, 5),
        ]);
    }

    public function check(): RedirectResponse
    {
        try {
            $result = $this->server->check();
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->updater->discard();

        return back()->with('success', match (true) {
            $result['update'] !== null => "Version {$result['update']->version} is available.",
            $result['locked'] !== null => "Version {$result['locked']['version']} is out, but your update period has ended.",
            default => 'This site is up to date (version '.$this->installation->version().').',
        });
    }

    /**
     * Download, check and install the update in one go. When the owner edited
     * files the update replaces, they are listed first and the owner confirms.
     */
    public function install(Request $request): RedirectResponse|HttpResponse
    {
        $validated = $request->validate(['replace_changed' => ['boolean']]);
        $update = $this->server->last()['update'];

        if (! $update) {
            return back()->with('error', 'There is no update to install. Check for updates first.');
        }

        try {
            $plan = $this->updater->savedPlan();

            if (! $plan || $plan->to !== $update->version) {
                $plan = $this->updater->prepare($update);
            }

            $finish = $this->updater->apply($plan, (bool) ($validated['replace_changed'] ?? false));
        } catch (ChangedFilesException) {
            return back()->with('error', 'Some files the update replaces were changed on this site. Review them below.');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // A full page load, so the next request runs on the new files.
        return Inertia::location($finish);
    }

    public function cancel(): RedirectResponse
    {
        $this->updater->discard();

        return back()->with('success', 'The downloaded update was removed. Nothing was changed.');
    }
}

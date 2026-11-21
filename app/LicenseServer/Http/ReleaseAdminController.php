<?php

namespace App\LicenseServer\Http;

use App\Http\Controllers\Controller;
use App\LicenseServer\Models\Activation;
use App\LicenseServer\Models\License;
use App\LicenseServer\Models\Release;
use App\LicenseServer\ReleasePackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Super admin on the license server: upload update packages, write what
 * changed, choose who gets them, and see which version each site runs.
 */
class ReleaseAdminController extends Controller
{
    public function index(): Response
    {
        $sites = Activation::query()->whereNull('deactivated_at')->get(['version']);
        $releases = Release::query()->with('licenses:id')->get()
            ->sort(fn (Release $a, Release $b) => version_compare($b->version, $a->version))
            ->values();

        return Inertia::render('Admin/Releases/Index', [
            'releases' => $releases->map(fn (Release $release) => [
                'id' => $release->id,
                'version' => $release->version,
                'notes' => $release->notes,
                'size' => $release->size,
                'audience' => $release->audience,
                'license_ids' => $release->licenses->pluck('id')->values(),
                'published_at' => $release->published_at?->toIso8601String(),
                'created_at' => $release->created_at?->toIso8601String(),
                'sites' => $sites->where('version', $release->version)->count(),
            ]),
            'licenses' => License::query()->orderBy('customer_name')->get(['id', 'code', 'customer_name', 'updates_until', 'revoked_at'])
                ->map(fn (License $license) => [
                    'id' => $license->id,
                    'label' => "{$license->customer_name} ({$license->code})",
                    'updates_until' => $license->updates_until?->toDateString(),
                    'revoked' => $license->isRevoked(),
                ]),
            'versions' => $sites->groupBy(fn (Activation $activation) => $activation->version ?: 'unknown')
                ->map->count()
                ->sortKeysUsing(fn ($a, $b) => version_compare((string) $b, (string) $a))
                ->all(),
            'uploadLimit' => self::uploadLimit(),
            'uploadLimitBytes' => self::uploadLimitBytes(),
        ]);
    }

    public function store(Request $request, ReleasePackage $package): RedirectResponse
    {
        $validated = $request->validate([
            // In KB: 500 MB, more than any release needs; PHP's own limit usually bites first.
            'file' => ['required', 'file', 'max:512000'],
            ...$this->rules(),
        ], ['file.uploaded' => 'The upload failed. The ZIP is probably bigger than this server accepts ('.self::uploadLimit().'). Raise upload_max_filesize and post_max_size, or upload it with: php artisan release:publish path/to/file.zip']);

        try {
            $release = DB::transaction(function () use ($package, $request, $validated) {
                $release = $package->store($request->file('file')->getRealPath(), $validated['notes'] ?? null, (bool) $validated['publish'], $validated['audience']);
                $release->licenses()->sync($validated['audience'] === Release::AUDIENCE_SELECTED ? $validated['license_ids'] : []);

                return $release;
            });
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()]);
        }

        return back()->with('success', $release->isPublished()
            ? "Version {$release->version} is published. Sites offer it within a day."
            : "Version {$release->version} is saved as a draft.");
    }

    public function update(Request $request, Release $release): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $release->update([
            'notes' => $validated['notes'] ?? null,
            'audience' => $validated['audience'],
            'published_at' => $validated['publish'] ? ($release->published_at ?? now()) : null,
        ]);
        $release->licenses()->sync($validated['audience'] === Release::AUDIENCE_SELECTED ? $validated['license_ids'] : []);

        return back()->with('success', "Version {$release->version} saved.");
    }

    public function destroy(Release $release): RedirectResponse
    {
        Storage::disk('local')->delete($release->path);
        $release->delete();

        return back()->with('success', "Version {$release->version} deleted. Sites that installed it keep it.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'publish' => ['required', 'boolean'],
            'audience' => ['required', Rule::in([Release::AUDIENCE_ALL, Release::AUDIENCE_SELECTED])],
            'license_ids' => [Rule::excludeIf(fn () => request('audience') !== Release::AUDIENCE_SELECTED), 'required', 'array', 'min:1'],
            'license_ids.*' => [Rule::excludeIf(fn () => request('audience') !== Release::AUDIENCE_SELECTED), 'integer', Rule::exists('licenses', 'id')],
        ];
    }

    /**
     * The biggest upload PHP takes: the smaller of its two limits.
     */
    private static function uploadLimitBytes(): int
    {
        $bytes = fn (string $value) => match (strtoupper(substr(trim($value), -1))) {
            'G' => (int) $value * 1073741824,
            'M' => (int) $value * 1048576,
            'K' => (int) $value * 1024,
            default => (int) $value,
        };
        $limits = array_filter([$bytes((string) ini_get('upload_max_filesize')), $bytes((string) ini_get('post_max_size'))]);

        return $limits === [] ? 0 : min($limits);
    }

    private static function uploadLimit(): string
    {
        return 'upload_max_filesize '.ini_get('upload_max_filesize').', post_max_size '.ini_get('post_max_size');
    }
}

<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateSettingsRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Settings\Application\SettingsManager;
use App\Modules\Settings\Application\SettingsMediaManager;
use App\Modules\Settings\Support\SettingsRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsManager $settings,
        private readonly SettingsMediaManager $media,
    ) {}

    public function index(Request $request, ?string $group = null): Response|RedirectResponse
    {
        $group ??= 'general';

        if ($group === 'homepage') {
            return redirect()->route('admin.homepage-builder.index');
        }

        abort_unless(
            in_array($group, $this->registry->groupNames(), true),
            404,
        );

        $groups = collect($this->registry->groups())
            ->except('homepage')
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'],
            ])
            ->values()
            ->all();

        $schema = $this->registry->group($group);
        $schema['fields'] = collect($schema['fields'])
            ->map(fn (array $field) => Arr::except($field, ['rules']))
            ->all();

        $values = $this->settings->group($group);

        return Inertia::render('Admin/Settings/Index', [
            'groups' => $groups,
            'activeGroup' => $group,
            'schema' => $schema,
            'values' => $values,
            'media' => [
                'logo_url' => $this->settings->mediaUrl(
                    (string) ($this->settings->get('general', 'logo_path') ?? ''),
                ),
                'favicon_url' => $this->settings->mediaUrl(
                    (string) ($this->settings->get('general', 'favicon_path') ?? ''),
                ),
            ],
        ]);
    }

    public function update(
        UpdateSettingsRequest $request,
        string $group,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless(
            in_array($group, $this->registry->groupNames(), true),
            404,
        );

        $validated = $request->validated();
        $definitions = $this->registry->group($group)['fields'] ?? [];

        $values = collect($validated)
            ->only(array_keys($definitions))
            ->all();

        DB::transaction(function () use ($request, $group, &$values): void {
            if ($group !== 'general') {
                return;
            }

            $currentLogo = (string) ($this->settings->get('general', 'logo_path') ?? '');
            $currentFavicon = (string) ($this->settings->get('general', 'favicon_path') ?? '');

            if ($request->boolean('remove_logo')) {
                $this->media->remove($currentLogo);
                $values['logo_path'] = '';
            }

            if ($request->boolean('remove_favicon')) {
                $this->media->remove($currentFavicon);
                $values['favicon_path'] = '';
            }

            if ($request->hasFile('logo')) {
                $values['logo_path'] = $this->media->storeBranding(
                    $request->file('logo'),
                    'logo',
                    $currentLogo ?: null,
                );
            }

            if ($request->hasFile('favicon')) {
                $values['favicon_path'] = $this->media->storeBranding(
                    $request->file('favicon'),
                    'favicon',
                    $currentFavicon ?: null,
                );
            }
        });

        $this->settings->updateGroup(
            $group,
            $values,
            $request->user()?->getKey(),
        );

        $audit->log(
            'admin.settings.updated',
            actor: $request->user(),
            subjectType: 'settings',
            subjectId: $group,
            metadata: [
                'group' => $group,
                'keys' => array_values(array_keys($values)),
            ],
            request: $request,
        );

        return back()->with('status', 'Pengaturan berhasil disimpan.');
    }
}

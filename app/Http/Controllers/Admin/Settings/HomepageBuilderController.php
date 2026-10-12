<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateHomepageBuilderRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Settings\Application\HomepageBuilderManager;
use App\Modules\Settings\Application\HomepageSliderMediaManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HomepageBuilderController extends Controller
{
    public function index(HomepageBuilderManager $builder): Response
    {
        return Inertia::render('Admin/HomepageBuilder/Index', [
            'sections' => $builder->adminPayload(),
            'schemas' => $builder->schemas(),
        ]);
    }

    public function update(
        UpdateHomepageBuilderRequest $request,
        HomepageBuilderManager $builder,
        HomepageSliderMediaManager $media,
        AuditLogger $audit,
    ): RedirectResponse {
        // The builder manager is the schema whitelist for config fields. Using the
        // validated request envelope plus all nested config values is required here
        // because Laravel prunes sibling config keys once slide file rules exist.
        $request->validated();
        $sections = $request->all('sections')['sections'] ?? [];
        $prepared = $media->prepare($sections);

        try {
            $builder->save($prepared['sections']);
        } catch (Throwable $exception) {
            $media->rollback($prepared['created_paths']);

            throw $exception;
        }

        $media->commit($prepared['obsolete_paths']);

        $audit->log(
            'admin.homepage-builder.updated',
            actor: $request->user(),
            subjectType: 'homepage',
            subjectId: 'sections',
            metadata: [
                'section_count' => count($prepared['sections']),
                'enabled_count' => collect($prepared['sections'])
                    ->where('is_enabled', true)
                    ->count(),
                'order' => collect($prepared['sections'])
                    ->pluck('id')
                    ->values()
                    ->all(),
            ],
            request: $request,
        );

        return back()->with('status', 'Homepage builder berhasil disimpan.');
    }
}

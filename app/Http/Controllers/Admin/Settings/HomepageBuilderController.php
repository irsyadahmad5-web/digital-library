<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\UpdateHomepageBuilderRequest;
use App\Modules\Audit\Application\AuditLogger;
use App\Modules\Settings\Application\HomepageBuilderManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

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
        AuditLogger $audit,
    ): RedirectResponse {
        $sections = $request->validated('sections');

        $builder->save($sections);

        $audit->log(
            'admin.homepage-builder.updated',
            actor: $request->user(),
            subjectType: 'homepage',
            subjectId: 'sections',
            metadata: [
                'section_count' => count($sections),
                'enabled_count' => collect($sections)
                    ->where('is_enabled', true)
                    ->count(),
                'order' => collect($sections)
                    ->pluck('id')
                    ->values()
                    ->all(),
            ],
            request: $request,
        );

        return back()->with('status', 'Homepage builder berhasil disimpan.');
    }
}

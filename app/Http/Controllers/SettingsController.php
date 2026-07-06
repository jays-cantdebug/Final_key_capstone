<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SettingsFormRequest;
use App\Services\SystemSettingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly SystemSettingService $settingService)
    {
    }

    public function edit(): View
    {
        return view('settings.edit', [
            'settings' => $this->settingService->all()->keyBy('key'),
            'activeQuestionnaireVersion' => $this->settingService->activeQuestionnaireVersion(),
        ]);
    }

    public function update(SettingsFormRequest $request): RedirectResponse
    {
        $this->settingService->update($request->validated());

        return redirect()->route('settings.edit')->with('status', 'Settings updated successfully.');
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use App\Services\AssessmentWizardStaging;
use App\Services\RemoteAssessmentService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;

/**
 * Logging out ends the student-device draft started in THIS session. The
 * draft id is read from the logging-out session (still intact when Logout
 * fires), so a second session being signed out by EnsureSingleActiveSession
 * — which has no draft id — never cancels the real session's assessment.
 * Registered in AppServiceProvider (event discovery is off).
 */
class DiscardRemoteDraftOnLogout
{
    public function __construct(
        private readonly RemoteAssessmentService $remoteAssessments,
        private readonly Request $request,
    ) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User || ! $this->request->hasSession()) {
            return;
        }

        $draftId = $this->request->session()->get(AssessmentWizardStaging::SESSION_KEY.'.remote_draft_id');

        $this->remoteAssessments->ownedDraft($event->user, $draftId)?->delete();
    }
}

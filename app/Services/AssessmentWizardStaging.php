<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionnaireVersion;
use Illuminate\Contracts\Session\Session;

/**
 * The New Assessment wizard's session staging for Step 2, shared by the
 * two ways answers arrive — given on this device
 * (AssessmentWizardController::storeResponses()) or on a student device
 * (RemoteAssessmentController::submit()) — so both pin the version and
 * reset the review identically, and everything after (Step 3, the AI
 * review, the duplicate check, the final save) is the same code path.
 */
class AssessmentWizardStaging
{
    public const SESSION_KEY = 'assessment_wizard';

    /**
     * A live student-device draft: its id, and the plain link token (for the
     * live page's Copy link button) and short code (only their hashes are in
     * the database).
     */
    public const REMOTE_KEYS = ['remote_draft_id', 'remote_token', 'remote_short_code'];

    /**
     * Set after a student-device Submit; the answers are then read-only on
     * Step 2, and the value is recorded as the assessment's
     * `administration_mode` at the final save.
     */
    public const ADMINISTRATION_MODE_KEY = 'administration_mode';

    /**
     * Stage validated answers and pin `$version`, clearing any cached
     * review. In Take Again mode the consent timestamp is staged too
     * (`$retakeConsentAt`, or now).
     *
     * @param  array<int, int>  $responses
     */
    public function stageResponses(Session $session, QuestionnaireVersion $version, array $responses, ?\DateTimeInterface $retakeConsentAt = null): void
    {
        $session->put(self::SESSION_KEY.'.responses', $responses);
        $session->put(self::SESSION_KEY.'.questionnaire_version_id', $version->id);
        $session->forget(self::SESSION_KEY.'.review');

        if ($session->has(self::SESSION_KEY.'.existing_student_id')) {
            $session->put(self::SESSION_KEY.'.privacy_consent_at', $retakeConsentAt ?? now());
        }
    }

    public function forgetRemote(Session $session): void
    {
        $session->forget(array_map(fn (string $key): string => self::SESSION_KEY.'.'.$key, self::REMOTE_KEYS));
    }

    public function answeredOnStudentDevice(Session $session): bool
    {
        return $session->get(self::SESSION_KEY.'.'.self::ADMINISTRATION_MODE_KEY) !== null;
    }
}
